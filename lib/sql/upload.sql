-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2024-03-09 18:58:10
-- 服务器版本： 8.0.12
-- PHP 版本： 8.0.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `apiphp`
--

-- --------------------------------------------------------

--
-- 表的结构 `upload_file`
--

CREATE TABLE `upload_file` (
  `id` int(11) NOT NULL,
  `token` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `uid` int(11) NOT NULL DEFAULT '-1' COMMENT '上传用户id',
  `save_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '真实文件名',
  `original_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rule` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '文件上传规则',
  `reference` int(1) NOT NULL DEFAULT '0' COMMENT '文件被引用0-否1-是',
  `status` int(11) NOT NULL COMMENT '文件状态-1可用',
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `upload_rule`
--

CREATE TABLE `upload_rule` (
  `id` int(11) NOT NULL,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '规则名称',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '代码',
  `size` int(11) NOT NULL COMMENT '大小（kb）',
  `file_type` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '文件类型[JSON]',
  `save_path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'asset目录下的存储路径，以/开始',
  `original` int(1) NOT NULL COMMENT '保存原始文件名1-是0-否',
  `default_status` int(11) NOT NULL COMMENT '默认文件状态',
  `enable` int(1) NOT NULL COMMENT '状态（1-启用,0-停用）',
  `expand` json NOT NULL COMMENT '额外配置',
  `add_time` int(11) UNSIGNED NOT NULL COMMENT '添加时间',
  `update_time` int(11) UNSIGNED NOT NULL COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='文件上传规则表';

--
-- 转存表中的数据 `upload_rule`
--

INSERT INTO `upload_rule` (`id`, `name`, `code`, `size`, `file_type`, `save_path`, `original`, `default_status`, `enable`, `expand`, `add_time`, `update_time`) VALUES
(1, '默认图片上传规则', 'pic_upload', 2048, '[\"jpg\",\"png\"]', '/pic', 1, 1, 1, '{\"image\": {\"ratio\": 0, \"max_width\": 800, \"min_width\": 400, \"max_height\": 800, \"min_height\": 400}}', 0, 0);

--
-- 转储表的索引
--

--
-- 表的索引 `upload_file`
--
ALTER TABLE `upload_file`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `upload_rule`
--
ALTER TABLE `upload_rule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `upload_file`
--
ALTER TABLE `upload_file`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `upload_rule`
--
ALTER TABLE `upload_rule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
