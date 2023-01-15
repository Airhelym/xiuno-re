# beta4 -> beta5
DROP TABLE IF EXISTS bbs_queue;
CREATE TABLE bbs_queue (
  queueid int(11) unsigned NOT NULL default '0',		# 队列 id
  v int(11) NOT NULL default '0',			# 队列中存放的数据，只能为 int
  expiry int(11) unsigned NOT NULL default '0',		# 过期时间，默认 0，不过期
  UNIQUE KEY(queueid, v),
  KEY(expiry)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE bbs_post ADD COLUMN quotepid int(11) NOT NULL default '0';