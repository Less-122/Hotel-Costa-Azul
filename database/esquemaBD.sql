-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-11-2026 a las 14:30:16
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hotel_costa_azul`
--
DROP DATABASE hotel_costa_azul;
CREATE DATABASE IF NOT EXISTS `hotel_costa_azul` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hotel_costa_azul`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitacion`
--

CREATE TABLE `habitacion` (
  `numero_habitacion` varchar(5) NOT NULL,
  `tipo_habitacion` varchar(30) NOT NULL,
  `estado_limpieza` varchar(20) NOT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `habitacion`
--

INSERT INTO `habitacion` (`numero_habitacion`, `tipo_habitacion`, `estado_limpieza`, `disponible`) VALUES
('101', 'Sencilla', 'Limpia', 1),
('102', 'Sencilla', 'Sucia', 1),
('103', 'Sencilla', 'Limpia', 1),
('201', 'Doble', 'Limpia', 1),
('202', 'Doble', 'Mantenimiento', 0),
('203', 'Doble', 'Limpia', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `id_pago` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo_pago` varchar(20) NOT NULL,
  `canal_pago` varchar(20) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `id_recepcionista` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `politica_cancelacion`
--

CREATE TABLE `politica_cancelacion` (
  `id_politica` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `horas_anticipacion` smallint(5) UNSIGNED NOT NULL DEFAULT 72,
  `cobra_primera_noche` tinyint(1) NOT NULL DEFAULT 1,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `politica_cancelacion`
--

INSERT INTO `politica_cancelacion` (`id_politica`, `descripcion`, `horas_anticipacion`, `cobra_primera_noche`, `vigente`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Cancelacion gratuita hasta 48 horas antes de la fecha de entrada.', 48, 0, 0, '2026-01-01', '2026-09-30'),
(2, 'Cancelacion gratuita hasta 72 horas antes; despues se cobra la primera noche.', 72, 1, 1, '2026-10-01', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservacion`
--

CREATE TABLE `reservacion` (
  `id_reservacion` int(11) NOT NULL,
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
  `modificado_por` int(11) DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reservacion`
--

INSERT INTO `reservacion` (`id_reservacion`, `numero_confirmacion`, `fecha_entrada`, `fecha_salida`, `estado`, `notas`, `fecha_registro`, `canal`, `id_huesped`, `id_recepcionista`, `numero_habitacion`, `id_tarifa`, `id_politica`, `modificado_por`, `fecha_modificacion`) VALUES
(1, 'BF91B', '2026-11-12', '2026-11-14', 'Cancelada', '', '2026-11-12 21:45:58', 'En línea', 8, NULL, '201', 3, 2, 3, '2026-11-12 23:22:48'),
(2, 'DTWYR', '2026-11-12', '2026-11-13', 'Activa', NULL, '2026-11-12 23:23:11', 'Recepción', 9, 3, '101', 2, 2, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarifa`
--

CREATE TABLE `tarifa` (
  `id_tarifa` int(11) NOT NULL,
  `tipo_habitacion` varchar(30) NOT NULL,
  `precio_noche` decimal(10,2) NOT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarifa`
--

INSERT INTO `tarifa` (`id_tarifa`, `tipo_habitacion`, `precio_noche`, `vigente`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Sencilla', 780.00, 0, '2026-01-01', '2026-09-30'),
(2, 'Sencilla', 850.00, 1, '2026-10-01', NULL),
(3, 'Doble', 1200.00, 0, '2026-01-01', '2026-11-12'),
(4, 'Doble', 1400.00, 1, '2026-11-12', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `contacto` varchar(10) NOT NULL,
  `correo` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'Huesped'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `contacto`, `correo`, `password`, `rol`) VALUES
(2, 'Edgar Olivan Gonzales', '7894561235', 'admin@gmail.com', '$2y$10$F4.4iDe3pg2ewC0.xwTOLOKOLTpTRkoXPLJMiMaMU9TjZUPpXWIUe', 'Admin'),
(3, 'Ismerai Martinez Dominguez', '1234567895', 'recepcionista1@gmail.com', '$2y$10$z.gaNSB3IwTSQTv28KftDO1ww6Ipc5X01U1752J6poii3cPDSy7UC', 'Recepcionista'),
(4, 'Martina Moran Medina', '1234561234', 'camarista@gmail.com', '$2y$10$1GcznKp94OZovkkNx4/4Rew1sv016beANtMnDa1NBsuWszPUxhPxK', 'Camarista'),
(5, 'Administrador Demo', '6000000001', 'admin@costaazul.test', '$2y$12$3ulNX4ykhh0OyPbZ3mdn7.x.IONAOz3urCWxTHAF7XYMYLqqqjpeG', 'Admin'),
(6, 'Recepcionista Demo', '6000000002', 'recep1@costaazul.test', '$2y$12$GhC64KbNZnBy4gdQ3J48XOWz7Y/VugLq2hiPbyrzeS2ZD2iNWCZyO', 'Recepcionista'),
(7, 'Camarista Demo', '6000000003', 'camarista@costaazul.test', '$2y$12$OeZm0xuqBAPORVFF3cGQQ.1AeX/ew3wz1fmDMdouUxvqvm0m/XxcO', 'Camarista'),
(8, 'Huesped Demo', '6000000004', 'huesped.a@correo.test', '$2y$12$7FaFiCDpz9ZABtpHIykrdePR3wIbpl88npcmq9VSsOFEgJxyQLtHO', 'Huesped'),
(9, 'Leslie', '4545121278', 'leslie@gmail.com', '$2y$10$Z9SrgDQo3zuQQ8gt77SZn.yLaMC4Zinjc9NAGS9ZOHCUdXR4traaG', 'Huesped');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `habitacion`
--
ALTER TABLE `habitacion`
  ADD PRIMARY KEY (`numero_habitacion`);

--
-- Indices de la tabla `pago`
--
ALTER TABLE `pago`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pago_res` (`id_reservacion`),
  ADD KEY `fk_pago_recep` (`id_recepcionista`);

--
-- Indices de la tabla `politica_cancelacion`
--
ALTER TABLE `politica_cancelacion`
  ADD PRIMARY KEY (`id_politica`);

--
-- Indices de la tabla `reservacion`
--
ALTER TABLE `reservacion`
  ADD PRIMARY KEY (`id_reservacion`),
  ADD UNIQUE KEY `uq_reservacion_confirmacion` (`numero_confirmacion`),
  ADD KEY `fk_res_huesped` (`id_huesped`),
  ADD KEY `fk_res_recep` (`id_recepcionista`),
  ADD KEY `fk_res_hab` (`numero_habitacion`),
  ADD KEY `fk_res_tarifa` (`id_tarifa`),
  ADD KEY `fk_res_politica` (`id_politica`),
  ADD KEY `fk_reservacion_modificado_por` (`modificado_por`);

--
-- Indices de la tabla `tarifa`
--
ALTER TABLE `tarifa`
  ADD PRIMARY KEY (`id_tarifa`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuario_correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `pago`
--
ALTER TABLE `pago`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `politica_cancelacion`
--
ALTER TABLE `politica_cancelacion`
  MODIFY `id_politica` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `reservacion`
--
ALTER TABLE `reservacion`
  MODIFY `id_reservacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tarifa`
--
ALTER TABLE `tarifa`
  MODIFY `id_tarifa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `fk_pago_recep` FOREIGN KEY (`id_recepcionista`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_pago_res` FOREIGN KEY (`id_reservacion`) REFERENCES `reservacion` (`id_reservacion`);

--
-- Filtros para la tabla `reservacion`
--
ALTER TABLE `reservacion`
  ADD CONSTRAINT `fk_res_hab` FOREIGN KEY (`numero_habitacion`) REFERENCES `habitacion` (`numero_habitacion`),
  ADD CONSTRAINT `fk_res_huesped` FOREIGN KEY (`id_huesped`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_res_politica` FOREIGN KEY (`id_politica`) REFERENCES `politica_cancelacion` (`id_politica`),
  ADD CONSTRAINT `fk_res_recep` FOREIGN KEY (`id_recepcionista`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_res_tarifa` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`id_tarifa`),
  ADD CONSTRAINT `fk_reservacion_modificado_por` FOREIGN KEY (`modificado_por`) REFERENCES `usuario` (`id_usuario`);

-- --------------------------------------------------------
-- Correcciones de reglas de negocio (restricciones, índices y datos de prueba)
-- --------------------------------------------------------

-- Pago de ejemplo: anticipo en recepción de la reservación DTWYR (Sencilla, 1 noche = $850; saldo $450)
INSERT INTO `pago` (`id_pago`, `monto`, `fecha_hora`, `tipo_pago`, `canal_pago`, `id_reservacion`, `id_recepcionista`) VALUES
(1, 400.00, '2026-11-12 23:25:00', 'Anticipo', 'Recepción', 2, 3);

-- Valores permitidos (los mismos que escribe el código PHP)
ALTER TABLE `habitacion`
  ADD CONSTRAINT `ck_hab_limpieza` CHECK (`estado_limpieza` IN ('Limpia','Sucia','Mantenimiento')),
  ADD CONSTRAINT `ck_hab_disponible` CHECK (`disponible` IN (0,1));

ALTER TABLE `usuario`
  ADD CONSTRAINT `ck_usuario_rol` CHECK (`rol` IN ('Admin','Recepcionista','Camarista','Huesped'));

ALTER TABLE `tarifa`
  ADD CONSTRAINT `ck_tarifa_precio` CHECK (`precio_noche` > 0),
  ADD CONSTRAINT `ck_tarifa_fechas` CHECK (`fecha_fin` IS NULL OR `fecha_fin` >= `fecha_inicio`);

ALTER TABLE `politica_cancelacion`
  ADD CONSTRAINT `ck_pol_horas` CHECK (`horas_anticipacion` <= 720),
  ADD CONSTRAINT `ck_pol_fechas` CHECK (`fecha_fin` IS NULL OR `fecha_fin` >= `fecha_inicio`);

ALTER TABLE `reservacion`
  ADD CONSTRAINT `ck_res_estado` CHECK (`estado` IN ('Activa','En curso','Finalizada','Cancelada')),
  ADD CONSTRAINT `ck_res_canal` CHECK (`canal` IN ('En línea','Recepción')),
  ADD CONSTRAINT `ck_res_fechas` CHECK (`fecha_salida` > `fecha_entrada`),
  ADD KEY `idx_res_disponibilidad` (`numero_habitacion`,`estado`,`fecha_entrada`,`fecha_salida`),
  ADD KEY `idx_res_estado_entrada` (`estado`,`fecha_entrada`);

-- Libro de pagos: Anticipo/Liquidación/Penalización suman (> 0); Devolución resta (< 0)
ALTER TABLE `pago`
  ADD CONSTRAINT `ck_pago_tipo` CHECK (`tipo_pago` IN ('Anticipo','Liquidación','Penalización','Devolución')),
  ADD CONSTRAINT `ck_pago_canal` CHECK (`canal_pago` IN ('Pasarela en línea','Recepción','Sistema','Retención')),
  ADD CONSTRAINT `ck_pago_signo` CHECK ((`tipo_pago` = 'Devolución' AND `monto` < 0) OR (`tipo_pago` <> 'Devolución' AND `monto` > 0)),
  ADD KEY `idx_pago_fecha` (`fecha_hora`);
-- Una sola tarifa vigente por tipo de habitación y una sola política vigente (lo impone la BD)
ALTER TABLE `tarifa`
  ADD COLUMN `vigente_unico` tinyint(1) AS (IF(`vigente` = 1, 1, NULL)) PERSISTENT,
  ADD UNIQUE INDEX `uq_tarifa_vigente` (`tipo_habitacion`, `vigente_unico`);

ALTER TABLE `politica_cancelacion`
  ADD COLUMN `vigente_unico` tinyint(1) AS (IF(`vigente` = 1, 1, NULL)) PERSISTENT,
  ADD UNIQUE INDEX `uq_politica_vigente` (`vigente_unico`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
