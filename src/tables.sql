-- Adminer 4.8.1 MySQL 5.5.5-10.3.38-MariaDB-0+deb10u1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

SET NAMES utf8mb4;

CREATE TABLE `mid` (
  `msisdn` varchar(20) NOT NULL COMMENT 'Phone Number',
  `userid` bigint(20) NOT NULL COMMENT 'User ID',
  `chatid` bigint(20) NOT NULL COMMENT 'Chat ID',
  `verif` tinyint(3) unsigned NOT NULL COMMENT '0: number is not verified yet, 1: in process of verication, 2: verified, 3: received error 429 should be verified again in the future',
  `fchver` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Verification date',
  PRIMARY KEY (`msisdn`),
  KEY `userid` (`userid`),
  KEY `verif` (`verif`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Table with phone numbers and IDs';


CREATE TABLE `mo` (
  `idmo` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Record ID',
  `userid` bigint(20) NOT NULL COMMENT 'User ID of sender',
  `chatid` bigint(20) NOT NULL COMMENT 'Chat ID of sender',
  `msgid` bigint(20) NOT NULL COMMENT 'Message ID',
  `fchrec` datetime NOT NULL COMMENT 'Datetime received',
  `mensaje` varchar(8000) NOT NULL COMMENT 'Text of message',
  `estado` tinyint(3) unsigned NOT NULL COMMENT 'Status of message',
  PRIMARY KEY (`idmo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Table of received messages';


CREATE TABLE `mt` (
  `idmt` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Record ID',
  `origen` varchar(20) NOT NULL COMMENT 'Origin (put anything you want, name of app that generate the message for example)',
  `msisdn` varchar(20) NOT NULL COMMENT 'Phone number of destiny',
  `mensaje` varchar(2000) NOT NULL COMMENT 'Text of message',
  `fching` datetime NOT NULL COMMENT 'Datetime record added',
  `fchpro` datetime NOT NULL COMMENT 'Datetime processed',
  `fchenv` datetime NOT NULL COMMENT 'Datetime sent',
  `fchent` datetime NOT NULL COMMENT 'Datetime delivered',
  `fchlei` datetime NOT NULL COMMENT 'Datetime readed',
  `estado` tinyint(3) unsigned NOT NULL COMMENT 'Status: 0-Unsent, 1-Pending, 2-Error.Not sent, 3-Sent, 4-Delivered, 5-Readed',
  `coderr` varchar(100) NOT NULL COMMENT 'Description of error (for status 2)',
  `userid` bigint(20) NOT NULL COMMENT 'User ID',
  `chatid` bigint(20) NOT NULL COMMENT 'Chat ID',
  `msgid` bigint(20) NOT NULL COMMENT 'Message ID',
  `borrado` tinyint(3) unsigned NOT NULL COMMENT '(not used)',
  PRIMARY KEY (`idmt`),
  KEY `chatid_idenvio` (`chatid`,`msgid`),
  KEY `estado` (`estado`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Table of sent messages';


-- 2023-05-03 13:36:49
