#!/usr/bin/env python3
"""
抢单神器 - 独立 Python API 服务器
提供静态文件服务 + SQLite 持久化 mock API
启动: python3 api_server.py
访问: http://localhost:8000/h5/
"""
import json
import time
import os
import re
import sqlite3
import secrets
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
from datetime import datetime, timedelta

HOST = '0.0.0.0'
PORT = 8000
WEB_ROOT = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(WEB_ROOT, 'data.db')

# ===== 聚合登录配置（如需对接真实聚合登录服务，填入以下配置） =====
# 服务商: 聚合登录（示例占位，请替换为真实服务商提供的值）
JUHE_LOGIN_CONFIG = {
    'url': 'https://api.juhelogin.com/login',  # 聚合登录接口地址（请替换为真实地址）
    'app_id': '',  # 聚合登录 app_id（请替换为真实值）
    'app_key': '',  # 聚合登录 app_key（请替换为真实值）
    'enabled': False  # 是否启用聚合登录（True=调用真实聚合登录，False=本地 mock）
}

# ===== SQLite 初始化 =====
def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    db = get_db()
    cur = db.cursor()
    # 用户表
    cur.execute('''CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT UNIQUE,
        nickname TEXT,
        avatar TEXT,
        cs_avatar TEXT DEFAULT '',
        type INTEGER DEFAULT 1,
        openid TEXT,
        pid INTEGER DEFAULT 0,
        money REAL DEFAULT 0,
        score INTEGER DEFAULT 0,
        level INTEGER DEFAULT 1,
        group_chat INTEGER DEFAULT 0,
        createtime INTEGER
    )''')
    try:
        cur.execute('ALTER TABLE users ADD COLUMN cs_avatar TEXT DEFAULT ""')
    except sqlite3.OperationalError:
        pass
    # 打手表
    cur.execute('''CREATE TABLE IF NOT EXISTS thug (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT,
        status INTEGER DEFAULT 0,
        sh_status INTEGER DEFAULT 0,
        is_customer_service INTEGER DEFAULT 0,
        createtime INTEGER
    )''')
    # 订单表
    cur.execute('''CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no TEXT,
        user_id INTEGER,
        thug_id INTEGER DEFAULT 0,
        title TEXT,
        logo TEXT,
        price REAL,
        pay_price REAL,
        number INTEGER DEFAULT 1,
        game_id TEXT,
        game_qf TEXT,
        remarks TEXT,
        synopsis TEXT,
        status INTEGER DEFAULT 0,
        status_text TEXT,
        createtime INTEGER,
        paytime INTEGER,
        connecttime INTEGER,
        completetime INTEGER
    )''')
    # 系统通知/公告
    cur.execute('''CREATE TABLE IF NOT EXISTS notices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        synopsis TEXT,
        content TEXT,
        type INTEGER DEFAULT 1,
        createtime INTEGER
    )''')
    # 客服
    cur.execute('''CREATE TABLE IF NOT EXISTS customer_services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        user_id INTEGER,
        type TEXT DEFAULT 'im',
        avatar TEXT,
        online INTEGER DEFAULT 1
    )''')
    # 邀请码表
    cur.execute('''CREATE TABLE IF NOT EXISTS invite_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE,
        type TEXT DEFAULT 'thug',
        user_id INTEGER DEFAULT 0,
        name TEXT,
        createtime INTEGER
    )''')
    # 系统配置表（对应 FastAdmin fa_config）
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_config (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        group_name TEXT DEFAULT 'basic',
        title TEXT,
        tip TEXT DEFAULT '',
        type TEXT DEFAULT 'string',
        visible TEXT DEFAULT '',
        value TEXT DEFAULT '',
        content TEXT DEFAULT '',
        rule TEXT DEFAULT '',
        extend TEXT DEFAULT '',
        setting TEXT DEFAULT ''
    )''')
    # 第三方登录表（对应 FastAdmin fa_third）
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_third (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 0,
        platform TEXT DEFAULT '',
        apptype TEXT DEFAULT '',
        unionid TEXT DEFAULT '',
        openid TEXT DEFAULT '',
        openname TEXT DEFAULT '',
        access_token TEXT DEFAULT '',
        refresh_token TEXT DEFAULT '',
        expires_in INTEGER DEFAULT 0,
        createtime INTEGER,
        updatetime INTEGER,
        logintime INTEGER,
        expiretime INTEGER,
        UNIQUE(platform, openid)
    )''')
    db.commit()
    # 初始化默认数据
    cur.execute('SELECT COUNT(*) FROM notices')
    if cur.fetchone()[0] == 0:
        now = int(time.time())
        cur.execute('INSERT INTO notices (title, synopsis, content, type, createtime) VALUES (?,?,?,?,?)', ('欢迎使用创世网络', '点击查看平台介绍', '欢迎使用创世网络平台，本平台提供专业的电竞陪玩服务。', 1, now - 86400))
        cur.execute('INSERT INTO notices (title, synopsis, content, type, createtime) VALUES (?,?,?,?,?)', ('系统升级公告', '平台于今日完成升级', '平台于今日完成系统升级，新增抢单神器等功能。', 1, now - 43200))
    cur.execute('SELECT COUNT(*) FROM customer_services')
    if cur.fetchone()[0] == 0:
        cur.execute('INSERT INTO customer_services (name, user_id, type, avatar, online) VALUES (?,?,?,?,?)', ('平台客服小创', 0, 'im', '/static/yun/customer.png', 1))
    cur.execute('SELECT COUNT(*) FROM invite_codes')
    if cur.fetchone()[0] == 0:
        now = int(time.time())
        # 客服邀请码
        cur.execute('INSERT INTO invite_codes (code, type, user_id, name, createtime) VALUES (?,?,?,?,?)', ('CS8888', 'cs', 0, '客服小创', now))
        # 打手邀请码
        cur.execute('INSERT INTO invite_codes (code, type, user_id, name, createtime) VALUES (?,?,?,?,?)', ('THUG666', 'thug', 0, '大神老王', now))
    # 导入系统配置（来自数据库 SQL 脚本）
    cur.execute('SELECT COUNT(*) FROM fa_config')
    if cur.fetchone()[0] == 0:
        now = int(time.time())
        config_items = [
            # 配置分组
            ('configgroup', 'basic', '配置分组', '', 'string', '', '{"basic":"基础配置","dictionary":"字典配置","example":"配置","ulogin":"彩虹聚合登录","rainbow_epay":"彩虹易支付"}', '', '', '', ''),
            # 聚合登录 - 基础配置
            ('login_mode', 'basic', '登录方式', '选择前端启用的登录方式', 'radio', '', 'juhe', '{"juhe":"一键登录（需配置聚合登录）","mock":"游客模式（仅开发调试）"}', 'required', '', ''),
            # 聚合登录 - ulogin 分组
            ('ulogin_apiurl', 'ulogin', 'API地址', '聚合登录平台接口地址', 'string', '', 'https://u.suyanw.cn/', '', 'required', '', ''),
            ('ulogin_appid', 'ulogin', 'APPID', '在聚合登录平台获取的应用 APPID', 'string', '', '', '', 'required', '', ''),
            ('ulogin_appkey', 'ulogin', 'APPKEY', '在聚合登录平台获取的应用 APPKEY', 'password', '', '', '', 'required', '', ''),
            ('ulogin_types', 'ulogin', '启用登录方式', '勾选要在前端展示的第三方登录方式', 'checkbox', '', 'qq,wx,alipay', '{"qq":"QQ","wx":"微信","alipay":"支付宝","sina":"微博","baidu":"百度","douyin":"抖音"}', '', '', ''),
            ('ulogin_callback', 'ulogin', '回调地址', '聚合登录平台回调地址', 'string', '', '', '', '', '', ''),
            # 彩虹易支付 - 基础配置
            ('pay_methods', 'basic', '支付方式', '勾选要在前端收银台展示的支付方式', 'checkbox', '', 'balance,native_wechat', '{"balance":"余额支付","native_wechat":"原生微信支付","epay_wxpay":"彩虹易支付-微信","epay_alipay":"彩虹易支付-支付宝","epay_qqpay":"彩虹易支付-QQ钱包","epay_bank":"彩虹易支付-云闪付"}', '', '', ''),
            # 彩虹易支付 - rainbow_epay 分组
            ('epay_apiurl', 'rainbow_epay', 'API地址', '彩虹易支付平台接口地址', 'string', '', '', '', 'required', '', ''),
            ('epay_pid', 'rainbow_epay', '商户ID', '在彩虹易支付平台获取的商户 PID', 'string', '', '', '', 'required', '', ''),
            ('epay_key', 'rainbow_epay', '商户密钥', '在彩虹易支付平台获取的商户密钥', 'password', '', '', '', 'required', '', ''),
            ('epay_notify_url', 'rainbow_epay', '异步通知地址', '彩虹易支付异步通知地址', 'string', '', '', '', '', '', ''),
            # 小轮播图
            ('small_lunbo', 'basic', '小轮播图', '首页图片导航上方小轮播图', 'images', '', '', '', '', '', ''),
            # 商品卡片每行数量
            ('goods_card_col', 'basic', '商品卡片每行数量', '控制前端商品卡片每行显示的数量', 'radio', '', '2', '{"2":"2列","3":"3列"}', 'required', '', ''),
            # 前端 UI 装修 - 背景
            ('app_bg_color', 'basic', '页面背景颜色', '前端全局背景色（HEX，如 #FF7A45），留空使用默认渐变', 'string', '', '', '', '', '', ''),
            ('app_bg_image', 'basic', '页面背景图片', '前端全局背景图 URL（通过背景图上传接口设置）', 'string', '', '', '', '', '', ''),
            # 前端加载页配置
            ('loading_name', 'basic', '加载页名称', '加载页展示的品牌名称', 'string', '', '创世网络', '', '', '', ''),
            ('loading_logo', 'basic', '加载页 Logo', '加载页展示的 Logo 图片 URL', 'string', '', '/static/eon.png', '', '', '', ''),
        ]
        for item in config_items:
            cur.execute('INSERT INTO fa_config (name, group_name, title, tip, type, visible, value, content, rule, extend, setting) VALUES (?,?,?,?,?,?,?,?,?,?,?)', item)
    db.commit()
    # 补齐后续新增的配置项（兼容已有数据库）
    extra_items = [
        ('app_bg_color', 'basic', '页面背景颜色', '前端全局背景色（HEX，如 #FF7A45），留空使用默认渐变', 'string', '', '', '', '', '', ''),
        ('app_bg_image', 'basic', '页面背景图片', '前端全局背景图 URL（通过背景图上传接口设置）', 'string', '', '', '', '', '', ''),
        ('loading_name', 'basic', '加载页名称', '加载页展示的品牌名称', 'string', '', '创世网络', '', '', '', ''),
        ('loading_logo', 'basic', '加载页 Logo', '加载页展示的 Logo 图片 URL', 'string', '', '/static/eon.png', '', '', '', ''),
    ]
    for item in extra_items:
        cur.execute('INSERT OR IGNORE INTO fa_config (name, group_name, title, tip, type, visible, value, content, rule, extend, setting) VALUES (?,?,?,?,?,?,?,?,?,?,?)', item)
    db.commit()
    db.close()

init_db()

# ===== Mock 静态配置 =====
EXPIRE_TS = int(time.time()) + 86400 * 365
EXPIRE_TEXT = (datetime.now() + timedelta(days=365)).strftime('%Y-%m-%d %H:%M')
CONFIG = {
    "exclude_remark": True,
    "exclude_value_added": False,
    "keywords": "王者荣耀",
    "exclude_keywords": "陪玩"
}
TOGGLE_STATUS = 1

# ===== 辅助函数 =====
def now_ts():
    return int(time.time())

def fmt_time(ts):
    if not ts:
        return ''
    return datetime.fromtimestamp(ts).strftime('%Y-%m-%d %H:%M:%S')

def gen_token():
    return secrets.token_hex(16)

def get_user_by_token(token):
    if not token:
        return None
    db = get_db()
    row = db.execute('SELECT * FROM users WHERE token = ?', (token,)).fetchone()
    db.close()
    return dict(row) if row else None

# ===== 配置读取辅助函数 =====
def get_config(name, default=''):
    """从 fa_config 表读取配置值"""
    try:
        db = get_db()
        row = db.execute('SELECT value FROM fa_config WHERE name = ?', (name,)).fetchone()
        db.close()
        if row:
            return row['value']
    except Exception:
        pass
    return default

def get_ulogin_config():
    """获取聚合登录配置（从数据库读取）"""
    app_id = get_config('ulogin_appid', '')
    app_key = get_config('ulogin_appkey', '')
    url = get_config('ulogin_apiurl', 'https://u.suyanw.cn/')
    enabled = bool(app_id and app_key)
    return {
        'enabled': enabled,
        'url': url,
        'app_id': app_id,
        'app_key': app_key,
        'types': get_config('ulogin_types', 'qq,wx,alipay'),
        'login_mode': get_config('login_mode', 'juhe')
    }

def get_epay_config():
    """获取彩虹易支付配置"""
    return {
        'apiurl': get_config('epay_apiurl', ''),
        'pid': get_config('epay_pid', ''),
        'key': get_config('epay_key', ''),
        'notify_url': get_config('epay_notify_url', ''),
        'pay_methods': get_config('pay_methods', 'balance,native_wechat')
    }

# ===== API 处理器 =====
class Handler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass

    def send_json(self, data, status=200):
        body = json.dumps(data, ensure_ascii=False).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'token, Content-Type, X-Requested-With')
        self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def send_file(self, filepath):
        try:
            with open(filepath, 'rb') as f:
                content = f.read()
            ext = os.path.splitext(filepath)[1].lower()
            mime = {
                '.html': 'text/html; charset=utf-8',
                '.js': 'application/javascript; charset=utf-8',
                '.css': 'text/css; charset=utf-8',
                '.json': 'application/json; charset=utf-8',
                '.png': 'image/png',
                '.jpg': 'image/jpeg',
                '.jpeg': 'image/jpeg',
                '.gif': 'image/gif',
                '.webp': 'image/webp',
                '.svg': 'image/svg+xml',
                '.ico': 'image/x-icon',
                '.woff': 'font/woff',
                '.woff2': 'font/woff2',
                '.ttf': 'font/ttf',
                '.map': 'application/json',
            }.get(ext, 'application/octet-stream')
            self.send_response(200)
            self.send_header('Content-Type', mime)
            if ext in ('.html',):
                self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
                self.send_header('Pragma', 'no-cache')
            self.send_header('Content-Length', str(len(content)))
            self.end_headers()
            self.wfile.write(content)
        except FileNotFoundError:
            self.send_response(404)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'404 Not Found')

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'token, Content-Type, X-Requested-With')
        self.end_headers()

    def do_GET(self):
        parsed = urlparse(self.path)
        path = parsed.path
        qs = parse_qs(parsed.query)
        if path.startswith('/api/'):
            self.handle_api(path, qs, 'GET', {})
            return
        if path == '/' or path == '':
            path = '/index.html'
        elif path == '/h5' or path == '/h5/':
            path = '/h5/index.html'
        # /static/ 映射到 h5/static/（uni-app H5 资源路径）
        elif path.startswith('/static/'):
            h5_filepath = os.path.join(WEB_ROOT, 'h5', path.lstrip('/'))
            if os.path.isfile(h5_filepath):
                self.send_file(h5_filepath)
                return
        # /uploads/ 上传文件目录
        elif path.startswith('/uploads/'):
            up_filepath = os.path.join(WEB_ROOT, path.lstrip('/'))
            if os.path.isfile(up_filepath):
                self.send_file(up_filepath)
                return
        filepath = os.path.join(WEB_ROOT, path.lstrip('/'))
        if os.path.isfile(filepath):
            self.send_file(filepath)
            return
        if not os.path.splitext(path)[1] or path.endswith('/'):
            h5_index = os.path.join(WEB_ROOT, 'h5', 'index.html')
            if os.path.isfile(h5_index):
                self.send_file(h5_index)
                return
        self.send_response(404)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'404 Not Found')

    def do_POST(self):
        parsed = urlparse(self.path)
        path = parsed.path
        qs = parse_qs(parsed.query)
        content_length = int(self.headers.get('Content-Length', 0))
        ctype = self.headers.get('Content-Type', '')
        raw_body = self.rfile.read(content_length) if content_length > 0 else b''
        post_data = {}
        files = {}
        if ctype.startswith('multipart/form-data'):
            # 解析 multipart/form-data
            try:
                boundary = ctype.split('boundary=', 1)[1].encode('utf-8')
                parts = raw_body.split(b'--' + boundary)
                for part in parts:
                    if not part or part in (b'--\r\n', b'--', b'--\r\n--\r\n', b'--\r\n'):
                        continue
                    if b'Content-Disposition' not in part:
                        continue
                    head, _, content = part.partition(b'\r\n\r\n')
                    head_str = head.decode('utf-8', errors='ignore')
                    name_match = re.search(r'name="([^"]+)"', head_str)
                    if not name_match:
                        continue
                    field_name = name_match.group(1)
                    # 去掉结尾 \r\n
                    if content.endswith(b'\r\n'):
                        content = content[:-2]
                    filename_match = re.search(r'filename="([^"]*)"', head_str)
                    if filename_match:
                        filename = filename_match.group(1) or ''
                        if filename:
                            files[field_name] = {'filename': filename, 'data': content}
                        else:
                            post_data[field_name] = content.decode('utf-8', errors='ignore')
                    else:
                        post_data[field_name] = content.decode('utf-8', errors='ignore')
            except Exception as e:
                print('multipart parse error:', e)
        else:
            try:
                post_data = json.loads(raw_body) if raw_body else {}
            except json.JSONDecodeError:
                post_data = {}
        if path.startswith('/api/'):
            self.handle_api(path, qs, 'POST', post_data, files=files)
            return
        self.send_response(404)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'404 Not Found')

    def get_token(self):
        return self.headers.get('token', '')

    def handle_api(self, path, qs, method, body, files=None):
        global TOGGLE_STATUS, CONFIG
        token = self.get_token()
        # ===== 聚合登录 =====
        if path == '/api/login/juhe':
            # 聚合登录：接收聚合登录服务返回的 code/ticket，向聚合登录服务校验，校验通过后创建/复用本地用户
            code = body.get('code', '') or body.get('ticket', '')
            login_type = body.get('type', 'wechat')  # wechat/qq/alipay 等
            pid = int(body.get('pid', 0) or 0)
            if not code:
                self.send_json({"code": 0, "msg": "缺少登录凭证 code"})
                return
            # 校验聚合登录（如果启用真实聚合登录）
            openid = None
            ulogin = get_ulogin_config()
            if ulogin.get('enabled') and ulogin.get('url') and ulogin.get('app_id'):
                try:
                    import urllib.request as ur
                    import urllib.parse as up
                    params = up.urlencode({
                        'app_id': ulogin['app_id'],
                        'app_key': ulogin['app_key'],
                        'code': code,
                        'type': login_type
                    })
                    req = ur.Request(ulogin['url'], data=params.encode('utf-8'),
                                     headers={'Content-Type': 'application/x-www-form-urlencoded'})
                    resp = ur.urlopen(req, timeout=10)
                    data = json.loads(resp.read().decode('utf-8'))
                    if data.get('code') == 1 and data.get('data', {}).get('openid'):
                        openid = data['data']['openid']
                    else:
                        self.send_json({"code": 0, "msg": data.get('msg', '聚合登录校验失败')})
                        return
                except Exception as e:
                    self.send_json({"code": 0, "msg": f'聚合登录服务异常: {str(e)}'})
                    return
            else:
                # mock 模式：用 code 当作 openid
                openid = f'juhe_{login_type}_{code}'

            # 通过 openid 复用/新建用户
            db = get_db()
            row = db.execute('SELECT * FROM users WHERE openid = ?', (openid,)).fetchone()
            if row:
                u = dict(row)
            else:
                t = gen_token()
                now = now_ts()
                cur = db.execute(
                    'INSERT INTO users (token, nickname, avatar, type, openid, pid, createtime) VALUES (?,?,?,?,?,?,?)',
                    (t, '创世用户' + str(now)[-4:], '/static/eon.png', 1, openid, pid, now)
                )
                db.commit()
                u = dict(db.execute('SELECT * FROM users WHERE id = ?', (cur.lastrowid,)).fetchone())
            db.close()
            self.send_json({
                "code": 1,
                "msg": "登录成功",
                "data": {
                    "userinfo": {
                        "id": u['id'],
                        "token": u['token'],
                        "nickname": u['nickname'],
                        "avatar": u['avatar'],
                        "type": str(u['type']),
                        "thug_id": 0
                    }
                }
            })
            return

        if path == '/api/login/juhe/config':
            # 返回聚合登录配置（前端用于决定是否显示聚合登录按钮和跳转）
            ulogin = get_ulogin_config()
            types_list = ulogin.get('types', 'qq,wx,alipay').split(',')
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "enabled": ulogin.get('enabled', False),
                    "url": ulogin.get('url', ''),
                    "app_id": ulogin.get('app_id', ''),
                    "types": types_list,
                    "login_mode": ulogin.get('login_mode', 'juhe')
                }
            })
            return

        # ===== 用户相关 =====
        if path == '/api/user/quickRegister':
            # 微信一键登录（mock：直接创建/复用用户）
            pid = int(body.get('pid', 0) or 0)
            db = get_db()
            # 通过 openid 复用（mock 用固定 openid 模拟）
            openid = body.get('openid', 'mock_' + secrets.token_hex(8))
            row = db.execute('SELECT * FROM users WHERE openid = ?', (openid,)).fetchone()
            if row:
                u = dict(row)
            else:
                t = gen_token()
                now = now_ts()
                cur = db.execute(
                    'INSERT INTO users (token, nickname, avatar, type, openid, pid, createtime) VALUES (?,?,?,?,?,?,?)',
                    (t, '创世用户' + str(now)[-4:], '/static/eon.png', 1, openid, pid, now)
                )
                db.commit()
                u = dict(db.execute('SELECT * FROM users WHERE id = ?', (cur.lastrowid,)).fetchone())
            db.close()
            self.send_json({
                "code": 1,
                "msg": "登录成功",
                "data": {
                    "userinfo": {
                        "id": u['id'],
                        "token": u['token'],
                        "nickname": u['nickname'],
                        "avatar": u['avatar'],
                        "type": str(u['type']),
                        "thug_id": 0
                    }
                }
            })
            return

        if path == '/api/user/getUserInfo':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            thug = None
            if u.get('type') == 2:
                db = get_db()
                trow = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
                db.close()
                if trow:
                    thug = dict(trow)
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "id": u['id'],
                    "nickname": u['nickname'],
                    "avatar": u['avatar'],
                    "cs_avatar": u.get('cs_avatar', '') or u['avatar'],
                    "type": str(u['type']),
                    "money": u['money'],
                    "score": u['score'],
                    "level": u['level'],
                    "group_chat": u['group_chat'],
                    "thug": thug,
                    "examine": thug
                }
            })
            return

        if path == '/api/user/avatarUpload':
            # 客服头像上传（也兼容通用头像上传）
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            files = files or {}
            f = files.get('file') or files.get('avatar') or files.get('image')
            if not f or not f.get('filename'):
                self.send_json({"code": 0, "msg": "未上传文件"})
                return
            # 校验文件类型与大小
            ext = os.path.splitext(f['filename'])[1].lower()
            if ext not in ('.jpg', '.jpeg', '.png', '.gif', '.webp'):
                self.send_json({"code": 0, "msg": "图片格式不支持"})
                return
            if len(f['data']) > 5 * 1024 * 1024:
                self.send_json({"code": 0, "msg": "图片大小不能超过5MB"})
                return
            # 保存到 uploads 目录
            uploads_dir = os.path.join(WEB_ROOT, 'uploads')
            os.makedirs(uploads_dir, exist_ok=True)
            filename = f"avatar_{int(time.time())}_{secrets.token_hex(6)}{ext}"
            filepath = os.path.join(uploads_dir, filename)
            with open(filepath, 'wb') as fp:
                fp.write(f['data'])
            url = '/uploads/' + filename
            identity = (body.get('identity') if isinstance(body, dict) else '') or ''
            # 客服身份头像单独存储
            db = get_db()
            if identity == 'cs':
                db.execute('UPDATE users SET cs_avatar = ? WHERE id = ?', (url, u['id']))
            else:
                db.execute('UPDATE users SET avatar = ? WHERE id = ?', (url, u['id']))
            db.commit()
            db.close()
            self.send_json({
                "code": 1,
                "msg": "上传成功",
                "data": {
                    "url": url,
                    "avatar": url,
                    "full_url": '/h5' + url
                }
            })
            return

        # ===== 配置 =====
        if path == '/api/index/configure':
            # 从数据库读取系统配置
            small_lunbo = get_config('small_lunbo', '')
            goods_card_col = get_config('goods_card_col', '2')
            app_bg_color = get_config('app_bg_color', '')
            app_bg_image = get_config('app_bg_image', '')
            loading_name = get_config('loading_name', '创世网络')
            loading_logo = get_config('loading_logo', '/static/eon.png')
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "qiangdan_price": 25,
                    "qiangdan_enabled": 1,
                    "qiangdan_free_thugs": "1",
                    "club_name": "创世网络",
                    "brand_name": "创世网络",
                    "site_logo": "/static/eon.png",
                    "site_name": "创世网络",
                    "small_lunbo": small_lunbo.split(',') if small_lunbo else [],
                    "goods_card_col": int(goods_card_col) if goods_card_col.isdigit() else 2,
                    "app_bg_color": app_bg_color,
                    "app_bg_image": app_bg_image,
                    "loading_name": loading_name,
                    "loading_logo": loading_logo
                }
            })
            return

        # ===== 系统配置查询/更新 =====
        if path == '/api/config/get':
            # 获取配置项，支持按分组或名称查询
            group = qs.get('group', [''])[0] if isinstance(qs, dict) else ''
            name = qs.get('name', [''])[0] if isinstance(qs, dict) else ''
            db = get_db()
            if name:
                row = db.execute('SELECT * FROM fa_config WHERE name = ?', (name,)).fetchone()
                data = dict(row) if row else None
            elif group:
                rows = db.execute('SELECT * FROM fa_config WHERE group_name = ?', (group,)).fetchall()
                data = [dict(r) for r in rows]
            else:
                rows = db.execute('SELECT * FROM fa_config ORDER BY group_name, id').fetchall()
                data = [dict(r) for r in rows]
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        if path == '/api/config/save':
            # 保存配置项（支持批量）
            items = body.get('items', [])
            db = get_db()
            for item in items:
                name = item.get('name')
                value = item.get('value', '')
                if name:
                    db.execute('UPDATE fa_config SET value = ? WHERE name = ?', (value, name))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "保存成功"})
            return

        if path == '/api/config/uploadBg':
            # 上传前端背景图，保存后自动写入 app_bg_image 配置
            files = files or {}
            f = files.get('file') or files.get('image') or files.get('bg')
            if not f or not f.get('filename'):
                self.send_json({"code": 0, "msg": "未上传文件"})
                return
            ext = os.path.splitext(f['filename'])[1].lower()
            if ext not in ('.jpg', '.jpeg', '.png', '.gif', '.webp'):
                self.send_json({"code": 0, "msg": "图片格式不支持"})
                return
            if len(f['data']) > 5 * 1024 * 1024:
                self.send_json({"code": 0, "msg": "图片大小不能超过5MB"})
                return
            uploads_dir = os.path.join(WEB_ROOT, 'uploads')
            os.makedirs(uploads_dir, exist_ok=True)
            filename = f"bg_{int(time.time())}_{secrets.token_hex(6)}{ext}"
            filepath = os.path.join(uploads_dir, filename)
            with open(filepath, 'wb') as fp:
                fp.write(f['data'])
            url = '/uploads/' + filename
            db = get_db()
            db.execute("INSERT INTO fa_config (name, group_name, title, value) VALUES ('app_bg_image','basic','页面背景图片',?) ON CONFLICT(name) DO UPDATE SET value=excluded.value", (url,))
            db.commit()
            db.close()
            self.send_json({
                "code": 1,
                "msg": "上传成功",
                "data": {"url": url, "full_url": '/h5' + url}
            })
            return

        # ===== 系统通知 =====
        if path == '/api/index/noticeList':
            page = int(qs.get('page', ['1'])[0])
            limit = int(qs.get('limit', ['20'])[0])
            type_filter = qs.get('type', ['1'])[0]
            db = get_db()
            total = db.execute(
                'SELECT COUNT(*) FROM notices WHERE type = ?', (type_filter,)
            ).fetchone()[0]
            rows = db.execute(
                'SELECT * FROM notices WHERE type = ? ORDER BY createtime DESC LIMIT ? OFFSET ?',
                (type_filter, limit, (page - 1) * limit)
            ).fetchall()
            db.close()
            list_data = [{
                "id": r['id'],
                "title": r['title'],
                "synopsis": r['synopsis'],
                "content": r['content'],
                "type": r['type'],
                "createtime": fmt_time(r['createtime']),
                "create_time": fmt_time(r['createtime'])
            } for r in rows]
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "data": list_data,
                    "total": total,
                    "current_page": page,
                    "per_page": limit
                }
            })
            return

        if path == '/api/index/noticeMsg':
            nid = int(qs.get('id', ['0'])[0])
            db = get_db()
            row = db.execute('SELECT * FROM notices WHERE id = ?', (nid,)).fetchone()
            db.close()
            if not row:
                self.send_json({"code": 0, "msg": "公告不存在"})
                return
            r = dict(row)
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "id": r['id'],
                    "title": r['title'],
                    "synopsis": r['synopsis'],
                    "content": r['content'],
                    "type": r['type'],
                    "createtime": fmt_time(r['createtime'])
                }
            })
            return

        # ===== 订单列表（订单消息） =====
        if path == '/api/goods/orderList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            page = int(qs.get('page', ['1'])[0])
            limit = int(qs.get('limit', ['20'])[0])
            db = get_db()
            total = db.execute(
                'SELECT COUNT(*) FROM orders WHERE user_id = ?', (u['id'],)
            ).fetchone()[0]
            rows = db.execute(
                'SELECT * FROM orders WHERE user_id = ? ORDER BY createtime DESC LIMIT ? OFFSET ?',
                (u['id'], limit, (page - 1) * limit)
            ).fetchall()
            db.close()
            list_data = [{
                "id": r['id'],
                "order_no": r['order_no'],
                "title": r['title'],
                "logo": r['logo'],
                "price": r['price'],
                "pay_price": r['pay_price'],
                "number": r['number'],
                "status": r['status'],
                "status_text": r['status_text'],
                "synopsis": r['synopsis'],
                "create_time": fmt_time(r['createtime']),
                "createtime": fmt_time(r['createtime'])
            } for r in rows]
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "data": list_data,
                    "total": total,
                    "current_page": page,
                    "per_page": limit
                }
            })
            return

        # ===== 客服列表 =====
        if path == '/api/index/customerServiceList':
            db = get_db()
            rows = db.execute('SELECT * FROM customer_services WHERE online = 1').fetchall()
            db.close()
            list_data = [{
                "id": r['id'],
                "name": r['name'],
                "user_id": r['user_id'],
                "type": r['type'],
                "avatar": r['avatar'],
                "online": r['online']
            } for r in rows]
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": list_data
            })
            return

        # ===== IM 会话列表（mock：返回空，因为没有真实 IM） =====
        if path == '/api/im/getConversationList' or path == '/api/im/init':
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {"list": [], "total": 0}
            })
            return

        # ===== 抢单相关（保留原有） =====
        if '/api/qiangdan/info' in path:
            self.send_json({
                "code": 1,
                "msg": "获取成功",
                "data": {
                    "enabled": 1,
                    "price": 25,
                    "is_subscribed": True,
                    "is_free": True,
                    "expire_time": EXPIRE_TS,
                    "expire_text": EXPIRE_TEXT,
                    "qiangdan_status": TOGGLE_STATUS,
                    "config": CONFIG
                }
            })
            return
        if '/api/qiangdan/purchase' in path:
            self.send_json({
                "code": 1, "msg": "已为您免费开通",
                "data": {"expire_time": EXPIRE_TS, "expire_text": EXPIRE_TEXT}
            })
            return
        if '/api/qiangdan/sync' in path:
            self.send_json({
                "code": 1, "msg": "同步成功",
                "data": {"expire_time": EXPIRE_TS, "expire_text": EXPIRE_TEXT}
            })
            return
        if '/api/qiangdan/toggle' in path:
            status = body.get('status', 1)
            TOGGLE_STATUS = status
            self.send_json({"code": 1, "msg": "操作成功", "data": {"status": status}})
            return
        if '/api/qiangdan/saveConfig' in path:
            CONFIG['exclude_remark'] = bool(body.get('exclude_remark', 0))
            CONFIG['exclude_value_added'] = bool(body.get('exclude_value_added', 0))
            self.send_json({"code": 1, "msg": "保存成功", "data": CONFIG})
            return
        if '/api/qiangdan/logs' in path:
            self.send_json({
                "code": 1, "msg": "获取成功",
                "data": {"list": [], "total": 0, "total_grabbed": 0, "today_grabbed": 0}
            })
            return

        # ===== 支付配置 =====
        if path == '/api/pay/config':
            epay = get_epay_config()
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "pay_methods": epay['pay_methods'].split(','),
                    "epay_enabled": bool(epay['apiurl'] and epay['pid'] and epay['key']),
                    "epay_apiurl": epay['apiurl'],
                    "epay_pid": epay['pid']
                }
            })
            return

        # ===== 第三方登录绑定 =====
        if path == '/api/third/bind':
            platform = body.get('platform', '')
            openid = body.get('openid', '')
            openname = body.get('openname', '')
            token = self.get_token()
            user = get_user_by_token(token)
            if not user:
                self.send_json({"code": 0, "msg": "请先登录"})
                return
            if not platform or not openid:
                self.send_json({"code": 0, "msg": "参数不完整"})
                return
            now = int(time.time())
            db = get_db()
            db.execute(
                'INSERT OR REPLACE INTO fa_third (user_id, platform, openid, openname, createtime, logintime) VALUES (?,?,?,?,?,?)',
                (user['id'], platform, openid, openname, now, now)
            )
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "绑定成功"})
            return

        # ===== 邀请码校验 =====
        if path == '/api/invite/check':
            code = (qs.get('code', [''])[0] if isinstance(qs, dict) else '').strip() or body.get('code', '').strip()
            if not code:
                self.send_json({"code": 0, "msg": "请输入邀请码"})
                return
            db = get_db()
            row = db.execute('SELECT * FROM invite_codes WHERE code = ?', (code,)).fetchone()
            db.close()
            if not row:
                self.send_json({"code": 0, "msg": "邀请码不存在"})
                return
            inv = dict(row)
            self.send_json({
                "code": 1,
                "msg": "校验成功",
                "data": {
                    "type": inv['type'],  # cs | thug
                    "name": inv['name'],
                    "code": inv['code']
                }
            })
            return

        # ===== 打手入驻（注册秘钥） =====
        if path == '/api/thug/thugAdd':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            secret_key = (body.get('secret_key') or '').strip()
            if not secret_key:
                self.send_json({"code": 0, "msg": "请输入注册秘钥"})
                return
            db = get_db()
            inv = db.execute('SELECT * FROM invite_codes WHERE code = ? AND type = ?', (secret_key, 'thug')).fetchone()
            if not inv:
                db.close()
                self.send_json({"code": 0, "msg": "注册秘钥无效"})
                return
            # 检查是否已入驻
            existing = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            if existing:
                db.close()
                self.send_json({"code": 0, "msg": "您已提交过打手入驻申请"})
                return
            now = now_ts()
            db.execute(
                'INSERT INTO thug (user_id, name, status, sh_status, is_customer_service, createtime) VALUES (?,?,?,?,?,?)',
                (u['id'], inv['name'], 1, 1, 0, now)
            )
            db.execute('UPDATE users SET type = 2 WHERE id = ?', (u['id'],))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "打手入驻成功"})
            return

        # ===== 打手详情 =====
        if path == '/api/thug/thugMsg':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            thug_id = qs.get('thug_id', [None])[0]
            db = get_db()
            if thug_id:
                trow = db.execute('SELECT * FROM thug WHERE id = ?', (thug_id,)).fetchone()
            else:
                trow = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            if not trow:
                db.close()
                self.send_json({"code": 0, "msg": "打手信息不存在"})
                return
            t = dict(trow)
            db.close()
            self.send_json({
                "code": 1, "msg": "成功",
                "data": {
                    "id": t['id'],
                    "user_id": t['user_id'],
                    "name": t['name'],
                    "status": t['status'],
                    "sh_status": t['sh_status'],
                    "is_customer_service": t['is_customer_service'],
                    "jd_status": "1",
                    "frozen_money": "0.00",
                    "available_money": "0.00",
                    "total_money": "0.00",
                    "bond": "0.00",
                    "level": 1,
                    "createtime": t['createtime']
                }
            })
            return

        # ===== 打手接单列表 =====
        if path == '/api/thug/orderList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            status = qs.get('status', [''])[0]
            page = int(qs.get('page', ['1'])[0])
            limit = int(qs.get('limit', ['20'])[0])
            db = get_db()
            if status:
                total = db.execute('SELECT COUNT(*) FROM orders WHERE thug_id = ? AND status = ?', (u['id'], int(status))).fetchone()[0]
                rows = db.execute('SELECT * FROM orders WHERE thug_id = ? AND status = ? ORDER BY createtime DESC LIMIT ? OFFSET ?', (u['id'], int(status), limit, (page - 1) * limit)).fetchall()
            else:
                total = db.execute('SELECT COUNT(*) FROM orders WHERE thug_id = ?', (u['id'],)).fetchone()[0]
                rows = db.execute('SELECT * FROM orders WHERE thug_id = ? ORDER BY createtime DESC LIMIT ? OFFSET ?', (u['id'], limit, (page - 1) * limit)).fetchall()
            db.close()
            list_data = [{
                "id": r['id'], "order_no": r['order_no'], "title": r['title'],
                "amount": r['price'], "status": r['status'],
                "game_id": r['game_id'] or '', "wz_id": r['game_qf'] or '',
                "createtime": r['createtime']
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": list_data, "total": total}})
            return

        # ===== 可接单列表 =====
        if path == '/api/thug/receivingOrders':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            db = get_db()
            rows = db.execute('SELECT * FROM orders WHERE status = 1 ORDER BY createtime DESC LIMIT 20').fetchall()
            db.close()
            list_data = [{
                "id": r['id'], "order_no": r['order_no'], "title": r['title'],
                "amount": r['price'], "status": r['status'],
                "game_id": r['game_id'] or '', "wz_id": r['game_qf'] or '',
                "createtime": r['createtime']
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": list_data, "total": len(list_data)}})
            return

        # ===== 打手权限 =====
        if path == '/api/thug/getThugPermissions':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            self.send_json({"code": 1, "msg": "成功", "data": ["王者荣耀-打手", "和平精英-打手", "英雄联盟-打手", "穿越火线-打手"]})
            return

        # ===== 打手编辑 =====
        if path == '/api/thug/thugEdit':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            self.send_json({"code": 1, "msg": "保存成功"})
            return

        # ===== 客服入驻（注册秘钥） =====
        if path == '/api/cs/csAdd':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            secret_key = (body.get('secret_key') or '').strip()
            if not secret_key:
                self.send_json({"code": 0, "msg": "请输入注册秘钥"})
                return
            db = get_db()
            inv = db.execute('SELECT * FROM invite_codes WHERE code = ? AND type = ?', (secret_key, 'cs')).fetchone()
            if not inv:
                db.close()
                self.send_json({"code": 0, "msg": "注册秘钥无效"})
                return
            existing = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            now = now_ts()
            if existing:
                db.execute('UPDATE thug SET is_customer_service = 1, sh_status = 1 WHERE user_id = ?', (u['id'],))
            else:
                db.execute(
                    'INSERT INTO thug (user_id, name, status, sh_status, is_customer_service, createtime) VALUES (?,?,?,?,?,?)',
                    (u['id'], inv['name'], 1, 1, 1, now)
                )
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "客服入驻成功"})
            return

        # ===== 商品分类（首页/分类/打手专区/工作台共用，返回数组）=====
        if path == '/api/goods/categoryList':
            categories = [
                {"id": 1, "name": "王者荣耀", "image": "/h5/static/images/classify.png",
                 "children": [{"id": 101, "name": "排位上分"}, {"id": 102, "name": "娱乐陪玩"}]},
                {"id": 2, "name": "和平精英", "image": "/h5/static/images/classify.png",
                 "children": [{"id": 201, "name": "经典吃鸡"}, {"id": 202, "name": "娱乐陪玩"}]},
                {"id": 3, "name": "英雄联盟", "image": "/h5/static/images/classify.png",
                 "children": [{"id": 301, "name": "排位上分"}, {"id": 302, "name": "云顶之弈"}]},
                {"id": 4, "name": "穿越火线", "image": "/h5/static/images/classify.png",
                 "children": [{"id": 401, "name": "排位上分"}, {"id": 402, "name": "娱乐陪玩"}]},
            ]
            self.send_json({"code": 1, "msg": "成功", "data": categories})
            return

        # ===== 兜底：其他接口返回空数据，避免前端报错 =====
        self.send_json({"code": 1, "msg": "成功", "data": {"data": [], "total": 0}})


if __name__ == '__main__':
    server = HTTPServer((HOST, PORT), Handler)
    print(f"API 服务器已启动: http://localhost:{PORT}")
    print(f"H5 访问地址: http://localhost:{PORT}/h5/")
    print(f"SQLite 数据库: {DB_PATH}")
    server.serve_forever()
