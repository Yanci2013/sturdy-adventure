ALTER TABLE `fa_thug` ADD COLUMN `is_customer_service` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '客服身份:0=否,1=是' AFTER `recommend`;
