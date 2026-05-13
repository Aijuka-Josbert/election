/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: umu_vote
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-6 from Debian

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES
(1,'Smartest','male','2026-05-13 12:53:26'),
(2,'Most Approachable','male','2026-05-13 12:53:26'),
(3,'Most Stylish','male','2026-05-13 12:53:26'),
(4,'Most Influential','male','2026-05-13 12:53:26'),
(5,'Most Creative','male','2026-05-13 12:53:26'),
(6,'Most Social','male','2026-05-13 12:53:26'),
(7,'Best Smile','male','2026-05-13 12:53:26'),
(8,'Most Entertaining','male','2026-05-13 12:53:26'),
(9,'Smart (Dress Code)','male','2026-05-13 12:53:26'),
(10,'Brains (Outside the Box)','male','2026-05-13 12:53:26'),
(11,'Talent','male','2026-05-13 12:53:26'),
(12,'Confidence','male','2026-05-13 12:53:26'),
(13,'Self Awareness','male','2026-05-13 12:53:26'),
(14,'Smartest','female','2026-05-13 12:53:26'),
(15,'Most Approachable','female','2026-05-13 12:53:26'),
(16,'Most Stylish','female','2026-05-13 12:53:26'),
(17,'Most Influential','female','2026-05-13 12:53:26'),
(18,'Most Creative','female','2026-05-13 12:53:26'),
(19,'Most Social','female','2026-05-13 12:53:26'),
(20,'Best Smile','female','2026-05-13 12:53:26'),
(21,'Most Entertaining','female','2026-05-13 12:53:26'),
(22,'Smart (Dress Code)','female','2026-05-13 12:53:26'),
(23,'Brains (Outside the Box)','female','2026-05-13 12:53:26'),
(24,'Talent','female','2026-05-13 12:53:26'),
(25,'Confidence','female','2026-05-13 12:53:26'),
(26,'Self Awareness','female','2026-05-13 12:53:26');
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contestants`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `contestants` WRITE;
/*!40000 ALTER TABLE `contestants` DISABLE KEYS */;
INSERT INTO `contestants` VALUES
(1,'Wamani Sedrack','male','uploads/contestants/contestant_6a0474e988eb70.70543319.jpg','BSIT Yr 2','2026-05-13 12:56:09'),
(2,'Ekou Jeremiah','male','uploads/contestants/contestant_6a047502bd0326.77468442.jpg','BSIT 1','2026-05-13 12:56:34'),
(3,'Mariam Nnamale Nsereko','female','uploads/contestants/contestant_6a04752b8ba4d8.74495667.jpg','SASS Yr 1','2026-05-13 12:57:15'),
(4,'Maria','female','uploads/contestants/contestant_6a047d8620d3b4.94996148.jpg',NULL,'2026-05-13 13:32:54');
/*!40000 ALTER TABLE `contestants` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
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
  `user_id` int(11) NOT NULL,
  `contestant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `score` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vote` (`user_id`,`contestant_id`,`category_id`),
  KEY `fk_votes_contestant` (`contestant_id`),
  KEY `fk_votes_category` (`category_id`),
  CONSTRAINT `fk_votes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_contestant` FOREIGN KEY (`contestant_id`) REFERENCES `contestants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `votes` WRITE;
/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
INSERT INTO `votes` VALUES
(1,1,2,1,8,'2026-05-13 13:37:34'),
(2,1,1,1,5,'2026-05-13 13:37:34'),
(3,1,2,2,5,'2026-05-13 13:37:34'),
(4,1,1,2,8,'2026-05-13 13:37:34'),
(5,1,2,3,5,'2026-05-13 13:37:34'),
(6,1,1,3,9,'2026-05-13 13:37:34'),
(7,1,2,4,5,'2026-05-13 13:37:34'),
(8,1,1,4,8,'2026-05-13 13:37:34'),
(9,1,2,5,9,'2026-05-13 13:37:34'),
(10,1,1,5,5,'2026-05-13 13:37:34'),
(11,1,2,6,5,'2026-05-13 13:37:34'),
(12,1,1,6,5,'2026-05-13 13:37:34'),
(13,1,2,7,5,'2026-05-13 13:37:34'),
(14,1,1,7,9,'2026-05-13 13:37:34'),
(15,1,2,8,5,'2026-05-13 13:37:34'),
(16,1,1,8,8,'2026-05-13 13:37:34'),
(17,1,2,9,9,'2026-05-13 13:37:34'),
(18,1,1,9,5,'2026-05-13 13:37:34'),
(19,1,2,10,5,'2026-05-13 13:37:34'),
(20,1,1,10,9,'2026-05-13 13:37:34'),
(21,1,2,11,8,'2026-05-13 13:37:34'),
(22,1,1,11,5,'2026-05-13 13:37:34'),
(23,1,2,12,5,'2026-05-13 13:37:34'),
(24,1,1,12,9,'2026-05-13 13:37:34'),
(25,1,2,13,10,'2026-05-13 13:37:34'),
(26,1,1,13,5,'2026-05-13 13:37:34'),
(27,1,4,14,5,'2026-05-13 13:37:34'),
(28,1,3,14,8,'2026-05-13 13:37:34'),
(29,1,4,15,5,'2026-05-13 13:37:34'),
(30,1,3,15,8,'2026-05-13 13:37:34'),
(31,1,4,16,5,'2026-05-13 13:37:34'),
(32,1,3,16,7,'2026-05-13 13:37:34'),
(33,1,4,17,8,'2026-05-13 13:37:34'),
(34,1,3,17,5,'2026-05-13 13:37:34'),
(35,1,4,18,8,'2026-05-13 13:37:34'),
(36,1,3,18,5,'2026-05-13 13:37:34'),
(37,1,4,19,5,'2026-05-13 13:37:34'),
(38,1,3,19,8,'2026-05-13 13:37:34'),
(39,1,4,20,5,'2026-05-13 13:37:34'),
(40,1,3,20,8,'2026-05-13 13:37:34'),
(41,1,4,21,5,'2026-05-13 13:37:34'),
(42,1,3,21,8,'2026-05-13 13:37:34'),
(43,1,4,22,5,'2026-05-13 13:37:34'),
(44,1,3,22,7,'2026-05-13 13:37:34'),
(45,1,4,23,8,'2026-05-13 13:37:34'),
(46,1,3,23,5,'2026-05-13 13:37:34'),
(47,1,4,24,8,'2026-05-13 13:37:34'),
(48,1,3,24,5,'2026-05-13 13:37:34'),
(49,1,4,25,5,'2026-05-13 13:37:34'),
(50,1,3,25,8,'2026-05-13 13:37:34'),
(51,1,4,26,5,'2026-05-13 13:37:34'),
(52,1,3,26,8,'2026-05-13 13:37:34');
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

-- Dump completed on 2026-05-13 16:41:51
