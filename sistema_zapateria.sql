-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-11-2026 a las 06:07:34
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
-- Base de datos: `sistema_zapateria`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo`
--

CREATE TABLE `articulo` (
  `idArticulo` int(11) NOT NULL,
  `idItem` int(11) DEFAULT NULL,
  `idTicket` int(11) DEFAULT NULL,
  `cant` decimal(5,0) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `articulo`
--

INSERT INTO `articulo` (`idArticulo`, `idItem`, `idTicket`, `cant`, `total`) VALUES
(4, 70, 4, 1, 2499.00),
(5, 68, 4, 1, 2699.00),
(6, 66, 4, 4, 9396.00),
(7, 64, 5, 2, 4398.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `item`
--

CREATE TABLE `item` (
  `idItem` int(11) NOT NULL,
  `idZapato` int(11) DEFAULT NULL,
  `talla` decimal(4,2) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `stock` decimal(5,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `item`
--

INSERT INTO `item` (`idItem`, `idZapato`, `talla`, `precio`, `stock`) VALUES
(37, 25, 26.00, 2799.00, 0),
(38, 25, 27.00, 2899.00, 0),
(39, 25, 28.50, 2999.00, 1),
(40, 26, 25.50, 3499.00, 8),
(41, 26, 26.50, 3599.00, 12),
(42, 26, 27.50, 3649.00, 9),
(43, 27, 25.00, 1499.00, 20),
(44, 27, 26.00, 1549.00, 18),
(45, 27, 27.00, 1599.00, 14),
(46, 28, 24.50, 1399.00, 25),
(47, 28, 26.00, 1449.00, 19),
(48, 28, 27.50, 1499.00, 11),
(49, 29, 26.50, 4299.00, 7),
(50, 29, 27.50, 4399.00, 10),
(51, 29, 28.00, 4499.00, 5),
(52, 30, 25.00, 3899.00, 6),
(53, 30, 26.00, 3999.00, 9),
(54, 30, 27.00, 4099.00, 4),
(55, 31, 26.00, 1299.00, 16),
(56, 31, 27.00, 1349.00, 22),
(57, 31, 28.00, 1399.00, 13),
(58, 32, 26.50, 2199.00, 8),
(59, 32, 27.00, 2249.00, 11),
(60, 32, 28.50, 2299.00, 7),
(61, 33, 25.50, 2399.00, 14),
(62, 33, 26.50, 2449.00, 17),
(63, 33, 27.50, 2499.00, 9),
(64, 34, 26.00, 2199.00, 10),
(65, 34, 27.00, 2299.00, 15),
(66, 34, 28.00, 2349.00, 4),
(67, 35, 26.00, 2599.00, 10),
(68, 35, 27.00, 2699.00, 12),
(69, 35, 27.50, 2749.00, 6),
(70, 36, 25.00, 2499.00, 13),
(71, 36, 26.00, 2549.00, 16),
(72, 36, 27.00, 2599.00, 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marca`
--

CREATE TABLE `marca` (
  `idMarca` decimal(7,0) NOT NULL,
  `nombre` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `marca`
--

INSERT INTO `marca` (`idMarca`, `nombre`) VALUES
(1000001, 'Nike'),
(1000002, 'Adidas'),
(1000003, 'Vans'),
(1000004, 'Converse'),
(1000005, 'Timberland'),
(1000006, 'Dr. Martens'),
(1000007, 'Flexi'),
(1000008, 'Clarks'),
(1000009, 'Puma'),
(1000010, 'New Balance'),
(1000011, 'Lacoste'),
(1000012, 'Birkenstock');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket`
--

CREATE TABLE `ticket` (
  `idTicket` int(11) NOT NULL,
  `idUsuario` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `pagado` decimal(1,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket`
--

INSERT INTO `ticket` (`idTicket`, `idUsuario`, `total`, `pagado`) VALUES
(4, 1, 14594.00, 1),
(5, 1, 4398.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipozapato`
--

CREATE TABLE `tipozapato` (
  `idTipoZapato` int(11) NOT NULL,
  `nombre` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipozapato`
--

INSERT INTO `tipozapato` (`idTipoZapato`, `nombre`) VALUES
(1, 'Tenis Deportivos'),
(2, 'Casuales'),
(3, 'Botas'),
(4, 'Formales'),
(5, 'Sandalias');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `idUsuario` int(11) NOT NULL,
  `nombre` varchar(80) DEFAULT NULL,
  `apellido_p` varchar(80) DEFAULT NULL,
  `apellido_m` varchar(80) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` decimal(12,0) DEFAULT NULL,
  `rol` decimal(1,0) DEFAULT NULL,
  `pass` varchar(100) DEFAULT NULL,
  `activo` decimal(1,0) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`idUsuario`, `nombre`, `apellido_p`, `apellido_m`, `correo`, `telefono`, `rol`, `pass`, `activo`) VALUES
(1, 'Hola', 'Mundo', 'Mundo', 'hola@hola.com', 12345, 0, '$2y$10$KmGcclK7OVgFhUB4a1vbBu2Jr7FhFwtLFbAKcG/9v7yjouYLA8aby', 1),
(5, 'Administrador', 'RIMO', 'Admin', 'admin@admin.com', 12345, 1, '$2y$10$4gqYv.ri6eIWm6UftVwYhuN3MitB.1WnN0GFFaaBJ.IU6QavItAHG', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `zapato`
--

CREATE TABLE `zapato` (
  `idZapato` int(11) NOT NULL,
  `idTipoZapato` int(11) DEFAULT NULL,
  `idMarca` decimal(7,0) DEFAULT NULL,
  `nombre` varchar(60) DEFAULT NULL,
  `img` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `zapato`
--

INSERT INTO `zapato` (`idZapato`, `idTipoZapato`, `idMarca`, `nombre`, `img`) VALUES
(25, 1, 1000001, 'Air Max 90', 'https://static.nike.com/a/images/t_default/u_9ddf04c7-2a9a-4d76-add1-d15af8f0263d,c_scale,fl_relative,w_1.0,h_1.0,fl_layer_apply/4f539405-4d30-4ed1-9f8e-1d668b7c2f47/NIKE+AIR+MAX+90+PRM+TREBLE.png'),
(26, 1, 1000002, 'Ultraboost Light', 'https://site-product.innovasport.com/img-test/IH3110/IH3110_1-1200x1200.jpg'),
(27, 2, 1000003, 'Old Skool Clásicos', 'https://http2.mlstatic.com/D_NQ_NP_722186-MLM48188335519_112021-O-tenis-vans-oldskool-classics-blackwhite-hy28-juvenil-gamuza.webp'),
(28, 2, 1000004, 'Chuck Taylor All Star', 'https://http2.mlstatic.com/D_Q_NP_2X_629649-MLM73799188935_012024-P.webp'),
(29, 3, 1000005, '6-Inch Premium Waterproof', 'https://assets.timberland.com/images/t_img/f_auto,h_1300,e_sharpen:60,w_1300/dpr_2.0/v1741199070/TB110061713-HERO/Mens-Timberland-Premium-6Inch-Waterproof-Boot-TBL-Wheat-Nubuck-HERO.png'),
(30, 3, 1000006, '1460 Smooth Leather', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSmHbkLUHVjMWYQAr_emLGORY7glZ_8vb6e4OhEaoXz_Q&s'),
(31, 4, 1000007, 'Oxford Piel Negro', 'https://http2.mlstatic.com/D_NQ_NP_805708-MLM83156744260_032025-O.webp'),
(32, 4, 1000008, 'Tilden Cap', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTxk8ljMQB493FZoNMAMQHZb0XW5dSIRt8wi_gjLkNBTjuH5RJ2iqou8w4&s=10'),
(33, 1, 1000009, 'RS-X Efekt', 'https://dpjye2wk9gi5z.cloudfront.net/wcsstore/ExtendedSitesCatalogAssetStore/images/catalog/zoom/1029059-9902V1.jpg'),
(34, 1, 1000010, '574 Core', 'https://nb.scene7.com/is/image/NB/ml574evn_nb_02_i?wid=880&hei=880&bgc=f1f1f1&layer=1&bgcolor=f1f1f1&blendMode=mult'),
(35, 2, 1000011, 'Carnaby Evo', 'https://dpjye2wk9gi5z.cloudfront.net/wcsstore/ExtendedSitesCatalogAssetStore/images/catalog/zoom/1039610-0111V1.jpg'),
(36, 5, 1000012, 'Arizona Birko-Flor', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSEHKGbHrjSloO4rmmGxCTBkzTfhGgj1KCdRDPxT5K-Ug&s');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD PRIMARY KEY (`idArticulo`),
  ADD KEY `Rel_06` (`idItem`),
  ADD KEY `Rel_04` (`idTicket`);

--
-- Indices de la tabla `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`idItem`),
  ADD KEY `Rel_02` (`idZapato`);

--
-- Indices de la tabla `marca`
--
ALTER TABLE `marca`
  ADD PRIMARY KEY (`idMarca`);

--
-- Indices de la tabla `ticket`
--
ALTER TABLE `ticket`
  ADD PRIMARY KEY (`idTicket`),
  ADD KEY `Rel_05` (`idUsuario`);

--
-- Indices de la tabla `tipozapato`
--
ALTER TABLE `tipozapato`
  ADD PRIMARY KEY (`idTipoZapato`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`idUsuario`);

--
-- Indices de la tabla `zapato`
--
ALTER TABLE `zapato`
  ADD PRIMARY KEY (`idZapato`),
  ADD KEY `Rel_01` (`idTipoZapato`),
  ADD KEY `Rel_07` (`idMarca`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `articulo`
--
ALTER TABLE `articulo`
  MODIFY `idArticulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `item`
--
ALTER TABLE `item`
  MODIFY `idItem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de la tabla `ticket`
--
ALTER TABLE `ticket`
  MODIFY `idTicket` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tipozapato`
--
ALTER TABLE `tipozapato`
  MODIFY `idTipoZapato` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `idUsuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `zapato`
--
ALTER TABLE `zapato`
  MODIFY `idZapato` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD CONSTRAINT `Rel_04` FOREIGN KEY (`idTicket`) REFERENCES `ticket` (`idTicket`) ON UPDATE CASCADE,
  ADD CONSTRAINT `Rel_06` FOREIGN KEY (`idItem`) REFERENCES `item` (`idItem`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `Rel_02` FOREIGN KEY (`idZapato`) REFERENCES `zapato` (`idZapato`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `ticket`
--
ALTER TABLE `ticket`
  ADD CONSTRAINT `Rel_05` FOREIGN KEY (`idUsuario`) REFERENCES `usuario` (`idUsuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `zapato`
--
ALTER TABLE `zapato`
  ADD CONSTRAINT `Rel_01` FOREIGN KEY (`idTipoZapato`) REFERENCES `tipozapato` (`idTipoZapato`) ON UPDATE CASCADE,
  ADD CONSTRAINT `Rel_07` FOREIGN KEY (`idMarca`) REFERENCES `marca` (`idMarca`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
