/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.8-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: umu_vote
-- ------------------------------------------------------
-- Server version	11.8.8-MariaDB-1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `admin_audit_log`
--

DROP TABLE IF EXISTS `admin_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `admin_email` varchar(191) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_audit_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `admin_audit_log` WRITE;
/*!40000 ALTER TABLE `admin_audit_log` DISABLE KEYS */;
INSERT INTO `admin_audit_log` VALUES
(1,1,'josbert.aijuka@stud.umu.ac.ug','voting_mode_changed','previous_mode=rating new_mode=simple','127.0.0.1','2026-08-30 08:10:15'),
(2,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','opened','127.0.0.1','2026-08-30 08:10:15'),
(3,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','closed','127.0.0.1','2026-08-30 08:11:55'),
(4,1,'josbert.aijuka@stud.umu.ac.ug','results_public_changed','made public','127.0.0.1','2026-08-30 08:12:46'),
(5,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','opened','127.0.0.1','2026-08-30 16:56:41'),
(6,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','closed','127.0.0.1','2026-08-30 17:00:27'),
(7,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','opened','127.0.0.1','2026-08-30 17:06:29');
/*!40000 ALTER TABLE `admin_audit_log` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES
('event_date','2026-08-31 00:00:00'),
('results_public','1'),
('voting_end','2026-09-01 00:00:00'),
('voting_mode','simple'),
('voting_open','1'),
('voting_start','2026-08-31 00:00:00');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES
(1,'Smartest','male','2026-05-13 12:53:26',1),
(2,'Most Approachable','male','2026-05-13 12:53:26',1),
(3,'Most Stylish','male','2026-05-13 12:53:26',1),
(4,'Most Influential','male','2026-05-13 12:53:26',1),
(5,'Most Creative','male','2026-05-13 12:53:26',1),
(6,'Most Social','male','2026-05-13 12:53:26',1),
(7,'Best Smile','male','2026-05-13 12:53:26',1),
(8,'Most Entertaining','male','2026-05-13 12:53:26',1),
(9,'Smart (Dress Code)','male','2026-05-13 12:53:26',1),
(10,'Brains (Outside the Box)','male','2026-05-13 12:53:26',1),
(14,'Smartest','female','2026-05-13 12:53:26',1),
(15,'Most Approachable','female','2026-05-13 12:53:26',1),
(16,'Most Stylish','female','2026-05-13 12:53:26',1),
(17,'Most Influential','female','2026-05-13 12:53:26',1),
(18,'Most Creative','female','2026-05-13 12:53:26',1),
(19,'Most Social','female','2026-05-13 12:53:26',1),
(20,'Best Smile','female','2026-05-13 12:53:26',1),
(21,'Most Entertaining','female','2026-05-13 12:53:26',1),
(22,'Smart (Dress Code)','female','2026-05-13 12:53:26',1),
(23,'Brains (Outside the Box)','female','2026-05-13 12:53:26',1);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `contestants`
--

DROP TABLE IF EXISTS `contestants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contestants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `photo` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contestants`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `contestants` WRITE;
/*!40000 ALTER TABLE `contestants` DISABLE KEYS */;
INSERT INTO `contestants` VALUES
(5,'Wamani Sedrack','male','uploads/contestants/contestant_6a062afb0b3c27.21199324.jpg','BSIT II','2026-05-14 20:05:15',1),
(6,'Ekou Jeremiah','male','uploads/contestants/contestant_6a062b8a2a3eb3.01799911.jpg','BSIT I','2026-05-14 20:07:38',1),
(7,'Nnamale Mariam Nsereko','female','uploads/contestants/contestant_6a062bbad23e71.02783359.jpg','SASS I','2026-05-14 20:08:26',1),
(8,'Nerima Maria Wagama','female','uploads/contestants/contestant_6a062bfb379744.21347367.jpg','BSDC I','2026-05-14 20:09:31',1),
(9,'Ajulut Vincencia Cynthia','female','uploads/contestants/contestant_6a062c3cba26d5.55048244.jpg','BPH I','2026-05-14 20:10:36',1);
/*!40000 ALTER TABLE `contestants` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `rl_key` varchar(191) NOT NULL,
  `hit_count` int(10) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`rl_key`),
  KEY `idx_rate_limits_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES
('admin_settings:user:1:29801636',1,'2026-08-30 13:57:00'),
('admin_settings:user:1:29801640',1,'2026-08-30 14:01:00'),
('admin_settings:user:1:29801646',1,'2026-08-30 14:07:00'),
('admin_settings:user:1:29801647',1,'2026-08-30 14:08:00'),
('oauth_callback:user:1:29801635',1,'2026-08-30 13:56:00');
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `access` int(11) NOT NULL,
  `data` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `access` (`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES
('ac4464208c837df18d85676f207cda34',1788098896,''),
('e2845c1dfa70872753d63fb03d8c892c',1788098058,'');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `has_voted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_id` (`google_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'118273761186437018046','AIJUKA JOSBERT','josbert.aijuka@stud.umu.ac.ug',0,'2026-05-13 12:55:32');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `contestant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `score` tinyint(4) NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'rating',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vote` (`user_id`,`contestant_id`,`category_id`),
  KEY `fk_votes_contestant` (`contestant_id`),
  KEY `fk_votes_category` (`category_id`),
  KEY `idx_votes_mode` (`mode`),
  CONSTRAINT `fk_votes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_contestant` FOREIGN KEY (`contestant_id`) REFERENCES `contestants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `votes` WRITE;
/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
INSERT INTO `votes` VALUES
(41,1,5,1,1,'simple','2026-08-30 05:11:21'),
(42,1,6,2,1,'simple','2026-08-30 05:11:21'),
(43,1,6,3,1,'simple','2026-08-30 05:11:21'),
(44,1,5,4,1,'simple','2026-08-30 05:11:21'),
(45,1,6,5,1,'simple','2026-08-30 05:11:21'),
(46,1,6,6,1,'simple','2026-08-30 05:11:21'),
(47,1,5,7,1,'simple','2026-08-30 05:11:21'),
(48,1,6,8,1,'simple','2026-08-30 05:11:21'),
(49,1,6,9,1,'simple','2026-08-30 05:11:21'),
(50,1,5,10,1,'simple','2026-08-30 05:11:21');
/*!40000 ALTER TABLE `votes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-08-30 17:20:35
