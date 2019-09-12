-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1:3306
-- 產生時間： 2019 年 09 月 12 日 09:35
-- 伺服器版本： 5.7.26
-- PHP 版本： 7.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";


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
  `type` tinyint(4) NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

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
(8, 3, 1, '干燥机', '粮食烘干机', '2019-09-12 16:06:38', 1, '2019-09-12 16:06:38', 1, b'0');

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
  `type` tinyint(4) NOT NULL,
  `para` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
(1, 'admin', '7162485b30dbe2644067b6ebc5ebe0af', '系统管理员', '', '', 1, 1, '2019-09-12 13:10:31', 1, '2019-09-12 13:10:31', 1, 1, b'1'),
(2, 'yama', '98760ceb9a30e2885173e974285844f6', '山本', '', '', 3, 2, '2019-09-12 13:18:27', 1, '2019-09-12 13:18:27', 1, 1, b'1');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
