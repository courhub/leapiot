-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2019-09-01 23:46:42
-- 服务器版本： 10.3.11-MariaDB
-- PHP 版本： 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `yamamoto`
--

-- --------------------------------------------------------

--
-- 表的结构 `ym_alerm`
--

CREATE TABLE `ym_alerm` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity` int(10) UNSIGNED NOT NULL,
  `adate` datetime NOT NULL,
  `cdate` datetime NOT NULL,
  `errorcode` int(10) UNSIGNED NOT NULL,
  `type` tinyint(4) NOT NULL,
  `para` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `program` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ym_cycle`
--

CREATE TABLE `ym_cycle` (
  `id` int(10) UNSIGNED NOT NULL,
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
  `cdate` datetime NOT NULL DEFAULT current_timestamp(),
  `flag` bit(1) NOT NULL DEFAULT b'1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='设备使用CYCLE记录';

-- --------------------------------------------------------

--
-- 表的结构 `ym_entity`
--

CREATE TABLE `ym_entity` (
  `id` int(11) NOT NULL,
  `sort` int(10) UNSIGNED NOT NULL COMMENT '设备类型',
  `model` varchar(100) NOT NULL COMMENT '机型',
  `org` int(10) UNSIGNED NOT NULL COMMENT '所属组织(org)',
  `status` tinyint(3) UNSIGNED NOT NULL COMMENT '当前状态',
  `csn` varchar(50) NOT NULL COMMENT '客户编号',
  `psn` varchar(50) NOT NULL COMMENT '厂家编号',
  `specs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `lastgps` int(10) UNSIGNED NOT NULL COMMENT '最新的gps.id',
  `lastrecord` int(10) UNSIGNED NOT NULL COMMENT '最新的record.id',
  `lastcycle` int(10) UNSIGNED NOT NULL COMMENT '最新的cycle.id',
  `cdate` datetime NOT NULL DEFAULT current_timestamp(),
  `cuser` int(10) UNSIGNED NOT NULL,
  `udate` datetime NOT NULL DEFAULT current_timestamp(),
  `uuser` int(10) UNSIGNED NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='设备实体主表';

-- --------------------------------------------------------

--
-- 表的结构 `ym_gps`
--

CREATE TABLE `ym_gps` (
  `id` int(11) UNSIGNED NOT NULL,
  `entity` int(11) NOT NULL,
  `lon` float NOT NULL COMMENT '经度,正为东半球,负为西半球.',
  `lat` float NOT NULL COMMENT '纬度,正为北半球,负为南半球.',
  `velocity` int(11) NOT NULL COMMENT '速度,米/秒.',
  `direction` int(11) NOT NULL COMMENT '方向.正北为0度.',
  `cdate` datetime NOT NULL,
  `type` char(1) NOT NULL COMMENT '定位态别:，A=自主定位，D=差分，E=估算，N=数据无效'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ym_org`
--

CREATE TABLE `ym_org` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort` tinyint(3) UNSIGNED NOT NULL,
  `cdate` datetime NOT NULL DEFAULT current_timestamp(),
  `cuser` int(11) NOT NULL,
  `udate` datetime NOT NULL DEFAULT current_timestamp(),
  `uuser` int(11) NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ym_para`
--

CREATE TABLE `ym_para` (
  `id` int(10) UNSIGNED NOT NULL,
  `sort` int(10) UNSIGNED NOT NULL,
  `code` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `note` varchar(1000) NOT NULL,
  `cdate` datetime NOT NULL DEFAULT current_timestamp(),
  `cuser` int(10) UNSIGNED NOT NULL,
  `udate` datetime NOT NULL DEFAULT current_timestamp(),
  `uuser` int(10) UNSIGNED NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ym_record`
--

CREATE TABLE `ym_record` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity` int(10) UNSIGNED NOT NULL,
  `cdate` datetime NOT NULL,
  `rdate` datetime NOT NULL,
  `type` tinyint(4) NOT NULL,
  `para` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `ym_user`
--

CREATE TABLE `ym_user` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `pwd` varchar(40) NOT NULL,
  `realname` varchar(100) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `mobile` varchar(100) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `org` int(10) UNSIGNED NOT NULL,
  `cdate` datetime NOT NULL DEFAULT current_timestamp(),
  `cuser` int(10) UNSIGNED NOT NULL,
  `udate` datetime NOT NULL DEFAULT current_timestamp(),
  `uuser` int(10) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `flag` bit(1) NOT NULL DEFAULT b'1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转储表的索引
--

--
-- 表的索引 `ym_alerm`
--
ALTER TABLE `ym_alerm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity` (`entity`) USING BTREE;

--
-- 表的索引 `ym_cycle`
--
ALTER TABLE `ym_cycle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity` (`entity`);

--
-- 表的索引 `ym_entity`
--
ALTER TABLE `ym_entity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sort` (`sort`) USING BTREE,
  ADD KEY `org` (`org`);

--
-- 表的索引 `ym_gps`
--
ALTER TABLE `ym_gps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity` (`entity`) USING BTREE;

--
-- 表的索引 `ym_org`
--
ALTER TABLE `ym_org`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `ym_para`
--
ALTER TABLE `ym_para`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sort` (`sort`);

--
-- 表的索引 `ym_record`
--
ALTER TABLE `ym_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity` (`entity`);

--
-- 表的索引 `ym_user`
--
ALTER TABLE `ym_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `org` (`org`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `ym_alerm`
--
ALTER TABLE `ym_alerm`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_cycle`
--
ALTER TABLE `ym_cycle`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_entity`
--
ALTER TABLE `ym_entity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_gps`
--
ALTER TABLE `ym_gps`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_org`
--
ALTER TABLE `ym_org`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_para`
--
ALTER TABLE `ym_para`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_record`
--
ALTER TABLE `ym_record`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `ym_user`
--
ALTER TABLE `ym_user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
