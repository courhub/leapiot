-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1:3306
-- 產生時間： 2019 年 09 月 14 日 09:04
-- 伺服器版本： 5.7.26
-- PHP 版本： 7.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `yamamoto`
--

-- --------------------------------------------------------

--
-- 資料表結構 `ym_alerm`
--

DROP TABLE IF EXISTS `ym_alerm`;
CREATE TABLE IF NOT EXISTS `ym_alerm` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity` int(10) UNSIGNED NOT NULL,
  `adate` datetime NOT NULL,
  `cdate` datetime NOT NULL,
  `errorcode` int(10) UNSIGNED NOT NULL,
  `operating` tinyint(4) NOT NULL,
  `para` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `program` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 資料表結構 `ym_cycle`
--

DROP TABLE IF EXISTS `ym_cycle`;
CREATE TABLE IF NOT EXISTS `ym_cycle` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity` int(10) UNSIGNED NOT NULL,
  `bdate` datetime NOT NULL COMMENT '起始时间',
  `edate` datetime NOT NULL COMMENT '结束时间',
  `stage` int(10) UNSIGNED NOT NULL COMMENT '进度:0-开始,1-完成,2-未完成',
  `type` int(10) UNSIGNED NOT NULL COMMENT '类型:0-量测水分,4-计时',
  `amt` decimal(10,0) NOT NULL COMMENT '数量',
  `unit` varchar(10) NOT NULL COMMENT '单位',
  `kind` tinyint(3) UNSIGNED NOT NULL COMMENT '种类:0-稻子,1-麦子,6-玉米,20-其他',
  `brecord` int(10) UNSIGNED NOT NULL COMMENT 'cycle开始时的record.id',
  `erecord` int(10) UNSIGNED NOT NULL COMMENT 'cycle结束时的record.id',
  `cdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `flag` bit(1) NOT NULL DEFAULT b'1',
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='设备使用CYCLE记录';

--
-- 觸發器 `ym_cycle`
--
DROP TRIGGER IF EXISTS `trigger_ym_cycle_after_insert`;
DELIMITER $$
CREATE TRIGGER `trigger_ym_cycle_after_insert` AFTER INSERT ON `ym_cycle` FOR EACH ROW update `ym_entiry` set `lastcycle` = new.id where `id`= new.entity
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 資料表結構 `ym_entity`
--

DROP TABLE IF EXISTS `ym_entity`;
CREATE TABLE IF NOT EXISTS `ym_entity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sort` int(10) UNSIGNED NOT NULL COMMENT '设备类型',
  `model` varchar(100) NOT NULL COMMENT '机型',
  `org` int(10) UNSIGNED NOT NULL COMMENT '所属组织(org)',
  `status` tinyint(3) UNSIGNED NOT NULL COMMENT '当前状态',
  `csn` varchar(50) NOT NULL COMMENT '客户编号',
  `psn` varchar(50) NOT NULL COMMENT '厂家编号',
  `pwd` varchar(50) DEFAULT NULL COMMENT 'psn IOT连接密码',
  `spec` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `lastgps` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最新的gps.id',
  `lastrecord` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最新的record.id',
  `lastcycle` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最新的cycle.id',
  `cdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cuser` int(10) UNSIGNED NOT NULL DEFAULT '1',
  `udate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uuser` int(10) UNSIGNED NOT NULL DEFAULT '1',
  `flag` bit(1) NOT NULL DEFAULT b'1',
  PRIMARY KEY (`id`),
  KEY `sort` (`sort`) USING BTREE,
  KEY `org` (`org`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='设备实体主表';

--
-- 傾印資料表的資料 `ym_entity`
--

INSERT INTO `ym_entity` (`id`, `sort`, `model`, `org`, `status`, `csn`, `psn`, `pwd`, `spec`, `lastgps`, `lastrecord`, `lastcycle`, `cdate`, `cuser`, `udate`, `uuser`, `flag`) VALUES
(1, 1, '120H', 3, 1, 'HGJ001', '00001', '7162485b30dbe2644067b6ebc5ebe0af', '', 0, 0, 0, '2019-09-12 16:15:03', 1, '2019-09-12 16:15:03', 1, b'1');

-- --------------------------------------------------------

--
-- 資料表結構 `ym_gps`
--

DROP TABLE IF EXISTS `ym_gps`;
CREATE TABLE IF NOT EXISTS `ym_gps` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity` int(11) NOT NULL,
  `lon` float NOT NULL COMMENT '经度,正为东半球,负为西半球.',
  `lat` float NOT NULL COMMENT '纬度,正为北半球,负为南半球.',
  `velocity` int(11) NOT NULL COMMENT '速度,米/秒.',
  `direction` int(11) NOT NULL COMMENT '方向.正北为0度.',
  `cdate` datetime NOT NULL,
  `type` char(1) NOT NULL COMMENT '定位态别:，A=自主定位，D=差分，E=估算，N=数据无效',
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 觸發器 `ym_gps`
--
DROP TRIGGER IF EXISTS `trigger_ym_gps_after_insert`;
DELIMITER $$
CREATE TRIGGER `trigger_ym_gps_after_insert` AFTER INSERT ON `ym_gps` FOR EACH ROW update `ym_entity` set `lastgps` = new.id where `id` = new.entity
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 資料表結構 `ym_org`
--

DROP TABLE IF EXISTS `ym_org`;
CREATE TABLE IF NOT EXISTS `ym_org` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL COMMENT '上阶org id',
  `sort` tinyint(3) UNSIGNED NOT NULL,
  `cdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cuser` int(11) NOT NULL,
  `udate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uuser` int(11) NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- 傾印資料表的資料 `ym_org`
--

INSERT INTO `ym_org` (`id`, `name`, `pid`, `sort`, `cdate`, `cuser`, `udate`, `uuser`, `flag`) VALUES
(1, '平台', 1, 1, '2019-09-12 13:04:51', 1, '2019-09-12 13:04:51', 1, b'1'),
(2, '山本', 1, 2, '2019-09-12 13:05:39', 1, '2019-09-12 13:05:39', 1, b'1');

-- --------------------------------------------------------

--
-- 資料表結構 `ym_para`
--

DROP TABLE IF EXISTS `ym_para`;
CREATE TABLE IF NOT EXISTS `ym_para` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sort` int(10) UNSIGNED NOT NULL,
  `code` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `note` varchar(1000) NOT NULL,
  `cdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cuser` int(10) UNSIGNED NOT NULL,
  `udate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uuser` int(10) UNSIGNED NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1',
  PRIMARY KEY (`id`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8;

--
-- 傾印資料表的資料 `ym_para`
--

INSERT INTO `ym_para` (`id`, `sort`, `code`, `name`, `note`, `cdate`, `cuser`, `udate`, `uuser`, `flag`) VALUES
(1, 0, 1, '參數類別', '', '2019-09-06 15:56:06', 1, '2019-09-06 15:56:06', 1, b'1'),
(2, 1, 1, 'ORG類別', 'ym_org.sort', '2019-09-06 15:57:51', 1, '2019-09-06 15:57:51', 1, b'1'),
(3, 1, 2, '設備類別', 'ym_entity.sort', '2019-09-12 11:37:21', 1, '2019-09-12 11:37:21', 1, b'1'),
(4, 2, 1, '平台', '运营平台', '2019-09-12 11:41:47', 1, '2019-09-12 11:41:47', 1, b'1'),
(5, 2, 2, '商家', '', '2019-09-12 11:47:29', 1, '2019-09-12 11:47:29', 1, b'1'),
(6, 2, 3, '用户', '', '2019-09-12 11:48:03', 1, '2019-09-12 11:48:03', 1, b'1'),
(7, 3, 1, '烘干机', '粮食烘干机', '2019-09-12 13:00:32', 1, '2019-09-12 13:00:32', 1, b'1'),
(8, 3, 1, '干燥机', '干燥机', '2019-09-12 16:06:38', 1, '2019-09-12 16:06:38', 1, b'0'),
(9, 7, 1, '烘干条件', 'ym_cycle.type', '2019-09-13 10:19:47', 1, '2019-09-13 10:19:47', 1, b'1'),
(10, 7, 2, '谷物种类', 'ym_cycle.kind', '2019-09-13 10:20:36', 1, '2019-09-13 10:20:36', 1, b'1'),
(11, 7, 3, '烘干进度', 'ym_cycle.stage', '2019-09-13 10:21:14', 1, '2019-09-13 10:21:14', 1, b'1'),
(12, 1, 3, '权限层级', 'ym_user.level', '2019-09-13 10:25:37', 1, '2019-09-13 10:25:37', 1, b'1'),
(13, 12, 100, '平台管理员', '', '2019-09-13 10:26:18', 1, '2019-09-13 10:26:18', 1, b'1'),
(14, 12, 110, '平台浏览', '', '2019-09-13 10:27:26', 1, '2019-09-13 10:27:26', 1, b'1'),
(15, 12, 200, '商家管理员', '', '2019-09-13 10:27:57', 1, '2019-09-13 10:27:57', 1, b'1'),
(16, 12, 210, '商家浏览', '', '2019-09-13 10:28:12', 1, '2019-09-13 10:28:12', 1, b'1'),
(17, 12, 300, '用户管理员', '', '2019-09-13 10:28:39', 1, '2019-09-13 10:28:39', 1, b'1'),
(18, 12, 310, '用户浏览', '', '2019-09-13 10:28:53', 1, '2019-09-13 10:28:53', 1, b'1'),
(19, 7, 4, '错误代码', 'ym_alert.errorcode', '2019-09-13 14:43:47', 1, '2019-09-13 14:43:47', 1, b'1'),
(20, 19, 1, 'Lower screw overload / phase loss', '下螺旋超载/断相', '2019-09-13 14:50:50', 1, '2019-09-13 14:50:50', 1, b'1'),
(21, 19, 2, 'Elevator overload / phase loss', '升降机超载/断相', '2019-09-13 14:50:50', 1, '2019-09-13 14:50:50', 1, b'1'),
(22, 19, 3, 'Upper screw overload / phase loss', '上螺丝超载/断相', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(23, 19, 4, 'Blower 1 overload / phase loss', '送风机1超载/断相', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(24, 19, 5, 'Blower 2 overload / phase loss', '送风机2超载/断相', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(25, 19, 6, 'Suction blower overload / phase loss', '圧送风机超载/断相', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(26, 19, 11, 'Shutter drum error', '排粮滚筒出错', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(27, 19, 12, 'One-way rotation of shutter drum', '排粮滚筒单向旋转', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(28, 19, 13, 'Error on fixed position of shutter drum', '排粮滚筒固定位置出错', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(29, 19, 14, 'Abnormal action of shutter drum', '排粮滚筒异常动作', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(30, 19, 16, 'Discharge outlet partly open', '排粮口部分打开', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(31, 19, 17, 'Discharge outlet full open', '排粮口完全打开', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(32, 19, 18, 'Discharge outlet partly open', '排粮口部分打开', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(33, 19, 19, 'Discharge outlet closed', '排粮口关闭', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(34, 19, 21, 'Open air temperature thermistor wire break', '温度热敏电阻断线', '2019-09-13 14:50:51', 1, '2019-09-13 14:50:51', 1, b'1'),
(35, 19, 22, 'Open air temperature thermistor wire short-circuit', '温度热敏电阻短路', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(36, 19, 23, 'Hot air temperature thermistor wire break', '热风温度热敏电阻断线', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(37, 19, 24, 'Hot air temperature thermistor wire short-circuit', '热风温度热敏电阻短路', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(38, 19, 25, 'Hot air temperature too high', '热风温度太高', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(39, 19, 26, 'Hot air too high', '', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(40, 19, 27, 'Hot air too low', '', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(41, 19, 28, 'Air pressure sensor break', '气压传感器损坏', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(42, 19, 29, 'Non operation of burner', '燃烧器不工作', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(43, 19, 31, 'Burner trouble', '燃烧器不工作', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(44, 19, 41, 'Memory card error', '燃烧器故障', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(45, 19, 42, 'Discrepancy of the number set in the memory card', '插入记程序编号不统一', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(46, 19, 43, 'Dip switch setting error', '', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(47, 19, 45, 'Moisture meter lock error 1', '', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1'),
(48, 19, 46, 'Moisture meter lock error 2', '水分计锁定出错2', '2019-09-13 14:50:52', 1, '2019-09-13 14:50:52', 1, b'1');

-- --------------------------------------------------------

--
-- 資料表結構 `ym_record`
--

DROP TABLE IF EXISTS `ym_record`;
CREATE TABLE IF NOT EXISTS `ym_record` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity` int(10) UNSIGNED NOT NULL,
  `fdate` datetime NOT NULL COMMENT '状态开始时间',
  `tdate` datetime NOT NULL COMMENT '狀態结束時間',
  `operating` tinyint(4) NOT NULL COMMENT '设备状态',
  `para` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 觸發器 `ym_record`
--
DROP TRIGGER IF EXISTS `trigger_ym_record_after_insert`;
DELIMITER $$
CREATE TRIGGER `trigger_ym_record_after_insert` AFTER INSERT ON `ym_record` FOR EACH ROW update `ym_entiry` set `lastrecord` = new.id where `id`= new.entity
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- 資料表結構 `ym_user`
--

DROP TABLE IF EXISTS `ym_user`;
CREATE TABLE IF NOT EXISTS `ym_user` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `pwd` varchar(40) NOT NULL,
  `realname` varchar(100) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `mobile` varchar(100) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `org` int(10) UNSIGNED NOT NULL,
  `cdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cuser` int(10) UNSIGNED NOT NULL,
  `udate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uuser` int(10) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `org` (`org`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

--
-- 傾印資料表的資料 `ym_user`
--

INSERT INTO `ym_user` (`id`, `name`, `pwd`, `realname`, `mail`, `mobile`, `level`, `org`, `cdate`, `cuser`, `udate`, `uuser`, `status`, `flag`) VALUES
(1, 'admin', '7162485b30dbe2644067b6ebc5ebe0af', '系统管理员', '', '', 100, 1, '2019-09-12 13:10:31', 1, '2019-09-12 13:10:31', 1, 1, b'1'),
(2, 'yama', '98760ceb9a30e2885173e974285844f6', '山本', '', '', 200, 2, '2019-09-12 13:18:27', 1, '2019-09-12 13:18:27', 1, 1, b'1');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
