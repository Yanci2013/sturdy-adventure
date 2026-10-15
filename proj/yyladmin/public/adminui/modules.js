/* 自动生成：后台模块配置（由 database/gen_biz_modules.py 生成） */
window.MODULES = {
"member": {
"group": "会员管理",
"name": "会员管理",
"apis": {
"list": "admin/Member/memberList",
"add": "admin/Member/memberAdd",
"edit": "admin/Member/memberEdit",
"info": "admin/Member/memberInfo",
"dele": "admin/Member/memberDele",
"disable": "admin/Member/memberDisable"
},
"idField": "member_id",
"search": {
"field": "username",
"ph": "账号/手机/邮箱"
},
"columns": [
{
"f": "member_id",
"t": "ID"
},
{
"f": "username",
"t": "账号"
},
{
"f": "nickname",
"t": "昵称"
},
{
"f": "phone",
"t": "手机"
},
{
"f": "email",
"t": "邮箱"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "username",
"t": "账号",
"req": 1
},
{
"f": "nickname",
"t": "昵称",
"req": 1
},
{
"f": "password",
"t": "密码",
"req": 1,
"only": "add"
},
{
"f": "phone",
"t": "手机"
},
{
"f": "email",
"t": "邮箱"
}
]
},
"cs": {
"group": "客服管理",
"name": "客服管理",
"apis": {
"list": "admin/CustomerService/csList",
"add": "admin/CustomerService/csAdd",
"edit": "admin/CustomerService/csEdit",
"info": "admin/CustomerService/csInfo",
"dele": "admin/CustomerService/csDele",
"disable": "admin/CustomerService/csDisable"
},
"idField": "cs_id",
"search": {
"field": "name",
"ph": "客服名称/手机"
},
"columns": [
{
"f": "avatar",
"t": "头像",
"type": "image"
},
{
"f": "name",
"t": "客服名称"
},
{
"f": "phone",
"t": "手机"
},
{
"f": "wechat",
"t": "微信号"
},
{
"f": "account",
"t": "账号"
},
{
"f": "is_online",
"t": "在线",
"type": "online"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "name",
"t": "客服名称",
"req": 1
},
{
"f": "avatar",
"t": "头像URL"
},
{
"f": "phone",
"t": "手机号"
},
{
"f": "wechat",
"t": "微信号"
},
{
"f": "account",
"t": "登录账号"
},
{
"f": "is_online",
"t": "在线状态",
"type": "select",
"opts": [
[
"1",
"在线"
],
[
"0",
"离线"
]
]
},
{
"f": "sort",
"t": "排序",
"type": "number"
},
{
"f": "remark",
"t": "备注"
}
]
},
"banner": {
"group": "自定义UI",
"name": "轮播图",
"apis": {
"list": "admin/Banner/bannerList",
"add": "admin/Banner/bannerAdd",
"edit": "admin/Banner/bannerEdit",
"info": "admin/Banner/bannerInfo",
"dele": "admin/Banner/bannerDele",
"disable": "admin/Banner/bannerDisable"
},
"idField": "banner_id",
"search": {
"field": "title",
"ph": "标题"
},
"columns": [
{
"f": "image",
"t": "图片",
"type": "image"
},
{
"f": "title",
"t": "标题"
},
{
"f": "url",
"t": "跳转链接"
},
{
"f": "sort",
"t": "排序"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "title",
"t": "标题",
"req": 1
},
{
"f": "image",
"t": "图片URL"
},
{
"f": "url",
"t": "跳转链接"
},
{
"f": "sort",
"t": "排序",
"type": "number"
}
]
},
"notice": {
"group": "自定义UI",
"name": "公告管理",
"apis": {
"list": "admin/Notice/noticeList",
"add": "admin/Notice/noticeAdd",
"edit": "admin/Notice/noticeEdit",
"info": "admin/Notice/noticeInfo",
"dele": "admin/Notice/noticeDele",
"disable": "admin/Notice/noticeDisable"
},
"idField": "notice_id",
"search": {
"field": "title",
"ph": "标题"
},
"columns": [
{
"f": "title",
"t": "标题"
},
{
"f": "content",
"t": "内容"
},
{
"f": "type",
"t": "类型",
"type": "noticetype"
},
{
"f": "sort",
"t": "排序"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "title",
"t": "标题",
"req": 1
},
{
"f": "content",
"t": "内容",
"type": "textarea"
},
{
"f": "type",
"t": "类型",
"type": "select",
"opts": [
[
"1",
"公告"
],
[
"2",
"规则"
]
]
},
{
"f": "sort",
"t": "排序",
"type": "number"
}
]
},
"imageNav": {
"group": "自定义UI",
"name": "图片导航",
"apis": {
"list": "admin/ImageNav/navList",
"add": "admin/ImageNav/navAdd",
"edit": "admin/ImageNav/navEdit",
"info": "admin/ImageNav/navInfo",
"dele": "admin/ImageNav/navDele",
"disable": "admin/ImageNav/navDisable"
},
"idField": "nav_id",
"search": {
"field": "name",
"ph": "名称"
},
"columns": [
{
"f": "image",
"t": "图标",
"type": "image"
},
{
"f": "name",
"t": "名称"
},
{
"f": "url",
"t": "跳转链接"
},
{
"f": "sort",
"t": "排序"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "name",
"t": "名称",
"req": 1
},
{
"f": "image",
"t": "图片URL"
},
{
"f": "url",
"t": "跳转链接"
},
{
"f": "sort",
"t": "排序",
"type": "number"
}
]
},
"msgTemplate": {
"group": "自定义UI",
"name": "模板消息配置",
"apis": {
"list": "admin/MsgTemplate/tplList",
"add": "admin/MsgTemplate/tplAdd",
"edit": "admin/MsgTemplate/tplEdit",
"info": "admin/MsgTemplate/tplInfo",
"dele": "admin/MsgTemplate/tplDele",
"disable": "admin/MsgTemplate/tplDisable"
},
"idField": "tpl_id",
"search": {
"field": "name",
"ph": "模板名称"
},
"columns": [
{
"f": "name",
"t": "模板名称"
},
{
"f": "code",
"t": "标识"
},
{
"f": "title",
"t": "标题"
},
{
"f": "content",
"t": "内容"
},
{
"f": "sort",
"t": "排序"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "name",
"t": "模板名称",
"req": 1
},
{
"f": "code",
"t": "模板标识"
},
{
"f": "title",
"t": "消息标题"
},
{
"f": "content",
"t": "消息内容",
"type": "textarea"
},
{
"f": "sort",
"t": "排序",
"type": "number"
}
]
},
"order": {
"group": "订单管理",
"name": "订单管理",
"apis": {
"list": "admin/Order/orderList",
"add": "admin/Order/orderAdd",
"edit": "admin/Order/orderEdit",
"info": "admin/Order/orderInfo",
"dele": "admin/Order/orderDele",
"disable": "admin/Order/orderDisable"
},
"idField": "order_id",
"search": {
"field": "order_no",
"ph": "搜索订单管理"
},
"columns": [
{
"f": "order_no",
"t": "订单号",
"req": 0
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "goods_name",
"t": "商品",
"req": 0
},
{
"f": "amount",
"t": "金额",
"req": 0
},
{
"f": "commission",
"t": "佣金",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
},
{
"f": "pay_type",
"t": "支付方式",
"req": 0,
"type": "pay_type"
}
],
"form": [
{
"f": "order_no",
"t": "订单号",
"req": 1
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "goods_name",
"t": "商品",
"req": 0
},
{
"f": "amount",
"t": "金额",
"req": 0,
"type": "number"
},
{
"f": "commission",
"t": "佣金",
"req": 0,
"type": "number"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "pay_type",
"t": "支付方式",
"req": 0,
"type": "select",
"opts": [
[
"1",
"微信"
],
[
"2",
"支付宝"
],
[
"3",
"银行卡"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"withdraw": {
"group": "提现记录",
"name": "提现记录",
"apis": {
"list": "admin/Withdraw/withdrawList",
"add": "admin/Withdraw/withdrawAdd",
"edit": "admin/Withdraw/withdrawEdit",
"info": "admin/Withdraw/withdrawInfo",
"dele": "admin/Withdraw/withdrawDele",
"disable": "admin/Withdraw/withdrawDisable"
},
"idField": "withdraw_id",
"search": {
"field": "member_name",
"ph": "搜索提现记录"
},
"columns": [
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "money",
"t": "提现金额",
"req": 0
},
{
"f": "fee",
"t": "手续费",
"req": 0
},
{
"f": "real_money",
"t": "到账金额",
"req": 0
},
{
"f": "account",
"t": "收款账号",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "member_name",
"t": "会员",
"req": 1
},
{
"f": "money",
"t": "提现金额",
"req": 0,
"type": "number"
},
{
"f": "fee",
"t": "手续费",
"req": 0,
"type": "number"
},
{
"f": "real_money",
"t": "到账金额",
"req": 0,
"type": "number"
},
{
"f": "account",
"t": "收款账号",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"complaint": {
"group": "订单投诉",
"name": "订单投诉",
"apis": {
"list": "admin/OrderComplaint/complaintList",
"add": "admin/OrderComplaint/complaintAdd",
"edit": "admin/OrderComplaint/complaintEdit",
"info": "admin/OrderComplaint/complaintInfo",
"dele": "admin/OrderComplaint/complaintDele",
"disable": "admin/OrderComplaint/complaintDisable"
},
"idField": "complaint_id",
"search": {
"field": "order_no",
"ph": "搜索订单投诉"
},
"columns": [
{
"f": "order_no",
"t": "订单号",
"req": 0
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "content",
"t": "投诉内容",
"req": 0
},
{
"f": "reply",
"t": "回复",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "order_no",
"t": "订单号",
"req": 1
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "content",
"t": "投诉内容",
"req": 0,
"type": "textarea"
},
{
"f": "reply",
"t": "回复",
"req": 0,
"type": "textarea"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
}
]
},
"memberLevel": {
"group": "会员等级管理",
"name": "会员等级管理",
"apis": {
"list": "admin/MemberLevel/levelList",
"add": "admin/MemberLevel/levelAdd",
"edit": "admin/MemberLevel/levelEdit",
"info": "admin/MemberLevel/levelInfo",
"dele": "admin/MemberLevel/levelDele",
"disable": "admin/MemberLevel/levelDisable"
},
"idField": "level_id",
"search": {
"field": "level_name",
"ph": "搜索会员等级管理"
},
"columns": [
{
"f": "level_img",
"t": "图标",
"req": 0,
"type": "image"
},
{
"f": "level_name",
"t": "等级名称",
"req": 0
},
{
"f": "level_num",
"t": "等级值",
"req": 0
},
{
"f": "discount",
"t": "折扣",
"req": 0
},
{
"f": "sort",
"t": "排序",
"req": 0
}
],
"form": [
{
"f": "level_name",
"t": "等级名称",
"req": 1
},
{
"f": "level_img",
"t": "图标URL",
"req": 0
},
{
"f": "level_num",
"t": "等级值",
"req": 0,
"type": "number"
},
{
"f": "discount",
"t": "折扣(如0.9)",
"req": 0,
"type": "number"
},
{
"f": "sort",
"t": "排序",
"req": 0,
"type": "number"
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"fine": {
"group": "罚款管理",
"name": "罚款管理",
"apis": {
"list": "admin/Fine/fineList",
"add": "admin/Fine/fineAdd",
"edit": "admin/Fine/fineEdit",
"info": "admin/Fine/fineInfo",
"dele": "admin/Fine/fineDele",
"disable": "admin/Fine/fineDisable"
},
"idField": "fine_id",
"search": {
"field": "player_name",
"ph": "搜索罚款管理"
},
"columns": [
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "money",
"t": "罚款金额",
"req": 0
},
{
"f": "reason",
"t": "罚款原因",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "player_name",
"t": "打手",
"req": 1
},
{
"f": "money",
"t": "罚款金额",
"req": 0,
"type": "number"
},
{
"f": "reason",
"t": "罚款原因",
"req": 0,
"type": "textarea"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"player": {
"group": "打手管理",
"name": "打手管理",
"apis": {
"list": "admin/Player/playerList",
"add": "admin/Player/playerAdd",
"edit": "admin/Player/playerEdit",
"info": "admin/Player/playerInfo",
"dele": "admin/Player/playerDele",
"disable": "admin/Player/playerDisable"
},
"idField": "player_id",
"search": {
"field": "nickname",
"ph": "搜索打手管理"
},
"columns": [
{
"f": "nickname",
"t": "昵称",
"req": 0
},
{
"f": "avatar",
"t": "头像",
"req": 0,
"type": "image"
},
{
"f": "phone",
"t": "手机",
"req": 0
},
{
"f": "account",
"t": "账号",
"req": 0
},
{
"f": "level_name",
"t": "等级",
"req": 0
},
{
"f": "score",
"t": "评分",
"req": 0
},
{
"f": "balance",
"t": "余额",
"req": 0
},
{
"f": "is_online",
"t": "在线",
"req": 0,
"type": "online"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "nickname",
"t": "昵称",
"req": 1
},
{
"f": "avatar",
"t": "头像URL",
"req": 0
},
{
"f": "phone",
"t": "手机",
"req": 0
},
{
"f": "account",
"t": "账号",
"req": 0
},
{
"f": "level_name",
"t": "等级",
"req": 0
},
{
"f": "score",
"t": "评分",
"req": 0,
"type": "number"
},
{
"f": "balance",
"t": "余额",
"req": 0,
"type": "number"
},
{
"f": "is_online",
"t": "在线状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"在线"
],
[
"0",
"离线"
]
]
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"goods": {
"group": "商品管理",
"name": "商品",
"apis": {
"list": "admin/Goods/goodsList",
"add": "admin/Goods/goodsAdd",
"edit": "admin/Goods/goodsEdit",
"info": "admin/Goods/goodsInfo",
"dele": "admin/Goods/goodsDele",
"disable": "admin/Goods/goodsDisable"
},
"idField": "goods_id",
"search": {
"field": "goods_name",
"ph": "搜索商品"
},
"columns": [
{
"f": "image",
"t": "图片",
"req": 0,
"type": "image"
},
{
"f": "goods_name",
"t": "商品名称",
"req": 0
},
{
"f": "category_name",
"t": "分类",
"req": 0
},
{
"f": "price",
"t": "价格",
"req": 0
},
{
"f": "stock",
"t": "库存",
"req": 0
},
{
"f": "unit",
"t": "单位",
"req": 0
}
],
"form": [
{
"f": "goods_name",
"t": "商品名称",
"req": 1
},
{
"f": "category_name",
"t": "分类",
"req": 0
},
{
"f": "image",
"t": "图片URL",
"req": 0
},
{
"f": "price",
"t": "价格",
"req": 0,
"type": "number"
},
{
"f": "stock",
"t": "库存",
"req": 0,
"type": "number"
},
{
"f": "unit",
"t": "单位",
"req": 0
},
{
"f": "sort",
"t": "排序",
"req": 0,
"type": "number"
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"category": {
"group": "商品管理",
"name": "分类管理",
"apis": {
"list": "admin/Category/categoryList",
"add": "admin/Category/categoryAdd",
"edit": "admin/Category/categoryEdit",
"info": "admin/Category/categoryInfo",
"dele": "admin/Category/categoryDele",
"disable": "admin/Category/categoryDisable"
},
"idField": "category_id",
"search": {
"field": "category_name",
"ph": "搜索分类管理"
},
"columns": [
{
"f": "image",
"t": "图标",
"req": 0,
"type": "image"
},
{
"f": "category_name",
"t": "分类名称",
"req": 0
},
{
"f": "sort",
"t": "排序",
"req": 0
}
],
"form": [
{
"f": "category_name",
"t": "分类名称",
"req": 1
},
{
"f": "image",
"t": "图标URL",
"req": 0
},
{
"f": "sort",
"t": "排序",
"req": 0,
"type": "number"
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"prize": {
"group": "商品管理",
"name": "奖池",
"apis": {
"list": "admin/Prize/prizeList",
"add": "admin/Prize/prizeAdd",
"edit": "admin/Prize/prizeEdit",
"info": "admin/Prize/prizeInfo",
"dele": "admin/Prize/prizeDele",
"disable": "admin/Prize/prizeDisable"
},
"idField": "prize_id",
"search": {
"field": "prize_name",
"ph": "搜索奖池"
},
"columns": [
{
"f": "image",
"t": "图片",
"req": 0,
"type": "image"
},
{
"f": "prize_name",
"t": "奖品名称",
"req": 0
},
{
"f": "goods_name",
"t": "关联商品",
"req": 0
},
{
"f": "rate",
"t": "中奖概率",
"req": 0
},
{
"f": "stock",
"t": "数量",
"req": 0
}
],
"form": [
{
"f": "prize_name",
"t": "奖品名称",
"req": 1
},
{
"f": "goods_name",
"t": "关联商品",
"req": 0
},
{
"f": "image",
"t": "图片URL",
"req": 0
},
{
"f": "rate",
"t": "中奖概率(%)",
"req": 0,
"type": "number"
},
{
"f": "stock",
"t": "数量",
"req": 0,
"type": "number"
},
{
"f": "sort",
"t": "排序",
"req": 0,
"type": "number"
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"balanceLog": {
"group": "财务管理",
"name": "余额明细",
"apis": {
"list": "admin/BalanceLog/balanceLogList",
"add": "admin/BalanceLog/balanceLogAdd",
"edit": "admin/BalanceLog/balanceLogEdit",
"info": "admin/BalanceLog/balanceLogInfo",
"dele": "admin/BalanceLog/balanceLogDele",
"disable": "admin/BalanceLog/balanceLogDisable"
},
"idField": "log_id",
"search": {
"field": "member_name",
"ph": "搜索余额明细"
},
"columns": [
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "type",
"t": "类型",
"req": 0,
"type": "flow_type"
},
{
"f": "money",
"t": "变动金额",
"req": 0
},
{
"f": "before_money",
"t": "变动前",
"req": 0
},
{
"f": "after_money",
"t": "变动后",
"req": 0
},
{
"f": "remark",
"t": "备注",
"req": 0
},
{
"f": "create_time",
"t": "时间",
"req": 0
}
],
"form": [
{
"f": "member_name",
"t": "会员",
"req": 1
},
{
"f": "type",
"t": "类型",
"req": 0,
"type": "select",
"opts": [
[
"1",
"收入"
],
[
"2",
"支出"
]
]
},
{
"f": "money",
"t": "变动金额",
"req": 0,
"type": "number"
},
{
"f": "before_money",
"t": "变动前",
"req": 0,
"type": "number"
},
{
"f": "after_money",
"t": "变动后",
"req": 0,
"type": "number"
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"recharge": {
"group": "财务管理",
"name": "充值订单",
"apis": {
"list": "admin/Recharge/rechargeList",
"add": "admin/Recharge/rechargeAdd",
"edit": "admin/Recharge/rechargeEdit",
"info": "admin/Recharge/rechargeInfo",
"dele": "admin/Recharge/rechargeDele",
"disable": "admin/Recharge/rechargeDisable"
},
"idField": "recharge_id",
"search": {
"field": "order_no",
"ph": "搜索充值订单"
},
"columns": [
{
"f": "order_no",
"t": "订单号",
"req": 0
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "money",
"t": "金额",
"req": 0
},
{
"f": "pay_type",
"t": "支付方式",
"req": 0,
"type": "pay_type"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
},
{
"f": "create_time",
"t": "时间",
"req": 0
}
],
"form": [
{
"f": "order_no",
"t": "订单号",
"req": 1
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "money",
"t": "金额",
"req": 0,
"type": "number"
},
{
"f": "pay_type",
"t": "支付方式",
"req": 0,
"type": "select",
"opts": [
[
"1",
"微信"
],
[
"2",
"支付宝"
],
[
"3",
"银行卡"
]
]
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"commission": {
"group": "财务管理",
"name": "订单佣金",
"apis": {
"list": "admin/OrderCommission/commissionList",
"add": "admin/OrderCommission/commissionAdd",
"edit": "admin/OrderCommission/commissionEdit",
"info": "admin/OrderCommission/commissionInfo",
"dele": "admin/OrderCommission/commissionDele",
"disable": "admin/OrderCommission/commissionDisable"
},
"idField": "commission_id",
"search": {
"field": "order_no",
"ph": "搜索订单佣金"
},
"columns": [
{
"f": "order_no",
"t": "订单号",
"req": 0
},
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "order_amount",
"t": "订单金额",
"req": 0
},
{
"f": "rate",
"t": "佣金比例",
"req": 0
},
{
"f": "commission",
"t": "佣金",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "order_no",
"t": "订单号",
"req": 1
},
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "order_amount",
"t": "订单金额",
"req": 0,
"type": "number"
},
{
"f": "rate",
"t": "佣金比例",
"req": 0,
"type": "number"
},
{
"f": "commission",
"t": "佣金",
"req": 0,
"type": "number"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
}
]
},
"commissionLog": {
"group": "财务管理",
"name": "佣金明细",
"apis": {
"list": "admin/CommissionLog/clogList",
"add": "admin/CommissionLog/clogAdd",
"edit": "admin/CommissionLog/clogEdit",
"info": "admin/CommissionLog/clogInfo",
"dele": "admin/CommissionLog/clogDele",
"disable": "admin/CommissionLog/clogDisable"
},
"idField": "clog_id",
"search": {
"field": "player_name",
"ph": "搜索佣金明细"
},
"columns": [
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "type",
"t": "类型",
"req": 0,
"type": "flow_type"
},
{
"f": "money",
"t": "变动金额",
"req": 0
},
{
"f": "before_money",
"t": "变动前",
"req": 0
},
{
"f": "after_money",
"t": "变动后",
"req": 0
},
{
"f": "remark",
"t": "备注",
"req": 0
},
{
"f": "create_time",
"t": "时间",
"req": 0
}
],
"form": [
{
"f": "player_name",
"t": "打手",
"req": 1
},
{
"f": "type",
"t": "类型",
"req": 0,
"type": "select",
"opts": [
[
"1",
"收入"
],
[
"2",
"支出"
]
]
},
{
"f": "money",
"t": "变动金额",
"req": 0,
"type": "number"
},
{
"f": "before_money",
"t": "变动前",
"req": 0,
"type": "number"
},
{
"f": "after_money",
"t": "变动后",
"req": 0,
"type": "number"
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"deposit": {
"group": "财务管理",
"name": "保证金记录",
"apis": {
"list": "admin/Deposit/depositList",
"add": "admin/Deposit/depositAdd",
"edit": "admin/Deposit/depositEdit",
"info": "admin/Deposit/depositInfo",
"dele": "admin/Deposit/depositDele",
"disable": "admin/Deposit/depositDisable"
},
"idField": "deposit_id",
"search": {
"field": "player_name",
"ph": "搜索保证金记录"
},
"columns": [
{
"f": "player_name",
"t": "打手",
"req": 0
},
{
"f": "type",
"t": "类型",
"req": 0,
"type": "flow_type"
},
{
"f": "money",
"t": "变动金额",
"req": 0
},
{
"f": "before_money",
"t": "变动前",
"req": 0
},
{
"f": "after_money",
"t": "变动后",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "player_name",
"t": "打手",
"req": 1
},
{
"f": "type",
"t": "类型",
"req": 0,
"type": "select",
"opts": [
[
"1",
"收入"
],
[
"2",
"支出"
]
]
},
{
"f": "money",
"t": "变动金额",
"req": 0,
"type": "number"
},
{
"f": "before_money",
"t": "变动前",
"req": 0,
"type": "number"
},
{
"f": "after_money",
"t": "变动后",
"req": 0,
"type": "number"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"memberOrder": {
"group": "财务管理",
"name": "会员订单",
"apis": {
"list": "admin/MemberOrder/morderList",
"add": "admin/MemberOrder/morderAdd",
"edit": "admin/MemberOrder/morderEdit",
"info": "admin/MemberOrder/morderInfo",
"dele": "admin/MemberOrder/morderDele",
"disable": "admin/MemberOrder/morderDisable"
},
"idField": "morder_id",
"search": {
"field": "order_no",
"ph": "搜索会员订单"
},
"columns": [
{
"f": "order_no",
"t": "订单号",
"req": 0
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "level_name",
"t": "等级",
"req": 0
},
{
"f": "money",
"t": "金额",
"req": 0
},
{
"f": "months",
"t": "时长(月)",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "order_no",
"t": "订单号",
"req": 1
},
{
"f": "member_name",
"t": "会员",
"req": 0
},
{
"f": "level_name",
"t": "等级",
"req": 0
},
{
"f": "money",
"t": "金额",
"req": 0,
"type": "number"
},
{
"f": "months",
"t": "时长(月)",
"req": 0,
"type": "number"
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"attach": {
"group": "常规管理",
"name": "附件管理",
"apis": {
"list": "admin/Attachment/attachList",
"add": "admin/Attachment/attachAdd",
"edit": "admin/Attachment/attachEdit",
"info": "admin/Attachment/attachInfo",
"dele": "admin/Attachment/attachDele",
"disable": "admin/Attachment/attachDisable"
},
"idField": "attachment_id",
"search": {
"field": "name",
"ph": "搜索附件管理"
},
"columns": [
{
"f": "attachment_id",
"t": "ID",
"req": 0
},
{
"f": "name",
"t": "文件名",
"req": 0
},
{
"f": "path",
"t": "路径",
"req": 0
},
{
"f": "size",
"t": "大小",
"req": 0
},
{
"f": "mime",
"t": "类型",
"req": 0
},
{
"f": "create_time",
"t": "上传时间",
"req": 0
}
],
"form": [
{
"f": "name",
"t": "文件名",
"req": 1
},
{
"f": "path",
"t": "路径",
"req": 0
},
{
"f": "size",
"t": "大小",
"req": 0,
"type": "number"
},
{
"f": "mime",
"t": "类型",
"req": 0
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"plugin": {
"group": "常规管理",
"name": "插件管理",
"apis": {
"list": "admin/Plugin/pluginList",
"add": "admin/Plugin/pluginAdd",
"edit": "admin/Plugin/pluginEdit",
"info": "admin/Plugin/pluginInfo",
"dele": "admin/Plugin/pluginDele",
"disable": "admin/Plugin/pluginDisable"
},
"idField": "plugin_id",
"search": {
"field": "plugin_name",
"ph": "搜索插件管理"
},
"columns": [
{
"f": "plugin_name",
"t": "插件名称",
"req": 0
},
{
"f": "code",
"t": "标识",
"req": 0
},
{
"f": "version",
"t": "版本",
"req": 0
},
{
"f": "author",
"t": "作者",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "status"
}
],
"form": [
{
"f": "plugin_name",
"t": "插件名称",
"req": 1
},
{
"f": "code",
"t": "标识",
"req": 0
},
{
"f": "version",
"t": "版本",
"req": 0
},
{
"f": "author",
"t": "作者",
"req": 0
},
{
"f": "status",
"t": "状态",
"req": 0,
"type": "select",
"opts": [
[
"1",
"待处理"
],
[
"2",
"已完成"
],
[
"3",
"处理中"
]
]
},
{
"f": "remark",
"t": "备注",
"req": 0,
"type": "textarea"
}
]
},
"adminUser": {
"group": "权限管理",
"name": "管理员管理",
"apis": {
"list": "admin/AdminUser/userList",
"add": "admin/AdminUser/userAdd",
"edit": "admin/AdminUser/userEdit",
"info": "admin/AdminUser/userInfo",
"dele": "admin/AdminUser/userDele",
"disable": "admin/AdminUser/userDisable"
},
"idField": "admin_user_id",
"search": {
"field": "username",
"ph": "账号/昵称/邮箱"
},
"columns": [
{
"f": "admin_user_id",
"t": "ID"
},
{
"f": "username",
"t": "账号"
},
{
"f": "nickname",
"t": "昵称"
},
{
"f": "email",
"t": "邮箱"
},
{
"f": "login_num",
"t": "登录次数"
},
{
"f": "login_time",
"t": "最后登录"
},
{
"f": "is_disable",
"t": "状态",
"type": "disable"
}
],
"form": [
{
"f": "username",
"t": "账号",
"req": 1
},
{
"f": "nickname",
"t": "昵称",
"req": 1
},
{
"f": "password",
"t": "密码",
"req": 1,
"only": "add"
},
{
"f": "email",
"t": "邮箱"
},
{
"f": "sort",
"t": "排序",
"type": "number"
}
]
},
"adminLog": {
"group": "权限管理",
"name": "管理员日志",
"readonly": 1,
"apis": {
"list": "admin/AdminLog/logList",
"dele": "admin/AdminLog/logDele"
},
"idField": "admin_log_id",
"search": {
"field": "user_keyword",
"ph": "用户关键词"
},
"columns": [
{
"f": "admin_log_id",
"t": "ID"
},
{
"f": "admin_user_id",
"t": "用户ID"
},
{
"f": "admin_menu_id",
"t": "菜单ID"
},
{
"f": "request_method",
"t": "方法"
},
{
"f": "request_ip",
"t": "IP"
},
{
"f": "request_region",
"t": "地区"
},
{
"f": "create_time",
"t": "时间"
}
],
"form": []
}
};
window.MENU = [
{
"group": "数据统计",
"open": true,
"ic": "grid",
"items": [
{
"key": "dashboard",
"name": "数据统计",
"tag": "hot"
}
]
},
{
"group": "常规管理",
"ic": "setting",
"items": [
{
"key": "setting",
"name": "系统配置"
},
{
"key": "attach",
"name": "附件管理"
},
{
"key": "profile",
"name": "个人资料"
},
{
"key": "plugin",
"name": "插件管理",
"tag": "new"
}
]
},
{
"group": "权限管理",
"ic": "shield",
"items": [
{
"key": "adminUser",
"name": "管理员管理"
},
{
"key": "adminLog",
"name": "管理员日志"
},
{
"key": "adminRole",
"name": "角色组"
},
{
"key": "adminMenu",
"name": "菜单规则",
"tag": "菜单"
}
]
},
{
"group": "财务管理",
"ic": "wallet",
"items": [
{
"key": "balanceLog",
"name": "余额明细"
},
{
"key": "recharge",
"name": "充值订单"
},
{
"key": "commission",
"name": "订单佣金"
},
{
"key": "commissionLog",
"name": "佣金明细"
},
{
"key": "deposit",
"name": "保证金记录"
},
{
"key": "memberOrder",
"name": "会员订单"
}
]
},
{
"group": "商品管理",
"ic": "goods",
"items": [
{
"key": "prize",
"name": "奖池"
},
{
"key": "category",
"name": "分类管理"
},
{
"key": "goods",
"name": "商品"
}
]
},
{
"group": "打手管理",
"ic": "player",
"items": [
{
"key": "player",
"name": "打手管理"
}
]
},
{
"group": "会员管理",
"ic": "user",
"items": [
{
"key": "member",
"name": "会员管理"
},
{
"key": "memberLevel",
"name": "会员等级管理"
}
]
},
{
"group": "自定义UI",
"ic": "layout",
"items": [
{
"key": "imageNav",
"name": "图片导航"
},
{
"key": "banner",
"name": "轮播图",
"tag": "hot"
},
{
"key": "notice",
"name": "公告管理"
},
{
"key": "msgTemplate",
"name": "模板消息配置"
}
]
},
{
"group": "客服管理",
"ic": "headset",
"items": [
{
"key": "cs",
"name": "客服管理"
}
]
},
{
"group": "订单管理",
"ic": "list",
"items": [
{
"key": "order",
"name": "订单管理"
},
{
"key": "complaint",
"name": "订单投诉"
},
{
"key": "withdraw",
"name": "提现记录"
},
{
"key": "fine",
"name": "罚款管理"
}
]
}
];
