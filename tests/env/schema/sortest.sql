DROP TABLE IF EXISTS `sortest`;
CREATE TABLE IF NOT EXISTS `sortest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sort` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sort` (`sort`)
) ENGINE=MEMORY  DEFAULT CHARSET=utf8;
