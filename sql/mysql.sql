CREATE TABLE `{ticket}` (
  `id`          INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `subject`     VARCHAR(255)        NOT NULL DEFAULT '',
  `message`     TEXT,
  `uid`         INT(10) UNSIGNED    NOT NULL DEFAULT '0',
  `status`      TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `time_create` INT(10) UNSIGNED    NOT NULL DEFAULT '0',
  `ip`          CHAR(15)            NOT NULL DEFAULT '',
  `mid`         INT(10) UNSIGNED    NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `time_create` (`time_create`),
  KEY `mid` (`mid`),
  KEY `mid_status` (`mid`, `status`),
  KEY `mid_uid` (`mid`, `uid`),
  KEY `mid_status_uid` (`mid`, `status`, `uid`),
  KEY `time_create_id` (`time_create`, `id`)
);