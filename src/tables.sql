-- Adminer 4.8.1 MySQL 5.5.5-10.3.38-MariaDB-0+deb10u1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

SET NAMES utf8mb4;

CREATE TABLE `mid` (
  `msisdn` varchar(20) NOT NULL COMMENT 'Nro de telefono',
  `userid` bigint(20) NOT NULL COMMENT 'Id del usuario',
  `chatid` bigint(20) NOT NULL COMMENT 'Id del chat',
  `verif` tinyint(3) unsigned NOT NULL COMMENT 'Verificado',
  `fchver` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Fecha de verificación',
  PRIMARY KEY (`msisdn`),
  KEY `userid` (`userid`),
  KEY `verif` (`verif`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla de ChatsId de los números';


CREATE TABLE `mo` (
  `idmo` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id del registro',
  `userid` bigint(20) NOT NULL COMMENT 'Id del usuario de origen',
  `chatid` bigint(20) NOT NULL COMMENT 'Id del chat',
  `msgid` bigint(20) NOT NULL COMMENT 'Id del mensaje',
  `fchrec` datetime NOT NULL COMMENT 'Fecha y hora de recibido',
  `mensaje` varchar(8000) NOT NULL COMMENT 'Texto del Mensaje',
  `estado` tinyint(3) unsigned NOT NULL COMMENT 'Estado del mensaje',
  PRIMARY KEY (`idmo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla de mensajes recibidos';


CREATE TABLE `mt` (
  `idmt` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id del registro',
  `origen` varchar(20) NOT NULL COMMENT 'Identificador del origen',
  `msisdn` varchar(20) NOT NULL COMMENT 'Nro de telefono de destino',
  `mensaje` varchar(2000) NOT NULL COMMENT 'Texto del mensaje',
  `fching` datetime NOT NULL COMMENT 'Fecha de ingreso',
  `fchpro` datetime NOT NULL COMMENT 'Fecha de proceso',
  `fchenv` datetime NOT NULL COMMENT 'Fecha de envío',
  `fchent` datetime NOT NULL COMMENT 'Fecha de entrega',
  `fchlei` datetime NOT NULL COMMENT 'Fecha de lectura',
  `estado` tinyint(3) unsigned NOT NULL COMMENT 'Estado: 0-No enviado, 1-Pendiente, 2-Error en envio, 3-Enviado, 4-Entregado, 5-Leído',
  `coderr` varchar(100) NOT NULL COMMENT 'Código del error',
  `userid` bigint(20) NOT NULL COMMENT 'Id del usuario',
  `chatid` bigint(20) NOT NULL COMMENT 'Id del chat',
  `msgid` bigint(20) NOT NULL COMMENT 'Id asignado al enviar',
  `borrado` tinyint(3) unsigned NOT NULL COMMENT 'Código de borrado: 0: activo, 1: borrado',
  PRIMARY KEY (`idmt`),
  KEY `chatid_idenvio` (`chatid`,`msgid`),
  KEY `estado` (`estado`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla de mensajes enviados';


-- 2023-05-03 13:36:49
