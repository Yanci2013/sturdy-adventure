-- ============================================================
-- 创世网络 - 智能抢单神器 数据库变更
-- 后台可配置：开关、每日价格、免费打手列表
-- 执行方式：在 phpMyAdmin 或 mysql 命令行中导入本文件
-- ============================================================

-- 1. 系统配置项（后台「系统配置」中可编辑）
-- 智能抢单总开关
INSERT INTO `fa_config` (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('qiangdan_enabled', 'basic', '智能抢单开关', '是否开启智能抢单神器功能：0=关闭,1=开启', 'radio', '', '1', '{"0":"关闭","1":"开启"}', '', '', '')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `title` = VALUES(`title`), `content` = VALUES(`content`);

-- 每日价格
INSERT INTO `fa_config` (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('qiangdan_price', 'basic', '抢单神器每日价格', '智能抢单神器每天的开通费用（单位：元）', 'string', '', '25', '', '', '', '')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `title` = VALUES(`title`);

-- 免费打手ID列表
INSERT INTO `fa_config` (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES ('qiangdan_free_thugs', 'basic', '免费抢单打手ID', '免费开启抢单神器的打手ID，多个用英文逗号分隔（如：1,2,3）', 'string', '', '', '', '', '', '')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `title` = VALUES(`title`);

-- 2. 打手表增加抢单相关字段
ALTER TABLE `fa_thug`
  ADD COLUMN `qiangdan_expire_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '抢单神器到期时间戳，0=未开通' AFTER `is_customer_service`,
  ADD COLUMN `qiangdan_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '抢单神器运行状态：0=关闭,1=开启' AFTER `qiangdan_expire_time`,
  ADD COLUMN `qiangdan_config` text NULL COMMENT '抢单配置JSON' AFTER `qiangdan_status`;

-- 3. 抢单神器订阅订单表
CREATE TABLE IF NOT EXISTS `fa_qiangdan_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号',
  `thug_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '打手ID',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '支付金额',
  `days` int(11) unsigned NOT NULL DEFAULT '1' COMMENT '开通天数',
  `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending' COMMENT '订单状态',
  `pay_type` varchar(20) NOT NULL DEFAULT 'wechat' COMMENT '支付方式 wechat/alipay',
  `transaction_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方交易号',
  `expire_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '到期时间戳',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `thug_id` (`thug_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抢单神器订阅订单';

-- 4. 抢单日志表
CREATE TABLE IF NOT EXISTS `fa_qiangdan_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `thug_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '打手ID',
  `order_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '订单ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `status` enum('success','failed') NOT NULL DEFAULT 'success' COMMENT '抢单结果',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '抢单时间',
  PRIMARY KEY (`id`),
  KEY `thug_id` (`thug_id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抢单神器抢单日志';
