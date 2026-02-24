CREATE DATABASE IF NOT EXISTS `login_system`;
USE `login_system`;


DROP TABLE IF EXISTS `debts`;
CREATE TABLE `debts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` varchar(50) DEFAULT 'AHT TECH',
  `am` varchar(100) DEFAULT NULL,
  `sale_team_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `payment_milestone` varchar(255) DEFAULT NULL,
  `expected_prod_date` date DEFAULT NULL,
  `expected_payment_date` date DEFAULT NULL,
  `invoice_status_class` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'VND',
  `pl_class` varchar(50) DEFAULT NULL,
  `invoice_status` varchar(50) DEFAULT NULL,
  `vat_invoice` varchar(50) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `payment_month` varchar(50) DEFAULT NULL,
  `weekly_update` varchar(50) DEFAULT NULL,
  `am_notes` text,
  `delivery_notes` text,
  `production_status` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=260 DEFAULT CHARSET=utf8;

INSERT INTO `debts` VALUES('174','AHT TECH','Bùi Văn Duyệt','3','JAJUMA GMBH','',NULL,NULL,NULL,'Tím','6561.40','USD','Xấu',NULL,'0','2026-01-31','Paid','02/2026','Tuần 1','Added from Invoice: INV/2026/00047 (USD)',NULL,NULL,'2026-02-13 14:50:42','2026-02-13 14:50:42');
INSERT INTO `debts` VALUES('202','AHT TECH','Hyun C.','1','Online Visions PTY LTD','[PT] Online Vision By Task',NULL,NULL,NULL,'','963.75','USD','Xấu',NULL,'0','2026-01-31','Not paid','','','Added from Invoice: INV/2026/00045 (USD)',NULL,NULL,'2026-02-13 15:33:22','2026-02-13 15:33:22');
INSERT INTO `debts` VALUES('205','AHT TECH','Hyun C.','3','SIRUSS LTD','[BC5] Bed Supermarket',NULL,NULL,NULL,'Done','1690.00','USD','Xấu',NULL,'INV/2026/00012','2026-01-30','Paid','01/2026','Tuần 5','Added from Invoice: INV/2026/00012 (USD)',NULL,NULL,'2026-02-13 15:36:54','2026-02-13 15:36:54');
INSERT INTO `debts` VALUES('206','AHT TECH','Hyun C.','3','LOLLI MEDIA LIMITED','[DC5] Small Task 2025',NULL,NULL,NULL,'','8166.75','USD','Xấu',NULL,'INV/2026/00063','2026-01-31','Not paid','','','Added from Invoice: INV/2026/00063 (USD)',NULL,NULL,'2026-02-13 15:47:54','2026-02-13 15:47:54');
INSERT INTO `debts` VALUES('208','AHT TECH','Hyun C.','3','Online Visions PTY LTD','[PT] Online Vision By Task',NULL,NULL,NULL,'','963.75','USD','Xấu',NULL,'INV/2026/00045','2026-01-31','Not paid','','','Added from Invoice: INV/2026/00045 (USD)',NULL,NULL,'2026-02-13 15:47:58','2026-02-13 15:47:58');
INSERT INTO `debts` VALUES('209','AHT TECH','Hyun C.','3','Alexandra Buckard','[DC5] Agentur Emilian Delicated',NULL,NULL,NULL,'','1000.00','USD','Xấu',NULL,'INV/2026/00044','2026-01-31','Not paid','','','Added from Invoice: INV/2026/00044 (USD)',NULL,NULL,'2026-02-13 15:48:00','2026-02-13 15:48:00');
INSERT INTO `debts` VALUES('210','AHT TECH','Hyun C.','3','MERCY SHIPS GLOBAL','Mercyships.ch',NULL,NULL,NULL,'','6420.00','USD','Xấu',NULL,'INV/2026/00043','2026-01-31','Not paid','','','Added from Invoice: INV/2026/00043 (USD)',NULL,NULL,'2026-02-13 15:48:02','2026-02-13 15:48:02');
INSERT INTO `debts` VALUES('211','AHT TECH','Hyun C.','3','Sweetmag Solutions (M) Sdn Bhd','[BC5] Trainocate Training Slots',NULL,NULL,NULL,'','2385.00','USD','0',NULL,'INV/2026/00042','2026-01-31','Not paid','','','Added from Invoice: INV/2026/00042 (USD)',NULL,NULL,'2026-02-13 15:48:04','2026-02-13 16:41:01');
INSERT INTO `debts` VALUES('212','AHT TECH','Hyun C.','3','Sweetmag Solutions (M) Sdn Bhd','[BC5] TNGD Integration',NULL,NULL,NULL,'Done','1270.00','USD','Xấu',NULL,'INV/2026/00041','2026-01-31','Paid','01/2026','Tuần 4','Added from Invoice: INV/2026/00041 (USD)',NULL,NULL,'2026-02-13 15:48:06','2026-02-13 15:48:06');
INSERT INTO `debts` VALUES('213','AHT TECH','Hyun C.','3','3byChance GmbH','[DC5] 3BY by tasks',NULL,NULL,NULL,'Done','800.00','USD','Xấu',NULL,'INV/2026/00011','2026-01-30','Paid','01/2026','Tuần 3','Added from Invoice: INV/2026/00011 (USD)',NULL,NULL,'2026-02-13 15:48:08','2026-02-13 15:48:08');
INSERT INTO `debts` VALUES('214','AHT TECH','Hyun C.','3','365 HK Limited','[BC5] E-Commerce Platform',NULL,NULL,NULL,'Done','3059.00','USD','Xấu',NULL,'INV/2026/00009','2026-01-30','Paid','01/2026','Tuần 3','Added from Invoice: INV/2026/00009 (USD)',NULL,NULL,'2026-02-13 15:48:10','2026-02-13 15:48:10');
INSERT INTO `debts` VALUES('215','AHT TECH','Hyun C.','3','ZRooom Pte Ltd','[DC5] ZRooom Small Task',NULL,NULL,NULL,'Done','1000.00','USD','Xấu',NULL,'INV/2026/00008','2026-01-30','Paid','01/2026','Tuần 3','Added from Invoice: INV/2026/00008 (USD)',NULL,NULL,'2026-02-13 15:48:12','2026-02-13 15:48:12');
INSERT INTO `debts` VALUES('216','AHT TECH','Hyun C.','3','Goanna Digital Pty Ltd','[BC5] Winetrust Website',NULL,NULL,NULL,'Done','1242.00','USD','Xấu',NULL,'INV/2026/00007','2026-01-19','Paid','01/2026','Tuần 3','Added from Invoice: INV/2026/00007 (USD)',NULL,NULL,'2026-02-13 15:48:14','2026-02-13 15:48:14');
INSERT INTO `debts` VALUES('217','AHT TECH','Hyun C.','3','LOLLI MEDIA LIMITED, Mr Cyrus','',NULL,NULL,NULL,'','7852.00','USD','Xấu',NULL,'INV/2025/00438','2025-12-31','Not paid','','','Added from Invoice: INV/2025/00438 (USD)',NULL,NULL,'2026-02-13 15:49:54','2026-02-13 15:49:54');
INSERT INTO `debts` VALUES('218','AHT TECH','Hyun C.','3','Sweetmag Solutions (M) Sdn Bhd','[ONEXT]Sweetmag Small Task V2',NULL,NULL,NULL,'Done','2380.00','USD','Xấu',NULL,'INV/2025/00437','2025-12-31','Paid','01/2026','Tuần 1','Added from Invoice: INV/2025/00437 (USD)',NULL,NULL,'2026-02-13 15:49:56','2026-02-13 15:49:56');
INSERT INTO `debts` VALUES('219','AHT TECH','Hyun C.','3','Alexandra Buckard','[DC5] Agentur Emilian Delicated',NULL,NULL,NULL,'','1050.00','USD','Xấu',NULL,'INV/2025/00422','2025-12-31','Not paid','','','Added from Invoice: INV/2025/00422 (USD)',NULL,NULL,'2026-02-13 15:49:57','2026-02-13 15:49:57');
INSERT INTO `debts` VALUES('220','AHT TECH','Hyun C.','3','Teck Sang Pte Ltd','[DC5] Tecksangonline',NULL,NULL,NULL,'Done','2000.00','USD','Xấu',NULL,'INV/2025/00421','2025-12-31','Paid','12/2025','Tuần 5','Added from Invoice: INV/2025/00421 (USD)',NULL,NULL,'2026-02-13 15:49:59','2026-02-13 15:49:59');
INSERT INTO `debts` VALUES('222','AHT TECH','Hyun C.','3','Upplev Sydafrika AB','[DC5] Small Task 2025',NULL,NULL,NULL,'Done','44.87','USD','Xấu',NULL,'INV/2025/00369','2025-12-31','Paid','12/2025','Tuần 3','Added from Invoice: INV/2025/00369 (USD)',NULL,NULL,'2026-02-13 15:50:02','2026-02-13 15:50:02');
INSERT INTO `debts` VALUES('223','AHT TECH','Hyun C.','3','SIRUSS LTD','[BC5] Bed Supermarket',NULL,NULL,NULL,'Done','1240.00','USD','Xấu',NULL,'INV/2025/00367','2025-12-25','Paid','12/2025','Tuần 4','Added from Invoice: INV/2025/00367 (USD)',NULL,NULL,'2026-02-13 15:50:04','2026-02-13 15:50:04');
INSERT INTO `debts` VALUES('224','AHT TECH','Hyun C.','3','SIRUSS LTD','[BC5] Dou Delivery',NULL,NULL,NULL,'Done','4105.00','USD','Xấu',NULL,'INV/2025/00357','2025-12-19','Paid','12/2025','Tuần 4','Added from Invoice: INV/2025/00357 (USD)',NULL,NULL,'2026-02-13 15:50:06','2026-02-13 15:50:06');
INSERT INTO `debts` VALUES('225','AHT TECH','Hyun C.','3','Anders Wiking','[ONEXT] SWEPA',NULL,NULL,NULL,'Done','75.00','USD','Xấu',NULL,'INV/2025/00346','2025-11-30','Paid','11/2025','Tuần 5','Added from Invoice: INV/2025/00346 (USD)',NULL,NULL,'2026-02-13 15:50:13','2026-02-13 15:50:13');
INSERT INTO `debts` VALUES('227','AHT TECH','Hyun C.','3','LOLLI MEDIA LIMITED','',NULL,NULL,NULL,'Tím','11035.50','USD','Xấu',NULL,'INV/2025/00338','2025-11-30','Paid','02/2026','Tuần 1','Added from Invoice: INV/2025/00338 (USD)',NULL,NULL,'2026-02-13 15:50:17','2026-02-13 15:50:17');
INSERT INTO `debts` VALUES('229','AHT TECH','Hyun C.','3','MERCY SHIPS GLOBAL','Mercyships.ch',NULL,NULL,NULL,'Done','3420.00','USD','Xấu',NULL,'INV/2025/00335','2025-11-30','Paid','12/2025','Tuần 1','Added from Invoice: INV/2025/00335 (USD)',NULL,NULL,'2026-02-13 15:50:20','2026-02-13 15:50:20');
INSERT INTO `debts` VALUES('230','AHT TECH','Hyun C.','3','Alexandra Buckard','[DC5] Agentur Emilian Delicated',NULL,NULL,NULL,'Done','1000.00','USD','Xấu',NULL,'INV/2025/00334','2025-11-30','Paid','12/2025','Tuần 5','Added from Invoice: INV/2025/00334 (USD)',NULL,NULL,'2026-02-13 15:50:22','2026-02-13 15:50:22');
INSERT INTO `debts` VALUES('231','AHT TECH','Hyun C.','3','SIRUSS LTD','[DC5] Peter Gilding Website',NULL,NULL,NULL,'Done','1797.22','USD','Xấu',NULL,'INV/2025/00291','2025-11-30','Paid','12/2025','Tuần 1','Added from Invoice: INV/2025/00291 (USD)',NULL,NULL,'2026-02-13 15:50:24','2026-02-13 15:50:24');
INSERT INTO `debts` VALUES('232','AHT TECH','Hyun C.','3','365 HK Limited','[BC5] Scape Bot',NULL,NULL,NULL,'Done','1430.00','USD','Xấu',NULL,'INV/2025/00284','2025-11-27','Paid','11/2025','Tuần 4','Added from Invoice: INV/2025/00284 (USD)',NULL,NULL,'2026-02-13 15:50:26','2026-02-13 15:50:26');
INSERT INTO `debts` VALUES('233','AHT TECH','Hyun C.','3','Alexandra Buckard','[DC5] Agentur Emilian Delicated',NULL,NULL,NULL,'Done','1000.00','USD','Xấu',NULL,'INV/2025/00283','2025-11-27','Paid','11/2025','Tuần 3','Added from Invoice: INV/2025/00283 (USD)',NULL,NULL,'2026-02-13 15:50:28','2026-02-13 15:50:28');
INSERT INTO `debts` VALUES('234','AHT TECH','Hyun C.','3','Tree Of Life Designs','[DC5] Small Task 2025',NULL,NULL,NULL,'Done','177.22','USD','Xấu',NULL,'INV/2025/00282','2025-11-27','Paid','11/2025','Tuần 3','Added from Invoice: INV/2025/00282 (USD)',NULL,NULL,'2026-02-13 15:50:29','2026-02-13 15:50:29');
INSERT INTO `debts` VALUES('235','AHT TECH','Hyun C.','3','Shype Trading UG','',NULL,NULL,NULL,'Done','3000.00','USD','Xấu',NULL,'INV/2025/00018','2025-08-11','Paid','08/2025','Tuần 3','Added from Invoice: INV/2025/00018 (USD)',NULL,NULL,'2026-02-13 15:57:07','2026-02-13 15:57:07');
INSERT INTO `debts` VALUES('236','AHT TECH','Hyun C.','3','ZRooom Pte Ltd','',NULL,NULL,NULL,'Done','1000.00','USD','Xấu',NULL,'INV/2025/00019','2025-08-12','Paid','08/2025','Tuần 2','Added from Invoice: INV/2025/00019 (USD)',NULL,NULL,'2026-02-13 15:57:09','2026-02-13 15:57:09');
INSERT INTO `debts` VALUES('237','AHT TECH','Hyun C.','3','Sweetmag Solutions (M) Sdn Bhd','',NULL,NULL,NULL,'Done','5900.00','USD','Xấu',NULL,'INV/2025/00020','2025-08-15','Paid','08/2025','Tuần 3','Added from Invoice: INV/2025/00020 (USD)',NULL,NULL,'2026-02-13 15:57:11','2026-02-13 15:57:11');
INSERT INTO `debts` VALUES('238','AHT TECH','Hyun C.','3','Jolly Commerce LLP','',NULL,NULL,NULL,'Done','2500.00','USD','Xấu',NULL,'INV/2025/00026','2025-08-31','Paid','09/2025','Tuần 3','Added from Invoice: INV/2025/00026 (USD)',NULL,NULL,'2026-02-13 15:57:17','2026-02-13 15:57:17');
INSERT INTO `debts` VALUES('239','AHT TECH','Hyun C.','3','MERCY SHIPS GLOBAL, Mrs Corinne','',NULL,NULL,NULL,'Done','3420.00','USD','Xấu',NULL,'INV/2025/00027','2025-08-31','Paid','09/2025','Tuần 4','Added from Invoice: INV/2025/00027 (USD)',NULL,NULL,'2026-02-13 15:57:18','2026-02-13 15:57:18');
INSERT INTO `debts` VALUES('240','AHT TECH','Hyun C.','3','Alexandra Buckard','',NULL,NULL,NULL,'Done','1000.00','USD','Xấu',NULL,'INV/2025/00028','2025-08-31','Paid','09/2025','Tuần 5','Added from Invoice: INV/2025/00028 (USD)',NULL,NULL,'2026-02-13 15:57:20','2026-02-13 15:57:20');
INSERT INTO `debts` VALUES('241','AHT TECH','Hyun C.','3','MERCY SHIPS GLOBAL, Mrs Corinne','',NULL,NULL,NULL,'Done','3000.00','USD','Xấu',NULL,'INV/2025/00029','2025-08-31','Paid','09/2025','Tuần 4','Added from Invoice: INV/2025/00029 (USD)',NULL,NULL,'2026-02-13 15:57:22','2026-02-13 15:57:22');
INSERT INTO `debts` VALUES('242','AHT TECH','Hyun C.','3','SIRUSS LTD','',NULL,NULL,NULL,'Done','630.00','USD','Xấu',NULL,'INV/2025/00030','2025-08-31','Paid','09/2025','Tuần 1','Added from Invoice: INV/2025/00030 (USD)',NULL,NULL,'2026-02-13 15:57:24','2026-02-13 15:57:24');
INSERT INTO `debts` VALUES('243','AHT TECH','Hyun C.','3','Anders Wiking','',NULL,NULL,NULL,'Done','267.75','USD','Xấu',NULL,'INV/2025/00031','2025-08-31','Paid','08/2025','Tuần 5','Added from Invoice: INV/2025/00031 (USD)',NULL,NULL,'2026-02-13 15:57:26','2026-02-13 15:57:26');
INSERT INTO `debts` VALUES('244','AHT TECH','Hyun C.','3','LOLLI MEDIA LIMITED, Mr Cyrus','',NULL,NULL,NULL,'Done','13026.50','USD','Xấu',NULL,'INV/2025/00083','2025-08-31','Paid','10/2025','Tuần 3','Added from Invoice: INV/2025/00083 (USD)',NULL,NULL,'2026-02-13 15:57:28','2026-02-13 15:57:28');
INSERT INTO `debts` VALUES('245','AHT TECH','Hyun C.','3','Betfair Australia Pty Ltd','',NULL,NULL,NULL,'Done','1100.00','USD','Xấu',NULL,'INV/2025/00090','2025-09-09','Paid','09/2025','Tuần 2','Added from Invoice: INV/2025/00090 (USD)',NULL,NULL,'2026-02-13 15:57:31','2026-02-13 15:57:31');
INSERT INTO `debts` VALUES('246','AHT TECH','Hyun C.','3','Sweetmag Solutions (M) Sdn Bhd','',NULL,NULL,NULL,'Done','2000.00','USD','Xấu',NULL,'INV/2025/00103','2025-09-09','Paid','09/2025','Tuần 2','Added from Invoice: INV/2025/00103 (USD)',NULL,NULL,'2026-02-13 15:57:33','2026-02-13 15:57:33');
INSERT INTO `debts` VALUES('247','AHT TECH','Hyun C.','3','Teck Sang Pte Ltd','',NULL,NULL,NULL,'Done','2100.00','USD','Xấu',NULL,'INV/2025/00108','2025-09-18','Paid','09/2025','Tuần 4','Added from Invoice: INV/2025/00108 (USD)',NULL,NULL,'2026-02-13 15:57:35','2026-02-13 15:57:35');
INSERT INTO `debts` VALUES('248','AHT TECH','Hyun C.','3','SIRUSS LTD','',NULL,NULL,NULL,'Done','1650.00','USD','Xấu',NULL,'INV/2025/00113','2025-09-29','Paid','09/2025','Tuần 5','Added from Invoice: INV/2025/00113 (USD)',NULL,NULL,'2026-02-13 15:57:37','2026-02-13 15:57:37');
INSERT INTO `debts` VALUES('249','AHT TECH','Hyun C.','3','Jolly Commerce LLP','',NULL,NULL,NULL,'Done','2500.00','USD','Xấu',NULL,'INV/2025/00126','2025-09-30','Paid','10/2025','Tuần 1','Added from Invoice: INV/2025/00126 (USD)',NULL,NULL,'2026-02-13 15:57:39','2026-02-13 15:57:39');
INSERT INTO `debts` VALUES('250','AHT TECH','Hyun C.','3','SWDEV PTY LTD','',NULL,NULL,NULL,'Done','237.00','USD','Xấu',NULL,'INV/2025/00127','2025-09-30','Paid','10/2025','Tuần 3','Added from Invoice: INV/2025/00127 (USD)',NULL,NULL,'2026-02-13 15:57:41','2026-02-13 15:57:41');
INSERT INTO `debts` VALUES('251','AHT TECH','Hyun C.','3','MERCY SHIPS GLOBAL','',NULL,NULL,NULL,'Done','3420.00','USD','Xấu',NULL,'INV/2025/00138','2025-09-30','Paid','10/2025','Tuần 3','Added from Invoice: INV/2025/00138 (USD)',NULL,NULL,'2026-02-13 15:57:42','2026-02-13 15:57:42');
INSERT INTO `debts` VALUES('252','AHT TECH','Hyun C.','3','LOLLI MEDIA LIMITED, Mr Cyrus','',NULL,NULL,NULL,'Done','10614.00','USD','Xấu',NULL,'INV/2025/00139','2025-09-30','Paid','12/2025','Tuần 4','Added from Invoice: INV/2025/00139 (USD)',NULL,NULL,'2026-02-13 15:57:44','2026-02-13 15:57:44');
INSERT INTO `debts` VALUES('253','AHT TECH','Hyun C.','3','Alexandra Buckard','',NULL,NULL,NULL,'Done','1127.26','USD','Xấu',NULL,'INV/2025/00140','2025-09-30','Paid','10/2025','Tuần 1','Added from Invoice: INV/2025/00140 (USD)',NULL,NULL,'2026-02-13 15:57:47','2026-02-13 15:57:47');
INSERT INTO `debts` VALUES('254','AHT TECH','Hyun C.','3','SIRUSS LTD','[DC5] Bettws Hall Shopify Website',NULL,NULL,NULL,'Done','2500.00','USD','Xấu',NULL,'INV/2025/00202','2025-10-30','Paid','10/2025','Tuần 5','Added from Invoice: INV/2025/00202 (USD)',NULL,NULL,'2026-02-13 15:57:49','2026-02-13 15:57:49');
INSERT INTO `debts` VALUES('255','AHT TECH','Hyun C.','3','MERCY SHIPS GLOBAL','Mercyships.ch',NULL,NULL,NULL,'Done','6420.00','USD','Xấu',NULL,'INV/2025/00261','2025-10-31','Paid','11/2025','Tuần 3','Added from Invoice: INV/2025/00261 (USD)',NULL,NULL,'2026-02-13 15:57:56','2026-02-13 15:57:56');
INSERT INTO `debts` VALUES('256','AHT TECH','Hyun C.','3','SWDEV PTY LTD','[BC5] Essano Shopify Website',NULL,NULL,NULL,'Done','1861.00','USD','Xấu',NULL,'INV/2025/00262','2025-10-31','Paid','12/2025','Tuần 1','Added from Invoice: INV/2025/00262 (USD)',NULL,NULL,'2026-02-13 15:57:58','2026-02-13 15:57:58');
INSERT INTO `debts` VALUES('257','AHT TECH','Hyun C.','3','Jolly Commerce LLP','',NULL,NULL,NULL,'Done','2500.00','USD','Xấu',NULL,'INV/2025/00263','2025-10-31','Paid','12/2025','Tuần 1','Added from Invoice: INV/2025/00263 (USD)',NULL,NULL,'2026-02-13 15:58:01','2026-02-13 15:58:01');
INSERT INTO `debts` VALUES('258','AHT TECH','Hyun C.','3','LOLLI MEDIA LIMITED','',NULL,NULL,NULL,'Done','13583.50','USD','Xấu',NULL,'INV/2025/00270','2025-10-31','Paid','12/2025','Tuần 4','Added from Invoice: INV/2025/00270 (USD)',NULL,NULL,'2026-02-13 15:58:03','2026-02-13 15:58:03');
INSERT INTO `debts` VALUES('259','AHT TECH','Hyun C.','3','Alexandra Buckard','[DC5] Agentur Emilian Delicated',NULL,NULL,NULL,'Done','2400.00','USD','Xấu',NULL,'INV/2025/00271','2025-10-31','Paid','11/2025','Tuần 3','Added from Invoice: INV/2025/00271 (USD)',NULL,NULL,'2026-02-13 15:58:05','2026-02-13 15:58:05');



DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `parent_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `fk_parent_dept` (`parent_id`),
  KEY `fk_dept_owner` (`owner_id`),
  KEY `fk_dept_manager` (`manager_id`),
  CONSTRAINT `fk_dept_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dept_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_parent_dept` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8;

INSERT INTO `departments` VALUES('1','AHT HOLDING','Tổng Công Ty','2026-02-12 11:30:02',NULL,'1',NULL,'1');
INSERT INTO `departments` VALUES('2','A1 Consulting Việt Nam','Cấp công ty thành viên','2026-02-12 11:32:27','1',NULL,NULL,'2');
INSERT INTO `departments` VALUES('3','KHỐI KINH DOANH (BUSINESS)','Cấp khối chức năng','2026-02-12 11:35:15','1',NULL,NULL,'3');
INSERT INTO `departments` VALUES('4','KHỐI SẢN XUÁT (DELIVERY)','Cấp khối chức năng','2026-02-12 11:35:33','1',NULL,NULL,'4');
INSERT INTO `departments` VALUES('5','KHÓI NHÂN SỰ (HR)','Cấp khối chức năng','2026-02-12 11:36:18','1',NULL,NULL,'5');
INSERT INTO `departments` VALUES('6','KHỐI TÀI CHÍNH (FINANCE)','Cấp khối chức năng','2026-02-12 11:37:22','1',NULL,NULL,'6');
INSERT INTO `departments` VALUES('7','BC ITO','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:40:40','29',NULL,NULL,'7');
INSERT INTO `departments` VALUES('8','BC5 ONEXT','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:40:53','29','1','2','8');
INSERT INTO `departments` VALUES('9','BC3 - AIHIVE','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:41:13','29',NULL,NULL,'9');
INSERT INTO `departments` VALUES('10','BC8 - MY','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:41:34','2','1',NULL,'10');
INSERT INTO `departments` VALUES('11','BC10 - GOV','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:41:57','29',NULL,NULL,'11');
INSERT INTO `departments` VALUES('12','BC6 - HN','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:42:12','2',NULL,NULL,'12');
INSERT INTO `departments` VALUES('13','BC7 - HCM','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:42:25','2','1','1','13');
INSERT INTO `departments` VALUES('14','BC9 - MSP','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:42:49','2',NULL,NULL,'14');
INSERT INTO `departments` VALUES('22','IT Team','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:47:15','1',NULL,NULL,'22');
INSERT INTO `departments` VALUES('25','QA','Cấp Phòn Ban - Bộ Phận','2026-02-12 11:50:57','1',NULL,NULL,'25');
INSERT INTO `departments` VALUES('29','AHT TECH','','2026-02-12 12:24:48','1',NULL,NULL,'29');



DROP TABLE IF EXISTS `kpi_cycles`;
CREATE TABLE `kpi_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `year` smallint(6) NOT NULL,
  `period_type` enum('monthly','quarterly','biannual','annual') NOT NULL DEFAULT 'quarterly',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

INSERT INTO `kpi_cycles` VALUES('1','Q1 2025','2025','quarterly','2025-01-01','2025-03-31','1','2026-02-23 14:37:22');
INSERT INTO `kpi_cycles` VALUES('2','Q2 2025','2025','quarterly','2025-04-01','2025-06-30','1','2026-02-23 14:37:22');
INSERT INTO `kpi_cycles` VALUES('3','Q3 2025','2025','quarterly','2025-07-01','2025-09-30','1','2026-02-23 14:37:22');
INSERT INTO `kpi_cycles` VALUES('4','Q4 2025','2025','quarterly','2025-10-01','2025-12-31','1','2026-02-23 14:37:22');
INSERT INTO `kpi_cycles` VALUES('5','Q1 2026','2026','quarterly','2026-01-01','2026-03-31','1','2026-02-23 14:37:22');
INSERT INTO `kpi_cycles` VALUES('6','Q2 2026','2026','quarterly','2026-04-01','2026-06-30','1','2026-02-23 14:37:22');



DROP TABLE IF EXISTS `kpi_definitions`;
CREATE TABLE `kpi_definitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` smallint(6) NOT NULL DEFAULT '2025',
  `department_id` int(11) DEFAULT NULL,
  `kpi_group` varchar(100) DEFAULT NULL,
  `kpi_name` varchar(255) NOT NULL,
  `target_base` varchar(255) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT '0.00',
  `kpi_owner_id` int(11) DEFAULT NULL,
  `is_condition` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `kpi_owner_id` (`kpi_owner_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `kpi_definitions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpi_definitions_ibfk_2` FOREIGN KEY (`kpi_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpi_definitions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;

INSERT INTO `kpi_definitions` VALUES('1','2026','8','Tài chính','Gross Margin','12000000000','15.00','1','1','0','1','2026-02-23 15:02:57');
INSERT INTO `kpi_definitions` VALUES('2','2026','8','Tài chính','DSO bình quân','1800000000','20.00','1','1','0','1','2026-02-23 15:04:38');
INSERT INTO `kpi_definitions` VALUES('4','2028','9','Tài chính','EBT BC','','0.00','2','0','0','1','2026-02-23 16:48:15');
INSERT INTO `kpi_definitions` VALUES('11','2026','8','Tài chính','Doanh thu BC','','0.00','2','0','0','2','2026-02-23 17:03:00');
INSERT INTO `kpi_definitions` VALUES('12','2026','8','Tài chính','Doanh thu BC','','0.00','2','0','0','2','2026-02-23 17:15:50');



DROP TABLE IF EXISTS `kpi_items`;
CREATE TABLE `kpi_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `kpi_group` varchar(100) DEFAULT NULL,
  `kpi_name` varchar(255) NOT NULL,
  `target_base` varchar(255) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT '0.00',
  `kpi_owner_id` int(11) DEFAULT NULL,
  `status` enum('draft','active','completed','cancelled') DEFAULT 'draft',
  `actual_value` varchar(255) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `notes` text,
  `is_kpi_condition` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `kpi_owner_id` (`kpi_owner_id`),
  KEY `created_by` (`created_by`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `kpi_items_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpi_items_ibfk_2` FOREIGN KEY (`kpi_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpi_items_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpi_items_ibfk_4` FOREIGN KEY (`cycle_id`) REFERENCES `kpi_cycles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

INSERT INTO `kpi_items` VALUES('1','8','5','Business','Doanh Thu BC','12','15.00','1','active','',NULL,'0','0','1','2026-02-23 14:45:08','2026-02-23 14:45:08');
INSERT INTO `kpi_items` VALUES('2','9','5','Business','Doanh Thu BC','10','15.00','1','active','',NULL,'0','0','1','2026-02-23 14:45:44','2026-02-23 14:45:44');



DROP TABLE IF EXISTS `kpi_monthly`;
CREATE TABLE `kpi_monthly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kpi_def_id` int(11) NOT NULL,
  `year` smallint(6) NOT NULL,
  `month` tinyint(4) NOT NULL COMMENT '1-12',
  `actual_value` varchar(255) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL COMMENT '0-100',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_def_ym` (`kpi_def_id`,`year`,`month`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `kpi_monthly_ibfk_1` FOREIGN KEY (`kpi_def_id`) REFERENCES `kpi_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kpi_monthly_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4;

INSERT INTO `kpi_monthly` VALUES('1','1','2026','1','22000000',NULL,'1','2026-02-23 15:58:31','');
INSERT INTO `kpi_monthly` VALUES('3','1','2026','2','50000000',NULL,'1','2026-02-23 16:19:35','');
INSERT INTO `kpi_monthly` VALUES('6','1','2026','3','1200000000',NULL,'1','2026-02-23 15:38:00','');
INSERT INTO `kpi_monthly` VALUES('18','1','2026','4','750000000',NULL,'1','2026-02-23 16:17:27','');
INSERT INTO `kpi_monthly` VALUES('30','2','2026','1','150000000',NULL,'1','2026-02-23 16:15:30','');
INSERT INTO `kpi_monthly` VALUES('37','2','2026','2','150000000',NULL,'1','2026-02-23 16:17:34','');
INSERT INTO `kpi_monthly` VALUES('38','2','2026','3','150000000',NULL,'1','2026-02-23 16:15:31','');
INSERT INTO `kpi_monthly` VALUES('39','2','2026','4','150000000',NULL,'1','2026-02-23 16:15:32','');
INSERT INTO `kpi_monthly` VALUES('41','1','2026','5','750000000',NULL,'1','2026-02-23 16:17:28','');
INSERT INTO `kpi_monthly` VALUES('42','1','2026','6','750000000',NULL,'1','2026-02-23 16:17:29','');
INSERT INTO `kpi_monthly` VALUES('43','1','2026','7','750000000',NULL,'1','2026-02-23 16:17:30','');
INSERT INTO `kpi_monthly` VALUES('44','1','2026','8','750000000',NULL,'1','2026-02-23 16:17:30','');
INSERT INTO `kpi_monthly` VALUES('45','1','2026','9','750000000',NULL,'1','2026-02-23 16:17:30','');
INSERT INTO `kpi_monthly` VALUES('46','1','2026','10','750000000',NULL,'1','2026-02-23 16:17:31','');
INSERT INTO `kpi_monthly` VALUES('47','1','2026','11','750000000',NULL,'1','2026-02-23 16:17:33','');
INSERT INTO `kpi_monthly` VALUES('49','2','2026','5','150000000',NULL,'1','2026-02-23 16:17:35','');
INSERT INTO `kpi_monthly` VALUES('50','2','2026','6','150000000',NULL,'1','2026-02-23 16:17:35','');
INSERT INTO `kpi_monthly` VALUES('51','2','2026','7','150000000',NULL,'1','2026-02-23 16:17:36','');
INSERT INTO `kpi_monthly` VALUES('52','2','2026','8','150000000',NULL,'1','2026-02-23 16:17:36','');
INSERT INTO `kpi_monthly` VALUES('53','2','2026','9','150000000',NULL,'1','2026-02-23 16:17:37','');
INSERT INTO `kpi_monthly` VALUES('54','2','2026','10','150000000',NULL,'1','2026-02-23 16:17:37','');
INSERT INTO `kpi_monthly` VALUES('55','2','2026','11','150000000',NULL,'1','2026-02-23 16:17:38','');



DROP TABLE IF EXISTS `kpi_quarterly`;
CREATE TABLE `kpi_quarterly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kpi_def_id` int(11) NOT NULL,
  `quarter` tinyint(4) NOT NULL COMMENT '1=Q1,2=Q2,3=Q3,4=Q4',
  `year` smallint(6) NOT NULL,
  `target_value` varchar(255) DEFAULT NULL,
  `weight_q` decimal(5,2) DEFAULT '0.00' COMMENT 'weight for this quarter',
  `status` enum('draft','active','completed','cancelled') DEFAULT 'draft',
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_def_qy` (`kpi_def_id`,`quarter`,`year`),
  CONSTRAINT `kpi_quarterly_ibfk_1` FOREIGN KEY (`kpi_def_id`) REFERENCES `kpi_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4;

INSERT INTO `kpi_quarterly` VALUES('1','1','1','2026','3000000000','0.00','active','');
INSERT INTO `kpi_quarterly` VALUES('3','1','2','2026','2900000000','0.00','active','');
INSERT INTO `kpi_quarterly` VALUES('4','1','3','2026','3000000000','0.00','active','');
INSERT INTO `kpi_quarterly` VALUES('5','1','4','2026','3000000000','0.00','active','');
INSERT INTO `kpi_quarterly` VALUES('30','2','1','2026','40.000.0000','0.00','active','');
INSERT INTO `kpi_quarterly` VALUES('35','2','2','2026','500000000','0.00','active','');



DROP TABLE IF EXISTS `kpi_templates`;
CREATE TABLE `kpi_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `kpi_group` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;

INSERT INTO `kpi_templates` VALUES('1','Doanh thu BC','Tài chính','0','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('2','EBT BC','Tài chính','1','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('3','DT từ Key Accounts','Tài chính','2','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('4','Gross Margin','Tài chính','3','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('5','Lãi / 1 NS SX / tháng','Hiệu suất','4','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('6','Utilization (billable)','Hiệu suất','5','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('7','Dự án đúng ngân sách / tiến độ','Dự án','6','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('8','SLA nhân sự / vendor','Vận hành','7','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('9','DSO bình quân','Tài chính','8','2026-02-23 16:27:25');
INSERT INTO `kpi_templates` VALUES('10','Attrition Key L1, L2','Nhân sự','9','2026-02-23 16:27:25');



DROP TABLE IF EXISTS `sale_teams`;
CREATE TABLE `sale_teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `order_num` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

INSERT INTO `sale_teams` VALUES('1','A1C HCM','0','2026-02-13 13:50:26');
INSERT INTO `sale_teams` VALUES('2','A1C HN','0','2026-02-13 13:50:36');
INSERT INTO `sale_teams` VALUES('3','AHT BD Global','0','2026-02-13 13:50:47');
INSERT INTO `sale_teams` VALUES('4','AHT BD SEA','0','2026-02-13 13:50:58');
INSERT INTO `sale_teams` VALUES('5','AHT BD ITO VN','0','2026-02-13 13:51:09');
INSERT INTO `sale_teams` VALUES('6','A1C CSM (Services)','0','2026-02-13 13:51:16');
INSERT INTO `sale_teams` VALUES('7','A1C CSM (License)','0','2026-02-13 13:51:26');



DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

INSERT INTO `system_settings` VALUES('1','smtp_host','smtp.gmail.com',NULL,'2026-02-12 14:28:28');
INSERT INTO `system_settings` VALUES('2','smtp_port','587',NULL,'2026-02-12 14:28:28');
INSERT INTO `system_settings` VALUES('3','smtp_user','phuson.cps@gmail.com',NULL,'2026-02-12 14:38:01');
INSERT INTO `system_settings` VALUES('4','smtp_pass','poqi ofdh bwtb xcfg',NULL,'2026-02-12 14:36:52');
INSERT INTO `system_settings` VALUES('5','smtp_encryption','tls',NULL,'2026-02-12 14:28:28');
INSERT INTO `system_settings` VALUES('6','smtp_from_email','hyun@arrowhitech.com',NULL,'2026-02-12 14:36:52');
INSERT INTO `system_settings` VALUES('7','smtp_from_name','AHT KPI System',NULL,'2026-02-12 14:28:28');



DROP TABLE IF EXISTS `user_sale_teams`;
CREATE TABLE `user_sale_teams` (
  `user_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`team_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `user_sale_teams_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_sale_teams_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `sale_teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `user_sale_teams` VALUES('1','3');
INSERT INTO `user_sale_teams` VALUES('2','3');
INSERT INTO `user_sale_teams` VALUES('1','5');



DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT 'Junior',
  `department_id` int(11) DEFAULT NULL,
  `employee_code` varchar(20) DEFAULT NULL,
  `skills` text,
  `join_date` date DEFAULT NULL,
  `status` enum('active','inactive','resigned','on_leave') DEFAULT 'active',
  `can_view_invoice` tinyint(1) DEFAULT '0',
  `is_am_bd` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `fk_user_dept` (`department_id`),
  CONSTRAINT `fk_user_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

INSERT INTO `users` VALUES('1','admin','$2y$12$buwZTymRS0dWBU/MY9BxYuhqZrhAMTQYKXAk.PMUwJTywvWiyZ4wO','Hyun C.','hyun@arrowhitech.com','admin','2026-02-12 10:25:14','/public/uploads/avatars/avatar_1_1770869891.png','+84989302850','','Junior','8','',NULL,NULL,'active','1','1');
INSERT INTO `users` VALUES('2','lionel','$2y$12$/HUMOSv52Rhjoa9.TFanc.jHoiFHKYwJGWF4Kr7G//tNtDTBGCC6.','Bùi Văn Duyệt','emily@arrowhitech.com','user','2026-02-12 12:38:48',NULL,NULL,'Division Manager','Junior','8','PHT015',NULL,'2026-02-05','active','1','1');

