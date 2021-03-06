-- phpMyAdmin SQL Dump
-- version 3.4.11.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2014 at 07:17 PM
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
) ENGINE=MEMORY  DEFAULT CHARSET=utf8 AUTO_INCREMENT=128 ;

--
-- Dumping data for table `sortest`
--

INSERT INTO `sortest` (`id`, `name`, `sort`) VALUES
(1, '1', 1071677440),
(2, '2', 1071710208),
(3, '3', 1071742976),
(4, '4', 1071775744),
(5, '5', 1071808512),
(6, '6', 1071841280),
(7, '7', 1071874048),
(8, '8', 1071906816),
(9, '9', 1071939584),
(128, '128', 1071972351),  /* conflict */
(10, '10', 1071972352),
(11, '11', 1072005120),
(12, '12', 1072037888),
(13, '13', 1072070656),
(14, '14', 1072103424),
(15, '15', 1072136192),
(16, '16', 1072168960),
(17, '17', 1072201728),
(18, '18', 1072234496),
(19, '19', 1072267264),
(20, '20', 1072300032),
(21, '21', 1072332800),
(22, '22', 1072365568),
(23, '23', 1072398336),
(24, '24', 1072431104),
(25, '25', 1072463872),
(26, '26', 1072496640),
(27, '27', 1072529408),
(28, '28', 1072562176),
(29, '29', 1072594944),
(30, '30', 1072627712),
(31, '31', 1072660480),
(32, '32', 1072693248),
(33, '33', 1072726016),
(34, '34', 1072758784),
(35, '35', 1072791552),
(36, '36', 1072824320),
(37, '37', 1072857088),
(38, '38', 1072889856),
(39, '39', 1072922624),
(40, '40', 1072955392),
(41, '41', 1072988160),
(42, '42', 1073020928),
(43, '43', 1073053696),
(44, '44', 1073086464),
(45, '45', 1073119232),
(46, '46', 1073152000),
(47, '47', 1073184768),
(48, '48', 1073217536),
(49, '49', 1073250304),
(50, '50', 1073283072),
(51, '51', 1073315840),
(52, '52', 1073348608),
(53, '53', 1073381376),
(54, '54', 1073414144),
(55, '55', 1073446912),
(56, '56', 1073479680),
(57, '57', 1073512448),
(58, '58', 1073545216),
(59, '59', 1073577984),
(60, '60', 1073610752),
(61, '61', 1073643520),
(62, '62', 1073676288),
(63, '63', 1073709056),
(64, '64', 1073741824),
(65, '65', 1073774592),
(66, '66', 1073807360),
(67, '67', 1073840128),
(68, '68', 1073872896),
(69, '69', 1073905664),
(70, '70', 1073938432),
(71, '71', 1073971200),
(72, '72', 1074003968),
(73, '73', 1074036736),
(74, '74', 1074069504),
(75, '75', 1074102272),
(76, '76', 1074135040),
(77, '77', 1074167808),
(78, '78', 1074200576),
(79, '79', 1074233344),
(80, '80', 1074266112),
(81, '81', 1074298880),
(82, '82', 1074331648),
(83, '83', 1074364416),
(84, '84', 1074397184),
(85, '85', 1074429952),
(86, '86', 1074462720),
(87, '87', 1074495488),
(88, '88', 1074528256),
(89, '89', 1074561024),
(90, '90', 1074593792),
(91, '91', 1074626560),
(92, '92', 1074659328),
(93, '93', 1074692096),
(94, '94', 1074724864),
(95, '95', 1074757632),
(96, '96', 1074790400),
(97, '97', 1074823168),
(98, '98', 1074855936),
(99, '99', 1074888704),
(100, '100', 1074921472),
(101, '101', 1074954240),
(102, '102', 1074987008),
(103, '103', 1075019776),
(104, '104', 1075052544),
(105, '105', 1075085312),
(106, '106', 1075118080),
(107, '107', 1075150848),
(108, '108', 1075183616),
(109, '109', 1075216384),
(110, '110', 1075249152),
(111, '111', 1075281920),
(112, '112', 1075314688),
(113, '113', 1075347456),
(114, '114', 1075380224),
(115, '115', 1075412992),
(116, '116', 1075445760),
(117, '117', 1075478528),
(118, '118', 1075511296),
(119, '119', 1075544064),
(120, '120', 1075576832),
(121, '121', 1075609600),
(122, '122', 1075642368),
(123, '123', 1075675136),
(124, '124', 1075707904),
(125, '125', 1075740672),
(126, '126', 1075773440),
(127, '127', 1075806208);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
