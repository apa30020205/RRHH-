-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: rrhh
-- ------------------------------------------------------
-- Server version	8.0.44

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
-- Table structure for table `funcionarios`
--

DROP TABLE IF EXISTS `funcionarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `funcionarios` (
  `cedula` varchar(20) NOT NULL COMMENT 'cedula key',
  `nombre` varchar(40) DEFAULT NULL COMMENT 'nombre y segundo nombre',
  `apellido` varchar(50) DEFAULT NULL COMMENT 'apellido y segundo apellido',
  `fecha_nacimiento` date NOT NULL,
  `edad` tinyint NOT NULL COMMENT 'Fecha de nacimiento calculable pero se puede pobne al llenar',
  `sangre` varchar(5) NOT NULL COMMENT 'tipo de sangre o+ etc',
  `no_posicion` int NOT NULL COMMENT 'es unica ',
  `posicion_funcional` varchar(45) NOT NULL COMMENT 'Nombre de la posicion',
  `fecha_inicio` date NOT NULL COMMENT 'cuando comenzo a laborar es unico con cada empleado y podiras cambiar de posicion funcional pero mantienes antiguedad',
  `sede_provincia` varchar(20) NOT NULL COMMENT 'en que lugar trabaja sede provincia comarca',
  `Direccion` varchar(100) NOT NULL COMMENT 'Para que direccion, regional o Espacio del empendedor trabaja',
  PRIMARY KEY (`cedula`),
  UNIQUE KEY `cedula_UNIQUE` (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-18 16:55:04
