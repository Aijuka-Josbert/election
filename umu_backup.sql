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
(1,1,'josbert.aijuka@stud.umu.ac.ug','contestant_updated','id=6 name=Ekou Jeremiah','127.0.0.1','2026-09-01 23:15:32'),
(2,1,'josbert.aijuka@stud.umu.ac.ug','voting_mode_changed','previous_mode=rating new_mode=simple','127.0.0.1','2026-09-02 00:51:08'),
(3,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','opened','127.0.0.1','2026-09-02 00:51:08'),
(4,1,'josbert.aijuka@stud.umu.ac.ug','voting_mode_changed','previous_mode=simple new_mode=rating','127.0.0.1','2026-09-02 01:00:00'),
(5,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','closed','127.0.0.1','2026-09-02 01:02:29'),
(6,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','opened','::1','2026-09-02 06:12:34'),
(7,1,'josbert.aijuka@stud.umu.ac.ug','voting_open_changed','closed','::1','2026-09-02 06:51:42');
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
('event_date','2026-09-02 00:00:00'),
('event_name','UMU Rubaga Varsity Ball'),
('event_tagline',''),
('female_title',''),
('logo_url',''),
('male_title',''),
('results_public','0'),
('theme_accent_color','#c9a227'),
('theme_background_color','#f7f7f7'),
('theme_primary_color','#c8102e'),
('theme_text_color','#121212'),
('voting_end','2026-09-02 06:30:00'),
('voting_mode','rating'),
('voting_open','0'),
('voting_start','2026-09-02 06:15:00');
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
  `gender` enum('male','female','all') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES
(1,'Smartest','all','2026-05-13 12:53:26',1),
(2,'Most Approachable','all','2026-05-13 12:53:26',1),
(3,'Most Stylish','all','2026-05-13 12:53:26',1),
(4,'Most Influential','all','2026-05-13 12:53:26',1),
(5,'Most Creative','all','2026-05-13 12:53:26',1),
(6,'Most Social','all','2026-05-13 12:53:26',1),
(7,'Best Smile','all','2026-05-13 12:53:26',1),
(8,'Most Entertaining','all','2026-05-13 12:53:26',1),
(9,'Smart (Dress Code)','all','2026-05-13 12:53:26',1),
(10,'Brains (Outside the Box)','all','2026-05-13 12:53:26',1),
(27,'wierd looking','all','2026-08-31 18:40:16',1);
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
(6,'Ekou Jeremiah','male','uploads/contestants/contestant_6a062b8a2a3eb3.01799911.jpg','BSIT II','2026-05-14 20:07:38',1),
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
('admin_settings:user:1:29801963',1,'2026-08-30 19:24:00'),
('admin_settings:user:1:29801974',1,'2026-08-30 19:35:00'),
('admin_settings:user:1:29802478',1,'2026-08-31 03:59:00'),
('admin_settings:user:1:29802771',1,'2026-08-31 08:52:00'),
('admin_settings:user:1:29803355',1,'2026-08-31 18:36:00'),
('admin_settings:user:1:29803381',1,'2026-08-31 19:02:00'),
('admin_settings:user:1:29804039',1,'2026-09-01 06:00:00'),
('admin_settings:user:1:29804042',1,'2026-09-01 06:03:00'),
('admin_settings:user:1:29804043',1,'2026-09-01 06:04:00'),
('admin_settings:user:1:29804046',1,'2026-09-01 06:07:00'),
('admin_settings:user:1:29804047',2,'2026-09-01 06:08:00'),
('admin_settings:user:1:29804069',1,'2026-09-01 06:30:00'),
('admin_settings:user:1:29804991',1,'2026-09-01 21:52:00'),
('admin_settings:user:1:29805000',1,'2026-09-01 22:01:00'),
('admin_settings:user:1:29805002',1,'2026-09-01 22:03:00'),
('admin_settings:user:1:29805312',1,'2026-09-02 03:13:00'),
('admin_settings:user:1:29805351',1,'2026-09-02 03:52:00'),
('oauth_callback:ip:::1:29805308',1,'2026-09-02 03:09:00'),
('oauth_callback:ip:127.0.0.1:29801961',1,'2026-08-30 19:22:00'),
('oauth_callback:ip:127.0.0.1:29802770',1,'2026-08-31 08:51:00'),
('oauth_callback:ip:127.0.0.1:29803354',1,'2026-08-31 18:35:00'),
('oauth_callback:ip:127.0.0.1:29803380',1,'2026-08-31 19:01:00'),
('oauth_callback:ip:127.0.0.1:29804036',1,'2026-09-01 05:57:00'),
('oauth_callback:ip:127.0.0.1:29804104',1,'2026-09-01 07:05:00'),
('oauth_callback:ip:127.0.0.1:29804890',1,'2026-09-01 20:11:00'),
('oauth_callback:user:1:29801635',1,'2026-08-30 13:56:00'),
('vote_submit:user:1:29802784',1,'2026-08-31 09:05:00'),
('vote_submit:user:1:29803356',1,'2026-08-31 18:37:00'),
('vote_submit:user:1:29803366',1,'2026-08-31 18:47:00'),
('vote_submit:user:1:29804040',1,'2026-09-01 06:01:00'),
('vote_submit:user:1:29804050',1,'2026-09-01 06:11:00'),
('vote_submit:user:1:29805317',1,'2026-09-02 03:18:00');
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
('22736600091de1ccf091739055b0207f',1788300152,'user_id|i:1;user_name|s:14:\"AIJUKA JOSBERT\";user_email|s:29:\"josbert.aijuka@stud.umu.ac.ug\";has_voted|i:1;csrf_token|s:64:\"c4699cd6c98319c23339ed63ab07a1ff46ea2c19f70da009cb301723e842d5cf\";'),
('2e5791103e114f1f24ed8aa71214fc27',1788301335,''),
('9696e3ca49d58d6c8703d8689f356afe',1788299824,'oauth_state|s:32:\"ee0bc96dd60b50cecefc3ab7a376b167\";'),
('b67ffa5596091ea24e0cd459f4aec889',1788328444,'user_id|i:1;user_name|s:14:\"AIJUKA JOSBERT\";user_email|s:29:\"josbert.aijuka@stud.umu.ac.ug\";has_voted|i:1;csrf_token|s:64:\"f43892566820415dcdeb450885267e48ee305d77c4d61eb50a1b6b040ccb9d5f\";');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `tie_break_log`
--

DROP TABLE IF EXISTS `tie_break_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tie_break_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `gender` varchar(16) NOT NULL,
  `winner_contestant_id` int(11) NOT NULL,
  `admin_user_id` int(10) unsigned DEFAULT NULL,
  `admin_email` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tie_break_lookup` (`category_id`,`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tie_break_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `tie_break_log` WRITE;
/*!40000 ALTER TABLE `tie_break_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `tie_break_log` ENABLE KEYS */;
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
(1,'118273761186437018046','AIJUKA JOSBERT','josbert.aijuka@stud.umu.ac.ug',1,'2026-05-13 12:55:32');
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
(1,1,6,1,3,'rating','2026-09-02 03:17:59'),
(2,1,5,1,5,'rating','2026-09-02 03:17:59'),
(3,1,9,1,5,'rating','2026-09-02 03:17:59'),
(4,1,8,1,3,'rating','2026-09-02 03:17:59'),
(5,1,7,1,5,'rating','2026-09-02 03:17:59'),
(6,1,6,2,4,'rating','2026-09-02 03:17:59'),
(7,1,5,2,5,'rating','2026-09-02 03:17:59'),
(8,1,9,2,4,'rating','2026-09-02 03:17:59'),
(9,1,8,2,4,'rating','2026-09-02 03:17:59'),
(10,1,7,2,5,'rating','2026-09-02 03:17:59'),
(11,1,6,3,5,'rating','2026-09-02 03:17:59'),
(12,1,5,3,4,'rating','2026-09-02 03:17:59'),
(13,1,9,3,5,'rating','2026-09-02 03:17:59'),
(14,1,8,3,4,'rating','2026-09-02 03:17:59'),
(15,1,7,3,4,'rating','2026-09-02 03:17:59'),
(16,1,6,4,3,'rating','2026-09-02 03:17:59'),
(17,1,5,4,5,'rating','2026-09-02 03:17:59'),
(18,1,9,4,5,'rating','2026-09-02 03:17:59'),
(19,1,8,4,3,'rating','2026-09-02 03:17:59'),
(20,1,7,4,4,'rating','2026-09-02 03:17:59'),
(21,1,6,5,5,'rating','2026-09-02 03:17:59'),
(22,1,5,5,4,'rating','2026-09-02 03:17:59'),
(23,1,9,5,4,'rating','2026-09-02 03:17:59'),
(24,1,8,5,5,'rating','2026-09-02 03:17:59'),
(25,1,7,5,4,'rating','2026-09-02 03:17:59'),
(26,1,6,6,3,'rating','2026-09-02 03:17:59'),
(27,1,5,6,5,'rating','2026-09-02 03:17:59'),
(28,1,9,6,3,'rating','2026-09-02 03:17:59'),
(29,1,8,6,4,'rating','2026-09-02 03:17:59'),
(30,1,7,6,5,'rating','2026-09-02 03:17:59'),
(31,1,6,7,4,'rating','2026-09-02 03:17:59'),
(32,1,5,7,5,'rating','2026-09-02 03:17:59'),
(33,1,9,7,5,'rating','2026-09-02 03:17:59'),
(34,1,8,7,4,'rating','2026-09-02 03:17:59'),
(35,1,7,7,5,'rating','2026-09-02 03:17:59'),
(36,1,6,8,5,'rating','2026-09-02 03:17:59'),
(37,1,5,8,4,'rating','2026-09-02 03:17:59'),
(38,1,9,8,4,'rating','2026-09-02 03:17:59'),
(39,1,8,8,4,'rating','2026-09-02 03:17:59'),
(40,1,7,8,5,'rating','2026-09-02 03:17:59'),
(41,1,6,9,4,'rating','2026-09-02 03:17:59'),
(42,1,5,9,5,'rating','2026-09-02 03:17:59'),
(43,1,9,9,4,'rating','2026-09-02 03:17:59'),
(44,1,8,9,5,'rating','2026-09-02 03:17:59'),
(45,1,7,9,4,'rating','2026-09-02 03:17:59'),
(46,1,6,10,4,'rating','2026-09-02 03:17:59'),
(47,1,5,10,5,'rating','2026-09-02 03:17:59'),
(48,1,9,10,5,'rating','2026-09-02 03:17:59'),
(49,1,8,10,4,'rating','2026-09-02 03:17:59'),
(50,1,7,10,4,'rating','2026-09-02 03:17:59');
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

-- Dump completed on 2026-09-03 13:10:21
