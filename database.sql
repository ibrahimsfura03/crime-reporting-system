CREATE DATABASE  IF NOT EXISTS `crime_reporting_system` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `crime_reporting_system`;
-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: localhost    Database: crime_reporting_system
-- ------------------------------------------------------
-- Server version	9.1.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `agents`
--

DROP TABLE IF EXISTS `agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agents` (
  `agent_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `badge_number` varchar(20) NOT NULL,
  `department` varchar(100) NOT NULL,
  `rank` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`agent_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `badge_number` (`badge_number`),
  CONSTRAINT `agents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agents`
--

LOCK TABLES `agents` WRITE;
/*!40000 ALTER TABLE `agents` DISABLE KEYS */;
INSERT INTO `agents` VALUES (1,2,'A12345','Criminal Investigation','Detective','active'),(2,3,'B67890','Cyber Crimes Unit','Senior Agent','active'),(3,11,'A26447','General Investigation',NULL,'active'),(5,13,'A21014','General Investigation',NULL,'active'),(7,15,'A37560','General Investigation',NULL,'active');
/*!40000 ALTER TABLE `agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crime_types`
--

DROP TABLE IF EXISTS `crime_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crime_types` (
  `type_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `severity` enum('low','medium','high','critical') NOT NULL,
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crime_types`
--

LOCK TABLES `crime_types` WRITE;
/*!40000 ALTER TABLE `crime_types` DISABLE KEYS */;
INSERT INTO `crime_types` VALUES (1,'Theft','Theft of property','medium'),(2,'Assault','Physical assault or attack','high'),(3,'Vandalism','Damage to property','low'),(4,'Fraud','Deception for financial gain','medium'),(5,'Breaking and Entering','Illegal entry into a property','high'),(6,'Cybercrime','Computer-related crimes','medium'),(7,'Harassment','Repeated unwanted contact or behavior','medium');
/*!40000 ALTER TABLE `crime_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incident_agent`
--

DROP TABLE IF EXISTS `incident_agent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incident_agent` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `agent_id` int NOT NULL,
  `assigned_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `incident_id` (`incident_id`,`agent_id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `incident_agent_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  CONSTRAINT `incident_agent_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`agent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incident_agent`
--

LOCK TABLES `incident_agent` WRITE;
/*!40000 ALTER TABLE `incident_agent` DISABLE KEYS */;
INSERT INTO `incident_agent` VALUES (1,1,3,'2025-05-10 15:16:04','&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key &quot;notes&quot; in &lt;b&gt;C:xampphtdocscrime-reporting-systemadminassign_report.php&lt;/b&gt; on line &lt;b&gt;275&lt;/b&gt;&lt;br /&gt;'),(2,3,7,'2025-06-24 20:43:23','go and resolve the issue rn please.'),(3,2,7,'2025-06-24 21:25:18','go and fix it sharppppppppppppp'),(4,4,7,'2025-06-25 09:35:02','go and resolve the issue now');
/*!40000 ALTER TABLE `incident_agent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incident_updates`
--

DROP TABLE IF EXISTS `incident_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incident_updates` (
  `update_id` int NOT NULL AUTO_INCREMENT,
  `incident_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status_id` int NOT NULL,
  `update_text` text NOT NULL,
  `update_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `incident_id` (`incident_id`),
  KEY `user_id` (`user_id`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `incident_updates_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  CONSTRAINT `incident_updates_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `incident_updates_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `status_codes` (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incident_updates`
--

LOCK TABLES `incident_updates` WRITE;
/*!40000 ALTER TABLE `incident_updates` DISABLE KEYS */;
INSERT INTO `incident_updates` VALUES (1,1,1,4,'Case assigned to agent.','2025-05-10 15:16:04'),(2,1,11,5,'solved','2025-05-10 15:28:32'),(3,3,14,4,'Case assigned to agent.','2025-06-24 20:43:23'),(4,3,15,2,'i am currentky working on it','2025-06-24 20:47:29'),(5,3,15,5,'i don solve am, wey my payouts?','2025-06-24 20:55:20'),(6,6,14,3,'Status updated by admin.','2025-06-24 21:07:23'),(7,2,12,4,'Case assigned to agent.','2025-06-24 21:25:18'),(8,2,15,5,'solved :))))))))))))','2025-06-24 21:25:58'),(9,4,12,4,'Case assigned to agent.','2025-06-25 09:35:02'),(10,4,15,1,'i willl not attend this rubbish','2025-06-25 09:36:26'),(11,4,15,3,'get out','2025-07-08 22:13:55');
/*!40000 ALTER TABLE `incident_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidents`
--

DROP TABLE IF EXISTS `incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidents` (
  `incident_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `crime_type_id` int NOT NULL,
  `status_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL,
  `incident_date` datetime NOT NULL,
  `reported_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_anonymous` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`incident_id`),
  KEY `user_id` (`user_id`),
  KEY `crime_type_id` (`crime_type_id`),
  KEY `incident_date` (`incident_date`),
  KEY `status_id` (`status_id`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`crime_type_id`) REFERENCES `crime_types` (`type_id`),
  CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`status_id`) REFERENCES `status_codes` (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidents`
--

LOCK TABLES `incidents` WRITE;
/*!40000 ALTER TABLE `incidents` DISABLE KEYS */;
INSERT INTO `incidents` VALUES (1,11,2,5,'idk','idkoooo','idktoo','2025-05-10 08:36:00','2025-05-10 07:36:36','2025-05-10 15:28:32',0),(2,13,5,5,'hello','helllllllllllllllllllllllllllllll','idk','2025-05-29 02:40:00','2025-06-23 01:37:15','2025-06-24 21:25:58',0),(3,14,6,5,'Fcaebook Account Hacking','someone hacked my fb account right in the freaking front of mai :(','sardauna estate','2025-06-03 23:34:00','2025-06-24 20:35:09','2025-06-24 20:55:20',0),(4,15,1,3,'Cooking gas stealing','as i just went to pray isha, someine stole my cooking gas, and theres even Danwake cooking on it.','sardauna estate','2025-06-24 21:37:00','2025-06-24 20:38:38','2025-07-08 22:13:55',1),(5,16,4,1,'I was scamed of 500k from opay','I was promised 500k, and i never received it','sardauna estate','2025-06-24 22:03:00','2025-06-24 21:03:58','2025-06-24 21:03:58',0),(6,16,5,3,'Someone breaks and enterd my room','the guy just enterd, and i dont even know him, please i need your immidiat attention at sardauna estate, ingawa road (uwaisu mashi) house number 9 room1','sardauna estate','2025-06-04 22:09:00','2025-06-24 21:05:58','2025-06-24 21:07:23',0),(7,12,6,1,'ddddccf','kvvyggfff fvffcf','mlmslms','2025-07-15 15:48:00','2025-07-10 11:48:22','2025-07-10 11:48:22',0);
/*!40000 ALTER TABLE `incidents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_codes`
--

DROP TABLE IF EXISTS `status_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_codes` (
  `status_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_codes`
--

LOCK TABLES `status_codes` WRITE;
/*!40000 ALTER TABLE `status_codes` DISABLE KEYS */;
INSERT INTO `status_codes` VALUES (1,'New','Newly reported incident, not yet reviewed'),(2,'Under Investigation','Incident is being actively investigated'),(3,'Pending Evidence','Waiting for additional evidence or information'),(4,'Assigned','Assigned to an agent but investigation not yet started'),(5,'Closed','Investigation completed and case closed');
/*!40000 ALTER TABLE `status_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','agent','user') NOT NULL DEFAULT 'user',
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `profile_image` varchar(255) DEFAULT 'default.jpg',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG','admin@example.com','System Administrator','admin','123-456-7890','123 Admin St, Admin City','admin_profile.jpg','2025-05-10 07:33:07','2025-05-10 07:33:07'),(2,'agent1','$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG','agent1@example.com','John Smith','agent','123-456-7891','456 Agent Ave, Agent City','agent1_profile.jpg','2025-05-10 07:33:08','2025-05-10 07:33:08'),(3,'agent2','$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG','agent2@example.com','Sarah Johnson','agent','123-456-7893','789 Agent Blvd, Agent Town','agent2_profile.jpg','2025-05-10 07:33:08','2025-05-10 07:33:08'),(4,'user1','$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG','user1@example.com','Jane Doe','user','123-456-7892','789 User Blvd, User City','user1_profile.jpg','2025-05-10 07:33:08','2025-05-10 15:50:39'),(5,'user2','$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG','user2@example.com','Robert Brown','user','123-456-7894','101 User St, User Town','user2_profile.jpg','2025-05-10 07:33:08','2025-05-10 07:33:08'),(11,'Ibrahim','$2y$10$g/Deg.iCuqVYNRr0HdB8TuArKkd9WeNSgi5Lz1fNx7kawKpDOXAmW','ibrahimsfura03@gmail.com','Ibrahim Suleiman Adamu','user','08033698607','Pantami','profile_11_1746890874.png','2025-05-10 07:35:46','2025-05-10 15:50:48'),(12,'ibrahimsfura','$2y$10$EyzDK2du0Db8UUQS5IFRzuJsh/XWK/QFt7m6jFwixd3vnQkXMKrzC','ibrahimsfura@gmail.com','Ibrahim Suleiman Adamu','admin','08033698607','Pantami','profile_12_1750796669.png','2025-06-23 01:26:17','2025-06-24 20:24:29'),(13,'ebrahim','$2y$10$LI9nxZP1bNxo/49VNl8eLOj5xk3Rmz0Xp6Jk/9nYgpLAHRNvcRSea','ibrahimsfur@gmail.com','Ibrahim Suleiman Adamu','agent','08033698607','Pantami','profile_13_1750642578.png','2025-06-23 01:34:20','2025-06-23 01:36:18'),(14,'abdulsamad','$2y$10$Z.6kF7LpErmwmQbny2S3QuehROTu7rY7Ffmrkh8nHxzL2vIeh.kcS','abdulsamad@gmail.com','Abdulsamad Suleiman','admin','9035044040','sardauna estate','profile_14_1750797232.png','2025-06-24 20:30:32','2025-06-24 20:44:13'),(15,'abdulsamad1','$2y$10$aNJ81FK3vCy.Q.4yyNvGpuBGbpkeUHxj9sXYWaTcWS56/LW0AO872','abdulsamad@yahoo.com','Abdulsamad Suleiman Ahmad','agent','9035044040','sardauna','profile_15_1750797418.png','2025-06-24 20:32:04','2025-06-24 20:40:37'),(16,'abdulsamad2','$2y$10$N6ggaXnRUFXwDIOmzKnlaeck8pXafHu8LPfZ7eVN7cznTzjA.skS6','abdulsamad@yandex.com','Abdulsamad Suleiman Ahmad','user','9035044040','sardauna estate','profile_16_1750798940.jpg','2025-06-24 20:32:46','2025-06-24 21:02:20');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-10 22:20:50
