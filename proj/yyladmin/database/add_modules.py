#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""为 yylAdmin 追加业务模块：数据表 + 演示数据 + 后台菜单"""
import os
import sqlite3
import shutil

DB = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'yyladmin.db')
CACHE = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'runtime', 'cache')

conn = sqlite3.connect(DB)
cur = conn.cursor()


def create(sql):
    cur.execute(sql)


# ---------- 数据表 ----------
create("""CREATE TABLE IF NOT EXISTS ya_customer_service (
  cs_id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL DEFAULT '',
  avatar TEXT DEFAULT '',
  phone TEXT DEFAULT '',
  wechat TEXT DEFAULT '',
  account TEXT DEFAULT '',
  is_online INTEGER NOT NULL DEFAULT 0,
  sort INTEGER NOT NULL DEFAULT 200,
  remark TEXT DEFAULT '',
  is_disable INTEGER NOT NULL DEFAULT 0,
  is_delete INTEGER NOT NULL DEFAULT 0,
  create_time TEXT, update_time TEXT, delete_time TEXT
)""")

create("""CREATE TABLE IF NOT EXISTS ya_banner (
  banner_id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL DEFAULT '',
  image TEXT DEFAULT '',
  url TEXT DEFAULT '',
  sort INTEGER NOT NULL DEFAULT 200,
  is_disable INTEGER NOT NULL DEFAULT 0,
  is_delete INTEGER NOT NULL DEFAULT 0,
  create_time TEXT, update_time TEXT, delete_time TEXT
)""")

create("""CREATE TABLE IF NOT EXISTS ya_notice (
  notice_id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL DEFAULT '',
  content TEXT DEFAULT '',
  type INTEGER NOT NULL DEFAULT 1,
  sort INTEGER NOT NULL DEFAULT 200,
  is_disable INTEGER NOT NULL DEFAULT 0,
  is_delete INTEGER NOT NULL DEFAULT 0,
  create_time TEXT, update_time TEXT, delete_time TEXT
)""")

create("""CREATE TABLE IF NOT EXISTS ya_image_nav (
  nav_id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL DEFAULT '',
  image TEXT DEFAULT '',
  url TEXT DEFAULT '',
  sort INTEGER NOT NULL DEFAULT 200,
  is_disable INTEGER NOT NULL DEFAULT 0,
  is_delete INTEGER NOT NULL DEFAULT 0,
  create_time TEXT, update_time TEXT, delete_time TEXT
)""")

create("""CREATE TABLE IF NOT EXISTS ya_msg_template (
  tpl_id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL DEFAULT '',
  code TEXT DEFAULT '',
  title TEXT DEFAULT '',
  content TEXT DEFAULT '',
  sort INTEGER NOT NULL DEFAULT 200,
  is_disable INTEGER NOT NULL DEFAULT 0,
  is_delete INTEGER NOT NULL DEFAULT 0,
  create_time TEXT, update_time TEXT, delete_time TEXT
)""")

now = '2026-10-15 23:30:00'

# ---------- 演示数据 ----------
def seed(table, rows, cols):
    if cur.execute("SELECT COUNT(*) FROM " + table).fetchone()[0] > 0:
        return
    placeholders = ','.join(['?'] * len(cols))
    cur.executemany("INSERT INTO %s (%s) VALUES (%s)" % (table, ','.join(cols), placeholders), rows)


seed('ya_customer_service', [
    ('客服小美', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=friendly%20female%20support%20agent%20avatar&image_size=square', '13800000001', 'cs_xiaomei', 'xiaomei', 1, 300, 'VIP专属客服', 0, 0, now, now, None),
    ('客服阿杰', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=friendly%20male%20support%20agent%20avatar&image_size=square', '13800000002', 'cs_ajie', 'ajie', 0, 250, '订单售后', 0, 0, now, now, None),
    ('客服婷婷', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=smiling%20female%20service%20avatar&image_size=square', '13800000003', 'cs_tingting', 'tingting', 1, 200, '充值问题', 0, 0, now, now, None),
], ['name', 'avatar', 'phone', 'wechat', 'account', 'is_online', 'sort', 'remark', 'is_disable', 'is_delete', 'create_time', 'update_time', 'delete_time'])

seed('ya_banner', [
    ('首页大图', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=game%20recruitment%20banner%20dark%20blue&image_size=landscape_16_9', '#/pages/index/index', 300, 0, 0, now, now, None),
    ('活动推广', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=promotion%20activity%20banner%20orange&image_size=landscape_16_9', '#/pages/index/desk', 250, 0, 0, now, now, None),
], ['title', 'image', 'url', 'sort', 'is_disable', 'is_delete', 'create_time', 'update_time', 'delete_time'])

seed('ya_notice', [
    ('平台上线公告', '抢单神器平台正式上线，欢迎各位打手入驻！', 1, 300, 0, 0, now, now, None),
    ('提现规则调整', '自即日起提现手续费调整为 1%，提现到账时间为 T+1。', 2, 250, 0, 0, now, now, None),
], ['title', 'content', 'type', 'sort', 'is_disable', 'is_delete', 'create_time', 'update_time', 'delete_time'])

seed('ya_image_nav', [
    ('个人中心', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=user%20profile%20icon%20flat&image_size=square', '#/pages/mine/index', 300, 0, 0, now, now, None),
    ('我的订单', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=order%20list%20icon%20flat&image_size=square', '#/pages/mine/order/order', 250, 0, 0, now, now, None),
    ('客服中心', 'https://trae-api-cn.mchost.guru/api/ide/v1/tool_text_to_image?prompt=customer%20service%20icon%20flat&image_size=square', '#/pages/playerMain/customer-service', 200, 0, 0, now, now, None),
], ['name', 'image', 'url', 'sort', 'is_disable', 'is_delete', 'create_time', 'update_time', 'delete_time'])

seed('ya_msg_template', [
    ('订单接单通知', 'order_accept', '您有一笔新订单待处理', '尊敬的用户，您的订单{{order_no}}已被接单，请及时处理。', 300, 0, 0, now, now, None),
    ('提现到账通知', 'withdraw_success', '提现成功', '您申请的提现{{money}}元已到账，请注意查收。', 250, 0, 0, now, now, None),
], ['name', 'code', 'title', 'content', 'sort', 'is_disable', 'is_delete', 'create_time', 'update_time', 'delete_time'])

# ---------- 后台菜单 ----------
menus = [
    (200, 0, '客服管理', '', 190),
    (201, 200, '客服管理', 'admin/CustomerService/csList', 200),
    (202, 200, '客服信息', 'admin/CustomerService/csInfo', 200),
    (203, 200, '客服添加', 'admin/CustomerService/csAdd', 200),
    (204, 200, '客服修改', 'admin/CustomerService/csEdit', 200),
    (205, 200, '客服删除', 'admin/CustomerService/csDele', 200),
    (206, 200, '客服禁用', 'admin/CustomerService/csDisable', 200),

    (210, 0, '自定义UI', '', 185),
    (211, 210, '轮播图列表', 'admin/Banner/bannerList', 210),
    (212, 210, '轮播图信息', 'admin/Banner/bannerInfo', 200),
    (213, 210, '轮播图添加', 'admin/Banner/bannerAdd', 200),
    (214, 210, '轮播图修改', 'admin/Banner/bannerEdit', 200),
    (215, 210, '轮播图删除', 'admin/Banner/bannerDele', 200),
    (216, 210, '轮播图禁用', 'admin/Banner/bannerDisable', 200),
    (220, 210, '公告列表', 'admin/Notice/noticeList', 190),
    (221, 210, '公告信息', 'admin/Notice/noticeInfo', 200),
    (222, 210, '公告添加', 'admin/Notice/noticeAdd', 200),
    (223, 210, '公告修改', 'admin/Notice/noticeEdit', 200),
    (224, 210, '公告删除', 'admin/Notice/noticeDele', 200),
    (225, 210, '公告禁用', 'admin/Notice/noticeDisable', 200),
    (230, 210, '图片导航列表', 'admin/ImageNav/navList', 180),
    (231, 210, '图片导航信息', 'admin/ImageNav/navInfo', 200),
    (232, 210, '图片导航添加', 'admin/ImageNav/navAdd', 200),
    (233, 210, '图片导航修改', 'admin/ImageNav/navEdit', 200),
    (234, 210, '图片导航删除', 'admin/ImageNav/navDele', 200),
    (235, 210, '图片导航禁用', 'admin/ImageNav/navDisable', 200),
    (240, 210, '模板消息列表', 'admin/MsgTemplate/tplList', 170),
    (241, 210, '模板消息信息', 'admin/MsgTemplate/tplInfo', 200),
    (242, 210, '模板消息添加', 'admin/MsgTemplate/tplAdd', 200),
    (243, 210, '模板消息修改', 'admin/MsgTemplate/tplEdit', 200),
    (244, 210, '模板消息删除', 'admin/MsgTemplate/tplDele', 200),
    (245, 210, '模板消息禁用', 'admin/MsgTemplate/tplDisable', 200),
]

cur.execute("DELETE FROM ya_admin_menu WHERE admin_menu_id >= 200")
cur.executemany(
    "INSERT INTO ya_admin_menu (admin_menu_id,menu_pid,menu_name,menu_url,menu_sort,is_disable,is_unauth,is_delete,create_time) VALUES (?,?,?,?,?,0,0,0,?)",
    [(m[0], m[1], m[2], m[3], m[4], now) for m in menus],
)

conn.commit()

# 统计
for t in ['ya_customer_service', 'ya_banner', 'ya_notice', 'ya_image_nav', 'ya_msg_template', 'ya_admin_menu']:
    print(t, cur.execute("SELECT COUNT(*) FROM " + t).fetchone()[0])
conn.close()

# 清理菜单缓存
if os.path.isdir(CACHE):
    shutil.rmtree(CACHE)
    print('cleared cache:', CACHE)
