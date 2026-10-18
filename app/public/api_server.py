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
from urllib.parse import urlparse, parse_qs, urlencode
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

# ===== 后台通用资源定义（用于后台各菜单的真实 CRUD 页面） =====
def _rf(key, label, t='text', options=None):
    return {'key': key, 'label': label, 'type': t, 'options': options or {}}

RESOURCES = {
    'goods_category': {'title': '商品分类管理', 'search': ['name'], 'columns': ['name', 'icon', 'sort', 'status'], 'fields': [
        _rf('name', '分类名称'), _rf('icon', '分类图标URL'), _rf('sort', '排序', 'number'), _rf('status', '是否显示', 'switch')]},
    'goods': {'title': '商品管理', 'search': ['name'], 'columns': ['name', 'category_id', 'price', 'status'], 'fields': [
        _rf('name', '商品名称'), _rf('category_id', '所属分类ID', 'number'), _rf('logo', '封面图URL'),
        _rf('price', '价格', 'number'), _rf('unit', '计价单位'), _rf('status', '上架状态', 'switch'), _rf('remark', '商品简介', 'textarea')]},
    'prize_pool': {'title': '奖池管理', 'search': ['name'], 'columns': ['name', 'rate', 'stock', 'status'], 'fields': [
        _rf('name', '奖品名称'), _rf('icon', '奖品图URL'), _rf('rate', '中奖概率(%)', 'number'),
        _rf('stock', '库存数量', 'number'), _rf('status', '是否启用', 'switch')]},
    'banner': {'title': '轮播图管理', 'search': ['title'], 'columns': ['image', 'title', 'link', 'sort', 'status'], 'fields': [
        _rf('title', '标题'), _rf('image', '图片URL'), _rf('link', '跳转链接'), _rf('sort', '排序', 'number'), _rf('status', '是否显示', 'switch')]},
    'order_complaint': {'title': '订单投诉', 'search': ['order_no', 'content'], 'columns': ['order_no', 'user_id', 'content', 'status'], 'fields': [
        _rf('order_no', '订单号'), _rf('user_id', '投诉用户ID', 'number'), _rf('content', '投诉内容', 'textarea'),
        _rf('status', '处理状态', 'select', {'0': '待处理', '1': '处理中', '2': '已解决', '3': '已驳回'})]},
    'withdraw': {'title': '提现记录', 'search': ['user_id', 'account'], 'columns': ['user_id', 'amount', 'account', 'status'], 'fields': [
        _rf('user_id', '用户ID', 'number'), _rf('amount', '提现金额', 'number'), _rf('account', '收款账号'),
        _rf('status', '状态', 'select', {'0': '待审核', '1': '已打款', '2': '已驳回'}), _rf('remark', '备注')]},
    'level': {'title': '等级列表', 'search': ['name'], 'columns': ['name', 'need_score', 'discount', 'remark'], 'fields': [
        _rf('name', '等级名称'), _rf('icon', '等级图标'), _rf('need_score', '所需积分', 'number'),
        _rf('discount', '折扣(0-1)', 'number'), _rf('remark', '权益说明', 'textarea')]},
    'cs_account': {'title': '客服账号', 'search': ['name', 'username'], 'columns': ['name', 'username', 'thug_id', 'status'], 'fields': [
        _rf('name', '客服名称'), _rf('username', '登录账号'), _rf('password', '登录密码'),
        _rf('thug_id', '关联打手ID', 'number'), _rf('status', '状态', 'switch')]},
    'fine': {'title': '罚款管理', 'search': ['name'], 'columns': ['name', 'amount', 'user_id', 'status'], 'fields': [
        _rf('name', '罚款项目'), _rf('amount', '罚款金额', 'number'), _rf('user_id', '用户ID', 'number'),
        _rf('reason', '罚款原因', 'textarea'), _rf('status', '状态', 'select', {'0': '待缴纳', '1': '已缴纳', '2': '已免除'})]},
    'quick_entry': {'title': '快捷入口', 'search': ['name'], 'columns': ['name', 'icon', 'color', 'link', 'sort'], 'fields': [
        _rf('name', '入口名称'), _rf('icon', '图标'), _rf('color', '图标颜色'), _rf('link', '跳转链接'),
        _rf('sort', '排序', 'number'), _rf('status', '是否显示', 'switch')]},
    'admin_user': {'title': '管理员管理', 'search': ['username', 'nickname'], 'columns': ['username', 'nickname', 'role', 'status'], 'fields': [
        _rf('username', '登录账号'), _rf('nickname', '昵称'), _rf('password', '登录密码'), _rf('role', '角色'), _rf('status', '状态', 'switch')]},
    'role': {'title': '角色管理', 'search': ['name'], 'columns': ['name', 'auth', 'remark'], 'fields': [
        _rf('name', '角色名称'), _rf('auth', '权限标识'), _rf('remark', '备注', 'textarea')]},
    'cron': {'title': '计划任务', 'search': ['name'], 'columns': ['name', 'cron', 'command', 'status'], 'fields': [
        _rf('name', '任务名称'), _rf('command', '执行命令'), _rf('cron', 'Cron表达式'), _rf('status', '是否启用', 'switch')]},
    'bill': {'title': '账单管理', 'search': ['title'], 'columns': ['title', 'amount', 'type', 'remark'], 'fields': [
        _rf('title', '账单标题'), _rf('amount', '金额', 'number'), _rf('type', '类型', 'select', {'1': '收入', '2': '支出'}), _rf('remark', '备注', 'textarea')]},
    'package': {'title': '我的套餐', 'search': ['name'], 'columns': ['name', 'price', 'days', 'status'], 'fields': [
        _rf('name', '套餐名称'), _rf('price', '价格', 'number'), _rf('days', '有效期(天)', 'number'),
        _rf('desc', '套餐说明', 'textarea'), _rf('status', '是否上架', 'switch')]},
    'balance': {'title': '余额明细', 'search': ['user_id', 'remark'], 'columns': ['user_id', 'amount', 'before', 'after', 'type', 'remark'], 'fields': [
        _rf('user_id', '用户ID', 'number'), _rf('amount', '变动金额', 'number'), _rf('before', '变动前', 'number'),
        _rf('after', '变动后', 'number'), _rf('type', '类型', 'select', {'1': '收入', '2': '支出'}), _rf('remark', '备注')]},
    'recharge_order': {'title': '充值订单', 'search': ['order_no', 'user_id'], 'columns': ['order_no', 'user_id', 'amount', 'method', 'status'], 'fields': [
        _rf('order_no', '订单号'), _rf('user_id', '用户ID', 'number'), _rf('amount', '充值金额', 'number'),
        _rf('method', '支付方式'), _rf('status', '状态', 'select', {'0': '待支付', '1': '已支付', '2': '已退款'})]},
    'order_commission': {'title': '订单佣金', 'search': ['order_no', 'user_id'], 'columns': ['order_no', 'user_id', 'amount', 'rate', 'status'], 'fields': [
        _rf('order_no', '订单号'), _rf('user_id', '用户ID', 'number'), _rf('amount', '佣金金额', 'number'),
        _rf('rate', '佣金比例', 'number'), _rf('status', '状态', 'select', {'0': '待结算', '1': '已结算'})]},
    'commission_detail': {'title': '佣金明细', 'search': ['user_id', 'remark'], 'columns': ['user_id', 'amount', 'type', 'remark'], 'fields': [
        _rf('user_id', '用户ID', 'number'), _rf('amount', '金额', 'number'),
        _rf('type', '类型', 'select', {'1': '获得', '2': '提现'}), _rf('remark', '备注')]},
    'refund': {'title': '退款记录', 'search': ['order_no', 'user_id'], 'columns': ['order_no', 'user_id', 'amount', 'status'], 'fields': [
        _rf('order_no', '订单号'), _rf('user_id', '用户ID', 'number'), _rf('amount', '退款金额', 'number'),
        _rf('reason', '退款原因', 'textarea'), _rf('status', '状态', 'select', {'0': '待处理', '1': '已退款', '2': '已驳回'})]},
    'deposit': {'title': '保证金记录', 'search': ['user_id'], 'columns': ['user_id', 'amount', 'status', 'remark'], 'fields': [
        _rf('user_id', '用户ID', 'number'), _rf('amount', '保证金金额', 'number'),
        _rf('status', '状态', 'select', {'0': '已缴纳', '1': '已退还', '2': '已扣除'}), _rf('remark', '备注')]},
    'wechat_notice': {'title': '公众号通知', 'search': ['title'], 'columns': ['title', 'template_id', 'status'], 'fields': [
        _rf('title', '通知标题'), _rf('template_id', '模板ID'), _rf('content', '通知内容', 'textarea'), _rf('status', '是否启用', 'switch')]},
    # ===== 单例配置型（表单页） =====
    'upload_storage': {'title': '存储配置', 'singleton': True, 'fields': [
        _rf('driver', '存储驱动', 'select', {'local': '本地存储', 'qiniu': '七牛云', 'oss': '阿里云OSS', 'cos': '腾讯云COS'}),
        _rf('domain', '访问域名'), _rf('qiniu_key', '七牛 AccessKey'), _rf('qiniu_secret', '七牛 SecretKey'),
        _rf('qiniu_bucket', '七牛 Bucket'), _rf('oss_key', 'OSS AccessKeyId'), _rf('oss_secret', 'OSS AccessKeySecret'), _rf('oss_bucket', 'OSS Bucket')]},
    'my_setting': {'title': '我的设置', 'singleton': True, 'fields': [
        _rf('site_name', '站点名称'), _rf('site_logo', '站点Logo'), _rf('copyright', '版权信息'),
        _rf('customer_phone', '客服电话'), _rf('notice', '全局公告', 'textarea')]},
    'wechat_robot': {'title': '企业微信机器人', 'singleton': True, 'fields': [
        _rf('webhook', 'Webhook地址'), _rf('enabled', '是否启用', 'switch'), _rf('secret', '签名密钥')]},
    'auth': {'title': '平台授权对接', 'singleton': True, 'fields': [
        _rf('domain', '授权域名'), _rf('license', '授权码'), _rf('enabled', '是否启用', 'switch')]},
    'sms_config': {'title': '短信配置', 'singleton': True, 'fields': [
        _rf('provider', '短信服务商', 'select', {'aliyun': '阿里云', 'tencent': '腾讯云', 'yunpian': '云片'}),
        _rf('access_key', 'AccessKey'), _rf('access_secret', 'AccessSecret'), _rf('sign', '短信签名'), _rf('template', '模板ID'), _rf('enabled', '是否启用', 'switch')]},
    'profile': {'title': '个人中心', 'singleton': True, 'fields': [
        _rf('nickname', '昵称'), _rf('avatar', '头像地址'), _rf('password', '登录密码'), _rf('signature', '个性签名')]},
}

# 资源表默认种子数据（仅在表为空时写入）
RESOURCE_SEED = {
    'level': [('V1 青铜', '', '10', '1', '初级会员'), ('V2 白银', '', '100', '0.98', '9.8折'),
              ('V3 黄金', '', '500', '0.95', '9.5折'), ('V4 铂金', '', '2000', '0.92', '9.2折'), ('V5 钻石', '', '5000', '0.88', '8.8折')],
    'goods_category': [('陪玩上分', '', '1', '1'), ('娱乐陪玩', '', '2', '1'), ('电竞教学', '', '3', '1')],
    'prize_pool': [('一等奖 iPhone', '', '1', '10', '1'), ('二等奖 现金红包', '', '9', '100', '1'), ('谢谢惠顾', '', '90', '0', '1')],
    'banner': [('首页横幅', '', '/pages/classify/index', '1', '1')],
    'quick_entry': [('打手入驻', 'user', '#2B6EFF', '/pages/hatchet/index', '1', '1'),
                    ('客服入驻', 'chat', '#FF7A45', '/pages/cs/index', '2', '1')],
    'upload_storage': [('local', '', '', '', '', '', '', '')],
    'my_setting': [('创世网络', '', '© 创世网络工作室', '', '根据国家法律规定，未成年人禁止下单！')],
    'wechat_robot': [('', '0', '')],
    'auth': [('', '', '1')],
    'sms_config': [('aliyun', '', '', '创世网络', '', '0')],
    'profile': [('超级管理员', '', '123456', '')],
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
        createtime INTEGER,
        real_name TEXT DEFAULT '',
        id_card TEXT DEFAULT '',
        realname_front TEXT DEFAULT '',
        realname_back TEXT DEFAULT '',
        realname_status INTEGER DEFAULT 0,
        realname_reason TEXT DEFAULT '',
        realname_time INTEGER DEFAULT 0
    )''')
    # 老库补字段（实名认证）
    for _col in ('real_name', 'id_card', 'realname_front', 'realname_back', 'realname_reason'):
        try:
            cur.execute('ALTER TABLE thug ADD COLUMN %s TEXT DEFAULT ""' % _col)
        except sqlite3.OperationalError:
            pass
    for _col in ('realname_status', 'realname_time'):
        try:
            cur.execute('ALTER TABLE thug ADD COLUMN %s INTEGER DEFAULT 0' % _col)
        except sqlite3.OperationalError:
            pass
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
    # 表结构迁移（新增字段，兼容旧库）
    for col, ddl in [
        ('session_timeout', "ALTER TABLE customer_services ADD COLUMN session_timeout INTEGER DEFAULT 3"),
        ('cs_mode', "ALTER TABLE customer_services ADD COLUMN cs_mode TEXT DEFAULT 'built_in'"),
        ('wecom_url', "ALTER TABLE customer_services ADD COLUMN wecom_url TEXT DEFAULT ''"),
        ('auto_assign', "ALTER TABLE customer_services ADD COLUMN auto_assign INTEGER DEFAULT 0"),
    ]:
        try:
            cur.execute(ddl)
        except Exception:
            pass
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
        ]
        for item in config_items:
            cur.execute('INSERT INTO fa_config (name, group_name, title, tip, type, visible, value, content, rule, extend, setting) VALUES (?,?,?,?,?,?,?,?,?,?,?)', item)
    db.commit()
    # ===== 通用资源表（后台各菜单 CRUD） =====
    for _res, _spec in RESOURCES.items():
        _cols = ', '.join([f['key'] + " TEXT DEFAULT ''" for f in _spec['fields']])
        cur.execute('CREATE TABLE IF NOT EXISTS res_%s (id INTEGER PRIMARY KEY AUTOINCREMENT, %s, createtime INTEGER)' % (_res, _cols))
        # 单例资源确保存在 id=1 的一行
        if _spec.get('singleton'):
            cur.execute('SELECT COUNT(*) FROM res_%s WHERE id = 1' % _res)
            if cur.fetchone()[0] == 0:
                cur.execute('INSERT INTO res_%s (id) VALUES (1)' % _res)
        # 种子数据（仅空表写入）
        seed = RESOURCE_SEED.get(_res)
        if seed and not _spec.get('singleton'):
            cur.execute('SELECT COUNT(*) FROM res_%s' % _res)
            if cur.fetchone()[0] == 0:
                _keys = [f['key'] for f in _spec['fields']]
                _ph = ','.join(['?'] * (len(_keys) + 1))
                for row in seed:
                    cur.execute('INSERT INTO res_%s (%s, createtime) VALUES (%s)' % (_res, ','.join(_keys), _ph), tuple(str(x) for x in row) + (int(time.time()),))
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

def set_config(name, value):
    """写入 fa_config 配置，不存在则插入"""
    db = get_db()
    db.execute(
        'INSERT INTO fa_config (name, value) VALUES (?, ?) '
        'ON CONFLICT(name) DO UPDATE SET value = excluded.value',
        (name, str(value))
    )
    db.commit()
    db.close()

# ===== 前端装修主题配置 =====
DEFAULT_DECORATION = {
    "name": "活力橙",
    "colors": {
        "brand": "#F25109",
        "brand_gradient": "#FF7C43",
        "page_bg": "#F5F5F5",
        "card_bg": "#FFFFFF",
        "text_main": "#1A1A1A",
        "text_sub": "#999999",
        "price": "#EA3A1C",
        "btn_text": "#FFFFFF",
        "tabbar_bg": "#FFFFFF",
        "top_text": "#FFFFFF",
        "search_bg": "#FFF8F1",
        "card_border": "#E8E8E8",
        "notice_bg": "#FFF3E0",
        "notice_text": "#F25109",
        "tabbar_active": "#F25109",
        "tabbar_text": "#999999",
        "tabbar_text_active": "#F25109",
        "tabbar_radius": "999",
    },
    "bg_image": "",
    "nav_style": "float_ellipse",
    "floating_nav": [
        {"text": "首页", "icon": "home", "color": "#22c55e", "link": "/pages/index/index"},
        {"text": "分类", "icon": "grid", "color": "#22c55e", "link": "/pages/classify/index"},
        {"text": "我的", "icon": "user", "color": "#22c55e", "link": "/pages/mine/index"},
    ],
}

def get_decoration_config():
    raw = get_config('frontend_decoration', '')
    if raw:
        try:
            cfg = json.loads(raw)
            # 合并默认值，避免缺字段
            merged = json.loads(json.dumps(DEFAULT_DECORATION))
            merged['colors'].update(cfg.get('colors', {}))
            merged['name'] = cfg.get('name', DEFAULT_DECORATION['name'])
            merged['bg_image'] = cfg.get('bg_image', '')
            merged['nav_style'] = cfg.get('nav_style', DEFAULT_DECORATION['nav_style'])
            merged['floating_nav'] = cfg.get('floating_nav', DEFAULT_DECORATION['floating_nav'])
            return merged
        except Exception:
            pass
    return json.loads(json.dumps(DEFAULT_DECORATION))

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
            # H5 入口注入前端装修主题脚本（从 /api/frontend/decoration 拉取配色并应用）
            if ext == '.html' and filepath.endswith(os.path.join('h5', 'index.html')):
                content = content.decode('utf-8', errors='ignore')
                theme_script = '''<script>
(function(){
  function applyTheme(cfg){
    var c = (cfg && cfg.colors) || {};
    var bg = cfg && cfg.bg_image ? cfg.bg_image : '';
    window.__floatingNav = (cfg && cfg.floating_nav) || [];
    window.__navStyle = (cfg && cfg.nav_style) || 'float_ellipse';
    if (window.__onNavConfig) { try { window.__onNavConfig(); } catch(e){} }
    var root = document.documentElement;
    var map = {
      brand:'--theme-brand', brand_gradient:'--theme-brand-gradient',
      page_bg:'--theme-page-bg', card_bg:'--theme-card-bg',
      text_main:'--theme-text-main', text_sub:'--theme-text-sub',
      price:'--theme-price', btn_text:'--theme-btn-text',
      tabbar_bg:'--theme-tabbar-bg', top_text:'--theme-top-text',
      search_bg:'--theme-search-bg', card_border:'--theme-card-border',
      notice_bg:'--theme-notice-bg', notice_text:'--theme-notice-text',
      tabbar_active:'--theme-tabbar-active', tabbar_text:'--theme-tabbar-text',
      tabbar_text_active:'--theme-tabbar-text-active'
    };
    for (var k in map) if (c[k]) root.style.setProperty(map[k], c[k]);
    if (c.page_bg) document.body.style.backgroundColor = c.page_bg;
    if (bg) { document.body.style.backgroundImage='url('+bg+')'; document.body.style.backgroundSize='cover'; document.body.style.backgroundPosition='center'; document.body.style.backgroundAttachment='fixed'; }
    var style = document.getElementById('__theme_override__');
    if (!style) { style = document.createElement('style'); style.id='__theme_override__'; document.head.appendChild(style); }
    style.textContent = [
      ':root{',
        '--theme-brand:'+(c.brand||'#F25109')+';',
        '--theme-brand-gradient:'+(c.brand_gradient||'#FF7C43')+';',
        '--theme-page-bg:'+(c.page_bg||'#F5F5F5')+';',
        '--theme-card-bg:'+(c.card_bg||'#fff')+';',
        '--theme-text-main:'+(c.text_main||'#1a1a1a')+';',
        '--theme-text-sub:'+(c.text_sub||'#999')+';',
        '--theme-price:'+(c.price||'#EA3A1C')+';',
        '--theme-btn-text:'+(c.btn_text||'#fff')+';',
        '--theme-tabbar-bg:'+(c.tabbar_bg||'#fff')+';',
        '--theme-tabbar-active:'+(c.tabbar_active||c.brand||'#F25109')+';',
        '--theme-tabbar-text:'+(c.tabbar_text||'#999')+';',
        '--theme-tabbar-text-active:'+(c.tabbar_text_active||c.tabbar_active||c.brand||'#F25109')+';',
        '--theme-top-text:'+(c.top_text||'#fff')+';',
        '--theme-search-bg:'+(c.search_bg||'#FFF8F1')+';',
        '--theme-card-border:'+(c.card_border||'#e8e8e8')+';',
        '--theme-notice-bg:'+(c.notice_bg||'#FFF3E0')+';',
        '--theme-notice-text:'+(c.notice_text||'#F25109')+';',
      '}',
      'html,body,#app,.uni-page-wrapper,.uni-page-body{background-color:var(--theme-page-bg)!important;background-image:none!important;}',
      '.uni-tabbar,.uni-tabbar__item,.uni-tabbar-nav{background:var(--theme-tabbar-bg)!important;}',
      // 通用按钮/主色覆盖
      'button,.uv-button--primary,[class*="primary"]{background:linear-gradient(90deg,var(--theme-brand),var(--theme-brand-gradient))!important;color:var(--theme-btn-text)!important;border-color:var(--theme-brand)!important;}',
      // 卡片/容器背景
      '[class*="card"],[class*="Card"],[class*="item"][class*="bg"]{background-color:var(--theme-card-bg)!important;}',
      // 文字颜色
      'uni-text,text,.uni-text{color:var(--theme-text-main);}',
      '[class*="sub"],[class*="muted"],[class*="desc"]{color:var(--theme-text-sub)!important;}',
      '[class*="price"],[class*="money"]{color:var(--theme-price)!important;}',
      '[class*="notice"],[class*="Notice"]{background:var(--theme-notice-bg)!important;color:var(--theme-notice-text)!important;}',
      '[class*="search"],[class*="Search"]{background:var(--theme-search-bg)!important;}'
    ].join('');
  }
  fetch('/api/frontend/decoration',{cache:'no-store'}).then(function(r){return r.json();}).then(function(res){
    if (res && res.data) applyTheme(res.data);
  }).catch(function(){});
  // 暴露给后台实时预览调用
  window.__applyTheme = applyTheme;
})();
</script>'''
                content = content.replace('</head>', theme_script + '\n</head>')
                content = content.encode('utf-8')
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
        # /yyl/ 反向代理到 yylAdmin 后台框架（登录/权限/菜单/日志等）
        if path.startswith('/yyl/'):
            self.proxy_yyl(path[len('/yyl'):], 'GET', b'', qs)
            return
        # 自定义后台 UI
        if path in ('/adminui', '/adminui/'):
            path = '/adminui/index.html'
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
        # /yyl/ 反向代理到 yylAdmin 后台框架
        if path.startswith('/yyl/'):
            self.proxy_yyl(path[len('/yyl'):], 'POST', raw_body, qs)
            return
        self.send_response(404)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'404 Not Found')

    def get_token(self):
        return self.headers.get('token', '')

    def proxy_yyl(self, target_path, method, body_bytes, qs=None):
        """把 /yyl/* 请求反向代理到 yylAdmin（后台框架，PHP）"""
        import urllib.request
        import urllib.error
        url = 'http://127.0.0.1:8080' + target_path
        if qs:
            url += ('&' if '?' in url else '?') + urlencode(qs, doseq=True)
        headers = {}
        for k in ('AdminToken', 'AdminUserId', 'Authorization', 'Content-Type'):
            v = self.headers.get(k)
            if v:
                headers[k] = v
        req = urllib.request.Request(url, data=body_bytes if method == 'POST' else None,
                                     headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                data = resp.read()
                ctype = resp.headers.get('Content-Type', 'application/json')
                code = resp.status
        except urllib.error.HTTPError as e:
            data = e.read()
            ctype = e.headers.get('Content-Type', 'application/json')
            code = e.code
        except Exception as e:
            # yylAdmin PHP 框架不可用时的本地回退，保证后台登录/退出可用
            if 'AdminLogin/login' in target_path:
                data = json.dumps({
                    "code": 200,
                    "msg": "登录成功（本地回退模式）",
                    "data": {"admin_token": "local-" + secrets.token_hex(16), "admin_user_id": 1}
                }).encode('utf-8')
                ctype = 'application/json; charset=utf-8'
                code = 200
            elif 'AdminLogin/logout' in target_path:
                data = json.dumps({"code": 200, "msg": "退出成功"}).encode('utf-8')
                ctype = 'application/json; charset=utf-8'
                code = 200
            else:
                data = json.dumps({"code": 0, "msg": "后台框架代理失败: %s" % e}).encode('utf-8')
                ctype = 'application/json; charset=utf-8'
                code = 502
        self.send_response(code)
        self.send_header('Content-Type', ctype)
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

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

        if path == '/api/upload/image':
            # 通用图片上传（实名认证证件照等，不修改用户头像）
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            files = files or {}
            f = files.get('file') or files.get('image')
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
            filename = f"upload_{int(time.time())}_{secrets.token_hex(6)}{ext}"
            with open(os.path.join(uploads_dir, filename), 'wb') as fp:
                fp.write(f['data'])
            url = '/uploads/' + filename
            self.send_json({"code": 1, "msg": "上传成功", "data": {"url": url, "full_url": url}})
            return

        # ===== 配置 =====
        if path == '/api/index/configure':
            # 从数据库读取系统配置
            small_lunbo = get_config('small_lunbo', '')
            goods_card_col = get_config('goods_card_col', '2')
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
                    "goods_card_col": int(goods_card_col) if goods_card_col.isdigit() else 2
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
                "amount": r['amount'], "status": r['status'],
                "game_id": r.get('game_id', ''), "wz_id": r.get('wz_id', ''),
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
                "amount": r['amount'], "status": r['status'],
                "game_id": r.get('game_id', ''), "wz_id": r.get('wz_id', ''),
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
            self.send_json({"code": 1, "msg": "成功", "data": {"can_grab": True, "can_assign": True}})
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

        # ===== 实名认证（打手）=====
        if path == '/api/realname/info':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            enabled = get_config('realname_enabled', '1') == '1'
            db = get_db()
            trow = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            db.close()
            t = dict(trow) if trow else {}
            self.send_json({"code": 1, "msg": "成功", "data": {
                "enabled": enabled,
                "status": int(t.get('realname_status') or 0),
                "real_name": t.get('real_name') or '',
                "id_card": t.get('id_card') or '',
                "front": t.get('realname_front') or '',
                "back": t.get('realname_back') or '',
                "reason": t.get('realname_reason') or '',
                "time": t.get('realname_time') or 0,
            }})
            return

        if path == '/api/realname/submit':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            if get_config('realname_enabled', '1') != '1':
                self.send_json({"code": 0, "msg": "实名认证功能未开启"})
                return
            real_name = (body.get('real_name') or '').strip()
            id_card = (body.get('id_card') or '').strip().upper()
            front = body.get('front') or ''
            back = body.get('back') or ''
            if not real_name or len(real_name) < 2:
                self.send_json({"code": 0, "msg": "请填写真实姓名"})
                return
            if not re.match(r'^\d{17}[\dX]$', id_card):
                self.send_json({"code": 0, "msg": "请填写正确的18位身份证号"})
                return
            if not front or not back:
                self.send_json({"code": 0, "msg": "请上传身份证正反面照片"})
                return
            db = get_db()
            trow = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            if not trow:
                now = now_ts()
                db.execute('INSERT INTO thug (user_id, name, status, sh_status, is_customer_service, createtime) VALUES (?,?,?,?,?,?)',
                           (u['id'], real_name, 0, 0, 0, now))
                trow = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            db.execute('UPDATE thug SET real_name = ?, id_card = ?, realname_front = ?, realname_back = ?, '
                       'realname_status = 1, realname_reason = "", realname_time = ? WHERE id = ?',
                       (real_name, id_card, front, back, now_ts(), trow['id']))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "提交成功，请等待平台审核"})
            return

        # ===== 客服端：管理打手列表 =====
        if path == '/api/cs/thugList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            kw = qs.get('keyword', [''])[0]
            where, args = 'WHERE 1=1', []
            if kw:
                where += ' AND (t.name LIKE ? OR t.real_name LIKE ? OR u.nickname LIKE ?)'
                args += ['%' + kw + '%', '%' + kw + '%', '%' + kw + '%']
            db = get_db()
            rows = db.execute(
                'SELECT t.id, t.name, t.real_name, t.id_card, t.realname_front, t.realname_back, '
                't.sh_status, t.is_customer_service, t.realname_status, '
                'u.nickname AS user_nickname, u.id AS user_id '
                'FROM thug t LEFT JOIN users u ON u.id = t.user_id ' + where + ' ORDER BY t.id DESC', args).fetchall()
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": {"list": [dict(r) for r in rows], "total": len(rows)}})
            return

        # ===== 前端装修配置（公开接口，H5 加载时拉取主题/背景）=====
        if path == '/api/frontend/decoration':
            cfg = get_decoration_config()
            self.send_json({"code": 1, "msg": "成功", "data": cfg})
            return

        # ===== 后台管理 API（自定义后台 UI 使用，与 H5 共用同一数据源）=====
        if path.startswith('/api/admin/'):
            self.handle_admin_api(path, qs, method, body)
            return

        # ===== 兜底：其他接口返回空数据，避免前端报错 =====
        self.send_json({"code": 1, "msg": "成功", "data": {"data": [], "total": 0}})


    # ===== 后台管理 API =====
    def handle_admin_api(self, path, qs, method, body):
        """自定义后台 UI 的业务接口，读写与 H5 相同的数据源"""

        def q(name, default=''):
            v = qs.get(name)
            return v[0] if v else default

        def ok(data=None, msg='操作成功'):
            self.send_json({"code": 1, "msg": msg, "data": data})

        db = get_db()
        try:
            # 控制台统计
            if path == '/api/admin/stat':
                def count(sql, args=()):
                    return db.execute(sql, args).fetchone()[0]
                today = int(datetime.now().replace(hour=0, minute=0, second=0, microsecond=0).timestamp())
                data = {
                    'user_count': count('SELECT COUNT(*) FROM users'),
                    'thug_count': count('SELECT COUNT(*) FROM thug'),
                    'thug_approved': count('SELECT COUNT(*) FROM thug WHERE sh_status = 1'),
                    'thug_pending': count('SELECT COUNT(*) FROM thug WHERE sh_status = 0'),
                    'cs_count': count('SELECT COUNT(*) FROM thug WHERE is_customer_service = 1'),
                    'order_count': count('SELECT COUNT(*) FROM orders'),
                    'order_today': count('SELECT COUNT(*) FROM orders WHERE createtime >= ?', (today,)),
                    'notice_count': count('SELECT COUNT(*) FROM notices'),
                    'invite_count': count('SELECT COUNT(*) FROM invite_codes'),
                    'money_sum': round(db.execute('SELECT IFNULL(SUM(money),0) FROM users').fetchone()[0] or 0, 2),
                }
                ok(data)
                return

            # 会员（用户）管理
            if path == '/api/admin/users':
                kw = q('keyword')
                page = max(1, int(q('page', 1) or 1))
                limit = max(1, int(q('limit', 20) or 20))
                where, args = '', []
                if kw:
                    where = 'WHERE nickname LIKE ? OR openid LIKE ? OR id = ?'
                    args = ['%' + kw + '%', '%' + kw + '%', kw if str(kw).isdigit() else -1]
                total = db.execute('SELECT COUNT(*) FROM users ' + where, args).fetchone()[0]
                rows = db.execute(
                    'SELECT id, nickname, avatar, type, openid, pid, money, score, level, group_chat, createtime '
                    'FROM users ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                    args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            if path == '/api/admin/user/save':
                uid = int(body.get('id') or 0)
                action = body.get('action') or 'edit'
                if action == 'delete':
                    db.execute('DELETE FROM users WHERE id = ?', (uid,))
                    db.commit()
                    ok(None, '已删除该会员')
                    return
                if action == 'recharge':
                    db.execute('UPDATE users SET money = money + ? WHERE id = ?',
                               (float(body.get('money') or 0), uid))
                    db.commit()
                    ok(None, '充值成功')
                    return
                db.execute('UPDATE users SET nickname = ?, money = ?, level = ? WHERE id = ?',
                           (body.get('nickname'), float(body.get('money') or 0),
                            int(body.get('level') or 1), uid))
                db.commit()
                ok(None, '保存成功')
                return

            # 打手管理
            if path == '/api/admin/thugs':
                kw = q('keyword')
                sh = q('sh_status')
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (t.name LIKE ? OR u.nickname LIKE ?)'
                    args += ['%' + kw + '%', '%' + kw + '%']
                if sh != '':
                    where += ' AND t.sh_status = ?'
                    args.append(int(sh))
                rows = db.execute(
                    'SELECT t.*, u.nickname AS user_nickname, u.money AS user_money '
                    'FROM thug t LEFT JOIN users u ON u.id = t.user_id ' + where +
                    ' ORDER BY t.id DESC', args).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': len(rows)})
                return

            if path == '/api/admin/thug/audit':
                tid = int(body.get('id') or 0)
                action = body.get('action')
                if action == 'approve':
                    db.execute('UPDATE thug SET sh_status = 1, status = 1 WHERE id = ?', (tid,))
                    msg = '已通过打手入驻审核'
                elif action == 'reject':
                    db.execute('UPDATE thug SET sh_status = 2, status = 0 WHERE id = ?', (tid,))
                    msg = '已驳回打手入驻申请'
                elif action == 'toggle_cs':
                    db.execute('UPDATE thug SET is_customer_service = ? WHERE id = ?',
                               (1 if body.get('is_customer_service') else 0, tid))
                    msg = '客服权限已更新'
                elif action == 'delete':
                    db.execute('DELETE FROM thug WHERE id = ?', (tid,))
                    msg = '已删除该打手'
                else:
                    db.execute('UPDATE thug SET name = ? WHERE id = ?', (body.get('name'), tid))
                    msg = '保存成功'
                db.commit()
                ok(None, msg)
                return

            # 实名认证：开关配置
            if path == '/api/admin/realname/config':
                ok({'enabled': get_config('realname_enabled', '1') == '1'})
                return

            if path == '/api/admin/realname/config/save':
                set_config('realname_enabled', '1' if body.get('enabled') else '0')
                ok(None, '实名认证设置已保存')
                return

            # 实名认证：审核打手认证信息
            if path == '/api/admin/realname/audit':
                tid = int(body.get('id') or 0)
                action = body.get('action')
                if action == 'approve':
                    db.execute('UPDATE thug SET realname_status = 2, realname_reason = "" WHERE id = ?', (tid,))
                    msg = '已通过实名认证'
                elif action == 'reject':
                    db.execute('UPDATE thug SET realname_status = 3, realname_reason = ? WHERE id = ?',
                               (body.get('reason', ''), tid))
                    msg = '已驳回实名认证'
                else:
                    msg = '未知操作'
                db.commit()
                ok(None, msg)
                return

            # 客服管理
            if path == '/api/admin/cs':
                rows = db.execute(
                    'SELECT c.*, u.nickname AS user_nickname FROM customer_services c '
                    'LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC').fetchall()
                ok({'list': [dict(r) for r in rows], 'total': len(rows)})
                return

            if path == '/api/admin/cs/save':
                cid = int(body.get('id') or 0)
                cs_mode = body.get('cs_mode', 'built_in')
                if cid:
                    db.execute(
                        'UPDATE customer_services SET name = ?, avatar = ?, online = ?, session_timeout = ?, '
                        'cs_mode = ?, wecom_url = ?, auto_assign = ? WHERE id = ?',
                        (body.get('name'), body.get('avatar', ''), int(body.get('online') or 0),
                         int(body.get('session_timeout') or 3), cs_mode, body.get('wecom_url', ''),
                         int(body.get('auto_assign') or 0), cid))
                else:
                    db.execute(
                        'INSERT INTO customer_services (name, user_id, type, avatar, online, session_timeout, cs_mode, wecom_url, auto_assign) '
                        'VALUES (?,?,?,?,?,?,?,?,?)',
                        (body.get('name'), int(body.get('user_id') or 0), body.get('type', 'im'),
                         body.get('avatar', ''), int(body.get('online') or 1),
                         int(body.get('session_timeout') or 3), cs_mode, body.get('wecom_url', ''),
                         int(body.get('auto_assign') or 0)))
                db.commit()
                ok(None, '保存成功')
                return

            if path == '/api/admin/cs/delete':
                db.execute('DELETE FROM customer_services WHERE id = ?', (int(body.get('id') or 0),))
                db.commit()
                ok(None, '已删除')
                return

            # 订单管理
            if path == '/api/admin/orders':
                kw = q('keyword')
                status = q('status')
                page = max(1, int(q('page', 1) or 1))
                limit = max(1, int(q('limit', 20) or 20))
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (order_no LIKE ? OR title LIKE ? OR game_id LIKE ?)'
                    args += ['%' + kw + '%', '%' + kw + '%', '%' + kw + '%']
                if status != '':
                    where += ' AND status = ?'
                    args.append(int(status))
                total = db.execute('SELECT COUNT(*) FROM orders ' + where, args).fetchone()[0]
                rows = db.execute(
                    'SELECT * FROM orders ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                    args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            if path == '/api/admin/order/save':
                db.execute('UPDATE orders SET status = ?, status_text = ? WHERE id = ?',
                           (int(body.get('status') or 0), body.get('status_text', ''), int(body.get('id') or 0)))
                db.commit()
                ok(None, '订单状态已更新')
                return

            # 公告管理
            if path == '/api/admin/notices':
                rows = db.execute('SELECT * FROM notices ORDER BY id DESC').fetchall()
                ok({'list': [dict(r) for r in rows], 'total': len(rows)})
                return

            if path == '/api/admin/notice/save':
                nid = int(body.get('id') or 0)
                if nid:
                    db.execute('UPDATE notices SET title = ?, synopsis = ?, content = ?, type = ? WHERE id = ?',
                               (body.get('title'), body.get('synopsis', ''), body.get('content', ''),
                                int(body.get('type') or 1), nid))
                else:
                    db.execute('INSERT INTO notices (title, synopsis, content, type, createtime) VALUES (?,?,?,?,?)',
                               (body.get('title'), body.get('synopsis', ''), body.get('content', ''),
                                int(body.get('type') or 1), now_ts()))
                db.commit()
                ok(None, '保存成功')
                return

            if path == '/api/admin/notice/delete':
                db.execute('DELETE FROM notices WHERE id = ?', (int(body.get('id') or 0),))
                db.commit()
                ok(None, '已删除')
                return

            # 邀请码管理
            if path == '/api/admin/invites':
                rows = db.execute('SELECT * FROM invite_codes ORDER BY id DESC').fetchall()
                ok({'list': [dict(r) for r in rows], 'total': len(rows)})
                return

            if path == '/api/admin/invite/save':
                import random, string
                iid = int(body.get('id') or 0)
                itype = body.get('type', 'thug')
                code = (body.get('code') or '').strip()
                if not code:
                    prefix = 'CS' if itype == 'cs' else 'THUG'
                    while True:
                        code = prefix + ''.join(random.choices(string.digits, k=6))
                        if not db.execute('SELECT id FROM invite_codes WHERE code = ?', (code,)).fetchone():
                            break
                if iid:
                    db.execute('UPDATE invite_codes SET code = ?, type = ?, name = ? WHERE id = ?',
                               (code, itype, body.get('name', ''), iid))
                else:
                    db.execute('INSERT INTO invite_codes (code, type, user_id, name, createtime) VALUES (?,?,?,?,?)',
                               (code, itype, int(body.get('user_id') or 0),
                                body.get('name', ''), now_ts()))
                db.commit()
                ok({'code': code}, '保存成功')
                return

            if path == '/api/admin/invite/delete':
                db.execute('DELETE FROM invite_codes WHERE id = ?', (int(body.get('id') or 0),))
                db.commit()
                ok(None, '已删除')
                return

            # 按用户ID直接开通权限（打手/客服）
            if path == '/api/admin/invite/grant':
                uid = int(body.get('user_id') or 0)
                itype = body.get('type', 'thug')
                if uid <= 0:
                    ok(None, '请输入有效的用户ID')
                    return
                u = db.execute('SELECT id, nickname FROM users WHERE id = ?', (uid,)).fetchone()
                if not u:
                    ok(None, '用户不存在')
                    return
                if itype == 'thug':
                    ex = db.execute('SELECT id FROM thug WHERE user_id = ?', (uid,)).fetchone()
                    if ex:
                        db.execute('UPDATE thug SET sh_status = 1, status = 1 WHERE id = ?', (ex['id'],))
                    else:
                        db.execute('INSERT INTO thug (user_id, name, status, sh_status, is_customer_service, createtime) '
                                   'VALUES (?,?,?,?,?,?)', (uid, u['nickname'] or '打手' + str(uid), 1, 1, 0, now_ts()))
                    db.commit()
                    ok(None, '已为用户开通打手权限')
                elif itype == 'cs':
                    ex = db.execute('SELECT id FROM thug WHERE user_id = ?', (uid,)).fetchone()
                    if ex:
                        db.execute('UPDATE thug SET sh_status = 1, status = 1, is_customer_service = 1 WHERE id = ?', (ex['id'],))
                    else:
                        db.execute('INSERT INTO thug (user_id, name, status, sh_status, is_customer_service, createtime) '
                                   'VALUES (?,?,?,?,?,?)', (uid, u['nickname'] or '客服' + str(uid), 1, 1, 1, now_ts()))
                    db.commit()
                    ok(None, '已为用户开通客服权限')
                else:
                    ok(None, '未知类型')
                return

            # 系统配置
            if path == '/api/admin/configs':
                group = q('group')
                if group:
                    rows = db.execute('SELECT * FROM fa_config WHERE group_name = ? ORDER BY id', (group,)).fetchall()
                else:
                    rows = db.execute('SELECT * FROM fa_config ORDER BY id').fetchall()
                ok({'list': [dict(r) for r in rows]})
                return

            if path == '/api/admin/config/save':
                items = body.get('items') or {}
                if isinstance(items, dict):
                    for k, v in items.items():
                        db.execute('UPDATE fa_config SET value = ? WHERE name = ?', (str(v), k))
                db.commit()
                ok(None, '配置已保存')
                return

            # 前端装修配置
            if path == '/api/admin/decoration':
                ok(get_decoration_config())
                return

            if path == '/api/admin/decoration/save':
                cfg = {
                    'name': body.get('name') or '自定义',
                    'colors': body.get('colors') or {},
                    'bg_image': body.get('bg_image') or '',
                    'nav_style': body.get('nav_style', 'float_ellipse'),
                    'floating_nav': body.get('floating_nav') or [],
                }
                set_config('frontend_decoration', json.dumps(cfg, ensure_ascii=False))
                ok(None, '装修配置已保存，前端将实时生效')
                return

            # ===== 通用资源（后台各菜单真实 CRUD） =====
            if path == '/api/admin/resource/defs':
                ok({'defs': RESOURCES})
                return

            if path == '/api/admin/resource':
                res = q('res')
                spec = RESOURCES.get(res)
                if not spec:
                    self.send_json({"code": 0, "msg": "未知资源: " + res})
                    return
                if spec.get('singleton'):
                    row = db.execute('SELECT * FROM res_%s WHERE id = 1' % res).fetchone()
                    if not row:
                        db.execute('INSERT INTO res_%s (id) VALUES (1)' % res)
                        db.commit()
                        row = db.execute('SELECT * FROM res_%s WHERE id = 1' % res).fetchone()
                    ok({'item': dict(row), 'singleton': True})
                    return
                kw = q('keyword')
                page = max(1, int(q('page', 1) or 1))
                limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = '', []
                if kw and spec.get('search'):
                    where = 'WHERE ' + ' OR '.join([c + ' LIKE ?' for c in spec['search']])
                    args = ['%' + kw + '%'] * len(spec['search'])
                total = db.execute('SELECT COUNT(*) FROM res_%s %s' % (res, where), args).fetchone()[0]
                rows = db.execute('SELECT * FROM res_%s %s ORDER BY id DESC LIMIT ? OFFSET ?' % (res, where),
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit, 'singleton': False})
                return

            if path == '/api/admin/resource/save':
                res = body.get('res')
                spec = RESOURCES.get(res)
                if not spec:
                    self.send_json({"code": 0, "msg": "未知资源: " + str(res)})
                    return
                keys = [f['key'] for f in spec['fields']]
                vals = [str(body.get(k, '')) for k in keys]
                rid = int(body.get('id') or 0)
                if spec.get('singleton') and not rid:
                    rid = 1
                if rid:
                    sets = ', '.join([k + ' = ?' for k in keys])
                    db.execute('UPDATE res_%s SET %s WHERE id = ?' % (res, sets), vals + [rid])
                else:
                    db.execute('INSERT INTO res_%s (%s, createtime) VALUES (%s, ?)' % (res, ','.join(keys), ','.join(['?'] * len(keys))),
                               vals + [now_ts()])
                    rid = db.execute('SELECT last_insert_rowid()').fetchone()[0]
                db.commit()
                ok({'id': rid}, '保存成功')
                return

            if path == '/api/admin/resource/delete':
                res = body.get('res')
                if res not in RESOURCES:
                    self.send_json({"code": 0, "msg": "未知资源: " + str(res)})
                    return
                db.execute('DELETE FROM res_%s WHERE id = ?' % res, (int(body.get('id') or 0),))
                db.commit()
                ok(None, '已删除')
                return

            self.send_json({"code": 0, "msg": "未知的后台接口: " + path})
        finally:
            db.close()


if __name__ == '__main__':
    server = HTTPServer((HOST, PORT), Handler)
    print(f"API 服务器已启动: http://localhost:{PORT}")
    print(f"H5 访问地址: http://localhost:{PORT}/h5/")
    print(f"SQLite 数据库: {DB_PATH}")
    server.serve_forever()
