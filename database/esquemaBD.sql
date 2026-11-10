-- Hotel Costa Azul (script corregido)
-- Recrea la BD con el nombre que usa config/conexion.php (solo desarrollo)
DROP DATABASE IF EXISTS `hotel_costa_azul`;
CREATE DATABASE `hotel_costa_azul` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hotel_costa_azul`;

SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- TABLAS (con PRIMARY KEY, UNIQUE y AUTO_INCREMENT desde el inicio)
-- --------------------------------------------------------

CREATE TABLE `habitacion` (
  `numero_habitacion` varchar(5) NOT NULL,
  `tipo_habitacion` varchar(30) NOT NULL,
  `estado_limpieza` varchar(20) NOT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`numero_habitacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `contacto` varchar(10) NOT NULL,
  `correo` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'Huesped',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_correo` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `politica_cancelacion` (
  `id_politica` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` text NOT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  PRIMARY KEY (`id_politica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tarifa` (
  `id_tarifa` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_habitacion` varchar(30) NOT NULL,
  `precio_noche` decimal(10,2) NOT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  PRIMARY KEY (`id_tarifa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reservacion` (
  `id_reservacion` int(11) NOT NULL AUTO_INCREMENT,
  `numero_confirmacion` varchar(5) NOT NULL,
  `fecha_entrada` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'Activa',
  `notas` text DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `canal` varchar(20) NOT NULL,
  `id_huesped` int(11) NOT NULL,
  `id_recepcionista` int(11) DEFAULT NULL,
  `numero_habitacion` varchar(5) NOT NULL,
  `id_tarifa` int(11) NOT NULL,
  `id_politica` int(11) NOT NULL,
  PRIMARY KEY (`id_reservacion`),
  UNIQUE KEY `uq_reservacion_confirmacion` (`numero_confirmacion`),
  KEY `fk_res_huesped` (`id_huesped`),
  KEY `fk_res_recep` (`id_recepcionista`),
  KEY `fk_res_hab` (`numero_habitacion`),
  KEY `fk_res_tarifa` (`id_tarifa`),
  KEY `fk_res_politica` (`id_politica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pago` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,2) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo_pago` varchar(20) NOT NULL,
  `canal_pago` varchar(20) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `id_recepcionista` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_pago`),
  KEY `fk_pago_res` (`id_reservacion`),
  KEY `fk_pago_recep` (`id_recepcionista`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- DATOS
-- --------------------------------------------------------

INSERT INTO `habitacion` (`numero_habitacion`, `tipo_habitacion`, `estado_limpieza`, `disponible`) VALUES
('101', 'Sencilla', 'Limpia', 1),
('102', 'Sencilla', 'Sucia', 1),
('103', 'Sencilla', 'Limpia', 1),
('201', 'Doble', 'Limpia', 1),
('202', 'Doble', 'Mantenimiento', 0),
('203', 'Doble', 'Limpia', 1);

INSERT INTO `politica_cancelacion` (`id_politica`, `descripcion`, `vigente`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Cancelacion gratuita hasta 48 horas antes de la fecha de entrada.', 0, '2026-01-01', '2026-09-30'),
(2, 'Cancelacion gratuita hasta 72 horas antes; despues se cobra la primera noche.', 1, '2026-10-01', NULL);

INSERT INTO `tarifa` (`id_tarifa`, `tipo_habitacion`, `precio_noche`, `vigente`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Sencilla', 780.00, 0, '2026-01-01', '2026-09-30'),
(2, 'Sencilla', 850.00, 1, '2026-10-01', NULL),
(3, 'Doble', 1200.00, 1, '2026-01-01', NULL);

-- Usuarios originales (ids explicitos)
INSERT INTO `usuario` (`id_usuario`, `nombre`, `contacto`, `correo`, `password`, `rol`) VALUES
(2, 'Edgar Olivan Gonzales', '7894561235', 'admin@gmail.com', '$2y$10$UXkkj.fnZyC4NkH1XOrc9.lBRVBsW3LBa1mtQvPR0h1Qs3H7FRNOG', 'Admin'),
(3, 'Ismerai Martinez Dominguez', '1234567895', 'recepcionista1@gmail.com', '$2y$10$z.gaNSB3IwTSQTv28KftDO1ww6Ipc5X01U1752J6poii3cPDSy7UC', 'Recepcionista'),
(4, 'Martina Moran Medina', '1234561234', 'camarista@gmail.com', '$2y$10$1GcznKp94OZovkkNx4/4Rew1sv016beANtMnDa1NBsuWszPUxhPxK', 'Camarista');

-- Usuarios de demostracion (contrasena: 123). Solo para desarrollo.
-- Sin id: MySQL les asigna 5, 6, 7 y 8 automaticamente.
INSERT INTO `usuario` (`nombre`, `contacto`, `correo`, `password`, `rol`) VALUES
('Administrador Demo', '6000000001', 'admin@costaazul.test', '$2y$12$3ulNX4ykhh0OyPbZ3mdn7.x.IONAOz3urCWxTHAF7XYMYLqqqjpeG', 'Admin'),
('Recepcionista Demo', '6000000002', 'recep1@costaazul.test', '$2y$12$GhC64KbNZnBy4gdQ3J48XOWz7Y/VugLq2hiPbyrzeS2ZD2iNWCZyO', 'Recepcionista'),
('Camarista Demo', '6000000003', 'camarista@costaazul.test', '$2y$12$OeZm0xuqBAPORVFF3cGQQ.1AeX/ew3wz1fmDMdouUxvqvm0m/XxcO', 'Camarista'),
('Huesped Demo', '6000000004', 'huesped.a@correo.test', '$2y$12$7FaFiCDpz9ZABtpHIykrdePR3wIbpl88npcmq9VSsOFEgJxyQLtHO', 'Huesped');

-- --------------------------------------------------------
-- LLAVES FORANEAS (al final, cuando ya existen todas las tablas)
-- --------------------------------------------------------

ALTER TABLE `reservacion`
  ADD CONSTRAINT `fk_res_hab` FOREIGN KEY (`numero_habitacion`) REFERENCES `habitacion` (`numero_habitacion`),
  ADD CONSTRAINT `fk_res_huesped` FOREIGN KEY (`id_huesped`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_res_politica` FOREIGN KEY (`id_politica`) REFERENCES `politica_cancelacion` (`id_politica`),
  ADD CONSTRAINT `fk_res_recep` FOREIGN KEY (`id_recepcionista`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_res_tarifa` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`id_tarifa`);

ALTER TABLE `pago`
  ADD CONSTRAINT `fk_pago_recep` FOREIGN KEY (`id_recepcionista`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_pago_res` FOREIGN KEY (`id_reservacion`) REFERENCES `reservacion` (`id_reservacion`);