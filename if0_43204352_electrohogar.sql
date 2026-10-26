-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql305.infinityfree.com
-- Tiempo de generación: 26-10-2026 a las 09:31:54
-- Versión del servidor: 11.4.13-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `if0_43204352_electrohogar`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carritos`
--

CREATE TABLE `carritos` (
  `id_carrito` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_sede_retiro` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`) VALUES
(1, 'cocina', 'Electrodomésticos de cocina, heladeras, cocinas, hornos y pequeños electrodomésticos'),
(2, 'lavado', 'Lavarropas, lavasecarropas, secarropas, planchas y aspiradoras'),
(3, 'tecnologia', 'Smart TVs, notebooks, audio, climatización y dispositivos inteligentes');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `dni_cuit` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `dni_cuit`, `nombre`, `apellido`, `email`) VALUES
(1, '38000000', 'jose', 'lopez', 'cliente1@gmail.com'),
(2, '37000000', 'Carlos', 'Gómez', 'cliente2@gmail.com'),
(3, '39000000', 'Homero', 'Tomson', 'cliente3@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ordenes`
--

CREATE TABLE `detalle_ordenes` (
  `id_detalle` int(11) NOT NULL,
  `id_orden` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `detalle_ordenes`
--

INSERT INTO `detalle_ordenes` (`id_detalle`, `id_orden`, `id_producto`, `cantidad`, `precio_unitario`) VALUES
(1, 1, 16, 1, '510000.00'),
(2, 1, 17, 1, '430000.00'),
(3, 1, 18, 1, '890000.00'),
(4, 2, 4, 1, '110000.00'),
(5, 2, 6, 1, '210000.00'),
(6, 3, 3, 1, '65000.00'),
(7, 3, 7, 1, '145000.00'),
(8, 4, 2, 1, '320000.00'),
(9, 5, 23, 1, '189750.00'),
(10, 5, 25, 1, '770500.00'),
(11, 5, 27, 1, '356500.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado`
--

CREATE TABLE `estado` (
  `id_estado` int(11) NOT NULL,
  `nombre_estado` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `estado`
--

INSERT INTO `estado` (`id_estado`, `nombre_estado`) VALUES
(6, 'cancelado'),
(5, 'completado'),
(4, 'en proceso');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `items_carrito`
--

CREATE TABLE `items_carrito` (
  `id_item` int(11) NOT NULL,
  `id_carrito` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes`
--

CREATE TABLE `ordenes` (
  `id_orden` int(11) NOT NULL,
  `codigo_seguimiento` varchar(50) DEFAULT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_sede_origen` int(11) NOT NULL,
  `tipo_entrega` varchar(50) NOT NULL,
  `id_estado` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `ordenes`
--

INSERT INTO `ordenes` (`id_orden`, `codigo_seguimiento`, `id_cliente`, `id_sede_origen`, `tipo_entrega`, `id_estado`) VALUES
(1, 'EH-3F416A21', 1, 1, 'retiro', 5),
(2, 'EH-814DC7', 1, 2, 'Retiro en Sucursal', 4),
(3, 'EH-2C8FC9', 2, 1, 'Retiro en Sucursal', 4),
(4, 'EH-55CB77', 1, 1, 'Retiro en Sucursal', 4),
(5, 'EH-1ABA01', 3, 1, 'Retiro en Sucursal', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `precio_anterior` decimal(10,2) DEFAULT NULL,
  `categoria` varchar(50) NOT NULL,
  `en_oferta` tinyint(1) DEFAULT 0,
  `imagen` varchar(255) NOT NULL,
  `url_pagina` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `descripcion`, `precio`, `precio_anterior`, `categoria`, `en_oferta`, `imagen`, `url_pagina`) VALUES
(1, 'Heladera No Frost 400L', 'Tecnología Inverter, dispenser de agua y estantes de vidrio templado.', '850000.00', NULL, 'cocina', 0, 'img/productos/heladera-400-no-frost.jpg', 'cocina.html'),
(2, 'Cocina a Gas 4 Hornallas', 'Acero inoxidable, encendido electrónico, horno autolimpiante y visor.', '320000.00', '375000.00', 'cocina', 1, 'img/productos/cocina-4-hornallas.jpg', 'cocina.html'),
(3, 'Licuadora Jarra de Vidrio', '1.5 litros de capacidad, 800W de potencia y cuchillas picahielo de acero.', '65000.00', NULL, 'cocina', 0, 'img/productos/licuadora-jarra-vidrio.jpg', 'cocina.html'),
(4, 'Horno Eléctrico 45 Litros', 'Convección forzada, temporizador de 60 minutos, grill y bandeja recolectora.', '110000.00', '135000.00', 'cocina', 1, 'img/productos/horno-electrico-45-litros.jpg', 'cocina.html'),
(5, 'Microondas Digital 28L', 'Con grill integrado, 10 niveles de potencia, descongelado automático y traba para niños.', '185000.00', NULL, 'cocina', 0, 'img/productos/microondas-digital-28l.jpg', 'cocina.html'),
(6, 'Cafetera Expreso Automática', 'Bomba italiana de 15 bares de presión, espumador de leche y filtro doble.', '210000.00', '245000.00', 'cocina', 1, 'img/productos/cafetera-expreso-automatica.jpg', 'cocina.html'),
(7, 'Freidora de Aire Digital 5L', 'Cocción sin aceite por aire caliente, 8 programas preestablecidos y panel táctil LED.', '145000.00', '170000.00', 'cocina', 1, 'img/productos/freidora-de-aire-digital-5l.jpg', 'cocina.html'),
(8, 'Pava Eléctrica Corte Mate', '1.7 litros, acero inoxidable y regulador de temperatura ideal para mate o café.', '45000.00', NULL, 'cocina', 0, 'img/productos/pava-electrica-corte-mate.jpg', 'cocina.html'),
(9, 'Batidora Planetaria 600W', 'Bowl de acero inoxidable de 4.5 litros, 8 velocidades y 3 accesorios metálicos.', '175000.00', NULL, 'cocina', 0, 'img/productos/batidora-planetaria-600w.jpg', 'cocina.html'),
(10, 'Tostadora Eléctrica Doble', 'Ranuras anchas, 7 niveles de tostado, función cancelar, descongelar y calentar.', '42000.00', NULL, 'cocina', 0, 'img/productos/tostadora-electrica-doble.jpg', 'cocina.html'),
(11, 'Lavavajillas 12 Cubiertos', '6 programas de lavado, función media carga, secado extra y bajo consumo energético.', '690000.00', NULL, 'cocina', 0, 'img/productos/lavavajillas-12-cubiertos.jpg', 'cocina.html'),
(12, 'Anafe Eléctrico Vitrocerámico', '4 zonas de cocción independientes, control táctil y bloqueo de seguridad térmico.', '280000.00', NULL, 'cocina', 0, 'img/productos/anafe-electrico-vitroceramico.jpg', 'cocina.html'),
(13, 'Campana Extractora Acero', '60 cm de ancho, motor de 3 velocidades, filtro metálico lavable y luz LED.', '160000.00', NULL, 'cocina', 0, 'img/productos/campana-extractora-acero.jpg', 'cocina.html'),
(14, 'Multiprocesadora 5 en 1', 'Incluye vaso licuador, discos ralladores, amasadora, exprimidor y picadora de 750W.', '95000.00', NULL, 'cocina', 0, 'img/productos/multiprocesadora.jpg', 'cocina.html'),
(15, 'Freezer Horizontal 250L', 'Función dual enfriador/congelador, cerradura con llave y ruedas de transporte.', '540000.00', '610000.00', 'cocina', 1, 'img/productos/freezer-horizontal-250l.jpg', 'cocina.html'),
(16, 'Lavarropas Automático 8kg', 'Carga frontal, 1200 RPM, tecnología Inverter y 15 programas de lavado rápido.', '510000.00', '620000.00', 'lavado', 1, 'img/productos/lavarropas-automatico-8kg.jpg', 'lavado.html'),
(17, 'Lavarropas Carga Superior 7kg', 'Sistema oriental, tambor de acero inoxidable, filtro atrapa pelusas y 8 programas.', '430000.00', NULL, 'lavado', 0, 'img/productos/lavarropas-carga-superior-7kg.jpg', 'lavado.html'),
(18, 'Lavasecarropas Digital 10kg', 'Lavado de 10kg y secado de 6kg, motor silencioso Direct Drive y lavado a vapor.', '890000.00', '980000.00', 'lavado', 1, 'img/productos/lavasecarropas-digital-10kg.jpg', 'lavado.html'),
(19, 'Secarropas por Calor 8kg', 'Secado por condensación, sensor de humedad automático y programa antiarrugas.', '480000.00', NULL, 'lavado', 0, 'img/productos/secarropas-por-calor-8kg.jpg', 'lavado.html'),
(20, 'Secarropas Centrífugo 5.5kg', '2800 RPM, tambor de acero inoxidable, salida de agua inferior y ultra compacto.', '135000.00', '160000.00', 'lavado', 1, 'img/productos/secarropas-centrifugo.jpg', 'lavado.html'),
(21, 'Lavarropas Semiautomático 6kg', 'Bajo consumo de agua y energía, 5 programas y tapa superior transparente.', '190000.00', NULL, 'lavado', 0, 'img/productos/lavarropas-semiautomatico-6kg.jpg', 'lavado.html'),
(22, 'Plancha a Vapor Cerámica', 'Base antiadherente, golpe de vapor vertical, sistema antigoteo y autolimpieza.', '38000.00', NULL, 'lavado', 0, 'img/productos/plancha-vapor.jpg', 'lavado.html'),
(23, 'Centro de Planchado a Vapor', 'Caldera de alta presión continua, depósito de 1.5L y planchado profesional rápido.', '165000.00', NULL, 'lavado', 0, 'img/productos/centro-planchado.jpg', 'lavado.html'),
(24, 'Plancha Vertical Portátil', 'Ideal para prendas delicadas y cortinas, calentamiento en 30 segundos y cepillo.', '52000.00', NULL, 'lavado', 0, 'img/productos/plancha-vertical-portatil.jpg', 'lavado.html'),
(25, 'Lavarropas Inteligente WiFi 9kg', 'Control desde el celular, dosificador automático de jabón y eficiencia energética A+++.', '670000.00', '750000.00', 'lavado', 1, 'img/productos/lavarropas-inteligente-wifi-9kg.jpg', 'lavado.html'),
(26, 'Secarropas Colgante a Calor', 'Flujo de aire caliente constante, capacidad para 6kg de ropa colgada y temporizador.', '155000.00', NULL, 'lavado', 0, 'img/productos/secarropas-colgante.jpg', 'lavado.html'),
(27, 'Aspiradora Robot Inteligente', 'Barre, aspira y trapea, mapeo láser, retorno automático a la base y control por App.', '310000.00', '360000.00', 'lavado', 1, 'img/productos/aspiradora-robot-inteligente.jpg', 'lavado.html'),
(28, 'Aspiradora Trineo sin Bolsa', '2000W de potencia, filtro HEPA antialérgico, depósito lavable de 2L y tubo metálico.', '125000.00', NULL, 'lavado', 0, 'img/productos/aspiradora-trineo.jpg', 'lavado.html'),
(29, 'Aspiradora Inalámbrica Vertical', 'Batería de litio de 45 min, cepillo motorizado con luz LED y convertible en aspiradora de mano.', '195000.00', NULL, 'lavado', 0, 'img/productos/aspiradora-inalambrica-vertical.jpg', 'lavado.html'),
(30, 'Hidrolavadora Alta Presión', '1400W, 110 bares de presión, corte automático, manguera de 5m y dosificador de detergente.', '140000.00', NULL, 'lavado', 0, 'img/productos/hidrolavadora-alta-presion.jpg', 'lavado.html'),
(31, 'Smart TV 50 4K UHD', 'Resolución Ultra HD, HDR10, control por voz e incluye Netflix, YouTube y Disney+.', '380000.00', '450000.00', 'tecnologia', 1, 'img/productos/smart-tv-50-4k-uhd.jpg', 'tecnologia.html'),
(32, 'Smart TV 65 OLED', 'Colores negros puros, tasa de refresco de 120Hz ideal para gaming y sonido Dolby Atmos.', '1150000.00', NULL, 'tecnologia', 0, 'img/productos/smart-tv-65-oled.jpg', 'tecnologia.html'),
(33, 'Smart TV 32 HD Android', 'Sistema Android TV integrado, Chromecast built-in, Bluetooth y bordes ultradelgados.', '210000.00', NULL, 'tecnologia', 0, 'img/productos/smart-tv-32-hd-android.jpg', 'tecnologia.html'),
(34, 'Barra de Sonido Bluetooth', '2.1 canales con subwoofer inalámbrico, 300W de potencia, entrada óptica y HDMI ARC.', '190000.00', '230000.00', 'tecnologia', 1, 'img/productos/barra-de-sonido-bluetooth.jpg', 'tecnologia.html'),
(35, 'Aire Acondicionado Inverter', 'Split frío/calor de 3200 frigorías, bajo consumo energético y gas ecológico R410A.', '650000.00', '780000.00', 'tecnologia', 1, 'img/productos/aire-acondicionado-inverter.jpg', 'tecnologia.html'),
(36, 'Notebook Core i5 15.6', 'Procesador Intel Core i5, 16GB RAM, disco sólido SSD de 512GB y pantalla Full HD.', '790000.00', '890000.00', 'tecnologia', 1, 'img/productos/notebook-core-i5.jpg', 'tecnologia.html'),
(37, 'Auriculares Inalámbricos ANC', 'Cancelación activa de ruido, 30 horas de batería, carga rápida USB-C y micrófono HD.', '85000.00', NULL, 'tecnologia', 0, 'img/productos/auriculares-inalambricos-anc.jpg', 'tecnologia.html'),
(38, 'Parlante Portatil Bluetooth', 'Torre de sonido con luces LED rítmicas, batería recargable, entrada de micrófono y 400W.', '145000.00', NULL, 'tecnologia', 0, 'img/productos/parlante-portatil-bluetooth.jpg', 'tecnologia.html'),
(39, 'Consola de Videojuegos 1TB', 'Gráficos en 4K a 120 fps, almacenamiento SSD ultrarrápido e incluye un joystick inalámbrico.', '920000.00', NULL, 'tecnologia', 0, 'img/productos/consola-de-videojuegos-1tb.jpg', 'tecnologia.html'),
(40, 'Tablet 10.5 128GB WiFi', 'Pantalla WUXGA, 4GB de RAM, cuatro parlantes estéreo, cuerpo metálico y batería de 7040mAh.', '290000.00', '330000.00', 'tecnologia', 1, 'img/productos/tablet-128gb-wifi.jpg', 'tecnologia.html'),
(41, 'Monitor Gamer 27 144Hz', 'Panel IPS HD, tiempo de respuesta de 1ms, tecnología FreeSync y ajuste de altura.', '310000.00', NULL, 'tecnologia', 0, 'img/productos/monitor-gamer-27-144hz.jpg', 'tecnologia.html'),
(42, 'Proyector Portatil Smart', 'Imagen de hasta 120 pulgadas Full HD, corrección trapezoidal automática y parlante integrado.', '260000.00', NULL, 'tecnologia', 0, 'img/productos/proyector-portatil-smart.jpg', 'tecnologia.html'),
(43, 'Cámara de Seguridad WiFi', 'Visión nocturna infrarroja, rotación de 360 grados, audio bidireccional y detección de movimiento.', '48000.00', NULL, 'tecnologia', 0, 'img/productos/camara-seguridad-wifi.jpg', 'tecnologia.html'),
(44, 'Smartwatch Deportivo GPS', 'Pantalla AMOLED de 1.4, resistente al agua, medidor de ritmo cardíaco y oxígeno en sangre.', '92000.00', NULL, 'tecnologia', 0, 'img/productos/smartwatch-deportivo-gps.jpg', 'tecnologia.html'),
(45, 'Router WiFi 6 Doble Banda', 'Alta velocidad de hasta 1800 Mbps, 4 antenas de alta ganancia y cobertura para todo el hogar.', '75000.00', NULL, 'tecnologia', 0, 'img/productos/router-wifi-6-doble-banda.jpg', 'tecnologia.html');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

CREATE TABLE `sedes` (
  `id_sede` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `capacidad_max_unidades` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id_sede`, `nombre`, `tipo`, `capacidad_max_unidades`) VALUES
(1, 'Sucursal Central', 'Principal', 45),
(2, 'Sucursal Norte', 'Secundaria', 45),
(3, 'Sucursal Sur', 'Secundaria', 45);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_sede`
--

CREATE TABLE `stock_sede` (
  `id_sede` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `stock_minimo_alerta` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `stock_sede`
--

INSERT INTO `stock_sede` (`id_sede`, `id_producto`, `cantidad`, `stock_minimo_alerta`) VALUES
(1, 1, 12, 5),
(1, 2, 11, 5),
(1, 3, 12, 5),
(1, 4, 12, 5),
(1, 5, 12, 5),
(1, 6, 12, 5),
(1, 7, 12, 5),
(1, 8, 12, 5),
(1, 9, 12, 5),
(1, 10, 12, 5),
(1, 11, 12, 5),
(1, 12, 12, 5),
(1, 13, 12, 5),
(1, 14, 12, 5),
(1, 15, 12, 5),
(1, 16, 12, 5),
(1, 17, 12, 5),
(1, 18, 12, 5),
(1, 19, 12, 5),
(1, 20, 12, 5),
(1, 21, 12, 5),
(1, 22, 12, 5),
(1, 23, 11, 5),
(1, 24, 12, 5),
(1, 25, 11, 5),
(1, 26, 12, 5),
(1, 27, 11, 5),
(1, 28, 12, 5),
(1, 29, 12, 5),
(1, 30, 12, 5),
(1, 31, 12, 5),
(1, 32, 12, 5),
(1, 33, 12, 5),
(1, 34, 12, 5),
(1, 35, 12, 5),
(1, 36, 12, 5),
(1, 37, 12, 5),
(1, 38, 12, 5),
(1, 39, 12, 5),
(1, 40, 12, 5),
(1, 41, 12, 5),
(1, 42, 12, 5),
(1, 43, 12, 5),
(1, 44, 12, 5),
(1, 45, 12, 5),
(2, 1, 10, 5),
(2, 2, 10, 5),
(2, 3, 10, 5),
(2, 4, 10, 5),
(2, 5, 10, 5),
(2, 6, 10, 5),
(2, 7, 10, 5),
(2, 8, 10, 5),
(2, 9, 10, 5),
(2, 10, 10, 5),
(2, 11, 10, 5),
(2, 12, 10, 5),
(2, 13, 10, 5),
(2, 14, 10, 5),
(2, 15, 10, 5),
(2, 16, 10, 5),
(2, 17, 10, 5),
(2, 18, 10, 5),
(2, 19, 10, 5),
(2, 20, 10, 5),
(2, 21, 10, 5),
(2, 22, 10, 5),
(2, 23, 10, 5),
(2, 24, 10, 5),
(2, 25, 10, 5),
(2, 26, 10, 5),
(2, 27, 10, 5),
(2, 28, 10, 5),
(2, 29, 10, 5),
(2, 30, 10, 5),
(2, 31, 10, 5),
(2, 32, 10, 5),
(2, 33, 10, 5),
(2, 34, 10, 5),
(2, 35, 10, 5),
(2, 36, 10, 5),
(2, 37, 10, 5),
(2, 38, 10, 5),
(2, 39, 10, 5),
(2, 40, 10, 5),
(2, 41, 10, 5),
(2, 42, 10, 5),
(2, 43, 10, 5),
(2, 44, 10, 5),
(2, 45, 10, 5),
(3, 1, 8, 5),
(3, 2, 8, 5),
(3, 3, 8, 5),
(3, 4, 8, 5),
(3, 5, 8, 5),
(3, 6, 8, 5),
(3, 7, 8, 5),
(3, 8, 8, 5),
(3, 9, 8, 5),
(3, 10, 8, 5),
(3, 11, 8, 5),
(3, 12, 8, 5),
(3, 13, 8, 5),
(3, 14, 8, 5),
(3, 15, 8, 5),
(3, 16, 8, 5),
(3, 17, 8, 5),
(3, 18, 8, 5),
(3, 19, 8, 5),
(3, 20, 8, 5),
(3, 21, 8, 5),
(3, 22, 8, 5),
(3, 23, 8, 5),
(3, 24, 8, 5),
(3, 25, 8, 5),
(3, 26, 8, 5),
(3, 27, 8, 5),
(3, 28, 8, 5),
(3, 29, 8, 5),
(3, 30, 8, 5),
(3, 31, 8, 5),
(3, 32, 8, 5),
(3, 33, 8, 5),
(3, 34, 8, 5),
(3, 35, 8, 5),
(3, 36, 8, 5),
(3, 37, 8, 5),
(3, 38, 8, 5),
(3, 39, 8, 5),
(3, 40, 8, 5),
(3, 41, 8, 5),
(3, 42, 8, 5),
(3, 43, 8, 5),
(3, 44, 8, 5),
(3, 45, 8, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('cliente','admin') DEFAULT 'cliente',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `carrito` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `dni`, `email`, `password`, `rol`, `fecha_registro`, `carrito`) VALUES
(3, 'Administrador', '00000000', 'admin@electrohogar.com', '$2y$12$DmUIFUiO/j5bqLTQ33MJkuJEuip3WObvQRH1A0O/Foi7EI6AJzKBG', 'admin', '2026-10-22 07:18:46', NULL),
(4, 'jose lopez', '38000000', 'cliente1@gmail.com', '$2y$12$9Iryo7WLjp6H0LJDfEcomede3MTKIms6vQEFX1boxUGaniRXYX1ZG', 'cliente', '2026-10-23 17:05:11', '[]'),
(5, 'Carlos Gómez', '37000000', 'cliente2@gmail.com', '$2y$12$q.Ps/1Nn.L81jEKobMXJ3.kqHco64HR1TjTbZCdv9UztHdiWkwNFC', 'cliente', '2026-10-25 07:43:24', '[]'),
(6, 'Homero Tomson', '39000000', 'cliente3@gmail.com', '$2y$12$Rj.Lfx/sPkDjebFYJcyYUuECMUCYhbAwfg6NNBOYnosulOzCbOPvS', 'cliente', '2026-10-26 06:28:01', '[]');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `carritos`
--
ALTER TABLE `carritos`
  ADD PRIMARY KEY (`id_carrito`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_sede_retiro` (`id_sede_retiro`),
  ADD KEY `id_estado` (`id_estado`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `dni_cuit` (`dni_cuit`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `detalle_ordenes`
--
ALTER TABLE `detalle_ordenes`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_orden` (`id_orden`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`id_estado`);

--
-- Indices de la tabla `items_carrito`
--
ALTER TABLE `items_carrito`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_carrito` (`id_carrito`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `ordenes`
--
ALTER TABLE `ordenes`
  ADD PRIMARY KEY (`id_orden`),
  ADD UNIQUE KEY `codigo_seguimiento` (`codigo_seguimiento`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_sede_origen` (`id_sede_origen`),
  ADD KEY `id_estado` (`id_estado`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD PRIMARY KEY (`id_sede`);

--
-- Indices de la tabla `stock_sede`
--
ALTER TABLE `stock_sede`
  ADD PRIMARY KEY (`id_sede`,`id_producto`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `carritos`
--
ALTER TABLE `carritos`
  MODIFY `id_carrito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_ordenes`
--
ALTER TABLE `detalle_ordenes`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `estado`
--
ALTER TABLE `estado`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `items_carrito`
--
ALTER TABLE `items_carrito`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ordenes`
--
ALTER TABLE `ordenes`
  MODIFY `id_orden` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `sedes`
--
ALTER TABLE `sedes`
  MODIFY `id_sede` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
