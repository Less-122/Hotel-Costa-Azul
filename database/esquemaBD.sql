-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 09-11-2026 a las 03:27:15
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitacion`
--

CREATE TABLE `habitacion` (
  `numero_habitacion` varchar(5) NOT NULL,
  `tipo_habitacion` varchar(30) NOT NULL,
  `estado_limpieza` varchar(20) NOT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1
) ;

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
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `politica_cancelacion`
--

CREATE TABLE `politica_cancelacion` (
  `id_politica` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `politica_cancelacion`
--

INSERT INTO `politica_cancelacion` (`id_politica`, `descripcion`, `vigente`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Cancelacion gratuita hasta 48 horas antes de la fecha de entrada.', 0, '2026-01-01', '2026-09-30'),
(2, 'Cancelacion gratuita hasta 72 horas antes; despues se cobra la primera noche.', 1, '2026-10-01', NULL);

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
  `id_politica` int(11) NOT NULL
) ;

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
(3, 'Doble', 1200.00, 1, '2026-01-01', NULL);

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
) ;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `contacto`, `correo`, `password`, `rol`) VALUES
(2, 'Edgar Olivan Gonzales', '7894561235', 'admin@gmail.com', '$2y$10$UXkkj.fnZyC4NkH1XOrc9.lBRVBsW3LBa1mtQvPR0h1Qs3H7FRNOG', 'Admin'),
(3, 'Ismerai Martinez Dominguez', '1234567895', 'recepcionista1@gmail.com', '$2y$10$z.gaNSB3IwTSQTv28KftDO1ww6Ipc5X01U1752J6poii3cPDSy7UC', 'Recepcionista'),
(4, 'Martina Moran Medina', '1234561234', 'camarista@gmail.com', '$2y$10$1GcznKp94OZovkkNx4/4Rew1sv016beANtMnDa1NBsuWszPUxhPxK', 'Camarista');

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
  ADD KEY `fk_res_politica` (`id_politica`);

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
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `politica_cancelacion`
--
ALTER TABLE `politica_cancelacion`
  MODIFY `id_politica` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `reservacion`
--
ALTER TABLE `reservacion`
  MODIFY `id_reservacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tarifa`
--
ALTER TABLE `tarifa`
  MODIFY `id_tarifa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `fk_res_tarifa` FOREIGN KEY (`id_tarifa`) REFERENCES `tarifa` (`id_tarifa`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
