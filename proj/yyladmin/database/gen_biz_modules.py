#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""生成业务模块：数据表 + 控制器 + 服务 + 后台菜单 + 角色权限 + SPA 配置(modules.js)"""
import os
import json
import sqlite3
import shutil

ROOT = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(ROOT, 'yyladmin.db')
CACHE = os.path.join(ROOT, '..', 'runtime', 'cache')
CTRL_DIR = os.path.join(ROOT, '..', 'app', 'admin', 'controller')
SVC_DIR = os.path.join(ROOT, '..', 'app', 'admin', 'service')
JS_FILE = os.path.join(ROOT, '..', 'public', 'adminui', 'modules.js')

now = '2026-10-15 23:30:00'

# ------------------------------------------------------------------
# 模块定义
#   key      前端模块标识
#   cls      PHP 控制器/服务类名
#   prefix   PHP 方法前缀
#   table    数据库表后缀（Db::name 名）
#   id       主键字段
#   name     中文名
#   group    所属分组（仅用于菜单/前端展示）
#   cols     字段列表：(字段, 类型 s/d)
#   search   列表搜索字段
#   seed     演示数据
#   colsJS   前端列表列
#   formJS   前端表单字段
# ------------------------------------------------------------------

def C(f, t, req=0, typ=''):
    # 兼容两种用法：C(f,t,1) 表示必填；C(f,t,'image') 表示控件/展示类型
    if isinstance(req, str):
        typ = req
        req = 0
    d = {'f': f, 't': t, 'req': req}
    if typ:
        d['type'] = typ
    return d

MODULES = [
    dict(key='order', cls='Order', prefix='order', table='order', id='order_id', name='订单管理', group='订单管理',
         cols=[('order_no', 's'), ('member_name', 's'), ('player_name', 's'), ('goods_name', 's'),
               ('amount', 'd'), ('commission', 'd'), ('status', 'd'), ('pay_type', 'd'), ('remark', 's')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('member_name', '会员'), C('player_name', '打手'), C('goods_name', '商品'),
                 C('amount', '金额'), C('commission', '佣金'), C('status', '状态', 'status'),
                 C('pay_type', '支付方式', 'pay_type')],
         formJS=[C('order_no', '订单号', 1), C('member_name', '会员'), C('player_name', '打手'), C('goods_name', '商品'),
                 C('amount', '金额', 0, 'number'), C('commission', '佣金', 0, 'number'),
                 C('status', '状态', 0, 'select_status'), C('pay_type', '支付方式', 0, 'select_pay'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('SY20261015001', '小明', '打手阿强', '王者荣耀代练', 120, 12, 2, 1, '已完成'),
               ('SY20261015002', '小红', '打手阿杰', '和平精英陪玩', 80, 8, 1, 2, '进行中'),
               ('SY20261015003', '小刚', '打手阿强', '英雄联盟上分', 200, 20, 3, 1, '待接单')]),

    dict(key='withdraw', cls='Withdraw', prefix='withdraw', table='withdraw', id='withdraw_id', name='提现记录', group='提现记录',
         cols=[('member_name', 's'), ('money', 'd'), ('fee', 'd'), ('real_money', 'd'), ('account', 's'), ('status', 'd'), ('remark', 's')],
         search='member_name', sort_default=300,
         colsJS=[C('member_name', '会员'), C('money', '提现金额'), C('fee', '手续费'), C('real_money', '到账金额'),
                 C('account', '收款账号'), C('status', '状态', 'status')],
         formJS=[C('member_name', '会员', 1), C('money', '提现金额', 0, 'number'), C('fee', '手续费', 0, 'number'),
                 C('real_money', '到账金额', 0, 'number'), C('account', '收款账号'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('小明', 100, 1, 99, 'wx_xiaoming', 1, '微信提现'),
               ('小红', 500, 5, 495, 'alipay_hong', 3, '已到账'),
               ('小刚', 50, 0.5, 49.5, 'wx_gang', 2, '审核中')]),

    dict(key='complaint', cls='OrderComplaint', prefix='complaint', table='order_complaint', id='complaint_id', name='订单投诉', group='订单投诉',
         cols=[('order_no', 's'), ('member_name', 's'), ('player_name', 's'), ('content', 's'), ('reply', 's'), ('status', 'd')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('member_name', '会员'), C('player_name', '打手'), C('content', '投诉内容'),
                 C('reply', '回复'), C('status', '状态', 'status')],
         formJS=[C('order_no', '订单号', 1), C('member_name', '会员'), C('player_name', '打手'),
                 C('content', '投诉内容', 0, 'textarea'), C('reply', '回复', 0, 'textarea'),
                 C('status', '状态', 0, 'select_status')],
         seed=[('SY20261015001', '小明', '打手阿强', '打手接单后未按时完成', '已联系打手处理', 2),
               ('SY20261015002', '小红', '打手阿杰', '沟通态度不好', '', 1)]),

    dict(key='memberLevel', cls='MemberLevel', prefix='level', table='member_level', id='level_id', name='会员等级管理', group='会员等级管理',
         cols=[('level_name', 's'), ('level_img', 's'), ('level_num', 'd'), ('discount', 'd'), ('remark', 's')],
         search='level_name', sort_default=300,
         colsJS=[C('level_img', '图标', 'image'), C('level_name', '等级名称'), C('level_num', '等级值'),
                 C('discount', '折扣'), C('sort', '排序')],
         formJS=[C('level_name', '等级名称', 1), C('level_img', '图标URL'), C('level_num', '等级值', 0, 'number'),
                 C('discount', '折扣(如0.9)', 0, 'number'), C('sort', '排序', 0, 'number'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('普通会员', '', 1, 1, '默认等级'),
               ('黄金会员', '', 2, 0.95, '消费满1000'),
               ('钻石会员', '', 3, 0.9, '消费满5000')]),

    dict(key='fine', cls='Fine', prefix='fine', table='fine', id='fine_id', name='罚款管理', group='罚款管理',
         cols=[('player_name', 's'), ('money', 'd'), ('reason', 's'), ('status', 'd'), ('remark', 's')],
         search='player_name', sort_default=300,
         colsJS=[C('player_name', '打手'), C('money', '罚款金额'), C('reason', '罚款原因'),
                 C('status', '状态', 'status')],
         formJS=[C('player_name', '打手', 1), C('money', '罚款金额', 0, 'number'), C('reason', '罚款原因', 0, 'textarea'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('打手阿强', 20, '接单后超时未完成', 1, ''), ('打手阿杰', 10, '辱骂客户', 2, '已扣除')]),

    dict(key='player', cls='Player', prefix='player', table='player', id='player_id', name='打手管理', group='打手管理',
         cols=[('nickname', 's'), ('avatar', 's'), ('phone', 's'), ('account', 's'), ('level_name', 's'),
               ('score', 'd'), ('balance', 'd'), ('is_online', 'd'), ('status', 'd'), ('remark', 's')],
         search='nickname', sort_default=300,
         colsJS=[C('nickname', '昵称'), C('avatar', '头像', 'image'), C('phone', '手机'), C('account', '账号'),
                 C('level_name', '等级'), C('score', '评分'), C('balance', '余额'),
                 C('is_online', '在线', 'online'), C('status', '状态', 'status')],
         formJS=[C('nickname', '昵称', 1), C('avatar', '头像URL'), C('phone', '手机'), C('account', '账号'),
                 C('level_name', '等级'), C('score', '评分', 0, 'number'), C('balance', '余额', 0, 'number'),
                 C('is_online', '在线状态', 0, 'select_online'), C('status', '状态', 0, 'select_status'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('打手阿强', '', '13900000001', 'qiang', '钻石打手', 4.9, 320, 1, 1, '王者荣耀'),
               ('打手阿杰', '', '13900000002', 'jie', '黄金打手', 4.7, 180, 0, 1, '和平精英'),
               ('打手小美', '', '13900000003', 'mei', '普通打手', 4.5, 60, 1, 1, '')]),

    dict(key='goods', cls='Goods', prefix='goods', table='goods', id='goods_id', name='商品', group='商品管理',
         cols=[('goods_name', 's'), ('category_name', 's'), ('category_pid', 'd'), ('category_id', 'd'), ('area', 's'),
               ('image', 's'), ('price', 'd'), ('stock', 'd'), ('unit', 's'), ('remark', 's')],
         search='goods_name', sort_default=300,
         colsJS=[C('image', '图片', 'image'), C('goods_name', '商品名称'), C('area', '区服'), C('category_name', '分类'),
                 C('price', '价格'), C('stock', '库存'), C('unit', '单位')],
         formJS=[C('goods_name', '商品名称', 1), C('area', '区服（自由填写）'),
                 C('category_pid', '一级分类', 0, 'catselect'), C('category_id', '二级分类', 0, 'subcatselect'),
                 C('category_name', '分类名称', 0, 'hidden'), C('image', '图片URL'),
                 C('price', '价格', 0, 'number'), C('stock', '库存', 0, 'number'), C('unit', '单位'),
                 C('sort', '排序', 0, 'number'), C('remark', '备注', 0, 'textarea')],
         seed=[('王者荣耀代练', '游戏代练 / 王者荣耀', 1, 4, '微信区', '', 100, 999, '单', ''),
               ('和平精英陪玩', '游戏陪玩 / 和平精英', 2, 5, 'QQ区', '', 50, 999, '小时', ''),
               ('英雄联盟上分', '游戏代练 / 英雄联盟', 1, 6, '全区', '', 150, 999, '单', '')]),

    dict(key='category', cls='Category', prefix='category', table='category', id='category_id', name='分类管理', group='商品管理',
         cols=[('category_name', 's'), ('category_pid', 'd'), ('image', 's'), ('remark', 's')],
         search='category_name', sort_default=300,
         colsJS=[C('image', '图标', 'image'), C('category_name', '分类名称'), C('category_pid', '上级分类', 'catparentname'),
                 C('sort', '排序')],
         formJS=[C('category_name', '分类名称', 1), C('category_pid', '上级分类', 0, 'catparent'),
                 C('image', '图标URL'), C('sort', '排序', 0, 'number'), C('remark', '备注', 0, 'textarea')],
         seed=[('游戏代练', 0, '', ''), ('游戏陪玩', 0, '', ''), ('游戏账号', 0, '', ''),
               ('王者荣耀代练', 1, '', ''), ('和平精英陪玩', 2, '', ''), ('英雄联盟代练', 1, '', '')]),

    dict(key='prize', cls='Prize', prefix='prize', table='prize', id='prize_id', name='奖池', group='商品管理',
         cols=[('prize_name', 's'), ('goods_name', 's'), ('image', 's'), ('rate', 'd'), ('stock', 'd'), ('remark', 's')],
         search='prize_name', sort_default=300,
         colsJS=[C('image', '图片', 'image'), C('prize_name', '奖品名称'), C('goods_name', '关联商品'),
                 C('rate', '中奖概率'), C('stock', '数量')],
         formJS=[C('prize_name', '奖品名称', 1), C('goods_name', '关联商品'), C('image', '图片URL'),
                 C('rate', '中奖概率(%)', 0, 'number'), C('stock', '数量', 0, 'number'),
                 C('sort', '排序', 0, 'number'), C('remark', '备注', 0, 'textarea')],
         seed=[('一等奖', '王者荣耀代练', '', 5, 10, ''), ('二等奖', '和平精英陪玩', '', 20, 100, ''),
               ('谢谢参与', '', '', 75, 9999, '')]),

    dict(key='balanceLog', cls='BalanceLog', prefix='balanceLog', table='balance_log', id='log_id', name='余额明细', group='财务管理',
         cols=[('member_name', 's'), ('type', 'd'), ('money', 'd'), ('before_money', 'd'), ('after_money', 'd'), ('remark', 's')],
         search='member_name', sort_default=300,
         colsJS=[C('member_name', '会员'), C('type', '类型', 'flow_type'), C('money', '变动金额'),
                 C('before_money', '变动前'), C('after_money', '变动后'), C('remark', '备注'),
                 C('create_time', '时间')],
         formJS=[C('member_name', '会员', 1), C('type', '类型', 0, 'select_flow'), C('money', '变动金额', 0, 'number'),
                 C('before_money', '变动前', 0, 'number'), C('after_money', '变动后', 0, 'number'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('小明', 1, 100, 0, 100, '充值'), ('小明', 2, -120, 100, -20, '下单消费'),
               ('小红', 1, 500, 0, 500, '充值')]),

    dict(key='recharge', cls='Recharge', prefix='recharge', table='recharge', id='recharge_id', name='充值订单', group='财务管理',
         cols=[('order_no', 's'), ('member_name', 's'), ('money', 'd'), ('pay_type', 'd'), ('status', 'd'), ('remark', 's')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('member_name', '会员'), C('money', '金额'),
                 C('pay_type', '支付方式', 'pay_type'), C('status', '状态', 'status'), C('create_time', '时间')],
         formJS=[C('order_no', '订单号', 1), C('member_name', '会员'), C('money', '金额', 0, 'number'),
                 C('pay_type', '支付方式', 0, 'select_pay'), C('status', '状态', 0, 'select_status'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('CZ20261015001', '小明', 100, 1, 2, ''), ('CZ20261015002', '小红', 500, 2, 2, ''),
               ('CZ20261015003', '小刚', 50, 1, 1, '待支付')]),

    dict(key='commission', cls='OrderCommission', prefix='commission', table='order_commission', id='commission_id', name='订单佣金', group='财务管理',
         cols=[('order_no', 's'), ('player_name', 's'), ('order_amount', 'd'), ('rate', 'd'), ('commission', 'd'), ('status', 'd')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('player_name', '打手'), C('order_amount', '订单金额'),
                 C('rate', '佣金比例'), C('commission', '佣金'), C('status', '状态', 'status')],
         formJS=[C('order_no', '订单号', 1), C('player_name', '打手'), C('order_amount', '订单金额', 0, 'number'),
                 C('rate', '佣金比例', 0, 'number'), C('commission', '佣金', 0, 'number'),
                 C('status', '状态', 0, 'select_status')],
         seed=[('SY20261015001', '打手阿强', 120, 10, 12, 2), ('SY20261015002', '打手阿杰', 80, 10, 8, 1)]),

    dict(key='commissionLog', cls='CommissionLog', prefix='clog', table='commission_log', id='clog_id', name='佣金明细', group='财务管理',
         cols=[('player_name', 's'), ('type', 'd'), ('money', 'd'), ('before_money', 'd'), ('after_money', 'd'), ('remark', 's')],
         search='player_name', sort_default=300,
         colsJS=[C('player_name', '打手'), C('type', '类型', 'flow_type'), C('money', '变动金额'),
                 C('before_money', '变动前'), C('after_money', '变动后'), C('remark', '备注'), C('create_time', '时间')],
         formJS=[C('player_name', '打手', 1), C('type', '类型', 0, 'select_flow'), C('money', '变动金额', 0, 'number'),
                 C('before_money', '变动前', 0, 'number'), C('after_money', '变动后', 0, 'number'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('打手阿强', 1, 12, 308, 320, '订单佣金'), ('打手阿杰', 2, -10, 190, 180, '罚款扣除')]),

    dict(key='deposit', cls='Deposit', prefix='deposit', table='deposit', id='deposit_id', name='保证金记录', group='财务管理',
         cols=[('player_name', 's'), ('type', 'd'), ('money', 'd'), ('before_money', 'd'), ('after_money', 'd'), ('status', 'd'), ('remark', 's')],
         search='player_name', sort_default=300,
         colsJS=[C('player_name', '打手'), C('type', '类型', 'flow_type'), C('money', '变动金额'),
                 C('before_money', '变动前'), C('after_money', '变动后'), C('status', '状态', 'status')],
         formJS=[C('player_name', '打手', 1), C('type', '类型', 0, 'select_flow'), C('money', '变动金额', 0, 'number'),
                 C('before_money', '变动前', 0, 'number'), C('after_money', '变动后', 0, 'number'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('打手阿强', 1, 200, 0, 200, 1, '入驻缴纳'), ('打手阿杰', 2, -50, 200, 150, 2, '违规扣除')]),

    dict(key='memberOrder', cls='MemberOrder', prefix='morder', table='member_order', id='morder_id', name='会员订单', group='财务管理',
         cols=[('order_no', 's'), ('member_name', 's'), ('level_name', 's'), ('money', 'd'), ('months', 'd'), ('status', 'd'), ('remark', 's')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('member_name', '会员'), C('level_name', '等级'),
                 C('money', '金额'), C('months', '时长(月)'), C('status', '状态', 'status')],
         formJS=[C('order_no', '订单号', 1), C('member_name', '会员'), C('level_name', '等级'),
                 C('money', '金额', 0, 'number'), C('months', '时长(月)', 0, 'number'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('HY20261015001', '小明', '黄金会员', 99, 12, 2, ''), ('HY20261015002', '小红', '钻石会员', 599, 12, 2, '')]),

    dict(key='attach', cls='Attachment', prefix='attach', table='attachment', id='attachment_id', name='附件管理', group='常规管理',
         cols=[('name', 's'), ('path', 's'), ('size', 'd'), ('mime', 's'), ('remark', 's')],
         search='name', sort_default=300,
         colsJS=[C('attachment_id', 'ID'), C('name', '文件名'), C('path', '路径'), C('size', '大小'),
                 C('mime', '类型'), C('create_time', '上传时间')],
         formJS=[C('name', '文件名', 1), C('path', '路径'), C('size', '大小', 0, 'number'), C('mime', '类型'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('默认头像', 'static/img/favicon.ico', 1024, 'image/x-icon', ''),
               ('登录背景', 'static/img/yyladmin_login.jpg', 34000, 'image/jpeg', '')]),

    dict(key='plugin', cls='Plugin', prefix='plugin', table='plugin', id='plugin_id', name='插件管理', group='常规管理',
         cols=[('plugin_name', 's'), ('code', 's'), ('version', 's'), ('author', 's'), ('status', 'd'), ('remark', 's')],
         search='plugin_name', sort_default=300,
         colsJS=[C('plugin_name', '插件名称'), C('code', '标识'), C('version', '版本'), C('author', '作者'),
                 C('status', '状态', 'status')],
         formJS=[C('plugin_name', '插件名称', 1), C('code', '标识'), C('version', '版本'), C('author', '作者'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('短信通知', 'sms_notify', '1.0.0', '砚世易游pro', 1, ''),
               ('微信支付', 'wx_pay', '1.2.0', '砚世易游pro', 1, '')]),

    # ================= 数据统计 =================
    dict(key='memberStat', cls='MemberStat', prefix='memberStat', table='member_stat', id='stat_id', name='会员统计', group='数据统计', nodele=1,
         cols=[('stat_date', 's'), ('total_member', 'd'), ('new_member', 'd'), ('active_member', 'd'), ('growth_rate', 'd')],
         search='stat_date', sort_default=300,
         colsJS=[C('stat_date', '日期'), C('total_member', '会员总数'), C('new_member', '新增会员'),
                 C('active_member', '活跃会员'), C('growth_rate', '增长率(%)')],
         formJS=[C('stat_date', '日期', 1), C('total_member', '会员总数', 0, 'number'),
                 C('new_member', '新增会员', 0, 'number'), C('active_member', '活跃会员', 0, 'number'),
                 C('growth_rate', '增长率(%)', 0, 'number')],
         seed=[('2026-10-13', 1120, 28, 260, 2.5), ('2026-10-14', 1160, 40, 288, 3.6),
               ('2026-10-15', 1210, 50, 305, 4.3)]),

    dict(key='orderStat', cls='OrderStat', prefix='orderStat', table='order_stat', id='stat_id', name='订单统计', group='数据统计', nodele=1,
         cols=[('stat_date', 's'), ('order_count', 'd'), ('order_amount', 'd'), ('finish_count', 'd'), ('cancel_count', 'd')],
         search='stat_date', sort_default=300,
         colsJS=[C('stat_date', '日期'), C('order_count', '订单数'), C('order_amount', '订单金额'),
                 C('finish_count', '完成数'), C('cancel_count', '取消数')],
         formJS=[C('stat_date', '日期', 1), C('order_count', '订单数', 0, 'number'),
                 C('order_amount', '订单金额', 0, 'number'), C('finish_count', '完成数', 0, 'number'),
                 C('cancel_count', '取消数', 0, 'number')],
         seed=[('2026-10-13', 120, 9600, 110, 5), ('2026-10-14', 150, 12000, 140, 6),
               ('2026-10-15', 180, 15600, 170, 4)]),

    dict(key='financeReport', cls='FinanceReport', prefix='financeReport', table='finance_report', id='report_id', name='财务报表', group='数据统计', nodele=1,
         cols=[('report_date', 's'), ('income', 'd'), ('expense', 'd'), ('refund', 'd'), ('profit', 'd')],
         search='report_date', sort_default=300,
         colsJS=[C('report_date', '日期'), C('income', '收入'), C('expense', '支出'),
                 C('refund', '退款'), C('profit', '利润')],
         formJS=[C('report_date', '日期', 1), C('income', '收入', 0, 'number'), C('expense', '支出', 0, 'number'),
                 C('refund', '退款', 0, 'number'), C('profit', '利润', 0, 'number')],
         seed=[('2026-10-13', 9600, 3200, 200, 6200), ('2026-10-14', 12000, 4000, 300, 7700),
               ('2026-10-15', 15600, 5200, 150, 10250)]),

    dict(key='inviteChannel', cls='InviteChannel', prefix='inviteChannel', table='invite_channel', id='channel_id', name='邀请渠道统计', group='数据统计', nodele=1,
         cols=[('channel_name', 's'), ('invite_count', 'd'), ('register_count', 'd'), ('order_count', 'd'), ('amount', 'd')],
         search='channel_name', sort_default=300,
         colsJS=[C('channel_name', '渠道'), C('invite_count', '邀请数'), C('register_count', '注册数'),
                 C('order_count', '下单数'), C('amount', '成交金额')],
         formJS=[C('channel_name', '渠道名称', 1), C('invite_count', '邀请数', 0, 'number'),
                 C('register_count', '注册数', 0, 'number'), C('order_count', '下单数', 0, 'number'),
                 C('amount', '成交金额', 0, 'number')],
         seed=[('微信朋友圈', 320, 180, 90, 8600), ('QQ群', 210, 120, 60, 5200),
               ('抖音短视频', 460, 260, 130, 13800)]),

    # ================= 权限管理 =================
    dict(key='loginSecurity', cls='LoginSecurity', prefix='loginSecurity', table='login_security', id='security_id', name='登录安全管理', group='权限管理',
         cols=[('username', 's'), ('ip', 's'), ('region', 's'), ('device', 's'), ('login_time', 's'), ('status', 'd'), ('remark', 's')],
         search='username', sort_default=300,
         colsJS=[C('username', '账号'), C('ip', 'IP'), C('region', '地区'), C('device', '设备'),
                 C('login_time', '登录时间'), C('status', '状态', 'status')],
         formJS=[C('username', '账号', 1), C('ip', 'IP'), C('region', '地区'), C('device', '设备'),
                 C('login_time', '登录时间'), C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('admin', '192.168.1.10', '广东深圳', 'Chrome / Windows', '2026-10-15 09:12', 2, ''),
               ('admin', '220.181.38.9', '北京', 'Safari / iPhone', '2026-10-15 18:40', 1, '异地登录')]),

    # ================= 财务管理 =================
    dict(key='refund', cls='Refund', prefix='refund', table='refund', id='refund_id', name='退款管理', group='财务管理',
         cols=[('order_no', 's'), ('member_name', 's'), ('refund_money', 'd'), ('reason', 's'), ('status', 'd'), ('remark', 's')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('member_name', '会员'), C('refund_money', '退款金额'),
                 C('reason', '退款原因'), C('status', '状态', 'status')],
         formJS=[C('order_no', '订单号', 1), C('member_name', '会员'), C('refund_money', '退款金额', 0, 'number'),
                 C('reason', '退款原因', 0, 'textarea'), C('status', '状态', 0, 'select_status'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('SY20261015001', '小明', 120, '打手未完成接单', 2, ''), ('SY20261015003', '小刚', 200, '客户申请退款', 1, '')]),

    dict(key='financeRecon', cls='FinanceRecon', prefix='financeRecon', table='finance_recon', id='recon_id', name='财务对账', group='财务管理',
         cols=[('recon_date', 's'), ('order_amount', 'd'), ('refund_amount', 'd'), ('commission', 'd'), ('settle_amount', 'd'), ('status', 'd')],
         search='recon_date', sort_default=300,
         colsJS=[C('recon_date', '对账日期'), C('order_amount', '订单金额'), C('refund_amount', '退款金额'),
                 C('commission', '佣金'), C('settle_amount', '结算金额'), C('status', '状态', 'status')],
         formJS=[C('recon_date', '对账日期', 1), C('order_amount', '订单金额', 0, 'number'),
                 C('refund_amount', '退款金额', 0, 'number'), C('commission', '佣金', 0, 'number'),
                 C('settle_amount', '结算金额', 0, 'number'), C('status', '状态', 0, 'select_status')],
         seed=[('2026-10-14', 12000, 300, 1200, 10500, 2), ('2026-10-15', 15600, 150, 1560, 13890, 1)]),

    # ================= 商品管理 =================
    dict(key='goodsStock', cls='GoodsStock', prefix='goodsStock', table='goods_stock', id='stock_id', name='商品库存', group='商品管理',
         cols=[('goods_name', 's'), ('sku', 's'), ('stock', 'd'), ('warning_stock', 'd'), ('unit', 's'), ('status', 'd')],
         search='goods_name', sort_default=300,
         colsJS=[C('goods_name', '商品名称'), C('sku', '规格'), C('stock', '库存'), C('warning_stock', '预警值'),
                 C('unit', '单位'), C('status', '状态', 'status')],
         formJS=[C('goods_name', '商品名称', 1), C('sku', '规格'), C('stock', '库存', 0, 'number'),
                 C('warning_stock', '预警值', 0, 'number'), C('unit', '单位'), C('status', '状态', 0, 'select_status')],
         seed=[('王者荣耀代练', '默认', 999, 50, '单', 2), ('和平精英陪玩', '默认', 999, 50, '小时', 2),
               ('英雄联盟上分', '默认', 20, 50, '单', 1)]),

    dict(key='goodsAudit', cls='GoodsAudit', prefix='goodsAudit', table='goods_audit', id='audit_id', name='商品审核', group='商品管理',
         cols=[('goods_name', 's'), ('player_name', 's'), ('price', 'd'), ('images', 's'), ('status', 'd'), ('remark', 's')],
         search='goods_name', sort_default=300,
         colsJS=[C('goods_name', '商品名称'), C('player_name', '所属打手'), C('price', '价格'),
                 C('status', '审核状态', 'status'), C('remark', '备注')],
         formJS=[C('goods_name', '商品名称', 1), C('player_name', '所属打手'), C('price', '价格', 0, 'number'),
                 C('images', '商品图片URL'), C('status', '审核状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('钻石代练包', '打手阿强', 199, '', 1, '待审核'), ('陪玩专属包', '打手小美', 88, '', 2, '已通过')]),

    # ================= 订单管理 =================
    dict(key='refundAudit', cls='RefundAudit', prefix='refundAudit', table='refund_audit', id='audit_id', name='退款审核', group='订单管理',
         cols=[('order_no', 's'), ('member_name', 's'), ('refund_money', 'd'), ('reason', 's'), ('status', 'd'), ('remark', 's')],
         search='order_no', sort_default=300,
         colsJS=[C('order_no', '订单号'), C('member_name', '会员'), C('refund_money', '退款金额'),
                 C('reason', '退款原因'), C('status', '审核状态', 'status')],
         formJS=[C('order_no', '订单号', 1), C('member_name', '会员'), C('refund_money', '退款金额', 0, 'number'),
                 C('reason', '退款原因', 0, 'textarea'), C('status', '审核状态', 0, 'select_status'),
                 C('remark', '审核备注', 0, 'textarea')],
         seed=[('SY20261015003', '小刚', 200, '客户申请退款', 1, ''), ('SY20261015001', '小明', 120, '打手未完成', 2, '已同意')]),

    # ================= 会员管理 =================
    dict(key='inviteCode', cls='InviteCode', prefix='inviteCode', table='invite_code', id='code_id', name='邀请码管理', group='会员管理',
         cols=[('code', 's'), ('member_name', 's'), ('used_num', 'd'), ('max_num', 'd'), ('status', 'd'), ('remark', 's')],
         search='code', sort_default=300, autogen='code',
         colsJS=[C('code', '邀请码'), C('member_name', '所属会员'), C('used_num', '已使用'),
                 C('max_num', '可用次数'), C('status', '状态', 'status')],
         formJS=[C('code', '邀请码', 1, 'codegen'), C('member_name', '所属会员'), C('used_num', '已使用', 0, 'number'),
                 C('max_num', '可用次数', 0, 'number'), C('status', '状态', 0, 'select_status'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('XT888', '小明', 3, 10, 2, ''), ('XT999', '小红', 10, 10, 3, '已用完')]),

    dict(key='inviteRecord', cls='InviteRecord', prefix='inviteRecord', table='invite_record', id='record_id', name='邀请记录', group='会员管理',
         cols=[('invite_code', 's'), ('inviter', 's'), ('invitee', 's'), ('ip', 's'), ('status', 'd'), ('remark', 's')],
         search='invite_code', sort_default=300,
         colsJS=[C('invite_code', '邀请码'), C('inviter', '邀请人'), C('invitee', '被邀请人'),
                 C('ip', '注册IP'), C('status', '状态', 'status')],
         formJS=[C('invite_code', '邀请码', 1), C('inviter', '邀请人'), C('invitee', '被邀请人'), C('ip', '注册IP'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('XT888', '小明', '小刚', '192.168.1.22', 2, ''), ('XT999', '小红', '小美', '192.168.1.33', 1, '')]),

    dict(key='inviteReward', cls='InviteReward', prefix='inviteReward', table='invite_reward', id='reward_id', name='邀请奖励配置', group='会员管理',
         cols=[('reward_name', 's'), ('reward_type', 's'), ('reward_money', 'd'), ('condition', 's'), ('status', 'd'), ('remark', 's')],
         search='reward_name', sort_default=300,
         colsJS=[C('reward_name', '奖励名称'), C('reward_type', '奖励类型'), C('reward_money', '奖励金额'),
                 C('condition', '达成条件'), C('status', '状态', 'status')],
         formJS=[C('reward_name', '奖励名称', 1), C('reward_type', '奖励类型'), C('reward_money', '奖励金额', 0, 'number'),
                 C('condition', '达成条件'), C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('邀请注册奖励', '余额', 5, '好友注册成功', 2, ''), ('邀请下单奖励', '余额', 10, '好友首次下单', 2, '')]),

    dict(key='memberBlacklist', cls='MemberBlacklist', prefix='memberBlacklist', table='member_blacklist', id='black_id', name='会员黑名单', group='会员管理',
         cols=[('member_name', 's'), ('phone', 's'), ('reason', 's'), ('operator', 's'), ('status', 'd'), ('remark', 's')],
         search='member_name', sort_default=300,
         colsJS=[C('member_name', '会员'), C('phone', '手机'), C('reason', '拉黑原因'), C('operator', '操作人'),
                 C('status', '状态', 'status')],
         formJS=[C('member_name', '会员', 1), C('phone', '手机'), C('reason', '拉黑原因', 0, 'textarea'),
                 C('operator', '操作人'), C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('小恶', '13800000000', '恶意刷单', 'admin', 2, ''), ('小坏', '13800000001', '辱骂客服', 'admin', 2, '')]),

    dict(key='stationLetter', cls='StationLetter', prefix='stationLetter', table='station_letter', id='letter_id', name='站内信管理', group='会员管理',
         cols=[('title', 's'), ('content', 's'), ('receiver', 's'), ('sender', 's'), ('is_read', 'd'), ('status', 'd')],
         search='title', sort_default=300,
         colsJS=[C('title', '标题'), C('content', '内容'), C('receiver', '接收人'), C('sender', '发送人'),
                 C('status', '状态', 'status')],
         formJS=[C('title', '标题', 1), C('content', '内容', 0, 'textarea'), C('receiver', '接收人'), C('sender', '发送人'),
                 C('status', '状态', 0, 'select_status')],
         seed=[('系统维护通知', '平台将于凌晨2点维护', '小明', '系统', 1, 2), ('活动奖励发放', '恭喜获得邀请奖励5元', '小红', '系统', 0, 2)]),

    dict(key='memberTag', cls='MemberTag', prefix='memberTag', table='member_tag', id='tag_id', name='会员标签', group='会员管理',
         cols=[('tag_name', 's'), ('color', 's'), ('member_num', 'd'), ('remark', 's')],
         search='tag_name', sort_default=300,
         colsJS=[C('tag_name', '标签名称'), C('color', '标签颜色'), C('member_num', '会员数'), C('remark', '备注')],
         formJS=[C('tag_name', '标签名称', 1), C('color', '标签颜色(如 #7c5cf6)'), C('member_num', '会员数', 0, 'number'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('高价值', '#f59e0b', 32, ''), ('活跃', '#1a9c54', 120, ''), ('流失风险', '#e5484d', 8, '')]),

    # ================= 自定义UI =================
    dict(key='popup', cls='Popup', prefix='popup', table='popup', id='popup_id', name='弹窗配置', group='自定义UI',
         cols=[('title', 's'), ('image', 's'), ('url', 's'), ('position', 's'), ('start_time', 's'), ('end_time', 's'), ('status', 'd')],
         search='title', sort_default=300,
         colsJS=[C('image', '弹窗图片', 'image'), C('title', '标题'), C('url', '跳转链接'), C('position', '位置'),
                 C('start_time', '开始时间'), C('end_time', '结束时间'), C('status', '状态', 'status')],
         formJS=[C('title', '标题', 1), C('image', '弹窗图片URL'), C('url', '跳转链接'), C('position', '展示位置'),
                 C('start_time', '开始时间'), C('end_time', '结束时间'), C('status', '状态', 0, 'select_status')],
         seed=[('国庆活动', '', '/pages/activity', '首页居中', '2026-10-01', '2026-10-31', 2),
               ('新客礼包', '', '/pages/gift', '首页底部', '2026-10-01', '2026-12-31', 1)]),

    # ================= 接口配置 =================
    dict(key='oauthLogin', cls='OauthLogin', prefix='oauthLogin', table='oauth_login', id='oauth_id', name='聚合登录', group='接口配置',
         cols=[('name', 's'), ('code', 's'), ('app_id', 's'), ('app_key', 's'), ('status', 'd'), ('remark', 's')],
         search='name', sort_default=300,
         colsJS=[C('name', '平台名称'), C('code', '标识'), C('app_id', 'AppID'), C('status', '状态', 'status')],
         formJS=[C('name', '平台名称', 1), C('code', '标识(如 qq/weixin)'), C('app_id', 'AppID'), C('app_key', 'AppKey'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('QQ登录', 'qq', '101xxxxxx', 'secret_qq', 2, ''), ('微信登录', 'weixin', 'wxxxxxx', 'secret_wx', 2, ''),
               ('手机号登录', 'phone', '', '', 1, '')]),

    dict(key='epay', cls='Epay', prefix='epay', table='epay', id='epay_id', name='易支付', group='接口配置',
         cols=[('name', 's'), ('api_url', 's'), ('pid', 's'), ('key', 's'), ('status', 'd'), ('remark', 's')],
         search='name', sort_default=300,
         colsJS=[C('name', '商户名称'), C('api_url', '接口地址'), C('pid', '商户ID'), C('status', '状态', 'status')],
         formJS=[C('name', '商户名称', 1), C('api_url', '接口地址'), C('pid', '商户ID'), C('key', '商户密钥'),
                 C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('易支付默认通道', 'https://pay.example.com', '10001', 'key_epay', 2, '')]),

    dict(key='wxMerchant', cls='WxMerchant', prefix='wxMerchant', table='wx_merchant', id='merchant_id', name='微信商户', group='接口配置',
         cols=[('merchant_name', 's'), ('mch_id', 's'), ('app_id', 's'), ('api_key', 's'), ('cert_path', 's'), ('status', 'd')],
         search='merchant_name', sort_default=300,
         colsJS=[C('merchant_name', '商户名称'), C('mch_id', '商户号'), C('app_id', 'AppID'), C('status', '状态', 'status')],
         formJS=[C('merchant_name', '商户名称', 1), C('mch_id', '商户号'), C('app_id', 'AppID'), C('api_key', 'API密钥'),
                 C('cert_path', '证书路径'), C('status', '状态', 0, 'select_status')],
         seed=[('小童电竞微信商户', '1600000000', 'wxa0000000', 'apikey_wx', '/cert/apiclient_cert.pem', 2)]),

    dict(key='smsConfig', cls='SmsConfig', prefix='smsConfig', table='sms_config', id='sms_id', name='短信配置', group='接口配置',
         cols=[('name', 's'), ('code', 's'), ('sign_name', 's'), ('access_key', 's'), ('access_secret', 's'), ('status', 'd')],
         search='name', sort_default=300,
         colsJS=[C('name', '服务商'), C('code', '标识'), C('sign_name', '签名'), C('status', '状态', 'status')],
         formJS=[C('name', '服务商名称', 1), C('code', '标识(如 aliyun)'), C('sign_name', '短信签名'),
                 C('access_key', 'AccessKey'), C('access_secret', 'AccessSecret'), C('status', '状态', 0, 'select_status')],
         seed=[('阿里云短信', 'aliyun', '小童电竞', 'LTAIxxxx', 'secret_sms', 2)]),

    # ================= 其他 =================
    dict(key='csSession', cls='CsSession', prefix='csSession', table='cs_session', id='session_id', name='客服会话记录', group='其他',
         cols=[('cs_name', 's'), ('member_name', 's'), ('content', 's'), ('duration', 'd'), ('status', 'd'), ('remark', 's')],
         search='cs_name', sort_default=300,
         colsJS=[C('cs_name', '客服'), C('member_name', '会员'), C('content', '最后消息'), C('duration', '时长(秒)'),
                 C('status', '状态', 'status'), C('create_time', '时间')],
         formJS=[C('cs_name', '客服', 1), C('member_name', '会员'), C('content', '最后消息', 0, 'textarea'),
                 C('duration', '时长(秒)', 0, 'number'), C('status', '状态', 0, 'select_status'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('客服小云', '小明', '你好，请问代练什么时候开始？', 320, 2, ''), ('客服小云', '小红', '好的谢谢', 180, 1, '')]),

    # ================= 风控管理 =================
    dict(key='riskRule', cls='RiskRule', prefix='riskRule', table='risk_rule', id='rule_id', name='风控规则配置', group='风控管理',
         cols=[('rule_name', 's'), ('rule_type', 's'), ('threshold', 'd'), ('action', 's'), ('status', 'd'), ('remark', 's')],
         search='rule_name', sort_default=300,
         colsJS=[C('rule_name', '规则名称'), C('rule_type', '规则类型'), C('threshold', '阈值'), C('action', '处理动作'),
                 C('status', '状态', 'status')],
         formJS=[C('rule_name', '规则名称', 1), C('rule_type', '规则类型'), C('threshold', '阈值', 0, 'number'),
                 C('action', '处理动作'), C('status', '状态', 0, 'select_status'), C('remark', '备注', 0, 'textarea')],
         seed=[('同IP多账号', 'IP限制', 5, '拦截下单', 2, ''), ('高频下单', '频率限制', 20, '人工审核', 2, '')]),

    dict(key='riskAccount', cls='RiskAccount', prefix='riskAccount', table='risk_account', id='risk_id', name='风险账号', group='风控管理',
         cols=[('member_name', 's'), ('risk_level', 'd'), ('risk_reason', 's'), ('handle_status', 'd'), ('remark', 's')],
         search='member_name', sort_default=300,
         colsJS=[C('member_name', '账号'), C('risk_level', '风险等级'), C('risk_reason', '风险原因'),
                 C('handle_status', '处理状态', 'status'), C('remark', '备注')],
         formJS=[C('member_name', '账号', 1), C('risk_level', '风险等级(1-3)', 0, 'number'),
                 C('risk_reason', '风险原因', 0, 'textarea'), C('handle_status', '处理状态', 0, 'select_status'),
                 C('remark', '备注', 0, 'textarea')],
         seed=[('小恶', 3, '多设备异地登录', 1, ''), ('小怪', 2, '频繁退款', 2, '已限制')]),

    dict(key='ipBlacklist', cls='IpBlacklist', prefix='ipBlacklist', table='ip_blacklist', id='ip_id', name='IP黑名单', group='风控管理',
         cols=[('ip', 's'), ('reason', 's'), ('operator', 's'), ('expire_time', 's'), ('status', 'd')],
         search='ip', sort_default=300,
         colsJS=[C('ip', 'IP地址'), C('reason', '封禁原因'), C('operator', '操作人'),
                 C('expire_time', '过期时间'), C('status', '状态', 'status')],
         formJS=[C('ip', 'IP地址', 1), C('reason', '封禁原因', 0, 'textarea'), C('operator', '操作人'),
                 C('expire_time', '过期时间'), C('status', '状态', 0, 'select_status')],
         seed=[('220.181.38.9', '恶意攻击', 'admin', '2026-12-31', 2), ('114.114.114.114', '刷单', 'admin', '2026-11-30', 2)]),

    dict(key='riskLog', cls='RiskLog', prefix='riskLog', table='risk_log', id='log_id', name='风控拦截日志', group='风控管理',
         cols=[('ip', 's'), ('member_name', 's'), ('rule_name', 's'), ('content', 's'), ('level', 'd'), ('status', 'd')],
         search='ip', sort_default=300,
         colsJS=[C('ip', 'IP'), C('member_name', '账号'), C('rule_name', '触发规则'), C('content', '拦截详情'),
                 C('level', '风险等级'), C('status', '状态', 'status'), C('create_time', '时间')],
         formJS=[C('ip', 'IP', 1), C('member_name', '账号'), C('rule_name', '触发规则'), C('content', '拦截详情', 0, 'textarea'),
                 C('level', '风险等级(1-3)', 0, 'number'), C('status', '状态', 0, 'select_status')],
         seed=[('220.181.38.9', '小恶', '同IP多账号', '同一IP注册5个账号，已拦截', 3, 2),
               ('114.114.114.114', '小怪', '高频下单', '1分钟下单20次，转人工', 2, 1)]),
]


# ------------------------------------------------------------------
# PHP 类名
# ------------------------------------------------------------------
def ctrl_name(m):
    return m['cls']


def svc_name(m):
    return m['cls'] + 'Service'


def param_lines(m, indent='            '):
    lines = []
    for (f, t) in m['cols']:
        if t == 's':
            lines.append("%s$param['%s'] = Request::param('%s/s', '');" % (indent, f, f))
        else:
            lines.append("%s$param['%s'] = Request::param('%s/d', 0);" % (indent, f, f))
    return '\n'.join(lines)


def build_controller(m):
    cls = ctrl_name(m)
    svc = svc_name(m)
    p = m['prefix']
    idf = m['id']
    params = param_lines(m, '            ')
    return """<?php
/*
 * @Description  : {name}
 */

namespace app\\admin\\controller;

use think\\facade\\Request;
use app\\admin\\service\\{svc};

class {cls}
{{
    /**
     * {name}列表
     */
    public function {p}List()
    {{
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('{search}/s', '');

        $where = [];
        if ($keyword) {{
            $where[] = ['{search}', 'like', '%' . $keyword . '%'];
        }}

        $order = [];
        if ($sort_field && $sort_type) {{
            $order = [$sort_field => $sort_type];
        }}

        $data = {svc}::list($where, $page, $limit, $order);

        return success($data);
    }}

    /**
     * {name}信息
     */
    public function {p}Info()
    {{
        $param['{idf}'] = Request::param('{idf}/d', '');

        $data = {svc}::info($param['{idf}']);

        return success($data);
    }}

    /**
     * {name}添加
     */
    public function {p}Add()
    {{
        if (Request::isGet()) {{
            $data = {svc}::add();
        }} else {{
{params}

            $data = {svc}::add($param, 'post');
        }}

        return success($data);
    }}

    /**
     * {name}修改
     */
    public function {p}Edit()
    {{
        $param['{idf}'] = Request::param('{idf}/d', '');

        if (Request::isGet()) {{
            $data = {svc}::edit($param);
        }} else {{
{params}

            $data = {svc}::edit($param, 'post');
        }}

        return success($data);
    }}

    /**
     * {name}删除
     */
    public function {p}Dele()
    {{
        $param['{idf}'] = Request::param('{idf}/d', '');

        $data = {svc}::dele($param['{idf}']);

        return success($data);
    }}

    /**
     * {name}是否禁用
     */
    public function {p}Disable()
    {{
        $param['{idf}']      = Request::param('{idf}/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = {svc}::disable($param);

        return success($data);
    }}
}}
""".format(name=m['name'], svc=svc, cls=cls, p=p, idf=idf, params=params, search=m['search'])


def build_service(m):
    cls = svc_name(m)
    table = m['table']
    idf = m['id']
    all_cols = [c[0] for c in m['cols']]
    field = ','.join([idf] + all_cols + ['sort', 'is_disable', 'create_time'])

    autogen = ''
    extra   = ''
    if m.get('autogen'):
        af = m['autogen']
        autogen = ("        if (empty($param['%s'])) {\n            $param['%s'] = self::randCode();\n        }\n" % (af, af))
        extra = """
    /**
     * 生成随机标识（大小写字母+数字混合）
     */
    public static function randCode($len = 8)
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code  = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
"""
    return """<?php
/*
 * @Description  : {name}
 */

namespace app\\admin\\service;

use think\\facade\\Db;

class {cls}
{{
    /**
     * {name}列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {{
        if (empty($field)) {{
            $field = '{field}';
        }}

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {{
            $order = ['sort' => 'desc', '{idf}' => 'desc'];
        }}

        $count = Db::name('{table}')->where($where)->count('{idf}');

        $list = Db::name('{table}')
            ->field($field)
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order($order)
            ->select()
            ->toArray();

        $data['count'] = $count;
        $data['pages'] = ceil($count / $limit);
        $data['page']  = $page;
        $data['limit'] = $limit;
        $data['list']  = $list;

        return $data;
    }}

    /**
     * {name}信息
     */
    public static function info($id)
    {{
        $info = Db::name('{table}')->where('{idf}', $id)->find();

        if (empty($info)) {{
            exception('数据不存在：' . $id);
        }}

        return $info;
    }}

    /**
     * {name}添加
     */
    public static function add($param = [], $method = 'get')
    {{
        if ($method == 'get') {{
            return [];
        }}

        unset($param['{idf}']);
        $param['create_time'] = date('Y-m-d H:i:s');
{autogen}
        $id = Db::name('{table}')->insertGetId($param);

        if (empty($id)) {{
            exception();
        }}

        $param['{idf}'] = $id;

        return $param;
    }}

    /**
     * {name}修改
     */
    public static function edit($param, $method = 'get')
    {{
        $id = $param['{idf}'];

        if ($method == 'get') {{
            return self::info($id);
        }}

        unset($param['{idf}']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('{table}')->where('{idf}', $id)->update($param);

        if ($res === false) {{
            exception();
        }}

        $param['{idf}'] = $id;

        return $param;
    }}

    /**
     * {name}删除
     */
    public static function dele($id)
    {{
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('{table}')->where('{idf}', $id)->update($update);

        if ($res === false) {{
            exception();
        }}

        return ['{idf}' => $id];
    }}

    /**
     * {name}是否禁用
     */
    public static function disable($param)
    {{
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('{table}')->where('{idf}', $param['{idf}'])->update($update);

        if ($res === false) {{
            exception();
        }}

        return $param;
    }}
{extra}}}
""".format(name=m['name'], cls=cls, table=table, idf=idf, field=field, autogen=autogen, extra=extra)


def sql_type(t):
    return 'TEXT' if t == 's' else 'INTEGER'


def build_table_sql(m):
    cols = []
    for (f, t) in m['cols']:
        cols.append("  `%s` %s DEFAULT ''" % (f, 'TEXT') if t == 's' else "  `%s` %s NOT NULL DEFAULT 0" % (f, 'INTEGER'))
    cols_sql = ',\n'.join(cols)
    return """CREATE TABLE IF NOT EXISTS ya_{table} (
  `{idf}` INTEGER PRIMARY KEY AUTOINCREMENT,
{cols_sql},
  `sort` INTEGER NOT NULL DEFAULT 200,
  `is_disable` INTEGER NOT NULL DEFAULT 0,
  `is_delete` INTEGER NOT NULL DEFAULT 0,
  `create_time` TEXT, `update_time` TEXT, `delete_time` TEXT
)""".format(table=m['table'], idf=m['id'], cols_sql=cols_sql)


# ------------------------------------------------------------------
# 1) 写 PHP 文件
# ------------------------------------------------------------------
for m in MODULES:
    with open(os.path.join(CTRL_DIR, ctrl_name(m) + '.php'), 'w', encoding='utf-8') as f:
        f.write(build_controller(m))
    with open(os.path.join(SVC_DIR, svc_name(m) + '.php'), 'w', encoding='utf-8') as f:
        f.write(build_service(m))
print('PHP files written:', len(MODULES) * 2)


# ------------------------------------------------------------------
# 2) 数据库：表 + 演示数据 + 菜单 + 角色权限
# ------------------------------------------------------------------
conn = sqlite3.connect(DB)
cur = conn.cursor()

for m in MODULES:
    cur.execute(build_table_sql(m))

    cnt = cur.execute("SELECT COUNT(*) FROM ya_%s" % m['table']).fetchone()[0]
    if cnt == 0:
        names = [c[0] for c in m['cols']] + ['is_disable', 'is_delete', 'create_time', 'update_time', 'delete_time']
        ph = ','.join(['?'] * len(names))
        rows = []
        for r in m['seed']:
            rows.append(tuple(r) + (0, 0, now, now, None))
        cur.executemany("INSERT INTO ya_%s (%s) VALUES (%s)" % (m['table'], ','.join(names), ph), rows)

# 兼容旧库：为已存在的表补充新增字段（子分类 / 区服）
migrate_cols = {
    'category': [('category_pid', 'INTEGER NOT NULL DEFAULT 0')],
    'goods': [('category_pid', 'INTEGER NOT NULL DEFAULT 0'),
              ('category_id', 'INTEGER NOT NULL DEFAULT 0'),
              ('area', "TEXT NOT NULL DEFAULT ''")],
}
for table, extra in migrate_cols.items():
    exists = [r[1] for r in cur.execute("PRAGMA table_info(ya_%s)" % table).fetchall()]
    for col, decl in extra:
        if col not in exists:
            cur.execute("ALTER TABLE ya_%s ADD COLUMN `%s` %s" % (table, col, decl))
            print('migrate: ya_%s += %s' % (table, col))

# 兼容旧库：客服表补充「客服类型」字段（im=内置客服，external=外置客服）
_cs_exists = [r[1] for r in cur.execute("PRAGMA table_info(ya_customer_service)").fetchall()]
if 'type' not in _cs_exists:
    cur.execute("ALTER TABLE ya_customer_service ADD COLUMN `type` TEXT NOT NULL DEFAULT 'im'")
    print('migrate: ya_customer_service += type')

# 兼容旧库：系统设置表补充「主题颜色」「客服设置」字段
_set_exists = [r[1] for r in cur.execute("PRAGMA table_info(ya_admin_setting)").fetchall()]
if 'admin_theme' not in _set_exists:
    cur.execute("ALTER TABLE ya_admin_setting ADD COLUMN `admin_theme` TEXT NULL DEFAULT ''")
    print('migrate: ya_admin_setting += admin_theme')
if 'admin_cs' not in _set_exists:
    cur.execute("ALTER TABLE ya_admin_setting ADD COLUMN `admin_cs` TEXT NULL DEFAULT ''")
    print('migrate: ya_admin_setting += admin_cs')

# 菜单：每个模块 1 个父级 + 6 个子项
cur.execute("DELETE FROM ya_admin_menu WHERE admin_menu_id >= 300")
menu_rows = []
mid = 300
for m in MODULES:
    p = m['prefix']
    parent = mid
    menu_rows.append((parent, 0, m['name'], '', 200))
    menu_rows.append((parent + 1, parent, m['name'] + '列表', 'admin/%s/%sList' % (m['cls'], p), 200))
    menu_rows.append((parent + 2, parent, m['name'] + '信息', 'admin/%s/%sInfo' % (m['cls'], p), 200))
    menu_rows.append((parent + 3, parent, m['name'] + '添加', 'admin/%s/%sAdd' % (m['cls'], p), 200))
    menu_rows.append((parent + 4, parent, m['name'] + '修改', 'admin/%s/%sEdit' % (m['cls'], p), 200))
    menu_rows.append((parent + 5, parent, m['name'] + '删除', 'admin/%s/%sDele' % (m['cls'], p), 200))
    menu_rows.append((parent + 6, parent, m['name'] + '禁用', 'admin/%s/%sDisable' % (m['cls'], p), 200))
    mid += 10

cur.executemany(
    "INSERT INTO ya_admin_menu (admin_menu_id,menu_pid,menu_name,menu_url,menu_sort,is_disable,is_unauth,is_delete,create_time) VALUES (?,?,?,?,?,0,0,0,?)",
    [r + (now,) for r in menu_rows],
)

# 系统设置：主题颜色、客服设置菜单（供后台权限校验）
for _name, _url in [
    ('主题颜色设置', 'admin/AdminSetting/settingTheme'),
    ('客服设置', 'admin/AdminSetting/settingCustomerService'),
]:
    _exists = cur.execute("SELECT admin_menu_id FROM ya_admin_menu WHERE menu_url=?", (_url,)).fetchone()
    if not _exists:
        _mid = cur.execute("SELECT COALESCE(MAX(admin_menu_id),0)+1 FROM ya_admin_menu WHERE admin_menu_id < 300").fetchone()[0]
        cur.execute(
            "INSERT INTO ya_admin_menu (admin_menu_id,menu_pid,menu_name,menu_url,menu_sort,is_disable,is_unauth,is_delete,create_time) VALUES (?,?,?,?,?,0,0,0,?)",
            (_mid, 53, _name, _url, 200, now),
        )
        print('menu +=', _url)

# 角色权限：把全部菜单授予 角色1(管理员)、角色2(演示，admin 账号使用)
all_ids = [str(r[0]) for r in cur.execute("SELECT admin_menu_id FROM ya_admin_menu WHERE is_delete=0 ORDER BY admin_menu_id")]
ids_str = ','.join(all_ids)
for rid in (1, 2):
    cur.execute("UPDATE ya_admin_role SET admin_menu_ids=? WHERE admin_role_id=?", (ids_str, rid))

conn.commit()

print('menus:', len(menu_rows), 'menu total:', len(all_ids))
for m in MODULES:
    print(' ', m['table'], cur.execute("SELECT COUNT(*) FROM ya_%s" % m['table']).fetchone()[0])
conn.close()


# ------------------------------------------------------------------
# 3) 生成前端配置 modules.js
# ------------------------------------------------------------------
STATUS = [['1', '待处理'], ['2', '已完成'], ['3', '处理中']]
PAY = [['1', '微信'], ['2', '支付宝'], ['3', '银行卡']]
ONLINE = [['1', '在线'], ['0', '离线']]
FLOW = [['1', '收入'], ['2', '支出']]

CUSTOM_INPUT = {
    'select_status': ('select', STATUS),
    'select_pay': ('select', PAY),
    'select_online': ('select', ONLINE),
    'select_flow': ('select', FLOW),
}

# 前端自定义控件类型（原样保留 type，由 adminui/index.html 渲染）
PASSTHROUGH_TYPES = ('catparent', 'catselect', 'subcatselect', 'hidden', 'codegen')

JS_MODULES = []


def js_module(m):
    cfg = {
        'group': m['group'],
        'name': m['name'],
        'apis': {
            'list': 'admin/%s/%sList' % (m['cls'], m['prefix']),
            'add': 'admin/%s/%sAdd' % (m['cls'], m['prefix']),
            'edit': 'admin/%s/%sEdit' % (m['cls'], m['prefix']),
            'info': 'admin/%s/%sInfo' % (m['cls'], m['prefix']),
            'dele': 'admin/%s/%sDele' % (m['cls'], m['prefix']),
            'disable': 'admin/%s/%sDisable' % (m['cls'], m['prefix']),
        },
        'idField': m['id'],
        'search': {'field': m['search'], 'ph': '搜索' + m['name']},
        'columns': [dict(c) for c in m['colsJS']],
        'form': [dict(c) for c in m['formJS']],
    }
    # 系统统计/报表类模块：只读，不显示编辑/禁用/删除按钮
    if m.get('nodele'):
        cfg['nodele'] = 1
    # 表单输入控件类型
    for f in cfg['form']:
        t = f.get('type')
        if t in CUSTOM_INPUT:
            kind, opts = CUSTOM_INPUT[t]
            f['type'] = kind
            f['opts'] = opts
        elif t == 'number':
            pass
        elif t == 'textarea':
            pass
        elif t in PASSTHROUGH_TYPES:
            pass
        else:
            f.pop('type', None)
    return m['key'], cfg


for m in MODULES:
    k, cfg = js_module(m)
    JS_MODULES.append((k, cfg))

# 保留原有 5 个模块的配置（会员/客服/轮播图/公告/图片导航/模板消息）
base_modules = {
    'member': {'group': '会员管理', 'name': '会员管理',
               'apis': {'list': 'admin/Member/memberList', 'add': 'admin/Member/memberAdd', 'edit': 'admin/Member/memberEdit', 'info': 'admin/Member/memberInfo', 'dele': 'admin/Member/memberDele', 'disable': 'admin/Member/memberDisable'},
               'idField': 'member_id', 'search': {'field': 'username', 'ph': '账号/手机/邮箱'},
               'columns': [{'f': 'member_id', 't': 'ID'}, {'f': 'username', 't': '账号'}, {'f': 'nickname', 't': '昵称'}, {'f': 'phone', 't': '手机'}, {'f': 'email', 't': '邮箱'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
               'form': [{'f': 'username', 't': '账号', 'req': 1}, {'f': 'nickname', 't': '昵称', 'req': 1}, {'f': 'password', 't': '密码', 'req': 1, 'only': 'add'}, {'f': 'phone', 't': '手机'}, {'f': 'email', 't': '邮箱'}]},
    'cs': {'group': '客服管理', 'name': '客服管理',
           'apis': {'list': 'admin/CustomerService/csList', 'add': 'admin/CustomerService/csAdd', 'edit': 'admin/CustomerService/csEdit', 'info': 'admin/CustomerService/csInfo', 'dele': 'admin/CustomerService/csDele', 'disable': 'admin/CustomerService/csDisable'},
           'idField': 'cs_id', 'search': {'field': 'name', 'ph': '客服名称/手机'},
           'columns': [{'f': 'avatar', 't': '头像', 'type': 'image'}, {'f': 'name', 't': '客服名称'}, {'f': 'type', 't': '客服类型', 'type': 'map', 'map': {'im': ['内置客服', 'blue'], 'external': ['外置客服', 'purple']}}, {'f': 'phone', 't': '手机'}, {'f': 'wechat', 't': '微信号'}, {'f': 'account', 't': '账号'}, {'f': 'is_online', 't': '在线', 'type': 'online'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
           'form': [{'f': 'name', 't': '客服名称', 'req': 1}, {'f': 'type', 't': '客服类型', 'type': 'select', 'opts': [['im', '内置客服'], ['external', '外置客服']]}, {'f': 'avatar', 't': '头像URL'}, {'f': 'phone', 't': '手机号'}, {'f': 'wechat', 't': '微信号'}, {'f': 'account', 't': '登录账号'}, {'f': 'is_online', 't': '在线状态', 'type': 'select', 'opts': ONLINE}, {'f': 'sort', 't': '排序', 'type': 'number'}, {'f': 'remark', 't': '备注'}]},
    'banner': {'group': '自定义UI', 'name': '轮播图',
               'apis': {'list': 'admin/Banner/bannerList', 'add': 'admin/Banner/bannerAdd', 'edit': 'admin/Banner/bannerEdit', 'info': 'admin/Banner/bannerInfo', 'dele': 'admin/Banner/bannerDele', 'disable': 'admin/Banner/bannerDisable'},
               'idField': 'banner_id', 'search': {'field': 'title', 'ph': '标题'},
               'columns': [{'f': 'image', 't': '图片', 'type': 'image'}, {'f': 'title', 't': '标题'}, {'f': 'url', 't': '跳转链接'}, {'f': 'sort', 't': '排序'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
               'form': [{'f': 'title', 't': '标题', 'req': 1}, {'f': 'image', 't': '图片URL'}, {'f': 'url', 't': '跳转链接'}, {'f': 'sort', 't': '排序', 'type': 'number'}]},
    'notice': {'group': '自定义UI', 'name': '公告管理',
               'apis': {'list': 'admin/Notice/noticeList', 'add': 'admin/Notice/noticeAdd', 'edit': 'admin/Notice/noticeEdit', 'info': 'admin/Notice/noticeInfo', 'dele': 'admin/Notice/noticeDele', 'disable': 'admin/Notice/noticeDisable'},
               'idField': 'notice_id', 'search': {'field': 'title', 'ph': '标题'},
               'columns': [{'f': 'title', 't': '标题'}, {'f': 'content', 't': '内容'}, {'f': 'type', 't': '类型', 'type': 'noticetype'}, {'f': 'sort', 't': '排序'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
               'form': [{'f': 'title', 't': '标题', 'req': 1}, {'f': 'content', 't': '内容', 'type': 'textarea'}, {'f': 'type', 't': '类型', 'type': 'select', 'opts': [['1', '公告'], ['2', '规则']]}, {'f': 'sort', 't': '排序', 'type': 'number'}]},
    'imageNav': {'group': '自定义UI', 'name': '图片导航',
                 'apis': {'list': 'admin/ImageNav/navList', 'add': 'admin/ImageNav/navAdd', 'edit': 'admin/ImageNav/navEdit', 'info': 'admin/ImageNav/navInfo', 'dele': 'admin/ImageNav/navDele', 'disable': 'admin/ImageNav/navDisable'},
                 'idField': 'nav_id', 'search': {'field': 'name', 'ph': '名称'},
                 'columns': [{'f': 'image', 't': '图标', 'type': 'image'}, {'f': 'name', 't': '名称'}, {'f': 'url', 't': '跳转链接'}, {'f': 'sort', 't': '排序'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
                 'form': [{'f': 'name', 't': '名称', 'req': 1}, {'f': 'image', 't': '图片URL'}, {'f': 'url', 't': '跳转链接'}, {'f': 'sort', 't': '排序', 'type': 'number'}]},
    'msgTemplate': {'group': '自定义UI', 'name': '模板消息配置',
                    'apis': {'list': 'admin/MsgTemplate/tplList', 'add': 'admin/MsgTemplate/tplAdd', 'edit': 'admin/MsgTemplate/tplEdit', 'info': 'admin/MsgTemplate/tplInfo', 'dele': 'admin/MsgTemplate/tplDele', 'disable': 'admin/MsgTemplate/tplDisable'},
                    'idField': 'tpl_id', 'search': {'field': 'name', 'ph': '模板名称'},
                    'columns': [{'f': 'name', 't': '模板名称'}, {'f': 'code', 't': '标识'}, {'f': 'title', 't': '标题'}, {'f': 'content', 't': '内容'}, {'f': 'sort', 't': '排序'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
                    'form': [{'f': 'name', 't': '模板名称', 'req': 1}, {'f': 'code', 't': '模板标识'}, {'f': 'title', 't': '消息标题'}, {'f': 'content', 't': '消息内容', 'type': 'textarea'}, {'f': 'sort', 't': '排序', 'type': 'number'}]},
}

# 框架模块（对接 yylAdmin 原有接口）
framework_modules = {
    'adminUser': {'group': '权限管理', 'name': '管理员管理',
                  'apis': {'list': 'admin/AdminUser/userList', 'add': 'admin/AdminUser/userAdd', 'edit': 'admin/AdminUser/userEdit', 'info': 'admin/AdminUser/userInfo', 'dele': 'admin/AdminUser/userDele', 'disable': 'admin/AdminUser/userDisable'},
                  'idField': 'admin_user_id', 'search': {'field': 'username', 'ph': '账号/昵称/邮箱'},
                  'columns': [{'f': 'admin_user_id', 't': 'ID'}, {'f': 'username', 't': '账号'}, {'f': 'nickname', 't': '昵称'}, {'f': 'email', 't': '邮箱'}, {'f': 'login_num', 't': '登录次数'}, {'f': 'login_time', 't': '最后登录'}, {'f': 'is_disable', 't': '状态', 'type': 'disable'}],
                  'form': [{'f': 'username', 't': '账号', 'req': 1}, {'f': 'nickname', 't': '昵称', 'req': 1}, {'f': 'password', 't': '密码', 'req': 1, 'only': 'add'}, {'f': 'email', 't': '邮箱'}, {'f': 'sort', 't': '排序', 'type': 'number'}]},
    'adminLog': {'group': '权限管理', 'name': '管理员日志', 'readonly': 1,
                 'apis': {'list': 'admin/AdminLog/logList', 'dele': 'admin/AdminLog/logDele'},
                 'idField': 'admin_log_id', 'search': {'field': 'user_keyword', 'ph': '用户关键词'},
                 'columns': [{'f': 'admin_log_id', 't': 'ID'}, {'f': 'admin_user_id', 't': '用户ID'}, {'f': 'admin_menu_id', 't': '菜单ID'}, {'f': 'request_method', 't': '方法'}, {'f': 'request_ip', 't': 'IP'}, {'f': 'request_region', 't': '地区'}, {'f': 'create_time', 't': '时间'}],
                 'form': []},
}

OUT = {}
OUT.update(base_modules)
for k, cfg in JS_MODULES:
    OUT[k] = cfg
OUT.update(framework_modules)

# 菜单结构（与用户需求文档一致）
MENU = [
    {'group': '数据统计', 'open': True, 'ic': 'grid', 'items': [
        {'key': 'dashboard', 'name': '数据大盘', 'tag': 'hot'},
        {'key': 'memberStat', 'name': '会员统计'},
        {'key': 'orderStat', 'name': '订单统计'},
        {'key': 'financeReport', 'name': '财务报表'},
        {'key': 'inviteChannel', 'name': '邀请渠道统计'}]},
    {'group': '常规管理', 'ic': 'setting', 'items': [
        {'key': 'setting', 'name': '系统配置'},
        {'key': 'attach', 'name': '附件管理'},
        {'key': 'profile', 'name': '个人资料'},
        {'key': 'plugin', 'name': '插件管理', 'tag': 'new'}]},
    {'group': '权限管理', 'ic': 'shield', 'items': [
        {'key': 'adminUser', 'name': '管理员管理'},
        {'key': 'adminLog', 'name': '管理员日志'},
        {'key': 'adminRole', 'name': '角色组'},
        {'key': 'adminMenu', 'name': '菜单规则', 'tag': '菜单'},
        {'key': 'loginSecurity', 'name': '登录安全管理'}]},
    {'group': '财务管理', 'ic': 'wallet', 'items': [
        {'key': 'balanceLog', 'name': '余额明细'},
        {'key': 'recharge', 'name': '充值订单'},
        {'key': 'commission', 'name': '订单佣金'},
        {'key': 'commissionLog', 'name': '佣金明细'},
        {'key': 'deposit', 'name': '保证金记录'},
        {'key': 'memberOrder', 'name': '会员订单'},
        {'key': 'refund', 'name': '退款管理'},
        {'key': 'financeRecon', 'name': '财务对账'}]},
    {'group': '商品管理', 'ic': 'goods', 'items': [
        {'key': 'prize', 'name': '奖池'},
        {'key': 'category', 'name': '分类管理'},
        {'key': 'goods', 'name': '商品'},
        {'key': 'goodsStock', 'name': '商品库存'},
        {'key': 'goodsAudit', 'name': '商品审核'}]},
    {'group': '订单管理', 'ic': 'list', 'items': [
        {'key': 'order', 'name': '订单管理'},
        {'key': 'complaint', 'name': '订单投诉'},
        {'key': 'withdraw', 'name': '提现记录'},
        {'key': 'fine', 'name': '罚款管理'},
        {'key': 'refundAudit', 'name': '退款审核'}]},
    {'group': '会员管理', 'ic': 'user', 'items': [
        {'key': 'member', 'name': '会员管理'},
        {'key': 'memberLevel', 'name': '会员等级管理'},
        {'key': 'inviteCode', 'name': '邀请码管理'},
        {'key': 'inviteRecord', 'name': '邀请记录'},
        {'key': 'inviteReward', 'name': '邀请奖励配置'},
        {'key': 'memberBlacklist', 'name': '会员黑名单'},
        {'key': 'stationLetter', 'name': '站内信管理'},
        {'key': 'memberTag', 'name': '会员标签'}]},
    {'group': '自定义UI', 'ic': 'layout', 'items': [
        {'key': 'banner', 'name': '轮播图', 'tag': 'hot'},
        {'key': 'notice', 'name': '公告管理'},
        {'key': 'imageNav', 'name': '图片导航'},
        {'key': 'msgTemplate', 'name': '模板消息配置'},
        {'key': 'popup', 'name': '弹窗配置'}]},
    {'group': '接口配置', 'ic': 'setting', 'items': [
        {'key': 'oauthLogin', 'name': '聚合登录'},
        {'key': 'epay', 'name': '易支付'},
        {'key': 'wxMerchant', 'name': '微信商户'},
        {'key': 'smsConfig', 'name': '短信配置'}]},
    {'group': '其他', 'ic': 'dots', 'items': [
        {'key': 'player', 'name': '打手管理'},
        {'key': 'cs', 'name': '客服管理'},
        {'key': 'csSession', 'name': '客服会话记录'}]},
    {'group': '风控管理', 'ic': 'shield', 'items': [
        {'key': 'riskRule', 'name': '风控规则配置'},
        {'key': 'riskAccount', 'name': '风险账号'},
        {'key': 'ipBlacklist', 'name': 'IP黑名单'},
        {'key': 'riskLog', 'name': '风控拦截日志'}]},
]

js = "/* 自动生成：后台模块配置（由 database/gen_biz_modules.py 生成） */\n"
js += "window.MODULES = " + json.dumps(OUT, ensure_ascii=False, indent=0) + ";\n"
js += "window.MENU = " + json.dumps(MENU, ensure_ascii=False, indent=0) + ";\n"
with open(JS_FILE, 'w', encoding='utf-8') as f:
    f.write(js)
print('modules.js written:', len(OUT), 'modules')


# 4) 清理菜单缓存
if os.path.isdir(CACHE):
    shutil.rmtree(CACHE)
    print('cleared cache:', CACHE)
print('done')
