-- ============================================================
-- 创世网络 - 完整数据库导出 (MySQL 兼容)
-- 表前缀: fa_ / res_
-- 导入方式: mysql -u root -p fastadmin < database_full.sql
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for customer_services
-- ----------------------------
DROP TABLE IF EXISTS `customer_services`;
CREATE TABLE IF NOT EXISTS `customer_services` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT,
  `user_id` INT(11),
  `type` TEXT DEFAULT 'im',
  `avatar` TEXT,
  `online` INT(11) DEFAULT 1,
  `session_timeout` INT(11) DEFAULT 3,
  `cs_mode` TEXT DEFAULT 'built_in',
  `wecom_url` TEXT DEFAULT '',
  `auto_assign` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of customer_services (1 rows)
INSERT INTO `customer_services` (`id`, `name`, `user_id`, `type`, `avatar`, `online`, `session_timeout`, `cs_mode`, `wecom_url`, `auto_assign`, `is_deleted`) VALUES
(1, '平台客服小创', 0, 'im', '/static/yun/customer.png', 1, 3, 'built_in', '', 0, 0);

-- ----------------------------
-- Table structure for fa_admin_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_admin_log`;
CREATE TABLE IF NOT EXISTS `fa_admin_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) DEFAULT 0,
  `admin_name` TEXT DEFAULT '',
  `module` TEXT DEFAULT '',
  `content` TEXT DEFAULT '',
  `ip` TEXT DEFAULT '',
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_admin_log (9 rows)
INSERT INTO `fa_admin_log` (`id`, `admin_id`, `admin_name`, `module`, `content`, `ip`, `create_time`) VALUES
(1, 1, '超级管理员', '系统设置', '修改了彩虹易支付配置', '127.0.0.1', 1792295372),
(2, 1, '超级管理员', '打手提现', '审核通过提现单 TXD001，金额 ¥100.00', '127.0.0.1', 1792297172),
(3, 1, '超级管理员', '打造次数套餐', '新增打造次数套餐（#4）', '127.0.0.1', 1792321576),
(4, 1, '超级管理员', '装备打造奖池', '新增装备打造奖池（#6）', '127.0.0.1', 1792321694),
(5, 1, '超级管理员', '装备打造奖池', '删除装备打造奖池（#6）', '127.0.0.1', 1792321698),
(6, 1, '超级管理员', '装备打造奖池', '新增装备打造奖池（#7）', '127.0.0.1', 1792321707),
(7, 1, '超级管理员', '装备打造奖池', '删除装备打造奖池（#7）', '127.0.0.1', 1792321711),
(8, 1, '超级管理员', '数据导出', '导出invite_codes，共 3 条', '127.0.0.1', 1792323515),
(9, 1, '超级管理员', '轮播图管理', '新增轮播图管理（#2）', '127.0.0.1', 1792324648);

-- ----------------------------
-- Table structure for fa_aggregate_login_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_aggregate_login_log`;
CREATE TABLE IF NOT EXISTS `fa_aggregate_login_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT 0,
  `channel` TEXT DEFAULT '',
  `openid` TEXT DEFAULT '',
  `nickname` TEXT DEFAULT '',
  `login_ip` TEXT DEFAULT '',
  `device` TEXT DEFAULT '',
  `login_time` INT(11) DEFAULT 0,
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_aggregate_login_log (1 rows)
INSERT INTO `fa_aggregate_login_log` (`id`, `user_id`, `channel`, `openid`, `nickname`, `login_ip`, `device`, `login_time`, `create_time`) VALUES
(1, 1, 'wechat', 'juhe_wechat_mock001', '创世用户0001', '127.0.0.1', 'iPhone / Safari', 0, 1792297772);

-- ----------------------------
-- Table structure for fa_config
-- ----------------------------
DROP TABLE IF EXISTS `fa_config`;
CREATE TABLE IF NOT EXISTS `fa_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT,
  `group_name` TEXT DEFAULT 'basic',
  `title` TEXT,
  `tip` TEXT DEFAULT '',
  `type` TEXT DEFAULT 'string',
  `visible` TEXT DEFAULT '',
  `value` TEXT DEFAULT '',
  `content` TEXT DEFAULT '',
  `rule` TEXT DEFAULT '',
  `extend` TEXT DEFAULT '',
  `setting` TEXT DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_config (38 rows)
INSERT INTO `fa_config` (`id`, `name`, `group_name`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`) VALUES
(1, 'configgroup', 'basic', '配置分组', '', 'string', '', '{"basic":"基础配置","dictionary":"字典配置","example":"配置","ulogin":"彩虹聚合登录","rainbow_epay":"彩虹易支付"}', '', '', '', ''),
(2, 'login_mode', 'basic', '登录方式', '选择前端启用的登录方式', 'radio', '', 'mock', '{"juhe":"一键登录（需配置聚合登录）","mock":"游客模式（仅开发调试）"}', 'required', '', ''),
(3, 'ulogin_apiurl', 'ulogin', 'API地址', '聚合登录平台接口地址', 'string', '', 'https://u.suyanw.cn/', '', 'required', '', ''),
(4, 'ulogin_appid', 'ulogin', 'APPID', '在聚合登录平台获取的应用 APPID', 'string', '', '', '', 'required', '', ''),
(5, 'ulogin_appkey', 'ulogin', 'APPKEY', '在聚合登录平台获取的应用 APPKEY', 'password', '', '', '', 'required', '', ''),
(6, 'ulogin_types', 'ulogin', '启用登录方式', '勾选要在前端展示的第三方登录方式', 'checkbox', '', 'qq,wx,alipay', '{"qq":"QQ","wx":"微信","alipay":"支付宝","sina":"微博","baidu":"百度","douyin":"抖音"}', '', '', ''),
(7, 'ulogin_callback', 'ulogin', '回调地址', '聚合登录平台回调地址', 'string', '', '', '', '', '', ''),
(8, 'pay_methods', 'basic', '支付方式', '勾选要在前端收银台展示的支付方式', 'checkbox', '', 'balance,native_wechat,epay_wxpay,epay_alipay,epay_qqpay,epay_bank', '{"balance":"余额支付","native_wechat":"原生微信支付","epay_wxpay":"彩虹易支付-微信","epay_alipay":"彩虹易支付-支付宝","epay_qqpay":"彩虹易支付-QQ钱包","epay_bank":"彩虹易支付-云闪付"}', '', '', ''),
(9, 'epay_apiurl', 'rainbow_epay', 'API地址', '彩虹易支付平台接口地址', 'string', '', '', '', 'required', '', ''),
(10, 'epay_pid', 'rainbow_epay', '商户ID', '在彩虹易支付平台获取的商户 PID', 'string', '', '', '', 'required', '', ''),
(11, 'epay_key', 'rainbow_epay', '商户密钥', '在彩虹易支付平台获取的商户密钥', 'password', '', '', '', 'required', '', ''),
(12, 'epay_notify_url', 'rainbow_epay', '异步通知地址', '彩虹易支付异步通知地址', 'string', '', '', '', '', '', ''),
(13, 'small_lunbo', 'basic', '小轮播图', '首页图片导航上方小轮播图', 'images', '', '', '', '', '', ''),
(14, 'goods_card_col', 'basic', '商品卡片每行数量', '控制前端商品卡片每行显示的数量', 'radio', '', '3', '{"2":"2列","3":"3列"}', 'required', '', ''),
(15, 'frontend_decoration', 'basic', NULL, '', 'string', '', '{"name": "纯白经典", "colors": {"brand": "#2B6EFF", "brand_gradient": "#5B8DEF", "page_bg": "#F5F7FA", "card_bg": "#FFFFFF", "text_main": "#1A1A1A", "text_sub": "#8A8A8A", "price": "#2B6EFF", "btn_text": "#FFFFFF", "tabbar_bg": "#FFFFFF", "top_text": "#FFFFFF", "search_bg": "#F0F3F8", "card_border": "#EAEAEA", "notice_bg": "#E8F0FF", "notice_text": "#2B6EFF", "tabbar_active": "#2B6EFF", "tabbar_text": "#999999", "tabbar_text_active": "#2B6EFF"}, "bg_image": "", "page_bg_type": "solid", "page_bg_from": "#FFFFFF", "page_bg_to": "#FFFFFF", "page_bg_deg": 180, "nav_style": "stacked_gold", "floating_nav": [{"text": "首页", "icon": "home", "color": "#F25109", "link": "/pages/index/index"}, {"text": "分类", "icon": "grid", "color": "#F25109", "link": "/pages/classify/index"}, {"text": "挑选打手", "icon": "gamepad", "color": "#F25109", "link": "/pages/player/index"}, {"text": "消息", "icon": "chat", "color": "#F25109", "link": "/pages/message/index"}, {"text": "我的", "icon": "user", "color": "#F25109", "link": "/pages/mine/index"}]}', '', '', '', ''),
(21, 'store_status', 'basic', NULL, '', 'string', '', '1', '', '', '', ''),
(22, 'close_qq', 'basic', NULL, '', 'string', '', '', '', '', '', ''),
(23, 'close_image', 'basic', NULL, '', 'string', '', '', '', '', '', ''),
(33, 'realname_enabled', 'basic', NULL, '', 'string', '', '1', '', '', '', ''),
(58, 'craft_mode', 'basic', NULL, '', 'string', '', 'lottery', '', '', '', ''),
(59, 'craft_banner', 'basic', NULL, '', 'string', '', '', '', '', '', ''),
(60, 'craft_jump', 'basic', NULL, '', 'string', '', '/pages/equip-craft/index', '', '', '', ''),
(61, 'lottery_banner', 'basic', NULL, '', 'string', '', '', '', '', '', ''),
(62, 'lottery_jump', 'basic', NULL, '', 'string', '', '', '', '', '', ''),
(68, 'craft_enabled', 'basic', NULL, '', 'string', '', '1', '', '', '', ''),
(69, 'craft_title', 'basic', NULL, '', 'string', '', '传世武器打造', '', '', '', ''),
(70, 'craft_subtitle', 'basic', NULL, '', 'string', '', '两阶段打造，进度满额解锁', '', '', '', ''),
(71, 'craft_price', 'basic', NULL, '', 'string', '', '1', '', '', '', ''),
(72, 'craft_progress_min', 'basic', NULL, '', 'string', '', '2', '', '', '', ''),
(73, 'craft_progress_max', 'basic', NULL, '', 'string', '', '5', '', '', '', ''),
(74, 'craft_crit_rate', 'basic', NULL, '', 'string', '', '15', '', '', '', ''),
(75, 'craft_crit_multi', 'basic', NULL, '', 'string', '', '2', '', '', '', ''),
(76, 'craft_bg', 'basic', NULL, '', 'string', '', '', '', '', '', ''),
(77, 'craft_rules', 'basic', NULL, '', 'string', '', 'test', '', '', '', ''),
(174, 'home_bgm_url', 'basic', NULL, '', 'string', '', 'https://kw-er.kuwo.cn/e729c0cfd815a7a8776d1ad4975831a7/6ad4bbe9/resource/30106/trackmedia/M500000qICQx1gB2G8.mp3', '', '', '', ''),
(175, 'home_bgm_title', 'basic', NULL, '', 'string', '', '背景音乐', '', '', '', ''),
(176, 'home_bgm_sub', 'basic', NULL, '', 'string', '', '首页背景音乐 · 点击播放', '', '', '', ''),
(177, 'home_bgm_enabled', 'basic', NULL, '', 'string', '', '1', '', '', '', '');

-- ----------------------------
-- Table structure for fa_craft_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_craft_log`;
CREATE TABLE IF NOT EXISTS `fa_craft_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT 0,
  `times` INT(11) DEFAULT 0,
  `phase` INT(11) DEFAULT 1,
  `progress` TEXT DEFAULT '0',
  `is_crit` INT(11) DEFAULT 0,
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for fa_craft_order
-- ----------------------------
DROP TABLE IF EXISTS `fa_craft_order`;
CREATE TABLE IF NOT EXISTS `fa_craft_order` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` TEXT DEFAULT '',
  `user_id` INT(11) DEFAULT 0,
  `package_id` INT(11) DEFAULT 0,
  `count` INT(11) DEFAULT 0,
  `amount` TEXT DEFAULT '0',
  `status` TEXT DEFAULT 'pending',
  `pay_time` INT(11) DEFAULT 0,
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for fa_craft_prize_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_craft_prize_log`;
CREATE TABLE IF NOT EXISTS `fa_craft_prize_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT 0,
  `prize_id` INT(11) DEFAULT 0,
  `prize_name` TEXT DEFAULT '',
  `prize_image` TEXT DEFAULT '',
  `status` TEXT DEFAULT 'bag',
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for fa_cron_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_cron_log`;
CREATE TABLE IF NOT EXISTS `fa_cron_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) DEFAULT 0,
  `task_name` TEXT DEFAULT '',
  `status` INT(11) DEFAULT 1,
  `message` TEXT DEFAULT '',
  `cost_ms` INT(11) DEFAULT 0,
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_cron_log (1 rows)
INSERT INTO `fa_cron_log` (`id`, `task_id`, `task_name`, `status`, `message`, `cost_ms`, `create_time`) VALUES
(1, 1, '订单超时自动取消', 1, '本次处理 0 条', 32, 1792298672);

-- ----------------------------
-- Table structure for fa_pay_callback_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_pay_callback_log`;
CREATE TABLE IF NOT EXISTS `fa_pay_callback_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `channel` TEXT DEFAULT '',
  `order_no` TEXT DEFAULT '',
  `raw` TEXT DEFAULT '',
  `sign_ok` INT(11) DEFAULT 0,
  `handled` INT(11) DEFAULT 0,
  `result` TEXT DEFAULT '',
  `ip` TEXT DEFAULT '',
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_pay_callback_log (1 rows)
INSERT INTO `fa_pay_callback_log` (`id`, `channel`, `order_no`, `raw`, `sign_ok`, `handled`, `result`, `ip`, `create_time`) VALUES
(1, 'epay', 'RC92298972', '{"trade_status":"TRADE_SUCCESS"}', 1, 1, '余额已增加', '127.0.0.1', 1792298072);

-- ----------------------------
-- Table structure for fa_pay_channel
-- ----------------------------
DROP TABLE IF EXISTS `fa_pay_channel`;
CREATE TABLE IF NOT EXISTS `fa_pay_channel` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` TEXT DEFAULT '',
  `name` TEXT DEFAULT '',
  `channel_type` TEXT DEFAULT '',
  `pid` TEXT DEFAULT '',
  `secret` TEXT DEFAULT '',
  `notify_url` TEXT DEFAULT '',
  `return_url` TEXT DEFAULT '',
  `timeout` INT(11) DEFAULT 300,
  `mch_id` TEXT DEFAULT '',
  `api_v3_key` TEXT DEFAULT '',
  `cert_serial` TEXT DEFAULT '',
  `private_key` TEXT DEFAULT '',
  `sort` INT(11) DEFAULT 0,
  `status` INT(11) DEFAULT 1,
  `remark` TEXT DEFAULT '',
  `create_time` INT(11) DEFAULT 0,
  `update_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_pay_channel (2 rows)
INSERT INTO `fa_pay_channel` (`id`, `code`, `name`, `channel_type`, `pid`, `secret`, `notify_url`, `return_url`, `timeout`, `mch_id`, `api_v3_key`, `cert_serial`, `private_key`, `sort`, `status`, `remark`, `create_time`, `update_time`) VALUES
(1, 'epay', '彩虹支付', 'epay', '', '', '/api/pay/notify/epay', '/h5/#/pages/index/paysuccess', 300, '', '', '', '', 1, 1, '支持微信/支付宝/QQ钱包，免签聚合', 1792298972, 1792298972),
(2, 'wechat_v3', '微信商户V3', 'wechat_v3', '', '', '/api/pay/notify/wechat', '', 300, '', '', '', '', 2, 0, '微信支付V3，支持 JSAPI/H5', 1792298972, 1792298972);

-- ----------------------------
-- Table structure for fa_sms_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_sms_log`;
CREATE TABLE IF NOT EXISTS `fa_sms_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mobile` TEXT DEFAULT '',
  `template_id` TEXT DEFAULT '',
  `content` TEXT DEFAULT '',
  `status` INT(11) DEFAULT 1,
  `provider_resp` TEXT DEFAULT '',
  `scene` TEXT DEFAULT '',
  `ip` TEXT DEFAULT '',
  `create_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_sms_log (1 rows)
INSERT INTO `fa_sms_log` (`id`, `mobile`, `template_id`, `content`, `status`, `provider_resp`, `scene`, `ip`, `create_time`) VALUES
(1, '13800000001', 'SMS_10001', '您的验证码为 8888，5 分钟内有效。', 1, 'provider=aliyun', 'login', '', 1792298372);

-- ----------------------------
-- Table structure for fa_third
-- ----------------------------
DROP TABLE IF EXISTS `fa_third`;
CREATE TABLE IF NOT EXISTS `fa_third` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT 0,
  `platform` TEXT DEFAULT '',
  `apptype` TEXT DEFAULT '',
  `unionid` TEXT DEFAULT '',
  `openid` TEXT DEFAULT '',
  `openname` TEXT DEFAULT '',
  `access_token` TEXT DEFAULT '',
  `refresh_token` TEXT DEFAULT '',
  `expires_in` INT(11) DEFAULT 0,
  `createtime` INT(11),
  `updatetime` INT(11),
  `logintime` INT(11),
  `expiretime` INT(11),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for fa_thug_withdraw
-- ----------------------------
DROP TABLE IF EXISTS `fa_thug_withdraw`;
CREATE TABLE IF NOT EXISTS `fa_thug_withdraw` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `thug_id` INT(11) DEFAULT 0,
  `thug_name` TEXT DEFAULT '',
  `withdraw_no` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '0',
  `fee` TEXT DEFAULT '0',
  `real_amount` TEXT DEFAULT '0',
  `account_type` TEXT DEFAULT 'wechat',
  `account` TEXT DEFAULT '',
  `apply_time` INT(11) DEFAULT 0,
  `audit_time` INT(11) DEFAULT 0,
  `pay_time` INT(11) DEFAULT 0,
  `status` INT(11) DEFAULT 0,
  `audit_admin` TEXT DEFAULT '',
  `reject_reason` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `create_time` INT(11) DEFAULT 0,
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of fa_thug_withdraw (5 rows)
INSERT INTO `fa_thug_withdraw` (`id`, `thug_id`, `thug_name`, `withdraw_no`, `amount`, `fee`, `real_amount`, `account_type`, `account`, `apply_time`, `audit_time`, `pay_time`, `status`, `audit_admin`, `reject_reason`, `remark`, `create_time`, `update_time`, `is_deleted`) VALUES
(1, 1, 'XT冰帝', 'TX31760001', '100.00', '2.00', '98.00', 'wechat', 'wx_iceking01', 1792310400, 1792314000, 1792315800, 3, '超级管理员', '', '首位打手提现', 1792310400, 1792310400, 0),
(2, 2, 'XT富贵', 'TX31760002', '500.00', '10.00', '490.00', 'alipay', 'ali_fugui@qq.com', 1792312200, 0, 0, 0, '', '', '大额提现待审核', 1792312200, 1792312200, 0),
(3, 3, 'XT动力', 'TX31760003', '200.00', '4.00', '196.00', 'wechat', 'wx_dongli02', 1792308600, 1792312600, 0, 2, '超级管理员', '', '已审核待打款', 1792308600, 1792308600, 0),
(4, 4, 'XTBaby', 'TX31760004', '60.00', '1.20', '58.80', 'wechat', 'wx_baby03', 1792305600, 1792309600, 0, 1, '超级管理员', '收款信息有误', '驳回示例', 1792305600, 1792305600, 0),
(5, 5, 'XT嘻宝', 'TX31760005', '150.00', '3.00', '147.00', 'alipay', 'ali_xibao@qq.com', 1792217600, 1792222600, 1792227600, 3, '超级管理员', '', '昨日已打款', 1792217600, 1792217600, 0);

-- ----------------------------
-- Table structure for invite_codes
-- ----------------------------
DROP TABLE IF EXISTS `invite_codes`;
CREATE TABLE IF NOT EXISTS `invite_codes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` TEXT,
  `type` TEXT DEFAULT 'thug',
  `user_id` INT(11) DEFAULT 0,
  `name` TEXT,
  `createtime` INT(11),
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of invite_codes (4 rows)
INSERT INTO `invite_codes` (`id`, `code`, `type`, `user_id`, `name`, `createtime`, `is_deleted`) VALUES
(1, 'CS8888', 'cs', 0, '客服小创', 1792298972, 0),
(2, 'THUG666', 'thug', 0, '大神老王', 1792298972, 0),
(3, 'oYImwZzN', 'cs', 0, '', 1792323480, 0),
(4, 'avz5juL4', 'thug', 0, '', 1792323522, 0);

-- ----------------------------
-- Table structure for notices
-- ----------------------------
DROP TABLE IF EXISTS `notices`;
CREATE TABLE IF NOT EXISTS `notices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` TEXT,
  `synopsis` TEXT,
  `content` TEXT,
  `type` INT(11) DEFAULT 1,
  `createtime` INT(11),
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of notices (2 rows)
INSERT INTO `notices` (`id`, `title`, `synopsis`, `content`, `type`, `createtime`, `is_deleted`) VALUES
(1, '欢迎使用创世网络', '点击查看平台介绍', '欢迎使用创世网络平台，本平台提供专业的电竞陪玩服务。', 1, 1792212572, 0),
(2, '系统升级公告', '平台于今日完成升级', '平台于今日完成系统升级，新增抢单神器等功能。', 1, 1792255772, 0);

-- ----------------------------
-- Table structure for orders
-- ----------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` TEXT,
  `user_id` INT(11),
  `thug_id` INT(11) DEFAULT 0,
  `title` TEXT,
  `logo` TEXT,
  `price` DECIMAL(10,2),
  `pay_price` DECIMAL(10,2),
  `number` INT(11) DEFAULT 1,
  `game_id` TEXT,
  `game_qf` TEXT,
  `remarks` TEXT,
  `synopsis` TEXT,
  `status` INT(11) DEFAULT 0,
  `status_text` TEXT,
  `createtime` INT(11),
  `paytime` INT(11),
  `connecttime` INT(11),
  `completetime` INT(11),
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_admin_user
-- ----------------------------
DROP TABLE IF EXISTS `res_admin_user`;
CREATE TABLE IF NOT EXISTS `res_admin_user` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` TEXT DEFAULT '',
  `nickname` TEXT DEFAULT '',
  `password` TEXT DEFAULT '',
  `role` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_auth
-- ----------------------------
DROP TABLE IF EXISTS `res_auth`;
CREATE TABLE IF NOT EXISTS `res_auth` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `domain` TEXT DEFAULT '',
  `license` TEXT DEFAULT '',
  `enabled` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_auth (1 rows)
INSERT INTO `res_auth` (`id`, `domain`, `license`, `enabled`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '', '', '', NULL, 0, 0);

-- ----------------------------
-- Table structure for res_balance
-- ----------------------------
DROP TABLE IF EXISTS `res_balance`;
CREATE TABLE IF NOT EXISTS `res_balance` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `before` TEXT DEFAULT '',
  `after` TEXT DEFAULT '',
  `type` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_banner
-- ----------------------------
DROP TABLE IF EXISTS `res_banner`;
CREATE TABLE IF NOT EXISTS `res_banner` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` TEXT DEFAULT '',
  `image` TEXT DEFAULT '',
  `link` TEXT DEFAULT '',
  `sort` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_banner (2 rows)
INSERT INTO `res_banner` (`id`, `title`, `image`, `link`, `sort`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '首页横幅', '/static/images/header_background.png', '/pages/classify/index', '1', '1', 1792298972, 0, 0),
(2, '1', '', '', '', '0', 1792324648, 1792324648, 0);

-- ----------------------------
-- Table structure for res_bill
-- ----------------------------
DROP TABLE IF EXISTS `res_bill`;
CREATE TABLE IF NOT EXISTS `res_bill` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `type` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_commission_detail
-- ----------------------------
DROP TABLE IF EXISTS `res_commission_detail`;
CREATE TABLE IF NOT EXISTS `res_commission_detail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `type` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_craft_package
-- ----------------------------
DROP TABLE IF EXISTS `res_craft_package`;
CREATE TABLE IF NOT EXISTS `res_craft_package` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `count` TEXT DEFAULT '',
  `price` TEXT DEFAULT '',
  `sort` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_craft_package (4 rows)
INSERT INTO `res_craft_package` (`id`, `name`, `count`, `price`, `sort`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '体验1次', '1', '1', '1', '1', 1792320370, 0, 0),
(2, '打造10次', '10', '9', '2', '1', 1792320370, 0, 0),
(3, '打造50次', '50', '40', '3', '1', 1792320370, 0, 0),
(4, '新套餐', '1', '1', '0', '1', 1792321576, 1792321576, 0);

-- ----------------------------
-- Table structure for res_craft_prize
-- ----------------------------
DROP TABLE IF EXISTS `res_craft_prize`;
CREATE TABLE IF NOT EXISTS `res_craft_prize` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `image` TEXT DEFAULT '',
  `type` TEXT DEFAULT '',
  `phase` TEXT DEFAULT '',
  `range` TEXT DEFAULT '',
  `sort` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_craft_prize (7 rows)
INSERT INTO `res_craft_prize` (`id`, `name`, `image`, `type`, `phase`, `range`, `sort`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '双截棍', '', 'normal', '1', '', '1', '1', 1792320370, 0, 0),
(2, '火焰刀', '', 'normal', '1', '', '2', '1', 1792320370, 0, 0),
(3, '熔岩双刃', '', 'stage', '1', '集齐10个碎片', '3', '1', 1792320370, 0, 0),
(4, '折叠直刀', '', 'normal', '2', '', '1', '1', 1792320370, 0, 0),
(5, '机械拳套', '', 'stage', '2', '集齐10个碎片', '2', '1', 1792320370, 0, 0),
(6, '测试奖品', '', 'normal', '1', '', '0', '1', 1792321694, 1792321698, 1),
(7, '测试奖品', '', 'normal', '1', '', '0', '1', 1792321707, 1792321711, 1);

-- ----------------------------
-- Table structure for res_cron
-- ----------------------------
DROP TABLE IF EXISTS `res_cron`;
CREATE TABLE IF NOT EXISTS `res_cron` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `command` TEXT DEFAULT '',
  `cron` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_cs_account
-- ----------------------------
DROP TABLE IF EXISTS `res_cs_account`;
CREATE TABLE IF NOT EXISTS `res_cs_account` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `username` TEXT DEFAULT '',
  `password` TEXT DEFAULT '',
  `thug_id` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_deposit
-- ----------------------------
DROP TABLE IF EXISTS `res_deposit`;
CREATE TABLE IF NOT EXISTS `res_deposit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_fine
-- ----------------------------
DROP TABLE IF EXISTS `res_fine`;
CREATE TABLE IF NOT EXISTS `res_fine` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `user_id` TEXT DEFAULT '',
  `reason` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_game_server
-- ----------------------------
DROP TABLE IF EXISTS `res_game_server`;
CREATE TABLE IF NOT EXISTS `res_game_server` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `game` TEXT DEFAULT '',
  `sort` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_game_server (9 rows)
INSERT INTO `res_game_server` (`id`, `name`, `game`, `sort`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '微信区', '王者荣耀', '1', '1', 1792310348, 0, 0),
(2, 'QQ区', '王者荣耀', '2', '1', 1792310348, 0, 0),
(3, 'iOS微信', '王者荣耀', '3', '1', 1792310348, 0, 0),
(4, '微信区', '和平精英', '4', '1', 1792310348, 0, 0),
(5, 'QQ区', '和平精英', '5', '1', 1792310348, 0, 0),
(6, '艾欧尼亚', '英雄联盟', '6', '1', 1792310348, 0, 0),
(7, '祖安', '英雄联盟', '7', '1', 1792310348, 0, 0),
(8, '北方大区', '穿越火线', '8', '1', 1792310348, 0, 0),
(9, '南方大区', '穿越火线', '9', '1', 1792310348, 0, 0);

-- ----------------------------
-- Table structure for res_goods
-- ----------------------------
DROP TABLE IF EXISTS `res_goods`;
CREATE TABLE IF NOT EXISTS `res_goods` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `category_id` TEXT DEFAULT '',
  `logo` TEXT DEFAULT '',
  `price` TEXT DEFAULT '',
  `unit` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_goods (5 rows)
INSERT INTO `res_goods` (`id`, `name`, `category_id`, `logo`, `price`, `unit`, `status`, `remark`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '王者荣耀陪练', '1', '/static/images/header_background.png', '25', '局', '1', '资深王者陪练，带你连胜上分', 1792298972, 0, 0),
(2, '和平精英陪玩', '1', '/static/images/header_background.png', '20', '局', '1', '吃鸡大神带你躺赢', 1792298972, 0, 0),
(3, '英雄联盟陪玩', '1', '/static/images/header_background.png', '30', '局', '1', 'LOL国服王者陪练', 1792298972, 0, 0),
(4, '原神代肝', '2', '/static/images/header_background.png', '15', '小时', '1', '原神每日委托/深渊代打', 1792298972, 0, 0),
(5, '电竞教学课程', '3', '/static/images/header_background.png', '99', '课时', '1', '职业选手一对一教学', 1792298972, 0, 0);

-- ----------------------------
-- Table structure for res_goods_category
-- ----------------------------
DROP TABLE IF EXISTS `res_goods_category`;
CREATE TABLE IF NOT EXISTS `res_goods_category` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `icon` TEXT DEFAULT '',
  `sort` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_goods_category (3 rows)
INSERT INTO `res_goods_category` (`id`, `name`, `icon`, `sort`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '陪玩上分', '', '1', '1', 1792298972, 0, 0),
(2, '娱乐陪玩', '', '2', '1', 1792298972, 0, 0),
(3, '电竞教学', '', '3', '1', 1792298972, 0, 0);

-- ----------------------------
-- Table structure for res_level
-- ----------------------------
DROP TABLE IF EXISTS `res_level`;
CREATE TABLE IF NOT EXISTS `res_level` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `icon` TEXT DEFAULT '',
  `need_score` TEXT DEFAULT '',
  `discount` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_level (5 rows)
INSERT INTO `res_level` (`id`, `name`, `icon`, `need_score`, `discount`, `remark`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, 'V1 青铜', '', '10', '1', '初级会员', 1792298972, 0, 0),
(2, 'V2 白银', '', '100', '0.98', '9.8折', 1792298972, 0, 0),
(3, 'V3 黄金', '', '500', '0.95', '9.5折', 1792298972, 0, 0),
(4, 'V4 铂金', '', '2000', '0.92', '9.2折', 1792298972, 0, 0),
(5, 'V5 钻石', '', '5000', '0.88', '8.8折', 1792298972, 0, 0);

-- ----------------------------
-- Table structure for res_my_setting
-- ----------------------------
DROP TABLE IF EXISTS `res_my_setting`;
CREATE TABLE IF NOT EXISTS `res_my_setting` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `site_name` TEXT DEFAULT '',
  `site_logo` TEXT DEFAULT '',
  `copyright` TEXT DEFAULT '',
  `customer_phone` TEXT DEFAULT '',
  `notice` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_my_setting (1 rows)
INSERT INTO `res_my_setting` (`id`, `site_name`, `site_logo`, `copyright`, `customer_phone`, `notice`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '', '', '', '', '', NULL, 0, 0);

-- ----------------------------
-- Table structure for res_order_commission
-- ----------------------------
DROP TABLE IF EXISTS `res_order_commission`;
CREATE TABLE IF NOT EXISTS `res_order_commission` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` TEXT DEFAULT '',
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `rate` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_order_complaint
-- ----------------------------
DROP TABLE IF EXISTS `res_order_complaint`;
CREATE TABLE IF NOT EXISTS `res_order_complaint` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` TEXT DEFAULT '',
  `user_id` TEXT DEFAULT '',
  `content` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_package
-- ----------------------------
DROP TABLE IF EXISTS `res_package`;
CREATE TABLE IF NOT EXISTS `res_package` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `price` TEXT DEFAULT '',
  `days` TEXT DEFAULT '',
  `desc` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_prize_pool
-- ----------------------------
DROP TABLE IF EXISTS `res_prize_pool`;
CREATE TABLE IF NOT EXISTS `res_prize_pool` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `icon` TEXT DEFAULT '',
  `rate` TEXT DEFAULT '',
  `stock` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_prize_pool (3 rows)
INSERT INTO `res_prize_pool` (`id`, `name`, `icon`, `rate`, `stock`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '一等奖 iPhone', '', '1', '10', '1', 1792298972, 0, 0),
(2, '二等奖 现金红包', '', '9', '100', '1', 1792298972, 0, 0),
(3, '谢谢惠顾', '', '90', '0', '1', 1792298972, 0, 0);

-- ----------------------------
-- Table structure for res_profile
-- ----------------------------
DROP TABLE IF EXISTS `res_profile`;
CREATE TABLE IF NOT EXISTS `res_profile` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nickname` TEXT DEFAULT '',
  `avatar` TEXT DEFAULT '',
  `password` TEXT DEFAULT '',
  `signature` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_profile (1 rows)
INSERT INTO `res_profile` (`id`, `nickname`, `avatar`, `password`, `signature`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '', '', '', '', NULL, 0, 0);

-- ----------------------------
-- Table structure for res_quick_entry
-- ----------------------------
DROP TABLE IF EXISTS `res_quick_entry`;
CREATE TABLE IF NOT EXISTS `res_quick_entry` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `icon` TEXT DEFAULT '',
  `color` TEXT DEFAULT '',
  `link` TEXT DEFAULT '',
  `sort` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_quick_entry (2 rows)
INSERT INTO `res_quick_entry` (`id`, `name`, `icon`, `color`, `link`, `sort`, `status`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '打手入驻', '/static/ym/kf.png', '#2B6EFF', '/pages/hatchet/index', '1', '1', 1792298972, 0, 0),
(2, '客服入驻', '/static/ym/ss.png', '#FF7A45', '/pages/cs/index', '2', '1', 1792298972, 0, 0);

-- ----------------------------
-- Table structure for res_recharge_order
-- ----------------------------
DROP TABLE IF EXISTS `res_recharge_order`;
CREATE TABLE IF NOT EXISTS `res_recharge_order` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` TEXT DEFAULT '',
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `method` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_refund
-- ----------------------------
DROP TABLE IF EXISTS `res_refund`;
CREATE TABLE IF NOT EXISTS `res_refund` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_no` TEXT DEFAULT '',
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `reason` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_role
-- ----------------------------
DROP TABLE IF EXISTS `res_role`;
CREATE TABLE IF NOT EXISTS `res_role` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT DEFAULT '',
  `auth` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_sms_config
-- ----------------------------
DROP TABLE IF EXISTS `res_sms_config`;
CREATE TABLE IF NOT EXISTS `res_sms_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `provider` TEXT DEFAULT '',
  `access_key` TEXT DEFAULT '',
  `access_secret` TEXT DEFAULT '',
  `sign` TEXT DEFAULT '',
  `template` TEXT DEFAULT '',
  `enabled` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_sms_config (1 rows)
INSERT INTO `res_sms_config` (`id`, `provider`, `access_key`, `access_secret`, `sign`, `template`, `enabled`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '', '', '', '', '', '', NULL, 0, 0);

-- ----------------------------
-- Table structure for res_upload_storage
-- ----------------------------
DROP TABLE IF EXISTS `res_upload_storage`;
CREATE TABLE IF NOT EXISTS `res_upload_storage` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `driver` TEXT DEFAULT '',
  `domain` TEXT DEFAULT '',
  `qiniu_key` TEXT DEFAULT '',
  `qiniu_secret` TEXT DEFAULT '',
  `qiniu_bucket` TEXT DEFAULT '',
  `oss_key` TEXT DEFAULT '',
  `oss_secret` TEXT DEFAULT '',
  `oss_bucket` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_upload_storage (1 rows)
INSERT INTO `res_upload_storage` (`id`, `driver`, `domain`, `qiniu_key`, `qiniu_secret`, `qiniu_bucket`, `oss_key`, `oss_secret`, `oss_bucket`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '', '', '', '', '', '', '', '', NULL, 0, 0);

-- ----------------------------
-- Table structure for res_wechat_notice
-- ----------------------------
DROP TABLE IF EXISTS `res_wechat_notice`;
CREATE TABLE IF NOT EXISTS `res_wechat_notice` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` TEXT DEFAULT '',
  `template_id` TEXT DEFAULT '',
  `content` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for res_wechat_robot
-- ----------------------------
DROP TABLE IF EXISTS `res_wechat_robot`;
CREATE TABLE IF NOT EXISTS `res_wechat_robot` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `webhook` TEXT DEFAULT '',
  `enabled` TEXT DEFAULT '',
  `secret` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of res_wechat_robot (1 rows)
INSERT INTO `res_wechat_robot` (`id`, `webhook`, `enabled`, `secret`, `createtime`, `update_time`, `is_deleted`) VALUES
(1, '', '', '', NULL, 0, 0);

-- ----------------------------
-- Table structure for res_withdraw
-- ----------------------------
DROP TABLE IF EXISTS `res_withdraw`;
CREATE TABLE IF NOT EXISTS `res_withdraw` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` TEXT DEFAULT '',
  `amount` TEXT DEFAULT '',
  `account` TEXT DEFAULT '',
  `status` TEXT DEFAULT '',
  `remark` TEXT DEFAULT '',
  `createtime` INT(11),
  `update_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for thug
-- ----------------------------
DROP TABLE IF EXISTS `thug`;
CREATE TABLE IF NOT EXISTS `thug` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `name` TEXT,
  `status` INT(11) DEFAULT 0,
  `sh_status` INT(11) DEFAULT 0,
  `is_customer_service` INT(11) DEFAULT 0,
  `createtime` INT(11),
  `real_name` TEXT DEFAULT '',
  `id_card` TEXT DEFAULT '',
  `realname_front` TEXT DEFAULT '',
  `realname_back` TEXT DEFAULT '',
  `realname_status` INT(11) DEFAULT 0,
  `realname_reason` TEXT DEFAULT '',
  `realname_time` INT(11) DEFAULT 0,
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of thug (2 rows)
INSERT INTO `thug` (`id`, `user_id`, `name`, `status`, `sh_status`, `is_customer_service`, `createtime`, `real_name`, `id_card`, `realname_front`, `realname_back`, `realname_status`, `realname_reason`, `realname_time`, `is_deleted`) VALUES
(1, 1, '预览打手', 1, 1, 1, 1792304414, '', '', '', '', 0, '', 0, 0),
(2, 2, '创世用户3381', 1, 1, 1, 1792323486, '', '', '', '', 0, '', 0, 0);

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `token` TEXT,
  `nickname` TEXT,
  `avatar` TEXT,
  `cs_avatar` TEXT DEFAULT '',
  `type` INT(11) DEFAULT 1,
  `openid` TEXT,
  `pid` INT(11) DEFAULT 0,
  `money` DECIMAL(10,2) DEFAULT 0,
  `score` INT(11) DEFAULT 0,
  `level` INT(11) DEFAULT 1,
  `group_chat` INT(11) DEFAULT 0,
  `createtime` INT(11),
  `is_deleted` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records of users (3 rows)
INSERT INTO `users` (`id`, `token`, `nickname`, `avatar`, `cs_avatar`, `type`, `openid`, `pid`, `money`, `score`, `level`, `group_chat`, `createtime`, `is_deleted`) VALUES
(1, '9a8c3c49069dccf7ba00e57e235b998c', '创世用户4395', '/static/eon.png', '', 2, 'preview_thug_001', 0, 0.0, 0, 1, 0, 1792304395, 0),
(2, 'e152f95ac9116b3cb7803ee2fd8144d1', '创世用户3381', '/static/eon.png', '', 1, 'juhe_wechat_mock_1792323380005', 0, 0.0, 0, 1, 0, 1792323381, 0),
(3, '6ef57c3312c966a301edb5d2a5e68006', '创世用户3593', '/static/eon.png', '', 1, 'juhe_wechat_mock_1792323591599', 0, 0.0, 0, 1, 0, 1792323593, 0);

SET FOREIGN_KEY_CHECKS = 1;
