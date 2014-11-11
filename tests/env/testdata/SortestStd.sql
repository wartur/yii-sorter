-- phpMyAdmin SQL Dump
-- version 3.4.11.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2014 at 09:08 AM
-- Server version: 1.0.14
-- PHP Version: 5.5.18-1+deb.sury.org~trusty+1

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT=0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `simple`
--

-- --------------------------------------------------------

--
-- Table structure for table `sortest`
--
-- Creation: Nov 04, 2014 at 08:17 PM
--

DROP TABLE IF EXISTS `sortest`;
CREATE TABLE IF NOT EXISTS `sortest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Имя',
  `sort` int(11) NOT NULL COMMENT 'Сортировка',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sort` (`sort`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=155 ;

--
-- Dumping data for table `sortest`
--

INSERT INTO `sortest` (`id`, `name`, `sort`) VALUES
(1, '1', 1073741824),
(2, '2', 1073774592),
(3, '3', 1073807360),
(4, '4', 1073840128),
(5, '5', 1073872896),
(6, '6', 1073905664),
(7, '7', 1073938432),
(8, '8', 1073971200),
(9, '9', 1074003968),
(10, '10', 1074036736),
(11, '11', 1074069504),
(12, '12', 1074102272),
(13, '13', 1074135040),
(14, '14', 1074167808),
(15, '15', 1074200576),
(16, '16', 1074233344),
(17, '17', 1074266112),
(18, '18', 1074298880),
(19, '19', 1074331648),
(20, '20', 1074364416),
(21, '21', 1074397184),
(22, '22', 1074429952),
(23, '23', 1074462720),
(24, '24', 1074495488),
(25, '25', 1074528256),
(26, '26', 1074561024),
(27, '27', 1074593792),
(28, '28', 1074626560),
(29, '29', 1074659328),
(30, '30', 1074692096),
(31, '31', 1074724864),
(32, '32', 1074757632),
(33, '33', 1074790400),
(34, '34', 1074823168),
(35, '35', 1074855936),
(36, '36', 1074888704),
(37, '37', 1074921472),
(38, '38', 1074954240),
(39, '39', 1074987008),
(40, '40', 1075019776),
(41, '41', 1075052544),
(42, '42', 1075085312),
(43, '43', 1075118080),
(44, '44', 1075150848),
(45, '45', 1075183616),
(46, '46', 1075216384),
(47, '47', 1075249152),
(48, '48', 1075281920),
(49, '49', 1075314688),
(50, '50', 1075347456),
(51, '51', 1075380224),
(52, '52', 1075412992),
(53, '53', 1075445760),
(54, '54', 1075478528),
(55, '55', 1075511296),
(56, '56', 1075544064),
(57, '57', 1075576832),
(58, '58', 1075609600),
(59, '59', 1075642368),
(60, '60', 1075675136),
(61, '61', 1075707904),
(62, '62', 1075740672),
(63, '63', 1075773440),
(64, '64', 1075806208),
(65, '65', 1075838976),
(66, '66', 1075871744),
(67, '67', 1075904512),
(68, '68', 1075937280),
(69, '69', 1075970048),
(70, '70', 1076002816),
(71, '71', 1076035584),
(72, '72', 1076068352),
(73, '73', 1076101120),
(74, '74', 1076133888),
(75, '75', 1076166656),
(76, '76', 1076199424),
(77, '77', 1076232192),
(78, '78', 1076264960),
(79, '79', 1076297728),
(80, '80', 1076330496),
(81, '81', 1076363264),
(82, '82', 1076396032),
(83, '83', 1076428800),
(84, '84', 1076461568),
(85, '85', 1076494336),
(86, '86', 1076527104),
(87, '87', 1076559872),
(88, '88', 1076592640),
(89, '89', 1076625408),
(90, '90', 1076658176),
(91, '91', 1076690944),
(92, '92', 1076723712),
(93, '93', 1076756480),
(94, '94', 1076789248),
(95, '95', 1076822016),
(96, '96', 1076854784),
(97, '97', 1076887552),
(98, '98', 1076920320),
(99, '99', 1076953088),
(100, '100', 1076985856),
(101, '101', 1077018624),
(102, '102', 1077051392),
(103, '103', 1077084160),
(104, '104', 1077116928),
(105, '105', 1077149696),
(106, '106', 1077182464),
(107, '107', 1077215232),
(108, '108', 1077248000),
(109, '109', 1077280768),
(110, '110', 1077313536),
(111, '111', 1077346304),
(112, '112', 1077379072),
(113, '113', 1077411840),
(114, '114', 1077444608),
(115, '115', 1077477376),
(116, '116', 1077510144),
(117, '117', 1077542912),
(118, '118', 1077575680),
(119, '119', 1077608448),
(120, '120', 1077641216),
(121, '121', 1077673984),
(122, '122', 1077706752),
(123, '123', 1077739520),
(124, '124', 1077772288),
(125, '125', 1077805056),
(126, '126', 1077837824),
(127, '127', 1077870592),
(128, '128', 1077903360),
(129, '129', 1077936128),
(130, '130', 1077968896),
(131, '131', 1078001664),
(132, '132', 1078034432),
(133, '133', 1078067200),
(134, '134', 1078099968),
(135, '135', 1078132736),
(136, '136', 1078165504),
(137, '137', 1078198272),
(138, '138', 1078231040),
(139, '139', 1078263808),
(140, '140', 1078296576),
(141, '141', 1078329344),
(142, '142', 1078362112),
(143, '143', 1078394880),
(144, '144', 1078427648),
(145, '145', 1078460416),
(146, '146', 1078493184),
(147, '147', 1078525952),
(148, '148', 1078558720),
(149, '149', 1078591488),
(150, '150', 1078624256),
(151, '151', 1078657024),
(152, '152', 1078689792),
(153, '153', 1078722560),
(154, '154', 1078755328);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;