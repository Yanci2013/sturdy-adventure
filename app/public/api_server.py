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
import ast
import html
import ssl
import random
import hashlib
import sqlite3
import secrets
import urllib.request as _urlreq
import urllib.parse as _urlparse
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
        _rf('name', '分类名称'), _rf('icon', '分类图标', 'image'), _rf('sort', '排序', 'number'), _rf('status', '是否显示', 'switch')]},
    'game_server': {'title': '区服管理', 'search': ['name', 'game'], 'columns': ['name', 'game', 'sort', 'status'], 'fields': [
        _rf('name', '区服名称'), _rf('game', '所属游戏'), _rf('sort', '排序', 'number'), _rf('status', '是否启用', 'switch')]},
    'goods': {'title': '商品管理', 'search': ['name'], 'columns': ['name', 'category_id', 'price', 'status'], 'fields': [
        _rf('name', '商品名称'), _rf('category_id', '所属分类ID', 'number'), _rf('logo', '封面图', 'image'),
        _rf('price', '价格', 'number'), _rf('unit', '计价单位'), _rf('status', '上架状态', 'switch'), _rf('remark', '商品简介', 'textarea')]},
    'prize_pool': {'title': '奖池管理', 'search': ['name'], 'columns': ['name', 'rate', 'stock', 'status'], 'fields': [
        _rf('name', '奖品名称'), _rf('icon', '奖品图', 'image'), _rf('rate', '中奖概率(%)', 'number'),
        _rf('price', '服务价格(¥)', 'number'), _rf('stock', '库存数量', 'number'), _rf('status', '是否启用', 'switch')]},
    'gift': {'title': '礼物管理', 'search': ['name'], 'columns': ['name', 'icon', 'price', 'sort', 'status'], 'fields': [
        _rf('name', '礼物名称'), _rf('icon', '礼物图标', 'image'), _rf('price', '礼物价格', 'number'),
        _rf('sort', '排序', 'number'), _rf('status', '是否上架', 'switch')]},
    'banner': {'title': '轮播图管理', 'search': ['title'], 'columns': ['image', 'title', 'link', 'sort', 'status'], 'fields': [
        _rf('title', '标题'), _rf('image', '轮播图片', 'image'), _rf('link', '跳转链接'), _rf('sort', '排序', 'number'), _rf('status', '是否显示', 'switch')]},
    'order_complaint': {'title': '订单投诉', 'search': ['order_no', 'content'], 'columns': ['order_no', 'user_id', 'content', 'status'], 'fields': [
        _rf('order_no', '订单号'), _rf('user_id', '投诉用户ID', 'number'), _rf('content', '投诉内容', 'textarea'),
        _rf('status', '处理状态', 'select', {'0': '待处理', '1': '处理中', '2': '已解决', '3': '已驳回'})]},
    'withdraw': {'title': '提现记录', 'search': ['user_id', 'account'], 'columns': ['user_id', 'amount', 'account', 'status'], 'fields': [
        _rf('user_id', '用户ID', 'number'), _rf('amount', '提现金额', 'number'), _rf('account', '收款账号'),
        _rf('status', '状态', 'select', {'0': '待审核', '1': '已打款', '2': '已驳回'}), _rf('remark', '备注')]},
    'level': {'title': '等级列表', 'search': ['name'], 'columns': ['name', 'need_score', 'discount', 'remark'], 'fields': [
        _rf('name', '等级名称'), _rf('icon', '等级图标', 'image'), _rf('need_score', '所需积分', 'number'),
        _rf('discount', '折扣(0-1)', 'number'), _rf('remark', '权益说明', 'textarea')]},
    'cs_account': {'title': '客服账号', 'search': ['name', 'username'], 'columns': ['name', 'username', 'thug_id', 'status'], 'fields': [
        _rf('name', '客服名称'), _rf('username', '登录账号'), _rf('password', '登录密码'),
        _rf('thug_id', '关联打手ID', 'number'), _rf('status', '状态', 'switch')]},
    'fine': {'title': '罚款管理', 'search': ['name'], 'columns': ['name', 'amount', 'user_id', 'status'], 'fields': [
        _rf('name', '罚款项目'), _rf('amount', '罚款金额', 'number'), _rf('user_id', '用户ID', 'number'),
        _rf('reason', '罚款原因', 'textarea'), _rf('status', '状态', 'select', {'0': '待缴纳', '1': '已缴纳', '2': '已免除'})]},
    'sys_bill': {'title': '系统缴费', 'search': ['bill_no', 'project'], 'columns': ['bill_no', 'project', 'user_id', 'amount', 'status'], 'fields': [
        _rf('user_id', '打手用户ID', 'number'), _rf('bill_no', '缴费单号'),
        _rf('project', '缴费项目'), _rf('reason', '缴纳原因'),
        _rf('remark', '缴费备注', 'textarea'), _rf('amount', '缴费金额', 'number'),
        _rf('deadline', '缴费截止日(YYYY-MM-DD)'),
        _rf('status', '状态', 'select', {'0': '待执行', '1': '已缴纳', '2': '已逾期'})]},
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
    'craft_prize': {'title': '装备打造奖池', 'search': ['name'], 'columns': ['image', 'name', 'type', 'phase', 'status'], 'fields': [
        _rf('name', '奖品名称'), _rf('image', '奖品图片', 'image'),
        _rf('type', '奖品类型', 'select', {'normal': '普通奖品', 'stage': '阶段完成奖励'}),
        _rf('phase', '所属阶段', 'select', {'1': '第一阶段', '2': '第二阶段'}),
        _rf('range', '公示进度区间'), _rf('sort', '排序', 'number'), _rf('status', '是否启用', 'switch')]},
    'craft_package': {'title': '打造次数套餐', 'search': ['name'], 'columns': ['name', 'count', 'price', 'sort', 'status'], 'fields': [
        _rf('name', '套餐名称'), _rf('count', '打造次数', 'number'), _rf('price', '价格', 'number'),
        _rf('sort', '排序', 'number'), _rf('status', '是否上架', 'switch')]},
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
    'game_server': [('微信区', '王者荣耀', '1', '1'), ('QQ区', '王者荣耀', '2', '1'), ('iOS微信', '王者荣耀', '3', '1'),
                    ('微信区', '和平精英', '4', '1'), ('QQ区', '和平精英', '5', '1'),
                    ('艾欧尼亚', '英雄联盟', '6', '1'), ('祖安', '英雄联盟', '7', '1'),
                    ('北方大区', '穿越火线', '8', '1'), ('南方大区', '穿越火线', '9', '1')],
    'goods': [('王者荣耀陪练', '1', '/static/images/header_background.png', '25', '局', '1', '资深王者陪练，带你连胜上分'),
              ('和平精英陪玩', '1', '/static/images/header_background.png', '20', '局', '1', '吃鸡大神带你躺赢'),
              ('英雄联盟陪玩', '1', '/static/images/header_background.png', '30', '局', '1', 'LOL国服王者陪练'),
              ('原神代肝', '2', '/static/images/header_background.png', '15', '小时', '1', '原神每日委托/深渊代打'),
              ('电竞教学课程', '3', '/static/images/header_background.png', '99', '课时', '1', '职业选手一对一教学')],
    'prize_pool': [('一等奖 iPhone', '', '1', '10', '1'), ('二等奖 现金红包', '', '9', '100', '1'), ('谢谢惠顾', '', '90', '0', '1')],
    'gift': [('小心心', '/static/eon.png', '1', '1', '1'), ('棒棒糖', '/static/eon.png', '5', '2', '1'),
             ('鲜花', '/static/eon.png', '10', '3', '1'), ('皇冠', '/static/eon.png', '88', '4', '1')],
    'banner': [('首页横幅', '/static/images/header_background.png', '/pages/classify/index', '1', '1')],
    'craft_prize': [('双截棍', '', 'normal', '1', '', '1', '1'), ('火焰刀', '', 'normal', '1', '', '2', '1'),
                    ('熔岩双刃', '', 'stage', '1', '集齐10个碎片', '3', '1'),
                    ('折叠直刀', '', 'normal', '2', '', '1', '1'), ('机械拳套', '', 'stage', '2', '集齐10个碎片', '2', '1')],
    'craft_package': [('体验1次', '1', '1', '1', '1'), ('打造10次', '10', '9', '2', '1'), ('打造50次', '50', '40', '3', '1')],
    'quick_entry': [],
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
    for _col in ('cs_avatar', 'mobile', 'sex', 'signature'):
        try:
            cur.execute('ALTER TABLE users ADD COLUMN %s TEXT DEFAULT ""' % _col)
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
    # 老库补字段（实名认证 + 打手资料）
    for _col in ('real_name', 'id_card', 'realname_front', 'realname_back', 'realname_reason',
                 'avatar', 'mobile', 'sex', 'synopsis', 'images', 'audio', 'game_content', 'grade'):
        try:
            cur.execute('ALTER TABLE thug ADD COLUMN %s TEXT DEFAULT ""' % _col)
        except sqlite3.OperationalError:
            pass
    for _col in ('realname_status', 'realname_time', 'audio_time', 'jd_status'):
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
    # IM 会话表
    cur.execute('''CREATE TABLE IF NOT EXISTS im_conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id TEXT UNIQUE,
        user_a INTEGER,
        user_b INTEGER,
        last_msg TEXT DEFAULT '',
        last_time INTEGER DEFAULT 0,
        accepted INTEGER DEFAULT 0,
        accepted_by INTEGER DEFAULT 0,
        createtime INTEGER,
        is_deleted INTEGER DEFAULT 0
    )''')
    # IM 消息表
    cur.execute('''CREATE TABLE IF NOT EXISTS im_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id TEXT,
        sender_id INTEGER,
        receiver_id INTEGER,
        msg_type TEXT DEFAULT 'text',
        content TEXT,
        is_read INTEGER DEFAULT 0,
        createtime INTEGER
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
        # 统一软删除字段（用户/打手/订单/公告/客服/邀请码）
        ('users.is_deleted', "ALTER TABLE users ADD COLUMN is_deleted INTEGER DEFAULT 0"),
        ('thug.is_deleted', "ALTER TABLE thug ADD COLUMN is_deleted INTEGER DEFAULT 0"),
        ('orders.is_deleted', "ALTER TABLE orders ADD COLUMN is_deleted INTEGER DEFAULT 0"),
        # 订单业务扩展字段
        ('orders.goods_id', "ALTER TABLE orders ADD COLUMN goods_id INTEGER DEFAULT 0"),
        ('orders.wz_id', "ALTER TABLE orders ADD COLUMN wz_id TEXT DEFAULT ''"),
        ('orders.complaint', "ALTER TABLE orders ADD COLUMN complaint INTEGER DEFAULT 0"),
        ('orders.award', "ALTER TABLE orders ADD COLUMN award TEXT DEFAULT ''"),
        ('orders.jackpot_id', "ALTER TABLE orders ADD COLUMN jackpot_id INTEGER DEFAULT 0"),
        ('orders.is_rated', "ALTER TABLE orders ADD COLUMN is_rated INTEGER DEFAULT 0"),
        ('orders.rating', "ALTER TABLE orders ADD COLUMN rating INTEGER DEFAULT 0"),
        ('orders.image', "ALTER TABLE orders ADD COLUMN image TEXT DEFAULT ''"),
        ('orders.thug_remarks', "ALTER TABLE orders ADD COLUMN thug_remarks TEXT DEFAULT ''"),
        ('orders.helper', "ALTER TABLE orders ADD COLUMN helper TEXT DEFAULT ''"),
        ('orders.refund_method', "ALTER TABLE orders ADD COLUMN refund_method TEXT DEFAULT ''"),
        ('orders.refund_time', "ALTER TABLE orders ADD COLUMN refund_time INTEGER DEFAULT 0"),
        ('notices.is_deleted', "ALTER TABLE notices ADD COLUMN is_deleted INTEGER DEFAULT 0"),
        ('customer_services.is_deleted', "ALTER TABLE customer_services ADD COLUMN is_deleted INTEGER DEFAULT 0"),
        ('invite_codes.is_deleted', "ALTER TABLE invite_codes ADD COLUMN is_deleted INTEGER DEFAULT 0"),
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
            # 店铺开关
            ('store_status', 'basic', '店铺状态', '1=正常营业，0=关店/打烊', 'radio', '', '1', '{"1":"正常营业","0":"关店/打烊"}', 'required', '', ''),
            ('close_qq', 'basic', '关店联系QQ', '关店提示页显示的联系QQ', 'string', '', '', '', '', '', ''),
            ('close_image', 'basic', '关店提示图片', '关店提示页顶部图片', 'string', '', '', '', '', '', ''),
            # 首页背景音乐
            ('home_bgm_url', 'basic', '背景音乐地址', '首页背景音乐文件地址（mp3）', 'string', '', '', '', '', '', ''),
            ('home_bgm_title', 'basic', '背景音乐标题', '首页背景音乐卡片标题', 'string', '', '背景音乐', '', '', '', ''),
            ('home_bgm_sub', 'basic', '背景音乐副标题', '首页背景音乐卡片副标题', 'string', '', '首页背景音乐 · 点击播放', '', '', '', ''),
            ('home_bgm_enabled', 'basic', '背景音乐开关', '1=显示，0=隐藏', 'radio', '', '1', '{"1":"显示","0":"隐藏"}', '', '', ''),
        ]
        for item in config_items:
            cur.execute('INSERT INTO fa_config (name, group_name, title, tip, type, visible, value, content, rule, extend, setting) VALUES (?,?,?,?,?,?,?,?,?,?,?)', item)
    # 确保首页顶部俱乐部名称 / Logo 配置项存在（兼容旧库）
    for _name, _title, _tip, _type, _value in [
        ('club_name', '俱乐部名称', '首页顶部显示的俱乐部名称', 'string', '创世网络'),
        ('site_logo', '站点Logo', '首页顶部Logo图片地址（支持后台上传）', 'image', '/static/eon.png'),
    ]:
        cur.execute('SELECT COUNT(*) FROM fa_config WHERE name = ?', (_name,))
        if cur.fetchone()[0] == 0:
            cur.execute('INSERT INTO fa_config (name, group_name, title, tip, type, visible, value, content, rule, extend, setting) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                        (_name, 'basic', _title, _tip, _type, '', _value, '', '', '', ''))
    db.commit()
    # ===== 通用资源表（后台各菜单 CRUD） =====
    for _res, _spec in RESOURCES.items():
        _cols = ', '.join([f['key'] + " TEXT DEFAULT ''" for f in _spec['fields']])
        cur.execute('CREATE TABLE IF NOT EXISTS res_%s (id INTEGER PRIMARY KEY AUTOINCREMENT, %s, createtime INTEGER, update_time INTEGER DEFAULT 0, is_deleted INTEGER DEFAULT 0)' % (_res, _cols))
        # 旧库补软删除/更新时间字段
        for _ddl in ("ALTER TABLE res_%s ADD COLUMN is_deleted INTEGER DEFAULT 0" % _res,
                     "ALTER TABLE res_%s ADD COLUMN update_time INTEGER DEFAULT 0" % _res):
            try:
                cur.execute(_ddl)
            except Exception:
                pass
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
    # ===== 新增业务模块表 =====
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_thug_withdraw (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        thug_id INTEGER DEFAULT 0, thug_name TEXT DEFAULT '', withdraw_no TEXT DEFAULT '',
        amount TEXT DEFAULT '0', fee TEXT DEFAULT '0', real_amount TEXT DEFAULT '0',
        account_type TEXT DEFAULT 'wechat', account TEXT DEFAULT '',
        apply_time INTEGER DEFAULT 0, audit_time INTEGER DEFAULT 0, pay_time INTEGER DEFAULT 0,
        status INTEGER DEFAULT 0, audit_admin TEXT DEFAULT '', reject_reason TEXT DEFAULT '',
        remark TEXT DEFAULT '', create_time INTEGER DEFAULT 0, update_time INTEGER DEFAULT 0,
        is_deleted INTEGER DEFAULT 0)''')
    cur.execute('CREATE INDEX IF NOT EXISTS idx_tw_paytime ON fa_thug_withdraw(pay_time)')
    cur.execute('CREATE INDEX IF NOT EXISTS idx_tw_status ON fa_thug_withdraw(status)')
    # 装备打造相关表
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_craft_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 0, times INTEGER DEFAULT 0,
        phase INTEGER DEFAULT 1, progress TEXT DEFAULT '0', is_crit INTEGER DEFAULT 0,
        create_time INTEGER DEFAULT 0)''')
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_craft_order (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_no TEXT DEFAULT '', user_id INTEGER DEFAULT 0,
        package_id INTEGER DEFAULT 0, count INTEGER DEFAULT 0, amount TEXT DEFAULT '0',
        status TEXT DEFAULT 'pending', pay_time INTEGER DEFAULT 0, create_time INTEGER DEFAULT 0)''')
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_craft_prize_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 0, prize_id INTEGER DEFAULT 0,
        prize_name TEXT DEFAULT '', prize_image TEXT DEFAULT '', status TEXT DEFAULT 'bag',
        create_time INTEGER DEFAULT 0)''')

    # 礼物记录（打手礼物墙）
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_gift_record (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 0, thug_id INTEGER DEFAULT 0,
        gift_id INTEGER DEFAULT 0, gift_name TEXT DEFAULT '', gift_icon TEXT DEFAULT '',
        num INTEGER DEFAULT 1, amount TEXT DEFAULT '0', pay_type TEXT DEFAULT 'balance',
        order_no TEXT DEFAULT '', status INTEGER DEFAULT 1, create_time INTEGER DEFAULT 0)''')
    cur.execute('CREATE INDEX IF NOT EXISTS idx_gift_rec_thug ON fa_gift_record(thug_id)')
    # 打赏记录
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_thug_reward (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 0, thug_id INTEGER DEFAULT 0,
        money TEXT DEFAULT '0', pay_type TEXT DEFAULT 'balance', order_no TEXT DEFAULT '',
        status INTEGER DEFAULT 1, create_time INTEGER DEFAULT 0)''')
    cur.execute('CREATE INDEX IF NOT EXISTS idx_reward_thug ON fa_thug_reward(thug_id)')
    # 投诉/售后扩展字段（旧库补列）
    for _col in ('order_id', 'thug_id', 'type', 'phone', 'images', 'refund_method', 'refund_time', 'handle_remark'):
        try:
            cur.execute("ALTER TABLE res_order_complaint ADD COLUMN %s TEXT DEFAULT ''" % _col)
        except Exception:
            pass

    cur.execute('''CREATE TABLE IF NOT EXISTS fa_aggregate_login_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER DEFAULT 0, channel TEXT DEFAULT '',
        openid TEXT DEFAULT '', nickname TEXT DEFAULT '', login_ip TEXT DEFAULT '',
        device TEXT DEFAULT '', login_time INTEGER DEFAULT 0, create_time INTEGER DEFAULT 0)''')
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_sms_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, mobile TEXT DEFAULT '', template_id TEXT DEFAULT '',
        content TEXT DEFAULT '', status INTEGER DEFAULT 1, provider_resp TEXT DEFAULT '',
        scene TEXT DEFAULT '', ip TEXT DEFAULT '', create_time INTEGER DEFAULT 0)''')
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_pay_callback_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, channel TEXT DEFAULT '', order_no TEXT DEFAULT '',
        raw TEXT DEFAULT '', sign_ok INTEGER DEFAULT 0, handled INTEGER DEFAULT 0,
        result TEXT DEFAULT '', ip TEXT DEFAULT '', create_time INTEGER DEFAULT 0)''')
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_admin_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER DEFAULT 0, admin_name TEXT DEFAULT '',
        module TEXT DEFAULT '', content TEXT DEFAULT '', ip TEXT DEFAULT '', create_time INTEGER DEFAULT 0)''')
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_cron_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER DEFAULT 0, task_name TEXT DEFAULT '',
        status INTEGER DEFAULT 1, message TEXT DEFAULT '', cost_ms INTEGER DEFAULT 0, create_time INTEGER DEFAULT 0)''')
    # 支付渠道表
    cur.execute('''CREATE TABLE IF NOT EXISTS fa_pay_channel (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT DEFAULT '', name TEXT DEFAULT '',
        channel_type TEXT DEFAULT '', pid TEXT DEFAULT '', secret TEXT DEFAULT '',
        notify_url TEXT DEFAULT '', return_url TEXT DEFAULT '', timeout INTEGER DEFAULT 300,
        mch_id TEXT DEFAULT '', api_v3_key TEXT DEFAULT '', cert_serial TEXT DEFAULT '',
        private_key TEXT DEFAULT '', sort INTEGER DEFAULT 0, status INTEGER DEFAULT 1,
        remark TEXT DEFAULT '', create_time INTEGER DEFAULT 0, update_time INTEGER DEFAULT 0)''')
    cur.execute('SELECT COUNT(*) FROM fa_pay_channel')
    if cur.fetchone()[0] == 0:
        _now = today_ts()
        cur.execute('''INSERT INTO fa_pay_channel
            (code, name, channel_type, notify_url, return_url, timeout, sort, status, remark, create_time, update_time)
            VALUES ('epay', '彩虹支付', 'epay', '/api/pay/notify/epay', '/h5/#/pages/index/paysuccess',
                    300, 1, 1, '支持微信/支付宝/QQ钱包，免签聚合', ?, ?)''', (_now, _now))
        cur.execute('''INSERT INTO fa_pay_channel
            (code, name, channel_type, mch_id, notify_url, timeout, sort, status, remark, create_time, update_time)
            VALUES ('wechat_v3', '微信商户V3', 'wechat_v3', '', '/api/pay/notify/wechat', 300, 2, 0,
                    '微信支付V3，支持 JSAPI/H5', ?, ?)''', (_now, _now))
    # 统一支付单：所有支付场景共用，异步回调/主动查询按场景发货
    cur.execute('''CREATE TABLE IF NOT EXISTS res_pay_order (
        id INTEGER PRIMARY KEY AUTOINCREMENT, out_trade_no TEXT DEFAULT '', user_id TEXT DEFAULT '',
        scene TEXT DEFAULT '', target_id TEXT DEFAULT '', title TEXT DEFAULT '',
        amount TEXT DEFAULT '0', pay_type TEXT DEFAULT '', status TEXT DEFAULT '0',
        trade_no TEXT DEFAULT '', createtime INTEGER DEFAULT 0, paytime INTEGER DEFAULT 0)''')
    cur.execute('CREATE INDEX IF NOT EXISTS idx_pay_order_no ON res_pay_order(out_trade_no)')
    # 传世武器抽奖券
    cur.execute('''CREATE TABLE IF NOT EXISTS res_lottery_ticket (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT DEFAULT '', times INTEGER DEFAULT 1,
        used INTEGER DEFAULT 0, source TEXT DEFAULT '', out_trade_no TEXT DEFAULT '',
        createtime INTEGER DEFAULT 0)''')
    # 传世武器抽奖记录（含游戏信息）
    cur.execute('''CREATE TABLE IF NOT EXISTS res_lottery_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT DEFAULT '', prize_id TEXT DEFAULT '',
        prize_name TEXT DEFAULT '', prize_icon TEXT DEFAULT '', region TEXT DEFAULT '',
        game_nick TEXT DEFAULT '', game_id TEXT DEFAULT '', helper TEXT DEFAULT '',
        createtime INTEGER DEFAULT 0)''')
    # 奖池价格字段（前端奖品池展示服务价）
    try:
        cur.execute("ALTER TABLE res_prize_pool ADD COLUMN price TEXT DEFAULT '0'")
    except Exception:
        pass
    # 业务模块演示数据（仅空表写入）
    cur.execute('SELECT COUNT(*) FROM fa_thug_withdraw')
    if cur.fetchone()[0] == 0:
        today = int(datetime.now().replace(hour=10, minute=0, second=0, microsecond=0).timestamp())
        rows = [
            (1, 'XT冰帝', 'TX' + str(today)[-6:] + '01', '100.00', '2.00', '98.00', 'wechat', 'wx_iceking01', today - 7200, today - 3600, today - 1800, 3, '超级管理员', '', '首位打手提现'),
            (2, 'XT富贵', 'TX' + str(today)[-6:] + '02', '500.00', '10.00', '490.00', 'alipay', 'ali_fugui@qq.com', today - 5400, 0, 0, 0, '', '', '大额提现待审核'),
            (3, 'XT动力', 'TX' + str(today)[-6:] + '03', '200.00', '4.00', '196.00', 'wechat', 'wx_dongli02', today - 9000, today - 5000, 0, 2, '超级管理员', '', '已审核待打款'),
            (4, 'XTBaby', 'TX' + str(today)[-6:] + '04', '60.00', '1.20', '58.80', 'wechat', 'wx_baby03', today - 12000, today - 8000, 0, 1, '超级管理员', '收款信息有误', '驳回示例'),
            (5, 'XT嘻宝', 'TX' + str(today)[-6:] + '05', '150.00', '3.00', '147.00', 'alipay', 'ali_xibao@qq.com', today - 100000, today - 95000, today - 90000, 3, '超级管理员', '', '昨日已打款'),
        ]
        for r in rows:
            cur.execute('''INSERT INTO fa_thug_withdraw
                (thug_id, thug_name, withdraw_no, amount, fee, real_amount, account_type, account,
                 apply_time, audit_time, pay_time, status, audit_admin, reject_reason, remark, create_time, update_time)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''', r + (r[8], r[8]))
    for tbl, seed_rows, sql in [
        ('fa_admin_log', [
            (1, '超级管理员', '系统设置', '修改了彩虹易支付配置', today_ts() - 3600),
            (1, '超级管理员', '打手提现', '审核通过提现单 TXD001，金额 ¥100.00', today_ts() - 1800),
        ], 'INSERT INTO fa_admin_log (admin_id, admin_name, module, content, ip, create_time) VALUES (?,?,?,?,\'127.0.0.1\',?)'),
        ('fa_sms_log', [
            ('13800000001', 'SMS_10001', '您的验证码为 8888，5 分钟内有效。', 1, 'provider=aliyun', 'login', today_ts() - 600),
        ], 'INSERT INTO fa_sms_log (mobile, template_id, content, status, provider_resp, scene, create_time) VALUES (?,?,?,?,?,?,?)'),
        ('fa_aggregate_login_log', [
            (1, 'wechat', 'juhe_wechat_mock001', '创世用户0001', '127.0.0.1', 'iPhone / Safari', today_ts() - 1200),
        ], 'INSERT INTO fa_aggregate_login_log (user_id, channel, openid, nickname, login_ip, device, create_time) VALUES (?,?,?,?,?,?,?)'),
        ('fa_pay_callback_log', [
            ('epay', 'RC' + str(today_ts())[-8:], '{"trade_status":"TRADE_SUCCESS"}', 1, 1, '余额已增加', '127.0.0.1', today_ts() - 900),
        ], 'INSERT INTO fa_pay_callback_log (channel, order_no, raw, sign_ok, handled, result, ip, create_time) VALUES (?,?,?,?,?,?,?,?)'),
        ('fa_cron_log', [
            (1, '订单超时自动取消', 1, '本次处理 0 条', 32, today_ts() - 300),
        ], 'INSERT INTO fa_cron_log (task_id, task_name, status, message, cost_ms, create_time) VALUES (?,?,?,?,?,?)'),
    ]:
        cur.execute('SELECT COUNT(*) FROM ' + tbl)
        if cur.fetchone()[0] == 0:
            for _r in seed_rows:
                cur.execute(sql, _r)
    db.commit()
    db.close()

def today_ts():
    return int(time.time())

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

# 订单状态文案（0待付款 1待接单 2待开始 3服务中 4待结单 5已完成 6售后中 7已退款 8已取消）
ORDER_STATUS_TEXT = {
    0: '待付款', 1: '待接单', 2: '待开始', 3: '服务中',
    4: '待结单', 5: '已完成', 6: '售后中', 7: '已退款', 8: '已取消',
}

def serialize_order(row):
    """把订单行序列化为前端需要的结构"""
    r = dict(row)
    get = lambda k, d='': (r.get(k) if r.get(k) is not None else d)
    status = int(get('status', 0) or 0)
    return {
        "id": r['id'],
        "order_no": get('order_no'),
        "goods_id": get('goods_id', 0),
        "user_id": get('user_id', 0),
        "thug_id": get('thug_id', 0),
        "title": get('title'),
        "logo": get('logo') or '/static/images/header_background.png',
        "image": get('logo') or '/static/images/header_background.png',
        "price": get('price', '0'),
        "pay_price": get('pay_price', '0'),
        "number": get('number', 1),
        "game_id": get('game_id'),
        "wz_id": get('wz_id'),
        "game_qf": get('game_qf'),
        "remarks": get('remarks'),
        "synopsis": get('synopsis'),
        "status": status,
        "status_text": get('status_text') or ORDER_STATUS_TEXT.get(status, ''),
        "complaint": get('complaint', 0),
        "award": get('award', ''),
        "jackpot_id": get('jackpot_id', 0),
        "Jackpot_id": get('jackpot_id', 0),
        "is_rated": get('is_rated', 0),
        "rating": get('rating', 0),
        "grade": get('rating', 0),
        "thug_remarks": get('thug_remarks', ''),
        "helper": get('helper', ''),
        "refund_method": get('refund_method', ''),
        "refund_amount": get('pay_price', '0'),
        "refund_time_text": fmt_time(r.get('refund_time')) if r.get('refund_time') else '',
        "createtime": fmt_time(r.get('createtime')),
        "create_time": fmt_time(r.get('createtime')),
        "paytime": fmt_time(r.get('paytime')) if r.get('paytime') else '',
        "connecttime": fmt_time(r.get('connecttime')) if r.get('connecttime') else '',
        "completetime": fmt_time(r.get('completetime')) if r.get('completetime') else '',
        "place_order": get_config('place_order', ''),
    }

def order_images(order):
    """订单服务凭证图片（逗号分隔字符串 -> 列表）"""
    raw = (order.get('image') or '') if isinstance(order, dict) else ''
    return [u for u in re.split(r'[,\n]', raw) if u.strip()] if raw else []

def gen_order_no(prefix='D'):
    """生成订单号：前缀 + 时间戳 + 随机三位"""
    return '%s%d%03d' % (prefix, now_ts(), random.randint(0, 999))

def get_thug_by_user(uid):
    """按用户ID查询打手记录"""
    try:
        db = get_db()
        row = db.execute('SELECT * FROM thug WHERE user_id = ? AND is_deleted = 0', (uid,)).fetchone()
        db.close()
        return dict(row) if row else None
    except Exception:
        return None

def add_balance_log(user_id, amount, before, after, type_, remark):
    """写入一条余额明细"""
    try:
        db = get_db()
        db.execute(
            'INSERT INTO res_balance (user_id, amount, before, after, type, remark, createtime, update_time) '
            'VALUES (?,?,?,?,?,?,?,?)',
            (str(user_id), str(amount), str(before), str(after), str(type_), remark, now_ts(), now_ts()))
        db.commit()
        db.close()
    except Exception:
        pass

def pick_prize_weighted():
    """按爆率权重从奖池中随机抽一个奖品，返回奖品行或 None"""
    prizes = [p for p in get_prize_pool_list()
              if str(p['status']) == '1' and float(p['rate'] or 0) > 0]
    if not prizes:
        return None
    total = sum(float(p['rate'] or 0) for p in prizes)
    pick = random.uniform(0, total)
    acc = 0.0
    chosen = prizes[-1]
    for p in prizes:
        acc += float(p['rate'] or 0)
        if pick <= acc:
            chosen = p
            break
    return chosen

PAY_TYPE_MAP = {
    'wechat': 'wxpay', 'wxpay': 'wxpay', 'epay_wxpay': 'wxpay', 'native_wechat': 'wxpay',
    'alipay': 'alipay', 'epay_alipay': 'alipay',
    'qq': 'qqpay', 'qqpay': 'qqpay', 'epay_qqpay': 'qqpay',
    'bank': 'bank', 'epay_bank': 'bank',
}

def get_epay_return_url(epay=None):
    """支付完成后的同步跳转地址（收银台支付成功后返回的页面）"""
    url = get_config('epay_return_url', '')
    if url:
        return url
    notify = (epay or get_epay_config()).get('notify_url', '')
    if notify and '://' in notify:
        return notify.split('://', 1)[0] + '://' + notify.split('://', 1)[1].split('/')[0] + '/h5/#/pages/index/paysuccess'
    return ''

def gen_epay_url(epay, out_trade_no, name, money, pay_type='wxpay'):
    """生成彩虹易支付收银台地址（支持微信/支付宝/QQ/云闪付）"""
    params = {
        'pid': epay.get('pid', ''),
        'type': PAY_TYPE_MAP.get(str(pay_type), 'wxpay'),
        'out_trade_no': out_trade_no,
        'notify_url': epay.get('notify_url', ''),
        'return_url': get_epay_return_url(epay),
        'name': name,
        'money': '%.2f' % float(money),
    }
    raw = '&'.join('%s=%s' % (k, params[k]) for k in sorted(params) if str(params[k]) != '')
    params['sign'] = hashlib.md5((raw + epay.get('key', '')).encode('utf-8')).hexdigest()
    params['sign_type'] = 'MD5'
    base = epay.get('apiurl', '').rstrip('/')
    return base + '/submit.php?' + urlencode(params)

def epay_verify_sign(epay, data):
    """校验彩虹易支付异步通知签名"""
    sign = str(data.get('sign', ''))
    if not sign:
        return False
    raw = '&'.join('%s=%s' % (k, data[k]) for k in sorted(data) if k not in ('sign', 'sign_type') and str(data[k]) != '')
    return hashlib.md5((raw + epay.get('key', '')).encode('utf-8')).hexdigest() == sign

def epay_query(epay, out_trade_no):
    """主动查询易支付订单状态，返回 True 表示已支付"""
    base = epay.get('apiurl', '').rstrip('/')
    if not (base and epay.get('pid') and epay.get('key')):
        return None
    try:
        q = urlencode({'act': 'order', 'pid': epay['pid'], 'key': epay['key'], 'out_trade_no': out_trade_no})
        with _urlreq.urlopen(base + '/api.php?' + q, timeout=8) as resp:
            info = json.loads(resp.read().decode('utf-8'))
        return str(info.get('trade_status', '')) == 'TRADE_SUCCESS' or int(info.get('code', 0) or 0) == 1
    except Exception:
        return None



PAY_SCENE_TITLE = {
    'recharge': '账户充值', 'bill': '系统缴费', 'fine': '罚款缴纳',
    'order': '陪玩服务', 'lottery': '传世武器抽奖', 'level': '开通会员', 'deposit': '保证金缴纳',
    'gift': '赠送礼物', 'reward': '打赏打手',
}

def create_pay_order(user_id, scene, target_id, amount, title='', pay_type='wechat', out_trade_no=None):
    """创建统一支付单，返回商户订单号"""
    ono = out_trade_no or gen_order_no('P')
    db = get_db()
    db.execute('INSERT INTO res_pay_order (out_trade_no,user_id,scene,target_id,title,amount,pay_type,status,createtime) '
               'VALUES (?,?,?,?,?,?,?,?,?)',
               (ono, str(user_id), scene, str(target_id or ''), title or PAY_SCENE_TITLE.get(scene, '在线支付'),
                '%.2f' % float(amount), pay_type, '0', now_ts()))
    db.commit()
    db.close()
    return ono

def apply_pay_scene(scene, user_id, target_id, amount, out_trade_no='', online=True):
    """按支付场景发货（充值加余额；缴费/罚款置已缴；订单置已支付；抽奖发券；会员开通）"""
    db = get_db()
    bal = None
    if scene == 'recharge':
        if online:
            row = db.execute('SELECT money FROM users WHERE id = ?', (str(user_id),)).fetchone()
            bal = float(row['money'] or 0) if row else 0.0
            after = round(bal + float(amount), 2)
            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, str(user_id)))
        db.commit()
        db.close()
        if online and bal is not None:
            add_balance_log(user_id, amount, bal, round(bal + float(amount), 2), 1, '账户充值')
        return True
    if scene == 'bill':
        db.execute("UPDATE res_sys_bill SET status = '1', update_time = ? WHERE id = ?", (now_ts(), str(target_id)))
    elif scene == 'fine':
        db.execute("UPDATE res_fine SET status = '1', update_time = ? WHERE id = ?", (now_ts(), str(target_id)))
    elif scene == 'order':
        db.execute('UPDATE orders SET status = 1, status_text = ?, paytime = ? WHERE id = ?',
                   (ORDER_STATUS_TEXT[1], now_ts(), target_id))
    elif scene == 'level':
        db.execute('UPDATE users SET level = ? WHERE id = ?', (target_id, str(user_id)))
    elif scene == 'deposit':
        db.execute('INSERT INTO res_deposit (user_id, amount, status, remark, createtime) VALUES (?,?,?,?,?)',
                   (str(user_id), '%.2f' % float(amount), '0', '保证金缴纳（在线）', now_ts()))
    elif scene == 'lottery':
        try:
            times = int(str(target_id) or '1')
        except Exception:
            times = 1
        times = 1 if times < 1 else (10 if times > 10 else times)
        db.execute('INSERT INTO res_lottery_ticket (user_id, times, used, source, out_trade_no, createtime) '
                   'VALUES (?,?,?,?,?,?)', (str(user_id), times, 0, 'pay', out_trade_no, now_ts()))
    elif scene == 'gift':
        parts = str(target_id or '').split('|')
        tid = parts[0] if len(parts) > 0 else '0'
        gid = parts[1] if len(parts) > 1 else '0'
        num = int(parts[2]) if len(parts) > 2 and str(parts[2]).isdigit() else 1
        g = db.execute('SELECT * FROM res_gift WHERE id = ? AND is_deleted = 0', (gid,)).fetchone()
        if g:
            db.execute('INSERT INTO fa_gift_record (user_id, thug_id, gift_id, gift_name, gift_icon, num, amount, '
                       'pay_type, order_no, status, create_time) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                       (str(user_id), tid, g['id'], g['name'], g['icon'], num, '%.2f' % float(amount),
                        'epay', out_trade_no, 1, now_ts()))
    elif scene == 'reward':
        db.execute('INSERT INTO fa_thug_reward (user_id, thug_id, money, pay_type, order_no, status, create_time) '
                   'VALUES (?,?,?,?,?,?,?)',
                   (str(user_id), str(target_id or '0'), '%.2f' % float(amount), 'epay', out_trade_no, 1, now_ts()))
    db.commit()
    db.close()
    return True

def legacy_fulfill(out_trade_no):
    """兼容旧流程：订单号/充值单号直接作为商户订单号"""
    db = get_db()
    o = db.execute('SELECT id, status FROM orders WHERE order_no = ? AND is_deleted = 0', (out_trade_no,)).fetchone()
    if o:
        if str(o['status']) == '0':
            db.execute('UPDATE orders SET status = 1, status_text = ?, paytime = ? WHERE id = ?',
                       (ORDER_STATUS_TEXT[1], now_ts(), o['id']))
            db.commit()
        db.close()
        return True
    r = db.execute('SELECT id, user_id, amount, status FROM res_recharge_order WHERE order_no = ?', (out_trade_no,)).fetchone()
    if r:
        if str(r['status']) == '0':
            row = db.execute('SELECT money FROM users WHERE id = ?', (str(r['user_id']),)).fetchone()
            bal = float(row['money'] or 0) if row else 0.0
            after = round(bal + float(r['amount'] or 0), 2)
            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, str(r['user_id'])))
            db.execute("UPDATE res_recharge_order SET status = '1', update_time = ? WHERE id = ?", (now_ts(), r['id']))
            db.commit()
            db.close()
            add_balance_log(r['user_id'], float(r['amount'] or 0), bal, after, 1, '账户充值')
            return True
        db.close()
        return True
    db.close()
    return False

def mark_pay_paid(out_trade_no, trade_no=''):
    """支付成功发货（幂等）"""
    db = get_db()
    row = db.execute('SELECT * FROM res_pay_order WHERE out_trade_no = ?', (out_trade_no,)).fetchone()
    if not row:
        db.close()
        return legacy_fulfill(out_trade_no)
    if str(row['status']) == '1':
        db.close()
        return True
    db.execute("UPDATE res_pay_order SET status = '1', trade_no = ?, paytime = ? WHERE id = ?",
               (str(trade_no or ''), now_ts(), row['id']))
    db.commit()
    db.close()
    return apply_pay_scene(row['scene'], row['user_id'], row['target_id'],
                           float(row['amount'] or 0), out_trade_no, online=True)


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

# ===== 后台权限 / 操作日志 / 通用辅助 =====
# 财务与流水类数据：永久保留，禁止删除（不提供删除接口）
FINANCE_NO_DELETE = {
    'withdraw', 'bill', 'balance', 'commission_detail', 'order_commission',
    'deposit', 'recharge_order', 'refund', 'thug_withdraw',
    'admin_log', 'sms_log', 'pay_callback_log', 'cron_log', 'aggregate_login_log',
}
ALL_PERMS = {'view', 'add', 'edit', 'delete', 'export', 'config'}

def resField_label(spec, col):
    """取资源字段的中文标签（导出表头用）"""
    for f in (spec.get('fields') or []):
        if f.get('key') == col:
            return f.get('label') or col
    return col

def current_admin(headers):
    """解析当前管理员身份与权限（本地回退模式，默认超级管理员拥有全部权限）"""
    uid, uname, role = 1, '超级管理员', '超级管理员'
    try:
        v = headers.get('AdminUserId') if headers else None
        if v:
            uid = int(v)
    except Exception:
        uid = 1
    try:
        db = get_db()
        row = db.execute('SELECT * FROM res_admin_user WHERE id = ?', (uid,)).fetchone()
        if row:
            d = dict(row)
            uname = d.get('nickname') or d.get('username') or uname
            role = d.get('role') or role
        perm_row = db.execute('SELECT auth FROM res_role WHERE name = ?', (role,)).fetchone()
        db.close()
    except Exception:
        perm_row = None
    perms = set(ALL_PERMS)
    if perm_row:
        auth = (perm_row['auth'] or '').strip()
        if auth and auth != '*':
            perms = set(x.strip() for x in auth.replace('，', ',').split(',') if x.strip())
    return {'id': uid, 'name': uname, 'role': role, 'perms': perms}

def has_perm(admin, perm):
    perms = (admin or {}).get('perms') or set()
    return '*' in perms or perm in perms

def log_admin_action(db, admin, module, content, ip=''):
    """写入管理员操作日志（不可删除）"""
    try:
        sql = 'INSERT INTO fa_admin_log (admin_id, admin_name, module, content, ip, create_time) VALUES (?,?,?,?,?,?)'
        args = ((admin or {}).get('id', 0), (admin or {}).get('name', ''), module, content, ip, now_ts())
        if db is not None:
            db.execute(sql, args); db.commit()
        else:
            _db = get_db(); _db.execute(sql, args); _db.commit(); _db.close()
    except Exception:
        pass

def client_ip(handler):
    try:
        xff = handler.headers.get('X-Forwarded-For')
        if xff:
            return xff.split(',')[0].strip()
        return handler.client_address[0]
    except Exception:
        return ''

# ===== 短信发送工具（统一封装） =====
SMS_RATE = {}  # {mobile: [ts,...]}
DEMO_MOBILE = '13800138000'  # 演示用打手手机号（真实场景取打手绑定手机号）
DASHU_ALERT_AMOUNT = 500.0   # 大额提现企微告警阈值（元）
SMS_SCENES = {
    'register': '注册验证码', 'login': '登录验证码', 'withdraw': '提现审核通知',
    'order': '订单状态变更通知', 'complaint': '投诉通知', 'deposit': '保证金提醒',
}

def send_sms(mobile, template_id, content, scene='', db=None):
    """统一短信发送：总开关/场景开关/频率限制/日志落库"""
    mobile = (mobile or '').strip()
    if not mobile:
        return {'ok': False, 'msg': '手机号为空'}
    if get_config('sms_enabled', '') != '1':
        return {'ok': False, 'msg': '短信总开关未开启'}
    if scene and get_config('sms_scene_' + scene, '1') != '1':
        return {'ok': False, 'msg': '该场景短信未开启'}
    # 频率限制：同一手机号 60 秒内仅 1 条
    now = now_ts()
    hist = [t for t in SMS_RATE.get(mobile, []) if now - t < 60]
    if hist:
        return {'ok': False, 'msg': '发送过于频繁，请稍后再试'}
    hist.append(now)
    SMS_RATE[mobile] = hist
    provider = get_config('sms_provider', 'aliyun')
    status, resp = 1, '发送成功（模拟）'
    if provider:
        # 真实接入时在此调用对应服务商 SDK；无凭证时记录模拟结果
        resp = 'provider=%s template=%s' % (provider, template_id)
    try:
        _db = db or get_db()
        _db.execute('INSERT INTO fa_sms_log (mobile, template_id, content, status, provider_resp, scene, ip, create_time) VALUES (?,?,?,?,?,?,?,?)',
                    (mobile, template_id, content, status, resp, scene, '', now))
        _db.commit()
        if db is None:
            _db.close()
    except Exception as e:
        return {'ok': False, 'msg': '短信日志写入失败: %s' % e}
    return {'ok': True, 'msg': '发送成功'}

def wecom_alert(title, content):
    """企微机器人告警推送（配置未启用时仅记录）"""
    try:
        cfg_webhook = get_config('wechat_robot_webhook', '')
        enabled = get_config('wechat_robot_enabled', '0')
        if not cfg_webhook:
            db = get_db()
            row = db.execute('SELECT webhook, enabled FROM res_wechat_robot WHERE id = 1').fetchone()
            db.close()
            if row:
                cfg_webhook = row['webhook'] or ''
                enabled = str(row['enabled'] or '0')
        if cfg_webhook and str(enabled) in ('1', 'True', 'true'):
            import urllib.request as ur
            payload = json.dumps({'msgtype': 'markdown', 'markdown': {'content': '**【%s】**\n%s' % (title, content)}}).encode('utf-8')
            ur.urlopen(ur.Request(cfg_webhook, data=payload, headers={'Content-Type': 'application/json'}), timeout=5)
    except Exception:
        pass

# ===== 前端装修主题配置 =====
DEFAULT_DECORATION = {
    "name": "电竞暗橙",
    "colors": {
        "brand": "#C05B34",
        "brand_gradient": "#A64A28",
        "page_bg": "#F4F5F7",
        "card_bg": "#FFFFFF",
        "text_main": "#2B303B",
        "text_sub": "#8A8F98",
        "price": "#C05B34",
        "btn_text": "#FFFFFF",
        "tabbar_bg": "#FFFFFF",
        "top_text": "#FFFFFF",
        "search_bg": "#FFFFFF",
        "card_border": "#EAECEF",
        "notice_bg": "#F2F3F5",
        "notice_text": "#6B7280",
        "tabbar_active": "#C05B34",
        "tabbar_text": "#9AA0A6",
        "tabbar_text_active": "#C05B34",
        "tabbar_radius": "999",
    },
    "bg_image": "",
    # 页面背景：solid=纯色 / gradient=渐变色；初始为纯白
    "page_bg_type": "gradient",
    "page_bg_from": "#F4F5F7",
    "page_bg_to": "#FFFFFF",
    "page_bg_deg": 180,
    "nav_style": "float_ellipse",
    "floating_nav": [
        {"text": "首页", "icon": "home", "color": "#C05B34", "link": "/pages/index/index"},
        {"text": "分类", "icon": "grid", "color": "#C05B34", "link": "/pages/classify/index"},
        {"text": "挑选打手", "icon": "gamepad", "color": "#C05B34", "link": "/pages/player/index"},
        {"text": "消息", "icon": "chat", "color": "#C05B34", "link": "/pages/message/index"},
        {"text": "我的", "icon": "user", "color": "#C05B34", "link": "/pages/mine/index"},
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
            merged['page_bg_type'] = cfg.get('page_bg_type', DEFAULT_DECORATION['page_bg_type'])
            merged['page_bg_from'] = cfg.get('page_bg_from', DEFAULT_DECORATION['page_bg_from'])
            merged['page_bg_to'] = cfg.get('page_bg_to', DEFAULT_DECORATION['page_bg_to'])
            merged['page_bg_deg'] = cfg.get('page_bg_deg', DEFAULT_DECORATION['page_bg_deg'])
            merged['nav_style'] = cfg.get('nav_style', DEFAULT_DECORATION['nav_style'])
            merged['floating_nav'] = cfg.get('floating_nav', DEFAULT_DECORATION['floating_nav'])
            # 店铺开关状态（开店/关店）
            merged['store_status'] = get_config('store_status', '1')  # 1=营业, 0=关店
            merged['close_qq'] = get_config('close_qq', '')
            merged['close_image'] = get_config('close_image', '')
            # 传世武器入口配置（打造与抽奖互斥）
            merged['craft_mode'] = get_config('craft_mode', 'lottery')
            merged['craft_banner'] = get_config('craft_banner', '')
            merged['craft_jump'] = get_config('craft_jump', '')
            merged['lottery_banner'] = get_config('lottery_banner', '/uploads/craft_lottery_banner.png')
            merged['lottery_jump'] = get_config('lottery_jump', '') or '/pages/lottery/detail'
            return merged
        except Exception:
            pass
    return json.loads(json.dumps(DEFAULT_DECORATION))

def get_store_config():
    """获取店铺开关配置"""
    return {
        'store_status': get_config('store_status', '1'),
        'close_qq': get_config('close_qq', ''),
        'close_image': get_config('close_image', ''),
    }

def get_home_bgm_config():
    """获取首页背景音乐配置"""
    return {
        'url': get_config('home_bgm_url', ''),
        'title': get_config('home_bgm_title', '背景音乐'),
        'sub': get_config('home_bgm_sub', '首页背景音乐 · 点击播放'),
        'enabled': get_config('home_bgm_enabled', '1'),
    }

# ===== 多平台音乐搜索 =====

_MUSIC_SSL_CTX = ssl.create_default_context()
_MUSIC_SSL_CTX.check_hostname = False
_MUSIC_SSL_CTX.verify_mode = ssl.CERT_NONE

def _music_http(url, headers=None, timeout=8):
    """发起 HTTP 请求并返回 JSON"""
    h = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'}
    if headers:
        h.update(headers)
    req = _urlreq.Request(url, headers=h)
    try:
        r = _urlreq.urlopen(req, timeout=timeout, context=_MUSIC_SSL_CTX)
        raw = r.read().decode('utf-8', errors='ignore')
        try:
            return json.loads(raw)
        except (json.JSONDecodeError, ValueError):
            # 部分接口（如酷我）返回 Python dict 格式
            try:
                return ast.literal_eval(raw)
            except Exception:
                return {'_error': 'parse failed', '_raw': raw[:200]}
    except Exception as e:
        return {'_error': str(e)}

def search_music_netease(keyword, limit=10):
    """网易云音乐搜索"""
    kw = _urlparse.quote(keyword)
    d = _music_http(f'https://music.163.com/api/cloudsearch/pc?s={kw}&type=1&limit={limit}&offset=0')
    songs = (d.get('result') or {}).get('songs', []) or []
    res = []
    for s in songs:
        artists = '/'.join(a.get('name', '') for a in s.get('ar', s.get('artists', [])))
        res.append({
            'platform': 'netease',
            'name': s.get('name', ''),
            'artist': artists,
            'album': (s.get('al') or s.get('album') or {}).get('name', ''),
            'cover': (s.get('al') or s.get('album') or {}).get('picUrl', ''),
            'duration': s.get('dt', s.get('duration', 0)),
            'id': s.get('id', ''),
        })
    return res

def search_music_qq(keyword, limit=10):
    """QQ音乐搜索"""
    kw = _urlparse.quote(keyword)
    d = _music_http(f'https://c.y.qq.com/soso/fcgi-bin/client_search_cp?w={kw}&format=json&n={limit}')
    songs = (((d.get('data') or {}).get('song') or {}).get('list')) or []
    res = []
    for s in songs:
        singers = '/'.join(sg.get('name', '') for sg in s.get('singer', []))
        res.append({
            'platform': 'qq',
            'name': s.get('songname', ''),
            'artist': singers,
            'album': s.get('albumname', ''),
            'cover': '',
            'duration': s.get('interval', 0) * 1000,
            'id': s.get('songmid', ''),
        })
    return res

def search_music_kugou(keyword, limit=10):
    """酷狗音乐搜索"""
    kw = _urlparse.quote(keyword)
    d = _music_http(f'https://mobilecdn.kugou.com/api/v3/search/song?keyword={kw}&page=1&pagesize={limit}')
    songs = ((d.get('data') or {}).get('info')) or []
    res = []
    for s in songs:
        res.append({
            'platform': 'kugou',
            'name': s.get('songname', ''),
            'artist': s.get('singername', ''),
            'album': s.get('album_name', ''),
            'cover': '',
            'duration': s.get('duration', 0) * 1000,
            'id': s.get('hash', ''),
            'album_id': s.get('album_audio_id', s.get('album_id', '')),
        })
    return res

def search_music_kuwo(keyword, limit=10):
    """酷我音乐搜索"""
    kw = _urlparse.quote(keyword)
    d = _music_http(f'https://search.kuwo.cn/r.s?all={kw}&ft=music&itemset=web_2013&client=kt&pn=0&rn={limit}&rformat=json&encoding=utf8')
    songs = d.get('abslist', []) if isinstance(d, dict) else []
    res = []
    for s in songs:
        rid = str(s.get('MUSICRID', '')).replace('MUSIC_', '')
        res.append({
            'platform': 'kuwo',
            'name': html.unescape(s.get('SONGNAME', s.get('NAME', ''))),
            'artist': html.unescape(s.get('ARTIST', '')),
            'album': html.unescape(s.get('ALBUM', '')),
            'cover': '',
            'duration': int(s.get('DURATION', 0)) * 1000,
            'id': rid,
        })
    return res

def search_music_soda(keyword, limit=10):
    """汽水音乐搜索（抖音）"""
    kw = _urlparse.quote(keyword)
    d = _music_http(f'https://soda.douyin.com/aweme/v1/web/search/?keyword={kw}&count={limit}&search_channel=single&offset=0')
    songs = []
    if isinstance(d, dict):
        songs = d.get('data', []) or []
    res = []
    for s in songs:
        music = s.get('music_info', s)
        res.append({
            'platform': 'soda',
            'name': music.get('title', ''),
            'artist': music.get('author', ''),
            'album': '',
            'cover': music.get('cover_url', music.get('cover_hd', {}).get('url_list', [''])[0] if isinstance(music.get('cover_hd'), dict) else ''),
            'duration': music.get('duration', 0),
            'id': music.get('id', ''),
        })
    return res

def search_music_kg(keyword, limit=10):
    """全民K歌搜索"""
    kw = _urlparse.quote(keyword)
    d = _music_http(f'https://kg.qq.com/cgi-bin/kg_search?keyword={kw}&num={limit}&p=1')
    songs = []
    if isinstance(d, dict):
        songs = (d.get('data') or {}).get('list', []) or []
    res = []
    for s in songs:
        res.append({
            'platform': 'kg',
            'name': s.get('song_name', s.get('name', '')),
            'artist': s.get('nick_name', s.get('singer', '')),
            'album': '',
            'cover': s.get('cover', s.get('cover_url', '')),
            'duration': 0,
            'id': s.get('shareid', s.get('id', '')),
        })
    return res

def search_music_feichangduo(keyword, limit=10):
    """非常多音乐（meting 聚合）"""
    kw = _urlparse.quote(keyword)
    res = []
    for server in ['netease', 'tencent', 'kugou', 'kuwo']:
        d = _music_http(f'https://api.injahow.cn/meting/?type=search&server={server}&name={kw}')
        if isinstance(d, list):
            for s in d[:limit]:
                res.append({
                    'platform': 'feichangduo',
                    'name': s.get('name', ''),
                    'artist': s.get('artist', ''),
                    'album': s.get('album', ''),
                    'cover': s.get('pic', ''),
                    'duration': 0,
                    'id': s.get('url_id', s.get('id', '')),
                    'source': server,
                })
            if res:
                break
    return res

MUSIC_SEARCHERS = {
    'netease': ('网易云音乐', search_music_netease),
    'qq': ('QQ音乐', search_music_qq),
    'kugou': ('酷狗音乐', search_music_kugou),
    'kugou_concept': ('酷狗概念版', search_music_kugou),
    'kuwo': ('酷我音乐', search_music_kuwo),
    'soda': ('汽水音乐', search_music_soda),
    'kg': ('K歌', search_music_kg),
    'feichangduo': ('非常多音乐', search_music_feichangduo),
}

def search_music(platform, keyword, limit=10):
    """统一音乐搜索入口"""
    if platform not in MUSIC_SEARCHERS:
        return []
    try:
        return MUSIC_SEARCHERS[platform][1](keyword, limit)
    except Exception as e:
        return [{'_error': str(e)}]

def get_music_url(platform, song_id, **kwargs):
    """获取歌曲播放直链"""
    try:
        if platform == 'netease':
            d = _music_http(f'https://music.163.com/api/song/url?id={song_id}')
            urls = d.get('data', []) or []
            return urls[0].get('url', '') if urls else ''
        elif platform == 'qq':
            guid = '1234567890'
            data = json.dumps({'req': {'module': 'CDN.SrfCdnDispatchServer', 'method': 'GetCdnDispatch', 'param': {'guid': guid, 'calltype': 0, 'userip': ''}}, 'req_0': {'module': 'vkey.GetVkeyServer', 'method': 'CgiGetVkey', 'param': {'guid': guid, 'songmid': [song_id], 'songtype': [0], 'uin': '0', 'loginflag': 1, 'platform': '20'}}})
            d = _music_http(f'https://u.y.qq.com/cgi-bin/musicu.fcg?data={_urlparse.quote(data)}')
            midurlinfo = ((d.get('req_0') or {}).get('data') or {}).get('midurlinfo', []) or []
            sip = ((d.get('req') or {}).get('data') or {}).get('sip', []) or []
            purl = midurlinfo[0].get('purl', '') if midurlinfo else ''
            return (sip[0] + purl) if (sip and purl) else ''
        elif platform in ('kugou', 'kugou_concept'):
            album_id = kwargs.get('album_id', '')
            d = _music_http(f'https://wwwapi.kugou.com/yy/index.php?r=play/getdata&hash={song_id}&album_id={album_id}&appid=1014&platid=1')
            data = d.get('data')
            if isinstance(data, dict):
                return data.get('play_url', '')
            elif isinstance(data, list) and data and isinstance(data[0], dict):
                return data[0].get('play_url', '')
            return ''
        elif platform == 'kuwo':
            d = _music_http(f'https://antiserver.kuwo.cn/anti.s?type=convert_url3&rid=MUSIC_{song_id}&format=mp3&response=url&br=128kmp3&from=web')
            return d.get('url', '') if isinstance(d, dict) else ''
        elif platform == 'soda':
            d = _music_http(f'https://soda.douyin.com/aweme/v1/web/music/play/?music_id={song_id}')
            if isinstance(d, dict):
                return ((d.get('data') or {}).get('play_url') or {}).get('url_list', [''])[0]
            return ''
        elif platform == 'feichangduo':
            src = kwargs.get('source', 'netease')
            d = _music_http(f'https://api.injahow.cn/meting/?type=url&server={src}&id={song_id}')
            if isinstance(d, dict):
                return d.get('url', '')
            return ''
    except Exception:
        return ''
    return ''

def get_craft_entry_config():
    """获取传世武器入口配置（打造与抽奖互斥，craft_mode 决定显示哪一个）"""
    images_raw = get_config('lottery_images', '')
    lottery_images = [u for u in re.split(r'[,\n]', images_raw) if u.strip()] if images_raw else []
    return {
        'craft_mode': get_config('craft_mode', 'lottery'),  # craft=打造传世武器, lottery=抽传世武器
        'craft_banner': get_config('craft_banner', ''),
        'craft_jump': get_config('craft_jump', ''),
        'lottery_banner': get_config('lottery_banner', '/uploads/craft_lottery_banner.png'),
        'lottery_jump': get_config('lottery_jump', '') or '/pages/lottery/detail',
        # 抽奖活动：自定义封面 + 抽奖图片（多张）
        'lottery_cover': get_config('lottery_cover', ''),
        'lottery_images': images_raw,
        'lottery_images_list': lottery_images,
        # 抽奖奖励爆率（读取奖池表）
        'lottery_prizes': get_prize_pool_list(),
    }


def get_prize_pool_list():
    """获取奖池奖励列表（含爆率 rate、库存 stock、服务价格 price）"""
    try:
        conn = get_db()
        rows = conn.execute(
            'SELECT id, name, icon, rate, stock, price, status FROM res_prize_pool '
            'WHERE is_deleted = 0 ORDER BY CAST(rate AS REAL) DESC, id ASC'
        ).fetchall()
        conn.close()
        return [{
            'id': r['id'], 'name': r['name'] or '', 'icon': r['icon'] or '',
            'rate': r['rate'] or '0', 'stock': r['stock'] or '0',
            'price': r['price'] or '0', 'status': r['status'] or '1'
        } for r in rows]
    except Exception:
        return []


def _norm_lottery_pool(p, idx, all_prizes):
    """规范化单个传世武器奖池（转盘/盲盒）"""
    p = p or {}
    pids = [str(v) for v in (p.get('prize_ids') or [])]
    prizes = [x for x in all_prizes if str(x['id']) in pids] if pids else all_prizes
    imgs = p.get('images') or []
    if isinstance(imgs, str):
        imgs = [u for u in re.split(r'[,\n]', imgs) if u.strip()]
    return {
        'id': str(p.get('id') or idx),
        'name': p.get('name') or ('奖池%d' % (idx + 1)),
        'mode': p.get('mode') if p.get('mode') in ('wheel', 'box') else 'wheel',
        'title': p.get('title') or p.get('name') or '传世武器抽奖',
        'subtitle': p.get('subtitle') or '奖品在商品池随机抽取',
        'cover': p.get('cover') or '',
        'images': imgs,
        'price': str(p.get('price') if p.get('price') is not None else '9.90'),
        'rules': p.get('rules') or '',
        'prizes': prizes,
    }


def get_lottery_pools():
    """传世武器抽奖：多奖池配置（转盘/盲盒），未配置时回退为单一默认池"""
    all_prizes = get_prize_pool_list()
    raw = get_config('lottery_pools', '')
    pools = []
    if raw:
        try:
            data = json.loads(raw)
            if isinstance(data, list):
                pools = [_norm_lottery_pool(p, i, all_prizes) for i, p in enumerate(data)]
        except Exception:
            pools = []
    if not pools:
        cfg = get_craft_entry_config()
        pools = [_norm_lottery_pool({
            'name': '传世武器组', 'mode': get_config('lottery_mode', 'wheel'),
            'title': get_config('lottery_title', '') or '传世武器抽奖',
            'subtitle': get_config('lottery_subtitle', '') or '极品传世武器等你来抽',
            'cover': cfg['lottery_cover'] or cfg['lottery_banner'],
            'images': cfg['lottery_images_list'],
            'price': get_config('lottery_price', '9.90'),
            'rules': get_config('lottery_rules', ''),
        }, 0, all_prizes)]
    return pools


def get_auto_settle_config():
    """获取订单自动结算与退款配置"""
    return {
        'auto_settle_enabled': get_config('auto_settle_enabled', '1'),
        'allow_unaccepted_refund': get_config('allow_unaccepted_refund', '1'),
    }

def get_withdraw_config():
    """获取提现设置配置"""
    return {
        'withdraw_enabled': get_config('withdraw_enabled', '1'),
        'withdraw_min': get_config('withdraw_min', '10'),
        'withdraw_max': get_config('withdraw_max', '10000'),
        'withdraw_fee_rate': get_config('withdraw_fee_rate', '0'),
        'withdraw_fee_fixed': get_config('withdraw_fee_fixed', '0'),
        'withdraw_daily_limit': get_config('withdraw_daily_limit', '0'),
        'withdraw_desc': get_config('withdraw_desc', '提现申请提交后将冻结对应余额，审核通过后人工打款到您填写的账户。'),
    }

def get_craft_config():
    """获取装备打造活动配置"""
    return {
        'craft_enabled': get_config('craft_enabled', '1'),
        'craft_title': get_config('craft_title', '传世武器打造'),
        'craft_subtitle': get_config('craft_subtitle', '两阶段打造，进度满额解锁'),
        'craft_price': get_config('craft_price', '1'),
        'craft_progress_min': get_config('craft_progress_min', '2'),
        'craft_progress_max': get_config('craft_progress_max', '5'),
        'craft_crit_rate': get_config('craft_crit_rate', '15'),
        'craft_crit_multi': get_config('craft_crit_multi', '2'),
        'craft_bg': get_config('craft_bg', ''),
        'craft_rules': get_config('craft_rules', '1. 购买打造次数后可选择打造1次或连续打造10次。\n2. 每次打造都会增加进度，并有概率触发暴击获得额外进度。\n3. 普通奖品进入背包，可领取或分解返还打造次数。'),
    }

def get_craft_stats():
    """获取装备打造运营概览数据"""
    try:
        users = db.execute('SELECT COUNT(DISTINCT user_id) FROM fa_craft_log').fetchone()[0]
    except Exception:
        users = 0
    try:
        total = db.execute('SELECT IFNULL(SUM(CAST(times AS REAL)),0) FROM fa_craft_log').fetchone()[0]
    except Exception:
        total = 0
    try:
        sales = db.execute("SELECT IFNULL(SUM(CAST(amount AS REAL)),0) FROM fa_craft_order WHERE status = 'paid'").fetchone()[0]
    except Exception:
        sales = 0
    try:
        pending = db.execute("SELECT COUNT(*) FROM fa_craft_prize_log WHERE status = 'pending'").fetchone()[0]
    except Exception:
        pending = 0
    try:
        bag = db.execute("SELECT COUNT(*) FROM fa_craft_prize_log WHERE status = 'bag'").fetchone()[0]
    except Exception:
        bag = 0
    return {
        'users': users, 'total_craft': total, 'sales': sales,
        'pending': pending, 'bag': bag,
        'enabled': get_config('craft_enabled', '1'),
    }


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

    def _send_csv(self, text, filename):
        data = text.encode('utf-8')
        self.send_response(200)
        self.send_header('Content-Type', 'text/csv; charset=utf-8')
        self.send_header('Content-Disposition', 'attachment; filename="%s"' % filename)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

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
    // 页面背景：图片 > 渐变 > 纯色（初始纯白）
    var bgType = (cfg && cfg.page_bg_type) || 'solid';
    var pgFrom = (cfg && cfg.page_bg_from) || c.page_bg || '#FFFFFF';
    var pgTo = (cfg && cfg.page_bg_to) || c.page_bg || '#FFFFFF';
    var pgDeg = (cfg && cfg.page_bg_deg) || 180;
    var pageBg;
    if (bg) pageBg = 'url('+bg+') center/cover no-repeat fixed';
    else if (bgType === 'gradient') pageBg = 'linear-gradient('+pgDeg+'deg,'+pgFrom+' 0%,'+pgTo+' 100%) fixed';
    else pageBg = (c.page_bg || '#FFFFFF') + ' fixed';
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
    document.body.style.background = pageBg;
    document.body.style.backgroundAttachment = 'fixed';
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
      'html,body,#app,.uni-page-wrapper,.uni-page-body{background:'+pageBg+'!important;background-attachment:fixed!important;}',
      '.uni-tabbar,.uni-tabbar__item,.uni-tabbar-nav{background:var(--theme-tabbar-bg)!important;}',
      // 通用按钮/主色覆盖
      'button,.uv-button--primary,[class*="primary"]{background:linear-gradient(90deg,var(--theme-brand),var(--theme-brand-gradient))!important;color:var(--theme-btn-text)!important;border-color:var(--theme-brand)!important;}',
      // 卡片/容器背景（排除 BEM 子元素，避免给子节点误加底色）
      '[class*="card"]:not([class*="__"]),[class*="Card"]:not([class*="__"]),[class*="item"][class*="bg"]{background-color:var(--theme-card-bg)!important;}',
      // 文字颜色
      'uni-text,text,.uni-text{color:var(--theme-text-main);}',
      '[class*="sub"]:not([class*="__"]),[class*="muted"],[class*="desc"]{color:var(--theme-text-sub)!important;}',
      '[class*="price"],[class*="money"]{color:var(--theme-price)!important;}',
      '[class*="notice"]:not([class*="__"]):not([class*="popup"]),[class*="Notice"]:not([class*="__"]){background:var(--theme-notice-bg)!important;color:var(--theme-notice-text)!important;}',
      '[class*="search"]:not([class*="__"]):not([class*="popup"]),[class*="Search"]:not([class*="__"]){background:var(--theme-search-bg)!important;}'
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
            if 'application/x-www-form-urlencoded' in ctype:
                post_data = {k: v[0] for k, v in parse_qs(raw_body.decode('utf-8', errors='ignore')).items()}
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
                    "mobile": u.get('mobile', '') or '',
                    "sex": u.get('sex', '') or '1',
                    "signature": u.get('signature', '') or '',
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
                    "club_name": get_config('club_name', '创世网络'),
                    "brand_name": get_config('club_name', '创世网络'),
                    "site_logo": get_config('site_logo', '/static/eon.png'),
                    "site_name": get_config('club_name', '创世网络'),
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
            page = int(qs.get('page', ['1'])[0] or 1)
            limit = int(qs.get('limit', qs.get('limint', ['20']))[0] or 20)
            status = qs.get('status', [''])[0]
            order_no = qs.get('order_no', [''])[0]
            db = get_db()
            # 打手端按 thug_id 过滤，用户端按 user_id 过滤
            is_thug = str(u.get('type')) == '2'
            if is_thug:
                trow = db.execute('SELECT id FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
                owner_col, owner_id = 'thug_id', (trow['id'] if trow else -1)
            else:
                owner_col, owner_id = 'user_id', u['id']
            where, args = 'WHERE %s = ? AND is_deleted = 0' % owner_col, [owner_id]
            if status not in ('', '10', None):
                where += ' AND status = ?'
                args.append(int(status))
            if order_no:
                where += ' AND order_no LIKE ?'
                args.append('%' + order_no + '%')
            total = db.execute('SELECT COUNT(*) FROM orders ' + where, args).fetchone()[0]
            rows = db.execute('SELECT * FROM orders ' + where + ' ORDER BY createtime DESC LIMIT ? OFFSET ?',
                              args + [limit, (page - 1) * limit]).fetchall()
            db.close()
            list_data = [serialize_order(r) for r in rows]
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": {
                    "data": list_data,
                    "list": list_data,
                    "total": total,
                    "current_page": page,
                    "per_page": limit,
                    "pages": (total + limit - 1) // limit if limit else 1,
                    "last_page": (total + limit - 1) // limit if limit else 1
                }
            })
            return

        # ===== 订单详情 =====
        if path == '/api/goods/orderMsg':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            oid = qs.get('id', ['0'])[0]
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            data = serialize_order(row)
            data['image'] = order_images(dict(row))
            # 下单用户信息
            if data.get('user_id'):
                urow = db.execute('SELECT id, nickname, avatar FROM users WHERE id = ?', (data['user_id'],)).fetchone()
                if urow:
                    data['nickname'] = urow['nickname'] or ''
                    data['user_avatar'] = urow['avatar'] or '/static/images/icon.png'
            # 打手信息
            if data.get('thug_id'):
                trow = db.execute('SELECT t.id, t.name, t.user_id, u.avatar FROM thug t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?',
                                  (data['thug_id'],)).fetchone()
                if trow:
                    data['thug'] = {"id": trow['id'], "name": trow['name'], "avatar": trow['avatar'] or '/static/images/icon.png'}
                    data['thug_user_id'] = trow['user_id']
            # 售后/投诉对象
            crow = db.execute(
                'SELECT * FROM res_order_complaint WHERE order_no = ? AND is_deleted = 0 ORDER BY id DESC LIMIT 1',
                (data.get('order_no'),)).fetchone()
            if crow:
                c = dict(crow)
                data['complaint'] = {
                    "id": c['id'], "order_no": c['order_no'], "content": c.get('content') or '',
                    "status": c.get('status'), "type": '1', "createtime": fmt_time(c.get('createtime'))
                }
            # 关联奖品信息
            if data.get('jackpot_id'):
                prow = db.execute('SELECT id, name, icon FROM res_prize_pool WHERE id = ?', (data['jackpot_id'],)).fetchone()
                if prow:
                    data['jackpot'] = {"id": prow['id'], "name": prow['name'], "image": prow['icon']}
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": data})
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
                "cs_mode": (r['cs_mode'] if 'cs_mode' in r.keys() else 'built_in'),
                "avatar": r['avatar'],
                "online": r['online']
            } for r in rows]
            self.send_json({
                "code": 1,
                "msg": "成功",
                "data": list_data
            })
            return

        # ===== IM 初始化 =====
        if path == '/api/im/init':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 0, "msg": "未登录"})
                return
            self.send_json({"code": 1, "msg": "成功", "data": {"user": {"id": u['id'], "nickname": u['nickname'], "avatar": u['avatar']}}})
            return

        # ===== IM 创建会话 =====
        if path == '/api/im/createConversation':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 0, "msg": "未登录"}); return
            target_id = int(body.get('target_id') or 0)
            if target_id < 0:
                self.send_json({"code": 0, "msg": "参数错误"}); return
            a, b = sorted([u['id'], target_id])
            cid = 'c_%d_%d' % (a, b)
            db = get_db()
            row = db.execute('SELECT * FROM im_conversations WHERE conversation_id = ?', (cid,)).fetchone()
            now = now_ts()
            if not row:
                db.execute('INSERT INTO im_conversations (conversation_id, user_a, user_b, createtime) VALUES (?,?,?,?)',
                           (cid, a, b, now))
            # 普通用户联系内置客服(target_id=0)时，重置为未接入，进入接入池
            if target_id == 0:
                is_sender_cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
                if not is_sender_cs:
                    db.execute('UPDATE im_conversations SET accepted = 0, accepted_by = 0 WHERE conversation_id = ?', (cid,))
            db.commit()
            # 内置客服 target_id=0 时返回客服信息
            if target_id == 0:
                target = {"id": 0, "nickname": "平台客服", "avatar": "/static/yun/customer.png", "userId": 0}
            else:
                tr = db.execute('SELECT id, nickname, avatar FROM users WHERE id = ?', (target_id,)).fetchone()
                target = {"id": tr['id'], "nickname": tr['nickname'] or '用户', "avatar": tr['avatar'] or '', "userId": tr['id']} if tr else {}
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": {
                "conversation_id": cid,
                "target": {"id": target['id'], "nickname": target['nickname'] or '用户', "avatar": target['avatar'] or '', "userId": target['id']} if target else {}
            }})
            return

        # ===== IM 会话列表 =====
        if path == '/api/im/getConversationList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 1, "msg": "成功", "data": {"list": [], "total": 0}}); return
            db = get_db()
            # 判断当前用户是否为客服
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if cs:
                # 客服：看到所有接入池会话（任一方为内置客服0）+ 自己参与的会话
                rows = db.execute(
                    'SELECT * FROM im_conversations WHERE is_deleted = 0 AND ((user_a = 0 OR user_b = 0) OR user_a = ? OR user_b = ?) ORDER BY last_time DESC, id DESC',
                    (u['id'], u['id'])).fetchall()
            else:
                rows = db.execute(
                    'SELECT * FROM im_conversations WHERE is_deleted = 0 AND (user_a = ? OR user_b = ?) ORDER BY last_time DESC, id DESC',
                    (u['id'], u['id'])).fetchall()
            list_data = []
            for r in rows:
                # 客服接入池：任一方为0时，另一方为真实咨询用户
                if r['user_a'] == 0:
                    other_id = r['user_b']
                elif r['user_b'] == 0:
                    other_id = r['user_a']
                else:
                    other_id = r['user_b'] if r['user_a'] == u['id'] else r['user_a']
                # 客服接入池：other_id=0 时取客服名称
                if other_id == 0:
                    other = {"id": 0, "nickname": "平台客服", "avatar": "/static/yun/customer.png", "userId": 0}
                else:
                    ou = db.execute('SELECT id, nickname, avatar FROM users WHERE id = ?', (other_id,)).fetchone()
                    other = {"id": ou['id'], "nickname": ou['nickname'] or '用户', "avatar": ou['avatar'] or '', "userId": ou['id']} if ou else {}
                unread = db.execute('SELECT COUNT(*) FROM im_messages WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0',
                                    (r['conversation_id'], u['id'])).fetchone()[0]
                list_data.append({
                    "target_id": other_id,
                    "type": "single",
                    "unread_count": unread,
                    "last_message": r['last_msg'] or '',
                    "last_message_time": r['last_time'] or r['createtime'],
                    "target": other,
                    "accepted": r['accepted'] or 0
                })
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": {"list": list_data, "total": len(list_data)}})
            return

        # ===== IM 发送消息 =====
        if path == '/api/im/sendMessage':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 0, "msg": "未登录"}); return
            target_id = int(body.get('target_id') or 0)
            msg_type = body.get('msg_type') or 'text'
            content = body.get('content') or ''
            if target_id < 0 or not content:
                self.send_json({"code": 0, "msg": "参数错误"}); return
            a, b = sorted([u['id'], target_id])
            cid = 'c_%d_%d' % (a, b)
            db = get_db()
            row = db.execute('SELECT * FROM im_conversations WHERE conversation_id = ?', (cid,)).fetchone()
            now = now_ts()
            if not row:
                db.execute('INSERT INTO im_conversations (conversation_id, user_a, user_b, createtime) VALUES (?,?,?,?)', (cid, a, b, now))
            # 普通用户给内置客服(target_id=0)发消息时，重置为未接入，重新进入接入池
            is_sender_cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if target_id == 0 and not is_sender_cs:
                db.execute('UPDATE im_conversations SET accepted = 0, accepted_by = 0 WHERE conversation_id = ?', (cid,))
            preview = content if msg_type == 'text' else ('[图片]' if msg_type == 'image' else '[消息]')
            db.execute('UPDATE im_conversations SET last_msg = ?, last_time = ? WHERE conversation_id = ?', (preview[:80], now, cid))
            db.execute('INSERT INTO im_messages (conversation_id, sender_id, receiver_id, msg_type, content, createtime) VALUES (?,?,?,?,?,?)',
                       (cid, u['id'], target_id, msg_type, content, now))
            mid = db.execute('SELECT last_insert_rowid()').fetchone()[0]
            db.commit()
            sender = {"id": u['id'], "nickname": u['nickname'], "avatar": u['avatar']}
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": {
                "id": mid, "target_id": target_id, "sender_id": u['id'], "sender": sender,
                "msg_type": msg_type, "content": content, "createtime": now
            }})
            return

        # ===== IM 消息列表 =====
        if path == '/api/im/getMessageList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 1, "msg": "成功", "data": {"list": [], "has_more": False}}); return
            target_id = int(qs.get('target_id', ['0'])[0] or 0)
            if target_id:
                a, b = sorted([u['id'], target_id])
                cid = 'c_%d_%d' % (a, b)
            else:
                cid = qs.get('conversation_id', [''])[0]
            db = get_db()
            rows = db.execute('SELECT * FROM im_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 50', (cid,)).fetchall()
            # 标记收到的消息为已读
            db.execute('UPDATE im_messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?', (cid, u['id']))
            db.commit()
            msgs = []
            for r in rows:
                s = db.execute('SELECT id, nickname, avatar FROM users WHERE id = ?', (r['sender_id'],)).fetchone()
                msgs.append({
                    "id": r['id'], "target_id": target_id, "sender_id": r['sender_id'],
                    "sender": {"id": s['id'], "nickname": s['nickname'] or '用户', "avatar": s['avatar'] or ''} if s else {},
                    "msg_type": r['msg_type'], "content": r['content'], "createtime": r['createtime']
                })
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": {"list": list(reversed(msgs)), "has_more": False}})
            return

        # ===== IM 标记已读 =====
        if path == '/api/im/markAsRead':
            u = get_user_by_token(token)
            if u:
                target_id = int(body.get('target_id') or 0)
                if target_id:
                    a, b = sorted([u['id'], target_id])
                    cid = 'c_%d_%d' % (a, b)
                    db = get_db()
                    db.execute('UPDATE im_messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?', (cid, u['id']))
                    db.commit(); db.close()
            self.send_json({"code": 1, "msg": "成功"})
            return

        # ===== 客服接入会话（从接入池转入回复池） =====
        if path == '/api/im/acceptConversation':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 0, "msg": "未登录"}); return
            db = get_db()
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if not cs:
                db.close(); self.send_json({"code": 0, "msg": "无客服权限"}); return
            target_id = int(body.get('target_id') or 0)
            a, b = sorted([u['id'], target_id])
            # 接入池会话是 user_b=0 的公共会话；客服接入后更新 accepted
            pool_cid = 'c_%d_%d' % tuple(sorted([target_id, 0]))
            db.execute('UPDATE im_conversations SET accepted = 1, accepted_by = ? WHERE conversation_id = ?', (u['id'], pool_cid))
            # 同时在客服与该用户之间建立一对一可见会话
            own_cid = 'c_%d_%d' % (a, b)
            now = now_ts()
            if not db.execute('SELECT id FROM im_conversations WHERE conversation_id = ?', (own_cid,)).fetchone():
                db.execute('INSERT INTO im_conversations (conversation_id, user_a, user_b, accepted, accepted_by, createtime) VALUES (?,?,?,?,?,?)',
                           (own_cid, a, b, 1, u['id'], now))
            db.commit(); db.close()
            self.send_json({"code": 1, "msg": "接入成功"})
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

        # ===== 商品分类列表 =====
        if path == '/api/goods/categoryList':
            db = get_db()
            rows = db.execute(
                'SELECT id, name, icon, sort, status FROM res_goods_category WHERE is_deleted = 0 AND status = "1" ORDER BY CAST(sort AS INTEGER) ASC, id ASC'
            ).fetchall()
            db.close()
            data = [{
                "id": r['id'],
                "name": r['name'] or '',
                "icon": r['icon'] or '',
                "image": r['icon'] or '/static/ym/1.png',
                "sort": r['sort'],
                "children": []
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 区服列表（前端下单选择区服） =====
        if path == '/api/game/serverList':
            db = get_db()
            rows = db.execute(
                'SELECT id, name, game, sort, status FROM res_game_server WHERE is_deleted = 0 AND status = "1" ORDER BY CAST(sort AS INTEGER) ASC, id ASC'
            ).fetchall()
            db.close()
            data = [{"id": r['id'], "name": r['name'] or '', "game": r['game'] or '', "sort": r['sort']} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 打手专区分类列表 =====
        if path == '/api/thug/categoryList':
            cats = [
                {'id': 1, 'name': '地铁专区', 'image': '/static/ym/1.png'},
                {'id': 2, 'name': '地铁/古风', 'image': '/static/ym/2.png'},
                {'id': 3, 'name': '修仙专区', 'image': '/static/ym/3.png'},
                {'id': 4, 'name': '经典专区', 'image': '/static/ym/4.png'},
                {'id': 5, 'name': '牧童专区', 'image': '/static/ym/5.png'},
            ]
            self.send_json({"code": 1, "msg": "成功", "data": cats})
            return

        # ===== 打手专区：打手列表（读取真实打手表，初始为空，打手入驻后展示） =====
        if path == '/api/thug/thugList':
            kw = qs.get('name', [''])[0].strip()
            t = qs.get('type', ['5'])[0]
            cid = qs.get('category_id', ['0'])[0]
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            db = get_db()
            rows = db.execute(
                'SELECT t.id, t.user_id, t.name, t.sh_status, t.is_customer_service, '
                'u.nickname AS uname, u.avatar AS uavatar '
                'FROM thug t LEFT JOIN users u ON u.id = t.user_id '
                'WHERE t.is_deleted = 0 ORDER BY t.id DESC'
            ).fetchall()
            db.close()
            all_thugs = []
            for r in rows:
                all_thugs.append({
                    'id': r['id'],
                    'name': r['name'] or r['uname'] or '打手',
                    'sex': '1',
                    'type': 3,
                    'is_new': 1 if not r['is_customer_service'] else 0,
                    'jd_status': 1 if r['sh_status'] == 1 else 0,
                    'price': 0,
                    'synopsis': '',
                    'image': r['uavatar'] or '/static/images/header_background.png',
                    'audio': '', 'audio_time': 0, 'tags': '', 'category_id': 0,
                })
            if kw:
                all_thugs = [x for x in all_thugs if kw in x['name']]
            if str(t) in ('1', '0'):
                all_thugs = [x for x in all_thugs if x['sex'] == str(t)]
            if str(cid) not in ('', '0', 'None'):
                all_thugs = [x for x in all_thugs if str(x['category_id']) == str(cid)]
            total = len(all_thugs)
            start = (page - 1) * limit
            data = all_thugs[start:start + limit]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": data, "total": total}})
            return

        # ===== 首页：轮播图列表 =====
        if path == '/api/index/lunboList':
            db = get_db()
            rows = db.execute(
                'SELECT id, title, image, link, sort, status FROM res_banner WHERE is_deleted = 0 AND status = "1" ORDER BY CAST(sort AS INTEGER) ASC, id ASC'
            ).fetchall()
            db.close()
            data = [{
                "id": r['id'],
                "title": r['title'] or '',
                "image": r['image'] or '',
                "link": r['link'] or '',
                "sort": r['sort']
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 首页：图片导航（快捷入口） =====
        if path == '/api/index/imageNavList':
            db = get_db()
            rows = db.execute(
                'SELECT id, name, icon, color, link, sort, status FROM res_quick_entry WHERE is_deleted = 0 AND status = "1" ORDER BY CAST(sort AS INTEGER) ASC, id ASC'
            ).fetchall()
            db.close()
            data = [{
                "id": r['id'],
                "name": r['name'] or '',
                "title": r['name'] or '',
                "icon": r['icon'] or '',
                "image": r['icon'] or '',
                "image_url": r['icon'] or '',
                "color": r['color'] or '',
                "link_url": r['link'] or '',
                "sort": r['sort']
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 首页：等级列表 =====
        if path == '/api/index/levelList':
            db = get_db()
            rows = db.execute(
                'SELECT id, name, icon, need_score, discount, remark FROM res_level WHERE is_deleted = 0 ORDER BY CAST(need_score AS INTEGER) ASC'
            ).fetchall()
            db.close()
            data = [{
                "id": r['id'],
                "name": r['name'] or '',
                "icon": r['icon'] or '',
                "need_score": r['need_score'],
                "discount": r['discount'],
                "remark": r['remark'] or ''
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 首页：订单播报 =====
        if path == '/api/index/order_broadcast':
            limit = int(qs.get('limit', ['50'])[0] or 50)
            db = get_db()
            rows = db.execute(
                'SELECT o.id, o.title, o.logo, u.nickname, u.avatar '
                'FROM orders o LEFT JOIN users u ON u.id = o.user_id '
                'WHERE o.is_deleted = 0 AND o.status >= 1 ORDER BY o.id DESC LIMIT ?',
                (limit,)
            ).fetchall()
            db.close()
            data = [{
                "id": r['id'],
                "title": r['title'] or '',
                "logo": r['logo'] or '/static/eon.png',
                "name": r['nickname'] or '用户' + str(r['id']),
                "avatar": r['avatar'] or '/static/eon.png'
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 抽传世武器活动：活动配置（封面/抽奖图片/奖励爆率/支付方式）=====
        if path == '/api/lottery/config':
            pools = get_lottery_pools()
            epay = get_epay_config()
            self.send_json({"code": 1, "msg": "成功", "data": {
                "enabled": get_config('craft_enabled', '1'),
                "pools": pools,
                "pay_methods": epay['pay_methods'].split(','),
                "epay_enabled": bool(epay['apiurl'] and epay['pid'] and epay['key']),
            }})
            return

        # ===== 抽传世武器活动：抽奖（按后台爆率加权）=====
        if path == '/api/lottery/draw':
            pool_id = str(body.get('pool_id', '0')) if isinstance(body, dict) else '0'
            pools = get_lottery_pools()
            pool = None
            for p in pools:
                if str(p['id']) == pool_id:
                    pool = p
                    break
            pool = pool or (pools[0] if pools else None)
            prizes = [p for p in (pool['prizes'] if pool else [])
                      if p['status'] == '1' and float(p['rate'] or 0) > 0]
            times = int(body.get('times', 1) or 1) if isinstance(body, dict) else 1
            times = 1 if times < 1 else (10 if times > 10 else times)
            if not prizes:
                self.send_json({"code": 0, "msg": "奖池暂未配置，请稍后再试"})
                return
            total = sum(float(p['rate'] or 0) for p in prizes)
            import random as _random
            results = []
            for _ in range(times):
                pick = _random.uniform(0, total)
                acc = 0.0
                chosen = prizes[-1]
                for p in prizes:
                    acc += float(p['rate'] or 0)
                    if pick <= acc:
                        chosen = p
                        break
                results.append({
                    "id": chosen['id'], "name": chosen['name'],
                    "icon": chosen['icon'], "rate": chosen['rate'],
                    "price": chosen.get('price') or '0'
                })
                try:
                    _d = get_db()
                    _d.execute("UPDATE res_prize_pool SET stock = CAST(stock AS INTEGER) - 1 "
                               "WHERE id = ? AND CAST(stock AS INTEGER) > 0", (chosen['id'],))
                    _d.commit()
                    _d.close()
                except Exception:
                    pass
            self.send_json({"code": 1, "msg": "抽奖成功", "data": {"results": results, "times": times}})
            return

        # ===== 商品列表 =====
        if path == '/api/goods/goodsList':
            kw = qs.get('name', [''])[0].strip()
            one_category = qs.get('one_category', [''])[0]
            two_category = qs.get('two_category', ['0'])[0]
            sort = qs.get('sort', [''])[0]
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            where, args = 'WHERE is_deleted = 0 AND status = "1"', []
            if kw:
                where += ' AND name LIKE ?'
                args.append('%' + kw + '%')
            if one_category and one_category != '0':
                where += ' AND category_id = ?'
                args.append(one_category)
            order = 'id DESC'
            if sort == 'price_asc':
                order = 'CAST(price AS REAL) ASC'
            elif sort == 'price_desc':
                order = 'CAST(price AS REAL) DESC'
            db = get_db()
            total = db.execute('SELECT COUNT(*) FROM res_goods ' + where, args).fetchone()[0]
            rows = db.execute(
                'SELECT id, name, category_id, logo, price, unit, status, remark FROM res_goods ' + where +
                ' ORDER BY ' + order + ' LIMIT ? OFFSET ?',
                args + [limit, (page - 1) * limit]
            ).fetchall()
            db.close()
            list_data = [{
                "id": r['id'],
                "name": r['name'] or '',
                "title": r['name'] or '',
                "category_id": r['category_id'],
                "logo": r['logo'] or '/static/images/header_background.png',
                "image": r['logo'] or '/static/images/header_background.png',
                "price": r['price'] or '0',
                "line_price": str(round(float(r['price'] or 0) * 1.3, 2)),
                "unit": r['unit'] or '局',
                "sales": 0,
                "synopsis": r['remark'] or '',
                "remark": r['remark'] or ''
            } for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": list_data, "total": total}})
            return

        # ===== 商品详情 =====
        if path == '/api/goods/goodsMsg':
            gid = qs.get('id', [''])[0] or qs.get('goods_id', [''])[0]
            db = get_db()
            row = db.execute(
                'SELECT id, name, category_id, logo, price, unit, status, remark FROM res_goods WHERE id = ? AND is_deleted = 0',
                (gid,)
            ).fetchone()
            db.close()
            if not row:
                self.send_json({"code": 0, "msg": "商品不存在"})
                return
            data = {
                "id": row['id'],
                "name": row['name'] or '',
                "title": row['name'] or '',
                "category_id": row['category_id'],
                "logo": row['logo'] or '/static/images/header_background.png',
                "image": row['logo'] or '/static/images/header_background.png',
                "price": row['price'] or '0',
                "com_price": str(round(float(row['price'] or 0) * 1.3, 2)),
                "line_price": str(round(float(row['price'] or 0) * 1.3, 2)),
                "unit": row['unit'] or '局',
                "sales": 0,
                "synopsis": row['remark'] or '',
                "remark": row['remark'] or '',
                "content": row['remark'] or '',
                "images": [row['logo'] or '/static/images/header_background.png']
            }
            # 赠送转盘：在后台配置了商品ID时，下单可赠送1次转盘抽奖
            award_ids = [x.strip() for x in str(get_config('goods_award_ids', '')).split(',') if x.strip()]
            if str(row['id']) in award_ids:
                data['award'] = 1
                data['prizeList'] = [{"id": p['id'], "name": p['name'], "image": p['icon'] or '/static/ym/1.png'}
                                     for p in get_prize_pool_list() if str(p['status']) == '1']
            else:
                data['award'] = 0
                data['prizeList'] = []
            self.send_json({"code": 1, "msg": "成功", "data": data})
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

        # ===== 统一支付：创建支付单（所有需要支付的功能都走这里）=====
        if path == '/api/pay/create':
            u = get_user_by_token(self.get_token())
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            scene = str(body.get('scene') or '')
            target_id = str(body.get('target_id') or '')
            pay_type = str(body.get('pay_type') or 'wechat')
            title = str(body.get('title') or '')
            amount = 0.0
            if scene == 'recharge':
                amount = float(body.get('amount') or 0)
            elif scene == 'lottery':
                times = int(body.get('times') or 1)
                times = 1 if times < 1 else (10 if times > 10 else times)
                unit = float(get_config('lottery_price', '9.90') or 0)
                amount = round(unit * times, 2)
                target_id = str(times)
                title = title or '传世武器抽奖x%d' % times
            elif scene in ('bill', 'fine', 'order'):
                table = {'bill': 'res_sys_bill', 'fine': 'res_fine', 'order': 'orders'}[scene]
                dbq = get_db()
                r = dbq.execute('SELECT * FROM %s WHERE id = ?' % table, (target_id,)).fetchone()
                dbq.close()
                if not r:
                    self.send_json({"code": 0, "msg": "支付对象不存在"})
                    return
                amount = float(r['amount'] if scene != 'order' else (r['pay_price'] or r['price'] or 0) or 0)
                title = title or (PAY_SCENE_TITLE.get(scene) or '在线支付')
            elif scene == 'level':
                amount = float(get_config('thug_level_price_%s' % target_id, '0') or 0)
            elif scene == 'deposit':
                amount = float(body.get('amount') or 0)
                title = title or '保证金缴纳'
            elif scene == 'reward':
                amount = float(body.get('money') or body.get('amount') or 0)
                target_id = str(body.get('thug_id') or target_id or '0')
                title = title or '打赏打手'
            elif scene == 'gift':
                gid = body.get('gift_id')
                num = max(1, int(body.get('num') or 1))
                thug_id = str(body.get('thug_id') or '0')
                dbq = get_db()
                g = dbq.execute('SELECT * FROM res_gift WHERE id = ? AND is_deleted = 0', (gid,)).fetchone()
                dbq.close()
                if not g:
                    self.send_json({"code": 0, "msg": "礼物不存在或已下架"})
                    return
                amount = round(float(g['price'] or 0) * num, 2)
                target_id = '%s|%s|%d' % (thug_id, g['id'], num)
                title = title or ('赠送礼物(%s)' % g['name'])
            if amount <= 0:
                self.send_json({"code": 0, "msg": "支付金额异常"})
                return
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < amount:
                    self.send_json({"code": 0, "msg": "余额不足，请先充值"})
                    return
                after = round(bal - amount, 2)
                dbq = get_db()
                dbq.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                dbq.commit()
                dbq.close()
                add_balance_log(u['id'], -amount, bal, after, 2, PAY_SCENE_TITLE.get(scene, '消费'))
                ono = create_pay_order(u['id'], scene, target_id, amount, title, pay_type)
                dbq = get_db()
                dbq.execute("UPDATE res_pay_order SET status = '1', paytime = ? WHERE out_trade_no = ?", (now_ts(), ono))
                dbq.commit()
                dbq.close()
                apply_pay_scene(scene, u['id'], target_id, amount, ono, online=False)
                self.send_json({"code": 1, "msg": "支付成功", "data": {"paid": True, "out_trade_no": ono}})
                return
            epay = get_epay_config()
            if not (epay.get('apiurl') and epay.get('pid') and epay.get('key')):
                self.send_json({"code": 0, "msg": "在线支付未配置，请在后台填写商户信息或使用余额支付"})
                return
            ono = create_pay_order(u['id'], scene, target_id, amount, title, pay_type)
            url = gen_epay_url(epay, ono, title or PAY_SCENE_TITLE.get(scene, '在线支付'), amount, pay_type)
            self.send_json({"code": 1, "msg": "请前往支付",
                            "data": {"paid": False, "out_trade_no": ono, "url": url}})
            return

        # ===== 统一支付：查询支付结果（H5 从收银台返回后轮询）=====
        if path in ('/api/pay/query', '/api/payment/sync'):
            out_trade_no = str(body.get('out_trade_no') or qs.get('out_trade_no', [''])[0] or '')
            paid = False
            if out_trade_no:
                dbq = get_db()
                row = dbq.execute('SELECT status FROM res_pay_order WHERE out_trade_no = ?', (out_trade_no,)).fetchone()
                dbq.close()
                if row and str(row['status']) == '1':
                    paid = True
                else:
                    epay = get_epay_config()
                    if epay.get('apiurl') and epay.get('pid') and epay.get('key'):
                        if epay_query(epay, out_trade_no):
                            paid = mark_pay_paid(out_trade_no)
            self.send_json({"code": 1, "msg": "成功", "data": {"paid": bool(paid)}})
            return

        # ===== 彩虹易支付：异步通知（验签后发货）=====
        if path == '/api/pay/notify/epay':
            epay = get_epay_config()
            data = {}
            data.update({k: v[0] for k, v in qs.items()})
            if isinstance(body, dict):
                data.update({k: str(v) for k, v in body.items()})
            sign_ok = epay_verify_sign(epay, data)
            out_trade_no = str(data.get('out_trade_no') or '')
            handled = 0
            result = 'sign fail'
            if sign_ok and str(data.get('trade_status', '')) == 'TRADE_SUCCESS' and out_trade_no:
                handled = 1 if mark_pay_paid(out_trade_no, data.get('trade_no', '')) else 0
                result = 'ok' if handled else 'order not found'
            try:
                dbq = get_db()
                dbq.execute('INSERT INTO fa_pay_callback_log (channel, order_no, raw, sign_ok, handled, result, ip, create_time) '
                            'VALUES (?,?,?,?,?,?,?,?)',
                            ('epay', out_trade_no, json.dumps(data, ensure_ascii=False), 1 if sign_ok else 0,
                             handled, result, self.client_address[0], today_ts()))
                dbq.commit()
                dbq.close()
            except Exception:
                pass
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'success' if (sign_ok and handled) else b'fail')
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
            # 关联用户头像/昵称
            urow = db.execute('SELECT id, nickname, avatar FROM users WHERE id = ?', (t.get('user_id'),)).fetchone()
            # 已完成订单数与平均评分
            stat = db.execute(
                'SELECT COUNT(*) AS c, IFNULL(AVG(CAST(rating AS REAL)),0) AS g FROM orders '
                'WHERE thug_id = ? AND is_deleted = 0 AND status = 5', (t['id'],)).fetchone()
            db.close()
            images = [x for x in re.split(r'[,\n]', (t.get('images') or '')) if x.strip()]
            grade = t.get('grade') or ('%.1f' % float(stat['g'] or 0) if stat and stat['g'] else '5.0')
            self.send_json({
                "code": 1, "msg": "成功",
                "data": {
                    "id": t['id'],
                    "user_id": t['user_id'],
                    "name": t['name'] or (urow['nickname'] if urow else '打手'),
                    "avatar": t.get('avatar') or (urow['avatar'] if urow else '/static/images/icon.png'),
                    "avatar_frame": None,
                    "sex": t.get('sex') or '1',
                    "grade": grade,
                    "type": 3,
                    "synopsis": t.get('synopsis') or '',
                    "game_content": t.get('game_content') or '',
                    "images": images,
                    "audio": t.get('audio') or '',
                    "audio_time": t.get('audio_time') or 0,
                    "order_count": stat['c'] if stat else 0,
                    "status": t['status'],
                    "sh_status": t['sh_status'],
                    "is_customer_service": t['is_customer_service'],
                    "jd_status": str(t.get('jd_status') or ('1' if t['sh_status'] == 1 else '0')),
                    "frozen_money": "0.00",
                    "available_money": "0.00",
                    "total_money": "0.00",
                    "bond": "0.00",
                    "level": 1,
                    "level_name": get_config('level_name_1', '') or '',
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
            page = int(qs.get('page', ['1'])[0] or 1)
            limit = int(qs.get('limit', ['20'])[0] or 20)
            db = get_db()
            trow = db.execute('SELECT id FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            tid = trow['id'] if trow else -1
            where, args = 'WHERE thug_id = ?', [tid]
            if status not in ('', '10', None):
                where += ' AND status = ?'
                args.append(int(status))
            total = db.execute('SELECT COUNT(*) FROM orders ' + where, args).fetchone()[0]
            rows = db.execute('SELECT * FROM orders ' + where + ' ORDER BY createtime DESC LIMIT ? OFFSET ?',
                              args + [limit, (page - 1) * limit]).fetchall()
            db.close()
            list_data = [serialize_order(r) for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": list_data, "list": list_data, "total": total}})
            return

        # ===== 可接单列表 =====
        if path == '/api/thug/receivingOrders':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            db = get_db()
            rows = db.execute('SELECT * FROM orders WHERE status = 1 AND is_deleted = 0 ORDER BY createtime DESC LIMIT 20').fetchall()
            db.close()
            list_data = [serialize_order(r) for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": list_data, "list": list_data, "total": len(list_data)}})
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
            thug_id = str(body.get('thug_id') or '').strip()
            db = get_db()
            if thug_id:
                trow = db.execute('SELECT * FROM thug WHERE id = ?', (thug_id,)).fetchone()
            else:
                trow = db.execute('SELECT * FROM thug WHERE user_id = ?', (u['id'],)).fetchone()
            if not trow:
                db.close()
                self.send_json({"code": 0, "msg": "打手信息不存在"})
                return
            allowed = ('name', 'avatar', 'mobile', 'sex', 'synopsis', 'images', 'audio', 'jd_status', 'game_content')
            fields = {k: str(body.get(k)) for k in allowed if k in body and body.get(k) is not None}
            if fields:
                sets = ', '.join('%s = ?' % k for k in fields)
                db.execute('UPDATE thug SET %s WHERE id = ?' % sets, tuple(fields.values()) + (trow['id'],))
                db.commit()
            db.close()
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

        # ===== 客服端：踢打手（下架/驳回） =====
        if path == '/api/cs/thugKick':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"}); return
            db = get_db()
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if not cs:
                db.close(); self.send_json({"code": 0, "msg": "无客服权限"}); return
            thug_id = int(body.get('thug_id') or 0)
            reason = (body.get('reason') or '').strip()
            if not thug_id:
                db.close(); self.send_json({"code": 0, "msg": "参数错误"}); return
            t = db.execute('SELECT user_id FROM thug WHERE id = ?', (thug_id,)).fetchone()
            if not t:
                db.close(); self.send_json({"code": 0, "msg": "打手不存在"}); return
            db.execute('UPDATE thug SET sh_status = 2, status = 0 WHERE id = ?', (thug_id,))
            db.commit(); db.close()
            self.send_json({"code": 1, "msg": "已踢出打手"})
            return

        # ===== 客服端：罚款打手 =====
        if path == '/api/cs/thugFine':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"}); return
            db = get_db()
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if not cs:
                db.close(); self.send_json({"code": 0, "msg": "无客服权限"}); return
            thug_id = int(body.get('thug_id') or 0)
            amount = float(body.get('amount') or 0)
            reason = (body.get('reason') or '').strip()
            if not thug_id or amount <= 0:
                db.close(); self.send_json({"code": 0, "msg": "请填写正确的罚款金额"}); return
            t = db.execute('SELECT user_id, name FROM thug WHERE id = ?', (thug_id,)).fetchone()
            if not t:
                db.close(); self.send_json({"code": 0, "msg": "打手不存在"}); return
            target_uid = t['user_id']
            ur = db.execute('SELECT money FROM users WHERE id = ?', (target_uid,)).fetchone()
            before = float(ur['money'] or 0) if ur else 0.0
            after = max(0.0, before - amount)
            now = now_ts()
            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, target_uid))
            db.execute('INSERT INTO res_fine (name, amount, user_id, reason, status, createtime, update_time) VALUES (?,?,?,?,?,?,?)',
                       (t['name'] or ('打手#' + str(thug_id)), amount, target_uid, reason, 0, now, now))
            db.execute('INSERT INTO res_balance (user_id, amount, before, after, type, remark, createtime, update_time) VALUES (?,?,?,?,?,?,?,?)',
                       (target_uid, amount, before, after, 'fine', '客服罚款：' + reason, now, now))
            db.commit(); db.close()
            self.send_json({"code": 1, "msg": "罚款成功，已扣除余额 %.2f 元" % amount})
            return

        # ===== 客服端：商品分类列表（用于添加商品时选择分类） =====
        if path == '/api/cs/categoryList':
            db = get_db()
            rows = db.execute('SELECT id, name, icon, sort, status FROM res_goods_category WHERE is_deleted = 0 AND status = "1" ORDER BY CAST(sort AS INTEGER) ASC, id ASC').fetchall()
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": [dict(r) for r in rows]})
            return

        # ===== 客服端：添加商品 =====
        if path == '/api/cs/goodsAdd':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"}); return
            db = get_db()
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if not cs:
                db.close(); self.send_json({"code": 0, "msg": "无客服权限"}); return
            name = (body.get('name') or '').strip()
            category_id = int(body.get('category_id') or 0)
            price = float(body.get('price') or 0)
            logo = body.get('logo') or ''
            unit = (body.get('unit') or '次').strip()
            remark = (body.get('remark') or '').strip()
            if not name:
                db.close(); self.send_json({"code": 0, "msg": "请填写商品名称"}); return
            if not category_id:
                db.close(); self.send_json({"code": 0, "msg": "请选择商品分类"}); return
            now = now_ts()
            db.execute('INSERT INTO res_goods (name, category_id, logo, price, unit, status, remark, createtime, update_time) VALUES (?,?,?,?,?,?,?,?,?)',
                       (name, category_id, logo, price, unit, '1', remark, now, now))
            gid = db.execute('SELECT last_insert_rowid()').fetchone()[0]
            db.commit(); db.close()
            self.send_json({"code": 1, "msg": "商品发布成功", "data": {"id": gid}})
            return

        # ===== 客服端：添加分类 =====
        if path == '/api/cs/categoryAdd':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"}); return
            db = get_db()
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if not cs:
                db.close(); self.send_json({"code": 0, "msg": "无客服权限"}); return
            name = (body.get('name') or '').strip()
            icon = body.get('icon') or ''
            sort = (body.get('sort') or '0').strip()
            if not name:
                db.close(); self.send_json({"code": 0, "msg": "请填写分类名称"}); return
            now = now_ts()
            db.execute('INSERT INTO res_goods_category (name, icon, sort, status, createtime, update_time) VALUES (?,?,?,?,?,?)',
                       (name, icon, sort, '1', now, now))
            cid = db.execute('SELECT last_insert_rowid()').fetchone()[0]
            db.commit(); db.close()
            self.send_json({"code": 1, "msg": "分类添加成功", "data": {"id": cid}})
            return

        # ===== 客服端：发布公告 =====
        if path == '/api/cs/noticeAdd':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"}); return
            db = get_db()
            cs = db.execute('SELECT id FROM thug WHERE user_id = ? AND is_customer_service = 1 AND sh_status = 1', (u['id'],)).fetchone()
            if not cs:
                db.close(); self.send_json({"code": 0, "msg": "无客服权限"}); return
            title = (body.get('title') or '').strip()
            synopsis = (body.get('synopsis') or '').strip()
            content = (body.get('content') or '').strip()
            if not title:
                db.close(); self.send_json({"code": 0, "msg": "请填写公告标题"}); return
            now = now_ts()
            db.execute('INSERT INTO notices (title, synopsis, content, type, createtime) VALUES (?,?,?,?,?)',
                       (title, synopsis, content, '1', now))
            nid = db.execute('SELECT last_insert_rowid()').fetchone()[0]
            db.commit(); db.close()
            self.send_json({"code": 1, "msg": "公告发布成功", "data": {"id": nid}})
            return

        # ===== 前端装修配置（公开接口，H5 加载时拉取主题/背景）=====
        if path == '/api/frontend/decoration':
            cfg = get_decoration_config()
            self.send_json({"code": 1, "msg": "成功", "data": cfg})
            return

        # ===== 首页背景音乐配置（公开接口）=====
        if path == '/api/frontend/home_bgm':
            self.send_json({"code": 1, "msg": "成功", "data": get_home_bgm_config()})
            return

        # ===== 后台管理 API（自定义后台 UI 使用，与 H5 共用同一数据源）=====
        if path.startswith('/api/admin/'):
            self.handle_admin_api(path, qs, method, body, files)
            return

        # ===== 用户下单 =====
        if path == '/api/goods/orderAdd':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            gid = body.get('id')
            number = int(body.get('number', 1) or 1)
            game_id = (body.get('game_id') or '').strip()
            wz_id = (body.get('wz_id') or '').strip()
            game_qf = body.get('game_qf') or '手机端'
            remarks = body.get('remarks') or ''
            thug_id = int(body.get('thug_id', 0) or 0)
            if not game_id or not wz_id:
                self.send_json({"code": 0, "msg": "请填写游戏昵称和ID账号"})
                return
            if number < 1:
                number = 1
            db = get_db()
            grow = db.execute('SELECT * FROM res_goods WHERE id = ? AND is_deleted = 0', (gid,)).fetchone()
            if not grow:
                db.close()
                self.send_json({"code": 0, "msg": "商品不存在或已下架"})
                return
            price = float(grow['price'] or 0)
            if game_qf == '电脑端':
                price = round(price * 1.3, 2)
            pay_price = round(price * number, 2)
            award_ids = [x.strip() for x in str(get_config('goods_award_ids', '')).split(',') if x.strip()]
            award = '1' if str(grow['id']) in award_ids else ''
            ono = gen_order_no('D')
            now = now_ts()
            cur = db.execute(
                'INSERT INTO orders (order_no, user_id, thug_id, goods_id, title, logo, price, pay_price, number, '
                'game_id, wz_id, game_qf, remarks, synopsis, status, status_text, award, createtime) '
                'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                (ono, u['id'], thug_id, grow['id'], grow['name'], grow['logo'], price, pay_price, number,
                 game_id, wz_id, game_qf, remarks, grow['remark'], 0, ORDER_STATUS_TEXT[0], award, now))
            order_id = cur.lastrowid
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "下单成功",
                            "data": {"order_id": order_id, "order_no": ono, "award": award}})
            return

        # ===== 订单支付（余额 / 在线支付）=====
        if path == '/api/goods/orderPay':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            oid = body.get('id')
            pay_type = body.get('pay_type') or 'balance'
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            o = dict(row)
            if int(o.get('user_id') or 0) != int(u['id']):
                db.close()
                self.send_json({"code": 0, "msg": "无权操作该订单"})
                return
            if int(o.get('status') or 0) != 0:
                db.close()
                self.send_json({"code": 0, "msg": "订单状态不支持支付"})
                return
            amount = float(o.get('pay_price') or 0)
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < amount:
                    db.close()
                    self.send_json({"code": 0, "msg": "余额不足，请先充值"})
                    return
                after = round(bal - amount, 2)
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute('UPDATE orders SET status = 1, status_text = ?, paytime = ? WHERE id = ?',
                           (ORDER_STATUS_TEXT[1], now_ts(), oid))
                db.commit()
                db.close()
                add_balance_log(u['id'], -amount, bal, after, 2, '订单支付（%s）' % o.get('order_no'))
                self.send_json({"code": 1, "msg": "支付成功",
                                "data": {"out_trade_no": o.get('order_no'), "paid": True}})
                return
            # 在线支付（彩虹易支付）
            epay = get_epay_config()
            db.close()
            if epay.get('apiurl') and epay.get('pid') and epay.get('key'):
                pay_url = gen_epay_url(epay, o.get('order_no'), o.get('title') or '陪玩服务', amount)
                self.send_json({"code": 1, "msg": "请前往支付",
                                "data": {"out_trade_no": o.get('order_no'), "url": pay_url, "paid": False}})
                return
            self.send_json({"code": 0, "msg": "在线支付暂未开通，请使用余额支付"})
            return

        # ===== 用户取消订单（含已支付退款到余额）=====
        if path == '/api/goods/orderCancel':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            oid = body.get('id')
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            o = dict(row)
            if int(o.get('user_id') or 0) != int(u['id']):
                db.close()
                self.send_json({"code": 0, "msg": "无权操作该订单"})
                return
            st = int(o.get('status') or 0)
            if st not in (0, 1, 2):
                db.close()
                self.send_json({"code": 0, "msg": "当前状态不可取消"})
                return
            if st >= 1:
                amount = float(o.get('pay_price') or 0)
                bal = float(u.get('money') or 0)
                after = round(bal + amount, 2)
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute('UPDATE orders SET status = 7, status_text = ?, refund_method = ?, refund_time = ? WHERE id = ?',
                           (ORDER_STATUS_TEXT[7], 'balance', now_ts(), oid))
                db.commit()
                db.close()
                add_balance_log(u['id'], amount, bal, after, 1, '订单退款（%s）' % o.get('order_no'))
            else:
                db.execute('UPDATE orders SET status = 8, status_text = ? WHERE id = ?', (ORDER_STATUS_TEXT[8], oid))
                db.commit()
                db.close()
            self.send_json({"code": 1, "msg": "已取消订单"})
            return

        # ===== 用户确认结单（带评分）=====
        if path == '/api/goods/orderConfirm':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            oid = body.get('id')
            grade = int(body.get('grade', 0) or 0)
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            o = dict(row)
            if int(o.get('user_id') or 0) != int(u['id']):
                db.close()
                self.send_json({"code": 0, "msg": "无权操作该订单"})
                return
            if int(o.get('status') or 0) != 4:
                db.close()
                self.send_json({"code": 0, "msg": "当前状态不可结单"})
                return
            db.execute('UPDATE orders SET status = 5, status_text = ?, completetime = COALESCE(completetime, ?), '
                       'is_rated = 1, rating = ? WHERE id = ?',
                       (ORDER_STATUS_TEXT[5], now_ts(), grade, oid))
            # 打手佣金结算（按后台佣金比例，默认 80%）
            if o.get('thug_id') and float(o.get('pay_price') or 0) > 0:
                rate = float(get_config('commission_rate', '0.8'))
                income = round(float(o.get('pay_price') or 0) * rate, 2)
                trow = db.execute('SELECT user_id FROM thug WHERE id = ?', (o['thug_id'],)).fetchone()
                if trow and trow['user_id']:
                    turow = db.execute('SELECT money FROM users WHERE id = ?', (trow['user_id'],)).fetchone()
                    tb = float(turow['money'] or 0) if turow else 0.0
                    ta = round(tb + income, 2)
                    db.execute('UPDATE users SET money = ? WHERE id = ?', (ta, trow['user_id']))
                    add_balance_log(trow['user_id'], income, tb, ta, 1, '订单收益（%s）' % o.get('order_no'))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "已确认结单"})
            return

        # ===== 打手接单 =====
        if path == '/api/thug/orderConnect':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            t = get_thug_by_user(u['id'])
            if not t:
                self.send_json({"code": 0, "msg": "请先入驻成为打手"})
                return
            oid = body.get('id')
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            o = dict(row)
            if int(o.get('status') or 0) != 1:
                db.close()
                self.send_json({"code": 0, "msg": "该订单已被接单"})
                return
            db.execute('UPDATE orders SET thug_id = ?, status = 2, status_text = ?, connecttime = ? WHERE id = ?',
                       (t['id'], ORDER_STATUS_TEXT[2], now_ts(), oid))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "接单成功"})
            return

        # ===== 打手开始服务 =====
        if path == '/api/thug/orderStart':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            oid = body.get('id')
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            if int(row['status'] or 0) != 2:
                db.close()
                self.send_json({"code": 0, "msg": "当前状态不可开始服务"})
                return
            db.execute('UPDATE orders SET status = 3, status_text = ? WHERE id = ?', (ORDER_STATUS_TEXT[3], oid))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "已开始服务"})
            return

        # ===== 打手上传凭证并结单 =====
        if path == '/api/thug/orderFinish':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            oid = body.get('id')
            images = body.get('image') or body.get('images') or ''
            if isinstance(images, list):
                images = ','.join([str(x) for x in images])
            remark = body.get('thug_remarks') or body.get('remarks') or ''
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            if int(row['status'] or 0) != 3:
                db.close()
                self.send_json({"code": 0, "msg": "当前状态不可结单"})
                return
            db.execute('UPDATE orders SET status = 4, status_text = ?, image = ?, thug_remarks = ?, completetime = ? WHERE id = ?',
                       (ORDER_STATUS_TEXT[4], images, remark, now_ts(), oid))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "已提交，等待用户确认"})
            return

        # ===== 打手取消订单 =====
        if path == '/api/thug/orderCancel':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            oid = body.get('id')
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            if int(row['status'] or 0) not in (1, 2):
                db.close()
                self.send_json({"code": 0, "msg": "当前状态不可取消"})
                return
            db.execute('UPDATE orders SET thug_id = 0, status = 1, status_text = ?, connecttime = 0 WHERE id = ?',
                       (ORDER_STATUS_TEXT[1], oid))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "已释放订单"})
            return

        # ===== 订单转盘：奖池列表 =====
        if path == '/api/goods/jackpotList':
            data = [{"id": p['id'], "name": p['name'], "image": p['icon'] or '/static/ym/1.png'}
                    for p in get_prize_pool_list() if str(p['status']) == '1']
            self.send_json({"code": 1, "msg": "成功", "data": data})
            return

        # ===== 订单转盘：抽奖（按后台爆率加权）=====
        if path == '/api/goods/orderSmoke':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            oid = body.get('id')
            db = get_db()
            row = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            if int(row['jackpot_id'] or 0) > 0:
                db.close()
                self.send_json({"code": 0, "msg": "该订单已抽过奖"})
                return
            prize = pick_prize_weighted()
            if not prize:
                db.close()
                self.send_json({"code": 0, "msg": "奖池暂未配置，请稍后再试"})
                return
            db.execute('UPDATE orders SET jackpot_id = ?, award = ? WHERE id = ?', (prize['id'], '1', oid))
            if str(prize.get('stock') or '0').isdigit() and int(prize['stock'] or 0) > 0:
                db.execute('UPDATE res_prize_pool SET stock = CAST(stock AS INTEGER) - 1 WHERE id = ?', (prize['id'],))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "抽奖成功", "data": prize['id']})
            return

        # ===== 指定打手列表（用户下单页选择）=====
        if path == '/api/goods/thugAppoint':
            page = max(1, int(qs.get('pages', qs.get('page', ['1']))[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            db = get_db()
            rows = db.execute(
                'SELECT t.id, t.name, u.avatar FROM thug t LEFT JOIN users u ON u.id = t.user_id '
                'WHERE t.is_deleted = 0 ORDER BY t.id DESC').fetchall()
            db.close()
            all_thugs = [{"id": r['id'], "name": r['name'] or '打手',
                          "avatar": r['avatar'] or '/static/images/icon.png'} for r in rows]
            start = (page - 1) * limit
            self.send_json({"code": 1, "msg": "成功",
                            "data": {"data": all_thugs[start:start + limit], "total": len(all_thugs)}})
            return

        # ===== 打手端：指定打手列表 =====
        if path == '/api/thug/thugAppoint':
            db = get_db()
            rows = db.execute(
                'SELECT t.id, t.name, u.avatar FROM thug t LEFT JOIN users u ON u.id = t.user_id '
                'WHERE t.is_deleted = 0 ORDER BY t.id DESC').fetchall()
            db.close()
            all_thugs = [{"id": r['id'], "name": r['name'] or '打手',
                          "avatar": r['avatar'] or '/static/images/icon.png'} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": all_thugs, "total": len(all_thugs)}})
            return

        # ===== 余额明细 =====
        if path == '/api/user/balanceLog':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            page = max(1, int(qs.get('page', qs.get('pages', ['1']))[0] or 1))
            limit = max(1, int(qs.get('limit', ['15'])[0] or 15))
            db = get_db()
            total = db.execute('SELECT COUNT(*) FROM res_balance WHERE user_id = ? AND is_deleted = 0',
                               (str(u['id']),)).fetchone()[0]
            rows = db.execute('SELECT * FROM res_balance WHERE user_id = ? AND is_deleted = 0 '
                              'ORDER BY id DESC LIMIT ? OFFSET ?',
                              (str(u['id']), limit, (page - 1) * limit)).fetchall()
            db.close()
            lst = [{"id": r['id'], "memo": r['remark'] or '余额变动', "remark": r['remark'] or '',
                    "balance": r['amount'], "before": r['before'], "after": r['after'],
                    "type": r['type'], "createtime_text": fmt_time(r['createtime'])} for r in rows]
            self.send_json({"code": 1, "msg": "成功",
                            "data": {"data": lst, "list": lst, "total": total,
                                     "last_page": (total + limit - 1) // limit if limit else 1}})
            return

        # ===== 余额记录（通用）=====
        if path == '/api/goods/moneyList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['15'])[0] or 15))
            db = get_db()
            rows = db.execute('SELECT * FROM res_balance WHERE user_id = ? AND is_deleted = 0 '
                              'ORDER BY id DESC LIMIT ? OFFSET ?',
                              (str(u['id']), limit, (page - 1) * limit)).fetchall()
            db.close()
            lst = [{"id": r['id'], "memo": r['remark'] or '余额变动', "balance": r['amount'],
                    "createtime_text": fmt_time(r['createtime'])} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": lst, "list": lst, "total": len(lst)}})
            return

        # ===== 提现记录 =====
        if path == '/api/goods/withdrawalList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            db = get_db()
            t = get_thug_by_user(u['id'])
            if t:
                rows = db.execute('SELECT * FROM fa_thug_withdraw WHERE thug_id = ? AND is_deleted = 0 '
                                  'ORDER BY id DESC LIMIT 50', (t['id'],)).fetchall()
                db.close()
                lst = [{"id": r['id'], "money": r['amount'], "amount": r['amount'],
                        "real_amount": r['real_amount'], "account": r['account'],
                        "status": r['status'], "status_text": {0: '待审核', 1: '审核中', 2: '已驳回', 3: '已到账'}.get(r['status'], '待审核'),
                        "createtime_text": fmt_time(r['create_time'])} for r in rows]
            else:
                rows = db.execute('SELECT * FROM res_withdraw WHERE user_id = ? AND is_deleted = 0 '
                                  'ORDER BY id DESC LIMIT 50', (str(u['id']),)).fetchall()
                db.close()
                lst = [{"id": r['id'], "money": r['amount'], "amount": r['amount'],
                        "account": r['account'], "status": r['status'],
                        "status_text": {'0': '待审核', '1': '已打款', '2': '已驳回'}.get(str(r['status']), '待审核'),
                        "createtime_text": fmt_time(r['createtime'])} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {"data": lst, "list": lst, "total": len(lst)}})
            return

        # ===== 用户申请提现 =====
        if path == '/api/user/userApply':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            wcfg = get_withdraw_config()
            if wcfg['withdraw_enabled'] != '1':
                self.send_json({"code": 0, "msg": "提现功能未开启"})
                return
            try:
                amount = float(body.get('money') or 0)
            except Exception:
                amount = 0
            if amount <= 0:
                self.send_json({"code": 0, "msg": "请输入正确的提现金额"})
                return
            if amount < float(wcfg['withdraw_min'] or 0):
                self.send_json({"code": 0, "msg": "最低提现金额为 ¥%s" % wcfg['withdraw_min']})
                return
            bal = float(u.get('money') or 0)
            if amount > bal:
                self.send_json({"code": 0, "msg": "可提现余额不足"})
                return
            account = (body.get('account') or '').strip()
            if not account:
                self.send_json({"code": 0, "msg": "请先设置提现收款信息"})
                return
            fee = round(amount * float(wcfg['withdraw_fee_rate'] or 0) + float(wcfg['withdraw_fee_fixed'] or 0), 2)
            after = round(bal - amount, 2)
            db = get_db()
            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
            db.execute('INSERT INTO res_withdraw (user_id, amount, account, status, remark, createtime, update_time) '
                       'VALUES (?,?,?,?,?,?,?)',
                       (str(u['id']), str(amount), account, '0',
                        '通道:%s 姓名:%s 手续费:%.2f' % (body.get('channel') or '', body.get('name') or '', fee),
                        now_ts(), now_ts()))
            db.commit()
            db.close()
            add_balance_log(u['id'], -amount, bal, after, 2, '申请提现')
            self.send_json({"code": 1, "msg": "提现申请已提交，请等待审核"})
            return

        # ===== 打手申请提现 =====
        if path == '/api/thug/thugApply':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            t = get_thug_by_user(u['id'])
            if not t:
                self.send_json({"code": 0, "msg": "请先入驻成为打手"})
                return
            wcfg = get_withdraw_config()
            if wcfg['withdraw_enabled'] != '1':
                self.send_json({"code": 0, "msg": "提现功能未开启"})
                return
            try:
                amount = float(body.get('money') or 0)
            except Exception:
                amount = 0
            if amount <= 0:
                self.send_json({"code": 0, "msg": "请输入正确的提现金额"})
                return
            bal = float(u.get('money') or 0)
            if amount > bal:
                self.send_json({"code": 0, "msg": "可提现余额不足"})
                return
            account = (body.get('account') or '').strip()
            if not account:
                self.send_json({"code": 0, "msg": "请先设置提现收款信息"})
                return
            fee = round(amount * float(wcfg['withdraw_fee_rate'] or 0) + float(wcfg['withdraw_fee_fixed'] or 0), 2)
            real = round(amount - fee, 2)
            after = round(bal - amount, 2)
            db = get_db()
            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
            db.execute('INSERT INTO fa_thug_withdraw (thug_id, thug_name, withdraw_no, amount, fee, real_amount, '
                       'account_type, account, apply_time, status, remark, create_time, update_time) '
                       'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                       (t['id'], t['name'], gen_order_no('W'), str(amount), str(fee), str(real),
                        body.get('channel') or '', account, now_ts(), 0,
                        '姓名:%s' % (body.get('name') or ''), now_ts(), now_ts()))
            db.commit()
            db.close()
            add_balance_log(u['id'], -amount, bal, after, 2, '申请提现')
            self.send_json({"code": 1, "msg": "提现申请已提交，请等待审核"})
            return

        # ===== 余额充值 =====
        if path == '/api/user/balanceRecharge':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            try:
                amount = float(body.get('money') or body.get('amount') or 0)
            except Exception:
                amount = 0
            if amount <= 0:
                self.send_json({"code": 0, "msg": "请输入正确的充值金额"})
                return
            db = get_db()
            ono = gen_order_no('R')
            db.execute('INSERT INTO res_recharge_order (order_no, user_id, amount, method, status, createtime, update_time) '
                       'VALUES (?,?,?,?,?,?,?)',
                       (ono, str(u['id']), str(amount), body.get('pay_type') or 'balance', '0', now_ts(), now_ts()))
            db.commit()
            db.close()
            epay = get_epay_config()
            if epay.get('apiurl') and epay.get('pid') and epay.get('key'):
                url = gen_epay_url(epay, ono, '账户充值', amount)
                self.send_json({"code": 1, "msg": "请前往支付", "data": {"out_trade_no": ono, "url": url}})
                return
            # 未配置在线支付：直接入账，便于测试
            db = get_db()
            bal = float(u.get('money') or 0)
            after = round(bal + amount, 2)
            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
            db.execute("UPDATE res_recharge_order SET status = '1', update_time = ? WHERE order_no = ?", (now_ts(), ono))
            db.commit()
            db.close()
            add_balance_log(u['id'], amount, bal, after, 1, '账户充值')
            self.send_json({"code": 1, "msg": "充值成功", "data": {"out_trade_no": ono, "paid": True}})
            return

        # ===== 我的团队 =====
        if path == '/api/user/userShare':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            db = get_db()
            total = db.execute('SELECT COUNT(*) FROM users WHERE pid = ? AND is_deleted = 0', (u['id'],)).fetchone()[0]
            today = int(datetime.now().replace(hour=0, minute=0, second=0, microsecond=0).timestamp())
            today_cnt = db.execute('SELECT COUNT(*) FROM users WHERE pid = ? AND is_deleted = 0 AND createtime >= ?',
                                   (u['id'], today)).fetchone()[0]
            rows = db.execute('SELECT id, nickname, avatar, createtime FROM users WHERE pid = ? AND is_deleted = 0 '
                              'ORDER BY id DESC LIMIT 50', (u['id'],)).fetchall()
            db.close()
            lst = [{"id": r['id'], "nickname": r['nickname'] or '用户', "avatar": r['avatar'] or '/static/eon.png',
                    "createtime_text": fmt_time(r['createtime'])} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {
                "total": total, "today": today_cnt, "invite_count": total,
                "list": lst, "data": lst,
                "invite_code": str(u['id'])
            }})
            return

        # ===== 用户消费排行榜 =====
        if path == '/api/user/userRanking':
            period = qs.get('type', ['1'])[0]
            db = get_db()
            rows = db.execute(
                'SELECT u.id, u.nickname, u.avatar, IFNULL(SUM(CAST(o.pay_price AS REAL)),0) AS total '
                'FROM users u LEFT JOIN orders o ON o.user_id = u.id AND o.is_deleted = 0 AND o.status >= 1 '
                'GROUP BY u.id ORDER BY total DESC LIMIT 50').fetchall()
            db.close()
            lst = [{"id": r['id'], "nickname": r['nickname'] or '用户',
                    "avatar": r['avatar'] or '/static/eon.png', "total": round(r['total'] or 0, 2),
                    "rank": i + 1} for i, r in enumerate(rows)]
            self.send_json({"code": 1, "msg": "成功", "data": {"list": lst, "data": lst}})
            return

        # ===== 接单/流水排行 =====
        if path == '/api/thug/thugStream':
            db = get_db()
            rows = db.execute(
                'SELECT t.id, t.name, u.avatar, COUNT(o.id) AS cnt, IFNULL(SUM(CAST(o.pay_price AS REAL)),0) AS total '
                'FROM thug t LEFT JOIN users u ON u.id = t.user_id '
                'LEFT JOIN orders o ON o.thug_id = t.id AND o.is_deleted = 0 AND o.status >= 4 '
                'WHERE t.is_deleted = 0 GROUP BY t.id ORDER BY total DESC LIMIT 50').fetchall()
            db.close()
            lst = [{"id": r['id'], "name": r['name'] or '打手', "avatar": r['avatar'] or '/static/eon.png',
                    "count": r['cnt'] or 0, "total": round(r['total'] or 0, 2), "rank": i + 1}
                   for i, r in enumerate(rows)]
            self.send_json({"code": 1, "msg": "成功", "data": {"list": lst, "data": lst}})
            return

        # ===== 商品评论列表 =====
        if path == '/api/goods/commentList':
            gid = qs.get('goods_id', [''])[0]
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            db = get_db()
            where = 'WHERE o.is_rated = 1 AND o.is_deleted = 0'
            args = []
            if gid:
                where += ' AND o.goods_id = ?'
                args.append(gid)
            total = db.execute('SELECT COUNT(*) FROM orders o ' + where, args).fetchone()[0]
            avg_row = db.execute('SELECT AVG(rating) FROM orders o ' + where, args).fetchone()[0]
            rows = db.execute(
                'SELECT o.id, o.rating, o.thug_remarks, o.createtime, u.nickname, u.avatar '
                'FROM orders o LEFT JOIN users u ON u.id = o.user_id ' + where +
                ' ORDER BY o.id DESC LIMIT ? OFFSET ?', args + [limit, (page - 1) * limit]).fetchall()
            db.close()
            lst = [{"id": r['id'], "rating": r['rating'] or 5, "content": r['thug_remarks'] or '服务很好，赞！',
                    "nickname": r['nickname'] or '匿名用户', "avatar": r['avatar'] or '/static/icon/default_avatar.png',
                    "images": [], "time": fmt_time(r['createtime'])} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": {
                "total": total, "avg_rating": round(avg_row or 0, 1), "list": lst,
                "current_page": page, "last_page": (total + limit - 1) // limit if limit else 1}})
            return

        # ===== 会员等级列表 =====
        if path == '/api/user/getLevelList':
            identity = qs.get('identity', ['user'])[0]
            db = get_db()
            rows = db.execute('SELECT * FROM res_level WHERE is_deleted = 0 ORDER BY CAST(need_score AS INTEGER) ASC').fetchall()
            db.close()
            lst = [{"id": r['id'], "name": r['name'] or '', "icon": r['icon'] or '',
                    "need_score": r['need_score'], "discount": r['discount'], "remark": r['remark'] or '',
                    "price": get_config('thug_level_price_%s' % r['id'], '0') if identity == 'thug' else '0',
                    "days": 30} for r in rows]
            self.send_json({"code": 1, "msg": "成功", "data": lst})
            return

        # ===== 用户成长值与下一等级 =====
        if path == '/api/user/scoreLevel':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            db = get_db()
            rows = db.execute('SELECT * FROM res_level WHERE is_deleted = 0 ORDER BY CAST(need_score AS INTEGER) ASC').fetchall()
            db.close()
            score = int(u.get('score') or 0)
            q_level = None
            h_level = None
            for r in rows:
                if score >= int(r['need_score'] or 0):
                    q_level = dict(r)
                elif h_level is None:
                    h_level = dict(r)
            def _lv(x):
                return None if not x else {"id": x['id'], "name": x['name'], "score": x['need_score'], "discount": x['discount']}
            self.send_json({"code": 1, "msg": "成功", "data": {
                "score": score, "q_level": _lv(q_level), "h_level": _lv(h_level)}})
            return

        # ===== 会员等级购买下单 =====
        if path == '/api/user/levelOrder':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            lid = body.get('level_id')
            db = get_db()
            row = db.execute('SELECT * FROM res_level WHERE id = ? AND is_deleted = 0', (lid,)).fetchone()
            db.close()
            if not row:
                self.send_json({"code": 0, "msg": "等级不存在"})
                return
            price = float(get_config('thug_level_price_%s' % row['id'], '0') or 0)
            epay = get_epay_config()
            if epay.get('apiurl') and epay.get('pid') and epay.get('key') and price > 0:
                url = gen_epay_url(epay, gen_order_no('L'), '开通会员-%s' % row['name'], price)
                self.send_json({"code": 1, "msg": "请前往支付", "data": {"url": url}})
                return
            # 无在线支付：直接开通（记录等级）
            db = get_db()
            db.execute('UPDATE users SET level = ? WHERE id = ?', (row['id'], u['id']))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "开通成功", "data": {"url": ""}})
            return

        # ===== 会员等级申请 =====
        if path == '/api/user/levelApply':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录"})
                return
            self.send_json({"code": 1, "msg": "申请已提交，请等待审核"})
            return

        # ===== 个人信息修改（用户端） =====
        if path == '/api/user/userEdit':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            allowed = ('nickname', 'avatar', 'cs_avatar', 'mobile', 'sex', 'signature')
            fields = {k: str(body.get(k)) for k in allowed if k in body and body.get(k) is not None}
            if fields:
                db = get_db()
                sets = ', '.join('%s = ?' % k for k in fields)
                db.execute('UPDATE users SET %s WHERE id = ?' % sets, tuple(fields.values()) + (u['id'],))
                db.commit()
                db.close()
            self.send_json({"code": 1, "msg": "修改成功"})
            return

        def _online_pay(prefix, name, amount, scene='', target_id='', user_id='', pay_type='wechat'):
            """在线支付：已配置易支付则创建统一支付单并返回收银台地址"""
            epay = get_epay_config()
            if epay.get('apiurl') and epay.get('pid') and epay.get('key'):
                if scene:
                    ono = create_pay_order(user_id, scene, target_id, amount, name, pay_type)
                else:
                    ono = gen_order_no(prefix)
                return {"code": 1, "msg": "请前往支付",
                        "data": {"out_trade_no": ono,
                                 "url": gen_epay_url(epay, ono, name, amount, pay_type), "paid": False}}
            return {"code": 0, "msg": "在线支付未开通，请使用余额支付"}

        # ===== 打手保证金充值 =====
        if path == '/api/thug/bond':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            money = float(body.get('money', 0) or 0)
            pay_type = body.get('pay_type') or 'balance'
            if money <= 0:
                self.send_json({"code": 0, "msg": "请输入充值金额"})
                return
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < money:
                    self.send_json({"code": 0, "msg": "余额不足，请先充值"})
                    return
                after = round(bal - money, 2)
                db = get_db()
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute('INSERT INTO res_deposit (user_id, amount, status, remark, createtime) VALUES (?,?,?,?,?)',
                           (str(u['id']), str(money), '0', '保证金缴纳', now_ts()))
                db.commit()
                db.close()
                add_balance_log(u['id'], -money, bal, after, 2, '保证金缴纳')
                self.send_json({"code": 1, "msg": "保证金充值成功"})
                return
            self.send_json(_online_pay('B', '保证金充值', money))
            return

        # ===== 罚款 =====
        if path == '/api/fine/fineList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            st = qs.get('status', ['-1'])[0]
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            db = get_db()
            where = "is_deleted = 0 AND user_id = ?"
            args = [str(u['id'])]
            if st not in ('-1', '', 'None'):
                where += " AND status = ?"
                args.append(str(st))
            rows = db.execute('SELECT * FROM res_fine WHERE %s ORDER BY id DESC' % where, tuple(args)).fetchall()
            unpaid = db.execute("SELECT IFNULL(SUM(CAST(amount AS REAL)),0) FROM res_fine "
                                "WHERE is_deleted = 0 AND user_id = ? AND status = '0'", (str(u['id']),)).fetchone()[0]
            db.close()
            data = [{"id": r['id'], "name": r['name'], "amount": r['amount'], "reason": r['reason'],
                     "status": int(r['status'] or 0), "createtime": fmt_time(r['createtime'])} for r in rows]
            total = len(data)
            start = (page - 1) * limit
            self.send_json({"code": 1, "msg": "成功",
                            "data": {"unpaid_total": float(unpaid or 0), "list": {"data": data[start:start + limit], "total": total}}})
            return

        if path == '/api/fine/fineCheck':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            db = get_db()
            row = db.execute("SELECT COUNT(*) AS c, IFNULL(SUM(CAST(amount AS REAL)),0) AS s FROM res_fine "
                             "WHERE is_deleted = 0 AND user_id = ? AND status = '0'", (str(u['id']),)).fetchone()
            db.close()
            cnt = int(row['c'] or 0)
            self.send_json({"code": 1, "msg": "成功",
                            "data": {"has_unpaid": cnt > 0, "count": cnt, "amount": float(row['s'] or 0)}})
            return

        if path == '/api/fine/finePay':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            fid = body.get('id')
            pay_type = body.get('pay_type') or 'balance'
            db = get_db()
            row = db.execute('SELECT * FROM res_fine WHERE id = ? AND is_deleted = 0', (fid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "罚单不存在"})
                return
            amount = float(row['amount'] or 0)
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < amount:
                    db.close()
                    self.send_json({"code": 0, "msg": "余额不足，请先充值"})
                    return
                after = round(bal - amount, 2)
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute("UPDATE res_fine SET status = '1', update_time = ? WHERE id = ?", (now_ts(), fid))
                db.commit()
                db.close()
                add_balance_log(u['id'], -amount, bal, after, 2, '缴纳罚款')
                self.send_json({"code": 1, "msg": "缴纳成功"})
                return
            db.close()
            self.send_json(_online_pay('F', '罚款缴纳', amount, scene='fine', target_id=row['id'], user_id=u['id'], pay_type=pay_type))
            return

        if path == '/api/fine/fineCreate':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            uid = str(body.get('user_id') or u['id'])
            db = get_db()
            db.execute('INSERT INTO res_fine (user_id, name, amount, reason, status, createtime) VALUES (?,?,?,?,?,?)',
                       (uid, str(body.get('name') or '违规罚款'), str(body.get('amount') or '0'),
                        str(body.get('reason') or ''), '0', now_ts()))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "罚单已创建"})
            return

        if path == '/api/fine/fineRevoke':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            db = get_db()
            db.execute("UPDATE res_fine SET status = '2', update_time = ? WHERE id = ?", (now_ts(), body.get('id')))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "已撤销"})
            return

        # ===== 系统缴费 =====
        if path in ('/api/bill/billList', '/api/bill/billRecordList'):
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            today = time.strftime('%Y-%m-%d')
            db = get_db()
            # 到期未缴自动置为已逾期
            db.execute("UPDATE res_sys_bill SET status = '2' WHERE is_deleted = 0 AND status = '0' "
                       "AND IFNULL(deadline,'') != '' AND deadline < ?", (today,))
            db.commit()
            where = "is_deleted = 0 AND user_id = ?"
            args = [str(u['id'])]
            if path == '/api/bill/billRecordList':
                where += " AND status = '1'"
            else:
                where += " AND status != '1'"
                st = qs.get('status', ['-1'])[0]
                if st not in ('-1', '', 'None'):
                    where += " AND status = ?"
                    args.append(str(st))
            rows = db.execute('SELECT * FROM res_sys_bill WHERE %s ORDER BY id DESC' % where, tuple(args)).fetchall()
            db.close()
            data = [{"id": r['id'], "bill_no": r['bill_no'] or '', "project": r['project'] or '',
                     "reason": r['reason'] or '', "remark": r['remark'] or '',
                     "amount": float(r['amount'] or 0), "deadline": r['deadline'] or '',
                     "status": int(r['status'] or 0),
                     "createtime": fmt_time(r['createtime'])} for r in rows]
            start = (page - 1) * limit
            self.send_json({"code": 1, "msg": "成功",
                            "data": {"list": {"data": data[start:start + limit], "total": len(data)}}})
            return

        if path in ('/api/bill/billPay', '/api/bill/billPayFor'):
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            bill_id = body.get('id')
            bill_no = body.get('bill_no')
            pay_type = body.get('pay_type') or 'balance'
            db = get_db()
            if bill_id:
                row = db.execute('SELECT * FROM res_sys_bill WHERE id = ? AND is_deleted = 0', (bill_id,)).fetchone()
            else:
                row = db.execute('SELECT * FROM res_sys_bill WHERE bill_no = ? AND is_deleted = 0', (bill_no,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "缴费单不存在"})
                return
            if str(row['status']) == '1':
                db.close()
                self.send_json({"code": 0, "msg": "该缴费单已缴纳"})
                return
            amount = float(row['amount'] or 0)
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < amount:
                    db.close()
                    self.send_json({"code": 0, "msg": "余额不足，请先充值"})
                    return
                after = round(bal - amount, 2)
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute("UPDATE res_sys_bill SET status = '1', update_time = ? WHERE id = ?", (now_ts(), row['id']))
                db.commit()
                db.close()
                add_balance_log(u['id'], -amount, bal, after, 2, '系统缴费')
                self.send_json({"code": 1, "msg": "缴纳成功"})
                return
            db.close()
            self.send_json(_online_pay('S', '系统缴费', amount, scene='bill', target_id=row['id'], user_id=u['id'], pay_type=pay_type))
            return

        # ===== 礼物墙 =====
        if path == '/api/giftwall/getThugGifts':
            db = get_db()
            rows = db.execute("SELECT id, name, icon, price FROM res_gift WHERE is_deleted = 0 AND status = '1' "
                              "ORDER BY CAST(sort AS INTEGER) ASC, id ASC").fetchall()
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": [
                {"id": r['id'], "name": r['name'], "icon": r['icon'] or '/static/eon.png', "price": float(r['price'] or 0)}
                for r in rows]})
            return

        if path == '/api/giftwall/getThugGiftStats':
            tid = qs.get('thug_id', [None])[0]
            db = get_db()
            rows = db.execute('SELECT gift_id, gift_name, gift_icon, SUM(num) AS cnt FROM fa_gift_record '
                              'WHERE thug_id = ? AND status = 1 GROUP BY gift_id ORDER BY cnt DESC', (tid,)).fetchall()
            wall = []
            for r in rows:
                f = db.execute('SELECT u.nickname, u.avatar FROM fa_gift_record g LEFT JOIN users u ON u.id = g.user_id '
                               'WHERE g.thug_id = ? AND g.gift_id = ? ORDER BY g.id ASC LIMIT 1',
                               (tid, r['gift_id'])).fetchone()
                wall.append({"gift_id": r['gift_id'], "gift_name": r['gift_name'],
                             "gift_icon": r['gift_icon'] or '/static/eon.png', "count": int(r['cnt'] or 0),
                             "first_sender_name": (f['nickname'] if f else '') or '匿名',
                             "first_sender_avatar": (f['avatar'] if f else '') or '/static/images/default.png'})
            db.close()
            self.send_json({"code": 1, "msg": "成功",
                            "data": {"gift_wall": wall, "total_gifts": sum(x['count'] for x in wall)}})
            return

        if path == '/api/giftwall/getThugGiftRecords':
            tid = qs.get('thug_id', [None])[0]
            db = get_db()
            rows = db.execute('SELECT * FROM fa_gift_record WHERE thug_id = ? AND status = 1 ORDER BY id DESC LIMIT 50',
                              (tid,)).fetchall()
            db.close()
            self.send_json({"code": 1, "msg": "成功", "data": {"list": [
                {"id": r['id'], "user_id": r['user_id'], "gift_id": r['gift_id'], "gift_name": r['gift_name'],
                 "gift_icon": r['gift_icon'], "num": r['num'], "amount": r['amount'], "createtime": fmt_time(r['create_time'])}
                for r in rows], "total": len(rows)}})
            return

        if path == '/api/giftwall/sendGift':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            tid = int(body.get('thug_id', 0) or 0)
            gid = body.get('gift_id')
            num = max(1, int(body.get('num', 1) or 1))
            pay_type = body.get('pay_type') or 'balance'
            db = get_db()
            gift = db.execute('SELECT * FROM res_gift WHERE id = ? AND is_deleted = 0', (gid,)).fetchone()
            if not gift:
                db.close()
                self.send_json({"code": 0, "msg": "礼物不存在或已下架"})
                return
            amount = round(float(gift['price'] or 0) * num, 2)
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < amount:
                    db.close()
                    self.send_json({"code": 0, "msg": "余额不足，请选择在线支付"})
                    return
                after = round(bal - amount, 2)
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute('INSERT INTO fa_gift_record (user_id, thug_id, gift_id, gift_name, gift_icon, num, amount, '
                           'pay_type, status, create_time) VALUES (?,?,?,?,?,?,?,?,?,?)',
                           (u['id'], tid, gift['id'], gift['name'], gift['icon'], num, str(amount), 'balance', 1, now_ts()))
                db.commit()
                db.close()
                add_balance_log(u['id'], -amount, bal, after, 2, '赠送礼物（%s）' % gift['name'])
                self.send_json({"code": 1, "msg": "赠送成功",
                                "data": {"gift_icon": gift['icon'], "num": num, "amount": amount}})
                return
            db.close()
            target = '%d|%s|%d' % (tid, gift['id'], num)
            self.send_json(_online_pay('G', '赠送礼物', amount, scene='gift',
                                       target_id=target, user_id=u['id'], pay_type=pay_type))
            return

        # ===== 打赏 =====
        if path in ('/api/thug/thugReward', '/api/thug/thugPay'):
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            tid = int(body.get('thug_id', 0) or 0)
            money = float(body.get('money', 0) or 0)
            pay_type = body.get('pay_type') or 'balance'
            if money <= 0:
                self.send_json({"code": 0, "msg": "请输入打赏金额"})
                return
            if pay_type == 'balance':
                bal = float(u.get('money') or 0)
                if bal < money:
                    self.send_json({"code": 0, "msg": "余额不足，请选择在线支付"})
                    return
                after = round(bal - money, 2)
                db = get_db()
                db.execute('UPDATE users SET money = ? WHERE id = ?', (after, u['id']))
                db.execute('INSERT INTO fa_thug_reward (user_id, thug_id, money, pay_type, status, create_time) '
                           'VALUES (?,?,?,?,?,?)', (u['id'], tid, str(money), 'balance', 1, now_ts()))
                db.commit()
                db.close()
                add_balance_log(u['id'], -money, bal, after, 2, '打赏打手')
                self.send_json({"code": 1, "msg": "打赏成功"})
                return
            self.send_json(_online_pay('R', '打赏打手', money, scene='reward',
                                       target_id=str(tid), user_id=u['id'], pay_type=pay_type))
            return

        if path == '/api/thug/thugRewardList':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            t = get_thug_by_user(u['id'])
            tid = t['id'] if t else 0
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limint', ['10'])[0] or 10))
            db = get_db()
            rows = db.execute('SELECT r.*, us.nickname AS uname, us.avatar AS uavatar FROM fa_thug_reward r '
                              'LEFT JOIN users us ON us.id = r.user_id WHERE r.thug_id = ? ORDER BY r.id DESC',
                              (tid,)).fetchall()
            db.close()
            data = [{"id": r['id'], "user_id": r['user_id'], "nickname": r['uname'] or '',
                     "avatar": r['uavatar'] or '', "money": r['money'], "pay_type": r['pay_type'],
                     "createtime": fmt_time(r['create_time'])} for r in rows]
            total = len(data)
            start = (page - 1) * limit
            self.send_json({"code": 1, "msg": "成功", "data": {"data": data[start:start + limit], "total": total}})
            return

        # ===== 订单投诉 / 售后 =====
        if path == '/api/goods/orderComplaint':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            oid = body.get('id')
            ctype = str(body.get('type') or '1')
            db = get_db()
            orow = db.execute('SELECT * FROM orders WHERE id = ? AND is_deleted = 0', (oid,)).fetchone()
            if not orow:
                db.close()
                self.send_json({"code": 0, "msg": "订单不存在"})
                return
            db.execute('INSERT INTO res_order_complaint (order_no, order_id, user_id, thug_id, type, phone, content, '
                       'images, status, createtime) VALUES (?,?,?,?,?,?,?,?,?,?)',
                       (orow['order_no'], str(oid), str(u['id']), str(orow['thug_id'] or 0), ctype,
                        str(body.get('phone') or ''), str(body.get('content') or ''),
                        str(body.get('images') or ''), '0', now_ts()))
            db.execute('UPDATE orders SET complaint = 1 WHERE id = ?', (oid,))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "提交成功"})
            return

        if path == '/api/goods/complaintMsg':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            cid = qs.get('id', [None])[0]
            db = get_db()
            row = db.execute('SELECT * FROM res_order_complaint WHERE id = ? AND is_deleted = 0', (cid,)).fetchone()
            db.close()
            if not row:
                self.send_json({"code": 0, "msg": "记录不存在"})
                return
            c = dict(row)
            imgs = [x for x in re.split(r'[,\n]', (c.get('images') or '')) if x.strip()]
            self.send_json({"code": 1, "msg": "成功", "data": {
                "id": c['id'], "order_no": c['order_no'], "type": c.get('type') or '1',
                "phone": c.get('phone') or '', "content": c.get('content') or '', "images": imgs,
                "status": int(c.get('status') or 0), "refund_method": c.get('refund_method') or '',
                "refund_time_text": fmt_time(c.get('refund_time')) if c.get('refund_time') else '',
                "createtime": fmt_time(c.get('createtime'))}})
            return

        if path == '/api/goods/orderService':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            st = qs.get('status', ['0'])[0]
            page = max(1, int(qs.get('page', ['1'])[0] or 1))
            limit = max(1, int(qs.get('limit', ['10'])[0] or 10))
            db = get_db()
            st_clause = "AND c.status = '0'" if st == '0' else "AND c.status != '0'"
            rows = db.execute(
                'SELECT c.*, o.title, o.logo, o.game_id, o.game_qf, o.remarks, o.pay_price, o.status AS order_status '
                'FROM res_order_complaint c LEFT JOIN orders o ON o.id = c.order_id '
                'WHERE c.is_deleted = 0 AND c.user_id = ? ' + st_clause + ' ORDER BY c.id DESC',
                (str(u['id']),)).fetchall()
            db.close()
            data = [{"id": r['id'], "type": r['type'] if 'type' in r.keys() else '0',
                     "title": r['title'] or '售后订单', "logo": r['logo'] or '/static/images/header_background.png',
                     "image": r['logo'] or '/static/images/header_background.png',
                     "game_id": r['game_id'] or '', "game_qf": r['game_qf'] or '', "remarks": r['remarks'] or '',
                     "pay_price": r['pay_price'] or '0', "status": int(r['status'] or 0),
                     "refund_method": (r['refund_method'] if 'refund_method' in r.keys() else '') or '',
                     "order_id": r['order_id'], "order_no": r['order_no'],
                     "createtime": fmt_time(r['createtime'])} for r in rows]
            total = len(data)
            start = (page - 1) * limit
            self.send_json({"code": 1, "msg": "成功", "data": {"data": data[start:start + limit], "total": total}})
            return

        if path == '/api/goods/orderRevoke':
            u = get_user_by_token(token)
            if not u:
                self.send_json({"code": 401, "msg": "未登录或登录已过期"})
                return
            cid = body.get('id')
            db = get_db()
            row = db.execute('SELECT * FROM res_order_complaint WHERE id = ? AND is_deleted = 0', (cid,)).fetchone()
            if not row:
                db.close()
                self.send_json({"code": 0, "msg": "记录不存在"})
                return
            db.execute("UPDATE res_order_complaint SET status = '3', update_time = ? WHERE id = ?", (now_ts(), cid))
            if row['order_id']:
                db.execute('UPDATE orders SET complaint = 0 WHERE id = ?', (row['order_id'],))
            db.commit()
            db.close()
            self.send_json({"code": 1, "msg": "已撤销"})
            return

        # ===== 兜底：其他接口返回空数据，避免前端报错 =====
        self.send_json({"code": 1, "msg": "成功", "data": {"data": [], "total": 0}})


    # ===== 后台管理 API =====
    def handle_admin_api(self, path, qs, method, body, files=None):
        """自定义后台 UI 的业务接口，读写与 H5 相同的数据源"""

        def q(name, default=''):
            v = qs.get(name)
            return v[0] if v else default

        def ok(data=None, msg='操作成功'):
            self.send_json({"code": 1, "msg": msg, "data": data})

        def fail(msg, code=0):
            self.send_json({"code": code, "msg": msg}, status=(403 if code == 403 else 200))

        admin = current_admin(self.headers)
        ip = client_ip(self)

        db = get_db()
        try:
            # 控制台统计
            if path == '/api/admin/stat':
                def count(sql, args=()):
                    return db.execute(sql, args).fetchone()[0]
                today = int(datetime.now().replace(hour=0, minute=0, second=0, microsecond=0).timestamp())
                # 今日打手收款：状态=已打款(3)，以打款时间为准当日 00:00~23:59:59
                tw_today_sum = db.execute(
                    'SELECT IFNULL(SUM(CAST(real_amount AS REAL)),0) FROM fa_thug_withdraw '
                    'WHERE status = 3 AND pay_time >= ? AND pay_time < ?', (today, today + 86400)).fetchone()[0]
                recharge_sum = db.execute(
                    "SELECT IFNULL(SUM(CAST(amount AS REAL)),0) FROM res_recharge_order WHERE is_deleted = 0").fetchone()[0]
                data = {
                    'user_count': count('SELECT COUNT(*) FROM users WHERE is_deleted = 0'),
                    'user_today': count('SELECT COUNT(*) FROM users WHERE is_deleted = 0 AND createtime >= ?', (today,)),
                    'thug_count': count('SELECT COUNT(*) FROM thug WHERE is_deleted = 0'),
                    'thug_today': count('SELECT COUNT(*) FROM thug WHERE is_deleted = 0 AND createtime >= ?', (today,)),
                    'thug_approved': count('SELECT COUNT(*) FROM thug WHERE is_deleted = 0 AND sh_status = 1'),
                    'thug_pending': count('SELECT COUNT(*) FROM thug WHERE is_deleted = 0 AND sh_status = 0'),
                    'cs_count': count('SELECT COUNT(*) FROM thug WHERE is_deleted = 0 AND is_customer_service = 1'),
                    'order_count': count('SELECT COUNT(*) FROM orders WHERE is_deleted = 0'),
                    'order_today': count('SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND createtime >= ?', (today,)),
                    'notice_count': count('SELECT COUNT(*) FROM notices WHERE is_deleted = 0'),
                    'invite_count': count('SELECT COUNT(*) FROM invite_codes WHERE is_deleted = 0'),
                    'money_sum': round(db.execute('SELECT IFNULL(SUM(money),0) FROM users WHERE is_deleted = 0').fetchone()[0] or 0, 2),
                    # 今日打手收款 + 待审核提现 + 充值总额
                    'thug_income_today': round(tw_today_sum or 0, 2),
                    'withdraw_pending': count('SELECT COUNT(*) FROM fa_thug_withdraw WHERE status = 0'),
                    'recharge_total': round(recharge_sum or 0, 2),
                }
                # 近 7 日订单 / 充值趋势
                trend_labels, order_trend, recharge_trend = [], [], []
                for i in range(6, -1, -1):
                    d0 = today - i * 86400
                    trend_labels.append(datetime.fromtimestamp(d0).strftime('%m-%d'))
                    order_trend.append(count('SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND createtime >= ? AND createtime < ?', (d0, d0 + 86400)))
                    recharge_trend.append(round(db.execute(
                        "SELECT IFNULL(SUM(CAST(amount AS REAL)),0) FROM res_recharge_order WHERE is_deleted = 0 AND createtime >= ? AND createtime < ?",
                        (d0, d0 + 86400)).fetchone()[0] or 0, 2))
                data['trend'] = {'labels': trend_labels, 'orders': order_trend, 'recharge': recharge_trend}
                ok(data)
                return

            # 会员（用户）管理
            if path == '/api/admin/users':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                kw = q('keyword')
                page = max(1, int(q('page', 1) or 1))
                limit = max(1, int(q('limit', 20) or 20))
                where, args = 'WHERE is_deleted = 0', []
                if kw:
                    where += ' AND (nickname LIKE ? OR openid LIKE ? OR id = ?)'
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
                    if not has_perm(admin, 'delete'):
                        fail('无权限删除该数据', 403); return
                    db.execute('UPDATE users SET is_deleted = 1 WHERE id = ?', (uid,))
                    db.commit()
                    log_admin_action(db, admin, '会员管理', '删除会员（#%s）' % uid, ip)
                    ok(None, '已删除该会员')
                    return
                if action == 'recharge':
                    if not has_perm(admin, 'edit'):
                        fail('无权限进行该操作', 403); return
                    db.execute('UPDATE users SET money = money + ? WHERE id = ?',
                               (float(body.get('money') or 0), uid))
                    db.commit()
                    log_admin_action(db, admin, '会员管理', '为会员（#%s）充值 ¥%s' % (uid, body.get('money')), ip)
                    ok(None, '充值成功')
                    return
                if not has_perm(admin, 'edit'):
                    fail('无权限进行该操作', 403); return
                db.execute('UPDATE users SET nickname = ?, money = ?, level = ? WHERE id = ?',
                           (body.get('nickname'), float(body.get('money') or 0),
                            int(body.get('level') or 1), uid))
                db.commit()
                log_admin_action(db, admin, '会员管理', '编辑会员（#%s）' % uid, ip)
                ok(None, '保存成功')
                return

            # 打手管理
            if path == '/api/admin/thugs':
                kw = q('keyword')
                sh = q('sh_status')
                where, args = 'WHERE t.is_deleted = 0', []
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
                if action in ('approve', 'reject', 'toggle_cs'):
                    if not has_perm(admin, 'edit'):
                        fail('无权限进行该操作', 403); return
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
                    if not has_perm(admin, 'delete'):
                        fail('无权限删除该数据', 403); return
                    db.execute('UPDATE thug SET is_deleted = 1 WHERE id = ?', (tid,))
                    msg = '已删除该打手'
                else:
                    db.execute('UPDATE thug SET name = ? WHERE id = ?', (body.get('name'), tid))
                    msg = '保存成功'
                db.commit()
                log_admin_action(db, admin, '打手管理', '%s（#%s）' % (msg, tid), ip)
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
                    'LEFT JOIN users u ON u.id = c.user_id WHERE c.is_deleted = 0 ORDER BY c.id DESC').fetchall()
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
                if not has_perm(admin, 'delete'):
                    fail('无权限删除该数据', 403); return
                db.execute('UPDATE customer_services SET is_deleted = 1 WHERE id = ?', (int(body.get('id') or 0),))
                db.commit()
                log_admin_action(db, admin, '客服管理', '删除客服（#%s）' % body.get('id'), ip)
                ok(None, '已删除')
                return

            # 订单管理
            if path == '/api/admin/orders':
                kw = q('keyword')
                status = q('status')
                page = max(1, int(q('page', 1) or 1))
                limit = max(1, int(q('limit', 20) or 20))
                where, args = 'WHERE is_deleted = 0', []
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
                rows = db.execute('SELECT * FROM notices WHERE is_deleted = 0 ORDER BY id DESC').fetchall()
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
                log_admin_action(db, admin, '公告管理', ('编辑' if nid else '发布') + '公告（#%s）' % (nid or '新增'), ip)
                ok(None, '保存成功')
                return

            if path == '/api/admin/notice/delete':
                if not has_perm(admin, 'delete'):
                    fail('无权限删除该数据', 403); return
                db.execute('UPDATE notices SET is_deleted = 1 WHERE id = ?', (int(body.get('id') or 0),))
                db.commit()
                log_admin_action(db, admin, '公告管理', '删除公告（#%s）' % body.get('id'), ip)
                ok(None, '已删除')
                return

            # 邀请码管理
            if path == '/api/admin/invites':
                rows = db.execute('SELECT * FROM invite_codes WHERE is_deleted = 0 ORDER BY id DESC').fetchall()
                ok({'list': [dict(r) for r in rows], 'total': len(rows)})
                return

            if path == '/api/admin/invite/save':
                import random, string
                iid = int(body.get('id') or 0)
                itype = body.get('type', 'thug')
                # 邀请码仅由后台随机生成（大小写英文字母 + 数字），禁止手动录入
                alphabet = string.ascii_letters + string.digits
                if iid:
                    # 编辑时保持原邀请码不变
                    row = db.execute('SELECT code FROM invite_codes WHERE id = ?', (iid,)).fetchone()
                    code = row['code'] if row else ''
                    if not code:
                        while True:
                            code = ''.join(random.choices(alphabet, k=8))
                            if not db.execute('SELECT id FROM invite_codes WHERE code = ?', (code,)).fetchone():
                                break
                    db.execute('UPDATE invite_codes SET code = ?, type = ?, name = ? WHERE id = ?',
                               (code, itype, body.get('name', ''), iid))
                else:
                    while True:
                        code = ''.join(random.choices(alphabet, k=8))
                        if not db.execute('SELECT id FROM invite_codes WHERE code = ?', (code,)).fetchone():
                            break
                    db.execute('INSERT INTO invite_codes (code, type, user_id, name, createtime) VALUES (?,?,?,?,?)',
                               (code, itype, int(body.get('user_id') or 0),
                                body.get('name', ''), now_ts()))
                db.commit()
                ok({'code': code}, '保存成功')
                return

            if path == '/api/admin/invite/delete':
                if not has_perm(admin, 'delete'):
                    fail('无权限删除该数据', 403); return
                db.execute('UPDATE invite_codes SET is_deleted = 1 WHERE id = ?', (int(body.get('id') or 0),))
                db.commit()
                log_admin_action(db, admin, '邀请码管理', '删除邀请码（#%s）' % body.get('id'), ip)
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
                    'page_bg_type': body.get('page_bg_type', 'solid'),
                    'page_bg_from': body.get('page_bg_from', '#FFFFFF'),
                    'page_bg_to': body.get('page_bg_to', '#FFFFFF'),
                    'page_bg_deg': body.get('page_bg_deg', 180),
                    'nav_style': body.get('nav_style', 'float_ellipse'),
                    'floating_nav': body.get('floating_nav') or [],
                }
                set_config('frontend_decoration', json.dumps(cfg, ensure_ascii=False))
                ok(None, '装修配置已保存，前端将实时生效')
                return

            # 店铺开关配置（开店/关店）
            if path == '/api/admin/store':
                ok(get_store_config())
                return

            if path == '/api/admin/store/save':
                set_config('store_status', str(body.get('store_status', '1')))
                set_config('close_qq', str(body.get('close_qq', '')))
                set_config('close_image', str(body.get('close_image', '')))
                ok(None, '店铺状态已保存')
                return

            # 首页背景音乐配置
            if path == '/api/admin/home_bgm':
                ok(get_home_bgm_config())
                return

            if path == '/api/admin/home_bgm/save':
                set_config('home_bgm_url', str(body.get('url', '')))
                set_config('home_bgm_title', str(body.get('title', '背景音乐')))
                set_config('home_bgm_sub', str(body.get('sub', '首页背景音乐 · 点击播放')))
                set_config('home_bgm_enabled', str(body.get('enabled', '1')))
                ok(None, '背景音乐配置已保存')
                return

            # 多平台音乐搜索
            if path == '/api/admin/music/search':
                platform = str(body.get('platform', 'netease'))
                keyword = str(body.get('keyword', '')).strip()
                limit = int(body.get('limit', 10))
                if not keyword:
                    ok({'list': [], 'platforms': {k: v[0] for k, v in MUSIC_SEARCHERS.items()}})
                    return
                songs = search_music(platform, keyword, limit)
                ok({'list': songs, 'platforms': {k: v[0] for k, v in MUSIC_SEARCHERS.items()}})
                return

            if path == '/api/admin/music/url':
                platform = str(body.get('platform', ''))
                song_id = str(body.get('id', ''))
                album_id = str(body.get('album_id', ''))
                source = str(body.get('source', ''))
                url = get_music_url(platform, song_id, album_id=album_id, source=source) if song_id else ''
                ok({'url': url})
                return

            # 传世武器入口配置
            if path == '/api/admin/craft':
                data = get_craft_entry_config()
                data.update({
                    'lottery_title': get_config('lottery_title', ''),
                    'lottery_subtitle': get_config('lottery_subtitle', ''),
                    'lottery_rules': get_config('lottery_rules', ''),
                    'lottery_price': get_config('lottery_price', '9.90'),
                    'lottery_mode': get_config('lottery_mode', 'wheel'),
                    'lottery_pools': get_config('lottery_pools', ''),
                })
                ok(data)
                return

            if path == '/api/admin/craft/save':
                set_config('craft_mode', str(body.get('craft_mode', 'lottery')))
                set_config('craft_banner', str(body.get('craft_banner', '')))
                set_config('craft_jump', str(body.get('craft_jump', '')))
                set_config('lottery_banner', str(body.get('lottery_banner', '')))
                set_config('lottery_jump', str(body.get('lottery_jump', '')))
                # 抽奖活动：自定义封面 + 抽奖图片（多张，逗号分隔）
                if 'lottery_cover' in body:
                    set_config('lottery_cover', str(body.get('lottery_cover', '')))
                if 'lottery_images' in body:
                    imgs = body.get('lottery_images', '')
                    if isinstance(imgs, list):
                        imgs = ','.join([str(x).strip() for x in imgs if str(x).strip()])
                    set_config('lottery_images', str(imgs))
                # 抽奖基础参数：标题/副标题/规则/单价/默认模式
                if 'lottery_title' in body:
                    set_config('lottery_title', str(body.get('lottery_title', '')))
                if 'lottery_subtitle' in body:
                    set_config('lottery_subtitle', str(body.get('lottery_subtitle', '')))
                if 'lottery_rules' in body:
                    set_config('lottery_rules', str(body.get('lottery_rules', '')))
                if 'lottery_price' in body:
                    set_config('lottery_price', str(body.get('lottery_price', '9.90')))
                if 'lottery_mode' in body:
                    set_config('lottery_mode', str(body.get('lottery_mode', 'wheel')))
                # 多奖池（转盘/盲盒）配置，前端传 JSON 字符串或数组
                if 'lottery_pools' in body:
                    pools = body.get('lottery_pools', '')
                    if isinstance(pools, (list, dict)):
                        pools = json.dumps(pools, ensure_ascii=False)
                    set_config('lottery_pools', str(pools))
                ok(None, '传世武器入口配置已保存')
                return

            # 订单自动结算与退款配置
            if path == '/api/admin/auto_settle':
                ok(get_auto_settle_config())
                return

            if path == '/api/admin/auto_settle/save':
                set_config('auto_settle_enabled', str(body.get('auto_settle_enabled', '1')))
                set_config('allow_unaccepted_refund', str(body.get('allow_unaccepted_refund', '1')))
                ok(None, '自动结算配置已保存')
                return

            # 提现设置配置
            if path == '/api/admin/withdraw_config':
                ok(get_withdraw_config())
                return

            if path == '/api/admin/withdraw_config/save':
                set_config('withdraw_enabled', str(body.get('withdraw_enabled', '1')))
                set_config('withdraw_min', str(body.get('withdraw_min', '10')))
                set_config('withdraw_max', str(body.get('withdraw_max', '10000')))
                set_config('withdraw_fee_rate', str(body.get('withdraw_fee_rate', '0')))
                set_config('withdraw_fee_fixed', str(body.get('withdraw_fee_fixed', '0')))
                set_config('withdraw_daily_limit', str(body.get('withdraw_daily_limit', '0')))
                set_config('withdraw_desc', str(body.get('withdraw_desc', '')))
                ok(None, '提现设置已保存')
                return

            # 装备打造活动配置
            if path == '/api/admin/craft_config':
                ok(get_craft_config())
                return

            if path == '/api/admin/craft_config/save':
                set_config('craft_enabled', str(body.get('craft_enabled', '1')))
                set_config('craft_title', str(body.get('craft_title', '传世武器打造')))
                set_config('craft_subtitle', str(body.get('craft_subtitle', '两阶段打造，进度满额解锁')))
                set_config('craft_price', str(body.get('craft_price', '1')))
                set_config('craft_progress_min', str(body.get('craft_progress_min', '2')))
                set_config('craft_progress_max', str(body.get('craft_progress_max', '5')))
                set_config('craft_crit_rate', str(body.get('craft_crit_rate', '15')))
                set_config('craft_crit_multi', str(body.get('craft_crit_multi', '2')))
                set_config('craft_bg', str(body.get('craft_bg', '')))
                set_config('craft_rules', str(body.get('craft_rules', '')))
                ok(None, '装备打造配置已保存')
                return

            if path == '/api/admin/craft/stats':
                ok(get_craft_stats())
                return

            # ============================================================
            # 打手提现记录（财务流水，禁止删除）
            # ============================================================
            def _day(s, plus=0):
                """日期字符串转当日 00:00 时间戳"""
                try:
                    return int(datetime.strptime(s[:10], '%Y-%m-%d').timestamp()) + plus
                except Exception:
                    return None

            def _tw_query():
                where = ['is_deleted = 0']
                args = []
                kw = q('keyword')
                if kw:
                    where.append('(thug_name LIKE ? OR withdraw_no LIKE ? OR thug_id = ?)')
                    args += ['%' + kw + '%', '%' + kw + '%', kw if str(kw).isdigit() else -1]
                at = q('account_type')
                if at != '':
                    where.append('account_type = ?'); args.append(at)
                st = q('status')
                if st != '':
                    where.append('status = ?'); args.append(int(st))
                a0, a1 = _day(q('apply_start')), _day(q('apply_end'), 86399)
                p0, p1 = _day(q('pay_start')), _day(q('pay_end'), 86399)
                if a0: where.append('apply_time >= ?'); args.append(a0)
                if a1: where.append('apply_time <= ?'); args.append(a1)
                if p0: where.append('pay_time >= ?'); args.append(p0)
                if p1: where.append('pay_time <= ?'); args.append(p1)
                return 'WHERE ' + ' AND '.join(where), args

            if path == '/api/admin/thug_withdraws':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                page = max(1, int(q('page', 1) or 1))
                limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = _tw_query()
                total = db.execute('SELECT COUNT(*) FROM fa_thug_withdraw ' + where, args).fetchone()[0]
                rows = db.execute('SELECT * FROM fa_thug_withdraw ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            if path == '/api/admin/thug_withdraw/stats':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                today = int(datetime.now().replace(hour=0, minute=0, second=0, microsecond=0).timestamp())
                d = {
                    # 今日打手收款：状态=已打款，按打款时间当日统计
                    'today_income': round(db.execute(
                        'SELECT IFNULL(SUM(CAST(real_amount AS REAL)),0) FROM fa_thug_withdraw '
                        'WHERE status = 3 AND pay_time >= ? AND pay_time < ?', (today, today + 86400)).fetchone()[0] or 0, 2),
                    'today_apply': db.execute('SELECT COUNT(*) FROM fa_thug_withdraw WHERE apply_time >= ? AND apply_time < ?', (today, today + 86400)).fetchone()[0],
                    'today_pending': db.execute('SELECT COUNT(*) FROM fa_thug_withdraw WHERE status = 0').fetchone()[0],
                    'today_rejected': db.execute('SELECT COUNT(*) FROM fa_thug_withdraw WHERE status = 1 AND audit_time >= ? AND audit_time < ?', (today, today + 86400)).fetchone()[0],
                }
                ok(d)
                return

            if path == '/api/admin/thug_withdraw/detail':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                row = db.execute('SELECT * FROM fa_thug_withdraw WHERE id = ?', (int(q('id') or 0),)).fetchone()
                if not row:
                    fail('提现单不存在'); return
                item = dict(row)
                logs = db.execute(
                    "SELECT * FROM fa_admin_log WHERE module = '打手提现' AND content LIKE ? ORDER BY id DESC LIMIT 20",
                    ('%' + (item.get('withdraw_no') or ('#%s' % item['id'])) + '%',)).fetchall()
                item['audit_logs'] = [dict(x) for x in logs]
                ok(item)
                return

            if path == '/api/admin/thug_withdraw/audit':
                if not has_perm(admin, 'edit'):
                    fail('无权限进行该操作', 403); return
                tid = int(body.get('id') or 0)
                action = body.get('action')
                row = db.execute('SELECT * FROM fa_thug_withdraw WHERE id = ?', (tid,)).fetchone()
                if not row:
                    fail('提现单不存在'); return
                item = dict(row)
                now = now_ts()
                thug = db.execute('SELECT t.id, t.name, t.user_id, u.money AS user_money '
                                  'FROM thug t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?',
                                  (item['thug_id'],)).fetchone()
                try:
                    if action == 'approve':
                        if int(item['status']) != 0:
                            fail('该提现单当前状态不可审核'); return
                        db.execute('UPDATE fa_thug_withdraw SET status = 2, audit_time = ?, audit_admin = ?, '
                                   'update_time = ? WHERE id = ?', (now, admin['name'], now, tid))
                        msg = '审核通过，状态变更为待打款'
                    elif action == 'reject':
                        if int(item['status']) != 0:
                            fail('该提现单当前状态不可驳回'); return
                        reason = (body.get('reason') or '').strip()
                        if not reason:
                            fail('请填写驳回原因'); return
                        db.execute('UPDATE fa_thug_withdraw SET status = 1, audit_time = ?, audit_admin = ?, '
                                   'reject_reason = ?, update_time = ? WHERE id = ?',
                                   (now, admin['name'], reason, now, tid))
                        msg = '已驳回，已短信通知打手'
                        send_sms(DEMO_MOBILE, 'WITHDRAW_REJECT',
                                 '您的提现申请（%s）被驳回，原因：%s' % (item.get('withdraw_no'), reason),
                                 scene='withdraw', db=db)
                    elif action == 'mark_paid':
                        if int(item['status']) != 2:
                            fail('仅待打款状态可标记已打款'); return
                        real = float(item['real_amount'] or 0)
                        # 事务：扣减平台/打手资金 + 余额明细 + 佣金流水
                        if thug:
                            uid = thug['user_id']
                            before = float(thug['user_money'] or 0)
                            if before < real:
                                fail('打手余额不足，无法打款'); return
                            after = before - real
                            db.execute('UPDATE users SET money = ? WHERE id = ?', (after, uid))
                            db.execute('INSERT INTO res_balance (user_id, amount, before, after, type, remark, createtime, is_deleted) '
                                       'VALUES (?,?,?,?,?,?,?,0)',
                                       (uid, str(real), str(before), str(after), '2',
                                        '打手提现打款（%s）' % item.get('withdraw_no'), now))
                            db.execute('INSERT INTO res_commission_detail (user_id, amount, type, remark, createtime, is_deleted) '
                                       'VALUES (?,?,?,?,?,0)',
                                       (uid, str(real), '2', '提现打款（%s）' % item.get('withdraw_no'), now))
                        db.execute('UPDATE fa_thug_withdraw SET status = 3, pay_time = ?, update_time = ? WHERE id = ?',
                                   (now, now, tid))
                        msg = '已标记打款，资金流水已生成'
                        send_sms(DEMO_MOBILE, 'WITHDRAW_PAID',
                                 '您的提现（%s）已打款，到账金额 ¥%s' % (item.get('withdraw_no'), item.get('real_amount')),
                                 scene='withdraw', db=db)
                        if real >= DASHU_ALERT_AMOUNT:
                            wecom_alert('大额提现打款', '打手 %s 提现 ¥%s 已打款（单号 %s）'
                                        % (item.get('thug_name'), item.get('real_amount'), item.get('withdraw_no')))
                    else:
                        fail('未知操作'); return
                    db.commit()
                except Exception as e:
                    db.rollback()
                    fail('操作失败：%s' % e)
                    return
                log_admin_action(db, admin, '打手提现', '%s（单号 %s，金额 ¥%s）'
                                 % (msg, item.get('withdraw_no'), item.get('amount')), ip)
                ok(None, msg)
                return

            if path == '/api/admin/thug_withdraw/export':
                if not has_perm(admin, 'export'):
                    fail('无权限导出数据', 403); return
                where, args = _tw_query()
                rows = db.execute('SELECT * FROM fa_thug_withdraw ' + where + ' ORDER BY id DESC', args).fetchall()
                head = ['ID', '打手ID', '打手昵称', '提现单号', '提现金额', '平台手续费', '实际到账',
                        '账户类型', '收款账号', '申请时间', '审核时间', '打款时间', '状态', '审核管理员', '备注']
                st_map = {0: '待审核', 1: '审核驳回', 2: '待打款', 3: '已打款'}
                import csv as _csv, io as _io
                buf = _io.StringIO(); w = _csv.writer(buf); w.writerow(head)
                for r in rows:
                    w.writerow([r['id'], r['thug_id'], r['thug_name'], r['withdraw_no'], r['amount'], r['fee'],
                                r['real_amount'], '微信' if r['account_type'] == 'wechat' else '支付宝', r['account'],
                                fmt_time(r['apply_time']), fmt_time(r['audit_time']), fmt_time(r['pay_time']),
                                st_map.get(int(r['status']), ''), r['audit_admin'], r['remark']])
                log_admin_action(db, admin, '数据导出', '导出打手提现记录，共 %d 条' % len(rows), ip)
                self._send_csv('\ufeff' + buf.getvalue(), 'thug_withdraw_%s.csv' % datetime.now().strftime('%Y%m%d%H%M%S'))
                return

            # ============================================================
            # 聚合登录记录（永久保留，禁止删除）
            # ============================================================
            if path == '/api/admin/aggregate_logs':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                kw = q('keyword'); page = max(1, int(q('page', 1) or 1)); limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (nickname LIKE ? OR openid LIKE ? OR user_id = ?)'
                    args += ['%' + kw + '%', '%' + kw + '%', kw if str(kw).isdigit() else -1]
                total = db.execute('SELECT COUNT(*) FROM fa_aggregate_login_log ' + where, args).fetchone()[0]
                rows = db.execute('SELECT * FROM fa_aggregate_login_log ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            # ============================================================
            # 短信记录（永久保留，禁止删除）+ 测试发送 + 场景开关
            # ============================================================
            if path == '/api/admin/sms_logs':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                kw = q('keyword'); page = max(1, int(q('page', 1) or 1)); limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (mobile LIKE ? OR content LIKE ? OR template_id LIKE ?)'
                    args += ['%' + kw + '%'] * 3
                total = db.execute('SELECT COUNT(*) FROM fa_sms_log ' + where, args).fetchone()[0]
                rows = db.execute('SELECT * FROM fa_sms_log ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            if path == '/api/admin/sms/config':
                scenes = {k: get_config('sms_scene_' + k, '1') == '1' for k in SMS_SCENES}
                tpls = {k: get_config('sms_tpl_' + k, '') for k in SMS_SCENES}
                ok({'enabled': get_config('sms_enabled', '0') == '1', 'provider': get_config('sms_provider', 'aliyun'),
                    'access_key': get_config('sms_access_key', ''), 'access_secret': get_config('sms_access_secret', ''),
                    'sign': get_config('sms_sign', '创世网络'), 'templates': tpls,
                    'scenes': scenes, 'scene_names': SMS_SCENES})
                return

            if path == '/api/admin/sms/config/save':
                if not has_perm(admin, 'config'):
                    fail('无权限修改该配置', 403); return
                set_config('sms_enabled', '1' if body.get('enabled') else '0')
                if body.get('provider'):
                    set_config('sms_provider', body.get('provider'))
                for k in ('access_key', 'access_secret', 'sign'):
                    if k in body:
                        set_config('sms_' + k, body.get(k) or '')
                tpls = body.get('templates') or {}
                for k in SMS_SCENES:
                    if k in tpls:
                        set_config('sms_tpl_' + k, tpls.get(k) or '')
                for k in SMS_SCENES:
                    if body.get('scenes') and k in body['scenes']:
                        set_config('sms_scene_' + k, '1' if body['scenes'][k] else '0')
                log_admin_action(db, admin, '短信配置', '修改短信配置与场景开关', ip)
                ok(None, '短信配置已保存')
                return

            if path == '/api/admin/sms/test':
                if not has_perm(admin, 'config'):
                    fail('无权限进行该操作', 403); return
                mobile = (body.get('mobile') or '').strip()
                if not re.match(r'^1\d{10}$', mobile):
                    fail('请输入正确的手机号'); return
                r = send_sms(mobile, body.get('template_id') or 'TEST', body.get('content') or '【创世网络】测试短信', scene='login', db=db)
                log_admin_action(db, admin, '短信配置', '发送测试短信至 %s：%s' % (mobile, r['msg']), ip)
                if r['ok']:
                    ok(None, '测试短信发送成功')
                else:
                    fail(r['msg'])
                return

            # ============================================================
            # 支付渠道管理
            # ============================================================
            if path == '/api/admin/pay_channels':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                rows = db.execute('SELECT * FROM fa_pay_channel ORDER BY sort ASC, id ASC').fetchall()
                ok({'list': [dict(r) for r in rows]})
                return

            if path == '/api/admin/pay_channel/save':
                if not has_perm(admin, 'config'):
                    fail('无权限修改该配置', 403); return
                cid = int(body.get('id') or 0)
                keys = ['code', 'name', 'channel_type', 'pid', 'secret', 'notify_url', 'return_url',
                        'mch_id', 'api_v3_key', 'cert_serial', 'private_key', 'remark']
                vals = [str(body.get(k, '')) for k in keys]
                if cid:
                    sets = ', '.join([k + ' = ?' for k in keys])
                    db.execute('UPDATE fa_pay_channel SET %s, timeout = ?, sort = ?, status = ?, update_time = ? WHERE id = ?' % sets,
                               vals + [int(body.get('timeout') or 300), int(body.get('sort') or 0),
                                       int(body.get('status') or 0), now_ts(), cid])
                else:
                    db.execute('INSERT INTO fa_pay_channel (%s, timeout, sort, status, create_time, update_time) VALUES (%s, ?, ?, ?, ?, ?)'
                               % (','.join(keys), ','.join(['?'] * len(keys))),
                               vals + [int(body.get('timeout') or 300), int(body.get('sort') or 0),
                                       int(body.get('status') or 0), now_ts(), now_ts()])
                db.commit()
                log_admin_action(db, admin, '支付渠道', '保存支付渠道 %s' % (body.get('name') or ''), ip)
                ok(None, '支付渠道已保存')
                return

            # ============================================================
            # 支付回调日志（永久保留，禁止删除）
            # ============================================================
            if path == '/api/admin/pay_callback_logs':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                kw = q('keyword'); page = max(1, int(q('page', 1) or 1)); limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (order_no LIKE ? OR channel LIKE ? OR result LIKE ?)'
                    args += ['%' + kw + '%'] * 3
                total = db.execute('SELECT COUNT(*) FROM fa_pay_callback_log ' + where, args).fetchone()[0]
                rows = db.execute('SELECT * FROM fa_pay_callback_log ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            # ============================================================
            # 管理员操作日志（永久保留，禁止删除）
            # ============================================================
            if path == '/api/admin/admin_logs':
                kw = q('keyword'); page = max(1, int(q('page', 1) or 1)); limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (admin_name LIKE ? OR module LIKE ? OR content LIKE ?)'
                    args += ['%' + kw + '%'] * 3
                total = db.execute('SELECT COUNT(*) FROM fa_admin_log ' + where, args).fetchone()[0]
                rows = db.execute('SELECT * FROM fa_admin_log ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            # ============================================================
            # 定时任务执行日志（永久保留，禁止删除）
            # ============================================================
            if path == '/api/admin/cron_logs':
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403); return
                kw = q('keyword'); page = max(1, int(q('page', 1) or 1)); limit = max(1, min(200, int(q('limit', 20) or 20)))
                where, args = 'WHERE 1=1', []
                if kw:
                    where += ' AND (task_name LIKE ? OR message LIKE ?)'
                    args += ['%' + kw + '%'] * 2
                total = db.execute('SELECT COUNT(*) FROM fa_cron_log ' + where, args).fetchone()[0]
                rows = db.execute('SELECT * FROM fa_cron_log ' + where + ' ORDER BY id DESC LIMIT ? OFFSET ?',
                                  args + [limit, (page - 1) * limit]).fetchall()
                ok({'list': [dict(r) for r in rows], 'total': total, 'page': page, 'limit': limit})
                return

            # ===== 通用资源（后台各菜单真实 CRUD） =====
            if path == '/api/admin/resource/defs':
                defs = {}
                for k, v in RESOURCES.items():
                    vv = dict(v)
                    vv['deletable'] = (k not in FINANCE_NO_DELETE) and v.get('deletable', True)
                    vv['finance'] = k in FINANCE_NO_DELETE
                    defs[k] = vv
                ok({'defs': defs, 'perms': sorted(admin['perms']),
                    'admin': {'id': admin['id'], 'name': admin['name'], 'role': admin['role']}})
                return

            if path == '/api/admin/resource':
                res = q('res')
                spec = RESOURCES.get(res)
                if not spec:
                    fail('未知资源: ' + res)
                    return
                if not has_perm(admin, 'view'):
                    fail('无权限查看该模块', 403)
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
                where, args = 'WHERE is_deleted = 0', []
                if kw and spec.get('search'):
                    where += ' AND (' + ' OR '.join([c + ' LIKE ?' for c in spec['search']]) + ')'
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
                    fail('未知资源: ' + str(res))
                    return
                rid = int(body.get('id') or 0)
                if not has_perm(admin, 'edit' if rid else 'add'):
                    fail('无权限进行该操作', 403)
                    return
                keys = [f['key'] for f in spec['fields']]
                vals = [str(body.get(k, '')) for k in keys]
                # 系统缴费：未填缴费单号时自动生成 20 位单号
                if res == 'sys_bill' and 'bill_no' in keys:
                    i = keys.index('bill_no')
                    if not vals[i].strip():
                        vals[i] = time.strftime('%Y%m%d%H%M%S') + '%06d' % random.randint(0, 999999)
                if spec.get('singleton') and not rid:
                    rid = 1
                if rid:
                    sets = ', '.join([k + ' = ?' for k in keys])
                    db.execute('UPDATE res_%s SET %s, update_time = ? WHERE id = ?' % (res, sets), vals + [now_ts(), rid])
                else:
                    db.execute('INSERT INTO res_%s (%s, createtime, update_time) VALUES (%s, ?, ?)' % (res, ','.join(keys), ','.join(['?'] * len(keys))),
                               vals + [now_ts(), now_ts()])
                    rid = db.execute('SELECT last_insert_rowid()').fetchone()[0]
                db.commit()
                log_admin_action(db, admin, spec.get('title', res), ('编辑' if body.get('id') else '新增') + '%s（#%s）' % (spec.get('title', res), rid), ip)
                ok({'id': rid}, '保存成功')
                return

            if path == '/api/admin/resource/delete':
                res = body.get('res')
                spec = RESOURCES.get(res)
                if not spec:
                    fail('未知资源: ' + str(res))
                    return
                # 财务 / 流水数据禁止删除
                if res in FINANCE_NO_DELETE:
                    fail('该业务财务数据不允许删除', 403)
                    return
                if not has_perm(admin, 'delete'):
                    fail('无权限删除该数据', 403)
                    return
                rid = int(body.get('id') or 0)
                db.execute('UPDATE res_%s SET is_deleted = 1, update_time = ? WHERE id = ?' % res, (now_ts(), rid))
                db.commit()
                log_admin_action(db, admin, spec.get('title', res), '删除%s（#%s）' % (spec.get('title', res), rid), ip)
                ok(None, '删除成功')
                return

            # ===== 列表数据导出 Excel（CSV，带 BOM） =====
            if path == '/api/admin/export':
                if not has_perm(admin, 'export'):
                    fail('无权限导出数据', 403)
                    return
                res = q('res')
                spec = RESOURCES.get(res)
                if not spec:
                    fail('未知资源: ' + res)
                    return
                kw = q('keyword')
                cols = spec.get('columns') or [f['key'] for f in spec.get('fields', [])]
                where, args = 'WHERE is_deleted = 0', []
                if kw and spec.get('search'):
                    where += ' AND (' + ' OR '.join([c + ' LIKE ?' for c in spec['search']]) + ')'
                    args = ['%' + kw + '%'] * len(spec['search'])
                rows = db.execute('SELECT * FROM res_%s %s ORDER BY id DESC' % (res, where), args).fetchall()
                import csv as _csv
                import io as _io
                buf = _io.StringIO()
                w = _csv.writer(buf)
                w.writerow(['ID'] + [resField_label(spec, c) for c in cols])
                for r in rows:
                    w.writerow([r['id']] + [str(r[c] if r[c] is not None else '') for c in cols])
                log_admin_action(db, admin, '数据导出', '导出%s，共 %d 条' % (spec.get('title', res), len(rows)), ip)
                self._send_csv('\ufeff' + buf.getvalue(), 'export_%s_%s.csv' % (res, datetime.now().strftime('%Y%m%d%H%M%S')))
                return

            # ===== 专用列表页导出（会员/打手/订单/公告/邀请码） =====
            if path == '/api/admin/export_table':
                if not has_perm(admin, 'export'):
                    fail('无权限导出数据', 403); return
                t = q('table')
                sh_map = {0: '待审核', 1: '已通过', 2: '已驳回'}
                rn_map = {0: '未提交', 1: '待审核', 2: '已认证', 3: '已驳回'}
                ost = {0: '待付款', 1: '待接单', 2: '服务中', 3: '待结单', 4: '售后中', 5: '已完成'}
                TABLES = {
                    'users': ('SELECT id,nickname,type,openid,money,level,createtime FROM users WHERE is_deleted=0 ORDER BY id DESC',
                              ['ID', '昵称', '类型', 'openid', '余额', '等级', '注册时间'],
                              lambda r: [r['id'], r['nickname'], '打手用户' if r['type'] == 2 else '普通用户', r['openid'], r['money'], r['level'], fmt_time(r['createtime'])]),
                    'thug': ('SELECT t.id,t.name,u.nickname un,t.sh_status,t.realname_status,t.createtime FROM thug t LEFT JOIN users u ON u.id=t.user_id WHERE t.is_deleted=0 ORDER BY t.id DESC',
                             ['ID', '打手名', '所属用户', '审核状态', '实名认证', '申请时间'],
                             lambda r: [r['id'], r['name'], r['un'], sh_map.get(int(r['sh_status'] or 0), ''), rn_map.get(int(r['realname_status'] or 0), ''), fmt_time(r['createtime'])]),
                    'orders': ('SELECT id,order_no,title,game_id,pay_price,price,status,status_text,createtime FROM orders WHERE is_deleted=0 ORDER BY id DESC',
                               ['ID', '订单号', '商品', '游戏ID', '实付', '原价', '状态', '下单时间'],
                               lambda r: [r['id'], r['order_no'], r['title'], r['game_id'], r['pay_price'], r['price'], r['status_text'] or ost.get(int(r['status'] or 0), ''), fmt_time(r['createtime'])]),
                    'notices': ('SELECT id,title,synopsis,createtime FROM notices WHERE is_deleted=0 ORDER BY id DESC',
                                ['ID', '标题', '摘要', '发布时间'],
                                lambda r: [r['id'], r['title'], r['synopsis'], fmt_time(r['createtime'])]),
                    'invite_codes': ('SELECT id,code,type,name,createtime FROM invite_codes WHERE is_deleted=0 ORDER BY id DESC',
                                     ['ID', '邀请码', '类型', '备注名称', '创建时间'],
                                     lambda r: [r['id'], r['code'], '客服邀请码' if r['type'] == 'cs' else '打手邀请码', r['name'], fmt_time(r['createtime'])]),
                    'customer_services': ('SELECT id,name,user_id,cs_mode,online FROM customer_services WHERE is_deleted=0 ORDER BY id DESC',
                                          ['ID', '客服名称', '绑定用户ID', '接入模式', '在线状态'],
                                          lambda r: [r['id'], r['name'], r['user_id'], '企微外部' if r['cs_mode'] == 'wecom' else '内置客服', '在线' if r['online'] == 1 else '离线']),
                }
                if t not in TABLES:
                    fail('不支持的导出类型: ' + t); return
                sql, head, mapper = TABLES[t]
                rows = db.execute(sql).fetchall()
                import csv as _csv, io as _io
                buf = _io.StringIO(); w = _csv.writer(buf); w.writerow(head)
                for r in rows:
                    w.writerow([('' if v is None else v) for v in mapper(r)])
                log_admin_action(db, admin, '数据导出', '导出%s，共 %d 条' % (t, len(rows)), ip)
                self._send_csv('\ufeff' + buf.getvalue(), '%s_%s.csv' % (t, datetime.now().strftime('%Y%m%d%H%M%S')))
                return

            # ===== 图片上传（仅校验后缀 jpg/jpeg/png/webp，不限制文件大小） =====
            if path == '/api/admin/upload':
                if not has_perm(admin, 'edit') and not has_perm(admin, 'add'):
                    fail('无权限上传文件', 403)
                    return
                f = (files or {}).get('file') or (files or {}).get('image')
                if not f:
                    fail('未接收到文件')
                    return
                fname = f.get('filename', '')
                ext = os.path.splitext(fname)[1].lower().lstrip('.')
                if ext not in ('jpg', 'jpeg', 'png', 'webp', 'gif', 'mp3', 'm4a', 'wav', 'ogg', 'aac'):
                    fail('仅支持 jpg/jpeg/png/webp/gif 图片 或 mp3/m4a/wav/ogg/aac 音频')
                    return
                up_dir = os.path.join(WEB_ROOT, 'uploads')
                os.makedirs(up_dir, exist_ok=True)
                save_name = secrets.token_hex(8) + '.' + ext
                with open(os.path.join(up_dir, save_name), 'wb') as fp:
                    fp.write(f.get('content', b''))
                url = '/uploads/' + save_name
                log_admin_action(db, admin, '文件上传', '上传文件 %s（%s）' % (url, fname), ip)
                ok({'url': url, 'name': save_name}, '上传成功')
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
