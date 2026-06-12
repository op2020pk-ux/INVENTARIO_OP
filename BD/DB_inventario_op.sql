-- ======================================================================
-- PROYECTO: Inventario_OP
-- AUTOR: Omar Pinto
-- DESCRIPCIÓN: Base de datos relacional completa para el control de inventario,
--              compras, ventas, devoluciones y gestión de usuarios por roles.
-- ======================================================================

CREATE DATABASE IF NOT EXISTS `DB_inventario_op` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `DB_inventario_op`;

-- ----------------------------------------------------------------------
-- 1. TABLA: CONFIGURACIÓN (Para almacenar la tasa del dólar y datos del negocio)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `clave` VARCHAR(50) NOT NULL UNIQUE,
  `valor` VARCHAR(255) NOT NULL,
  `ultima_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 2. TABLA: USUARIOS (Soporta el Panel Administrativo y Panel Staff)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `rol` ENUM('Administrador', 'Staff') NOT NULL DEFAULT 'Staff',
  `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 3. TABLA: CATEGORIAS
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `descripcion` TEXT DEFAULT NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 4. TABLA: PROVEEDORES
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rif` VARCHAR(30) NOT NULL UNIQUE,
  `nombre` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) DEFAULT NULL,
  `direccion` TEXT DEFAULT NULL,
  `estado` ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo', -- Campo agregado para solucionar el error en proveedores.php
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 5. TABLA: PRODUCTOS (Une Categorías y Proveedores)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_barras` VARCHAR(100) UNIQUE DEFAULT NULL, -- Intacto para tus módulos activos
  `codigo` VARCHAR(100) UNIQUE DEFAULT NULL,        -- Campo agregado para solucionar el error en productos.php
  `nombre` VARCHAR(150) NOT NULL,
  `id_categoria` INT NOT NULL,
  `id_proveedor` INT DEFAULT NULL,
  `precio_costo_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_venta_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_actual` INT NOT NULL DEFAULT 0,
  `stock_minimo` INT NOT NULL DEFAULT 5, -- Nivel de alerta para módulo "Stocks"
  `imagen` VARCHAR(255) DEFAULT 'defecto.png',
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_categoria`) REFERENCES `categorias`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 6. TABLA: COMPRAS (Encabezado de las órdenes de compra hechas a proveedores)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_proveedor` INT NOT NULL,
  `id_usuario` INT NOT NULL, -- Quién procesó la compra
  `fecha_compra` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `total_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado_orden` ENUM('Pendiente', 'Recibido', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
  FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 7. TABLA: DETALLE_COMPRAS (Artículos individuales dentro de cada compra)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detalle_compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_compra` INT NOT NULL,
  `id_producto` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_compra_usd` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`id_compra`) REFERENCES `compras`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 8. TABLA: VENTAS (Encabezado del proceso de facturación rápida)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ventas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL, -- Empleado o admin que vendió
  `fecha_venta` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `total_usd` DECIMAL(10,2) NOT NULL,
  `tasa_operacion` DECIMAL(10,2) NOT NULL, -- Tasa del día grabada al momento de vender
  `forma_pago` ENUM('Efectivo USD', 'Efectivo Bs', 'Pago Móvil', 'Mixto') NOT NULL DEFAULT 'Efectivo USD',
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 9. TABLA: DETALLE_VENTAS (Productos vendidos en cada transacción)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detalle_ventas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_venta` INT NOT NULL,
  `id_producto` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_venta_usd` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`id_venta`) REFERENCES `ventas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 10. TABLA: DEVOLUCIONES (Historial de mercancía devuelta)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo_devolucion` ENUM('Cliente', 'Proveedor') NOT NULL, 
  `id_producto` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `motivo` TEXT NOT NULL,
  `fecha_devolucion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `id_usuario` INT NOT NULL,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- 11. TABLA: HISTORIAL_STOCK (Para auditorías de inventario/Kardex)
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `historial_stock` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_producto` INT NOT NULL,
  `tipo_movimiento` ENUM('Entrada por Compra', 'Entrada por Devolución', 'Salida por Venta', 'Salida por Devolución', 'Ajuste Manual') NOT NULL,
  `cantidad` INT NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `fecha_movimiento` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ======================================================================
-- INSERCIÓN DE DATOS INICIALES Y OPTIMIZADOS (Evita Duplicados)
-- ======================================================================

-- 1. Insertar Categoría Base o de Respaldo Obligatoria (ID 1)
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`) VALUES 
(1, 'Sin Categoría / Temporal', 'Categoría de respaldo para productos cuya categoría original fue eliminada')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);

-- 2. Categorías de muestra comerciales
INSERT INTO `categorias` (`nombre`, `descripcion`) VALUES 
('Snacks y Golosinas', 'Papas, galletas, chocolates y dulces en general'),
('Bebidas', 'Refrescos, jugos, malteadas y agua mineral'),
('Víveres', 'Productos alimenticios empaquetados generales')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);

-- 3. Configuración del Negocio y Control de la Tasa de Cambio
INSERT INTO `configuracion` (`clave`, `valor`) VALUES 
('tasa_dolar', '45.00'),
('nombre_quiosco', 'Quiosco Control OP')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- 4. Usuarios del Sistema (Administrador y Staff con contraseñas en texto plano para desarrollo)
INSERT INTO `usuarios` (`usuario`, `password`, `nombre`, `rol`, `estado`) VALUES 
('admin', 'admin123', 'Omar Pinto', 'Administrador', 'Activo'),
('empleado', 'user123', 'Asistente Quiosco', 'Staff', 'Activo')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `rol` = VALUES(`rol`);


-- ======================================================================
-- MECANISMO DE SEGURIDAD: TRIGGER (DISPARADOR) PARA ELIMINACIÓN DE CATEGORÍAS
-- ======================================================================
-- Evita errores estrictos. Si el Admin borra una categoría, reubica la mercancía en la ID 1.

DELIMITER $$

CREATE TRIGGER `tg_seguridad_eliminar_categoria` 
BEFORE DELETE ON `categorias`
FOR EACH ROW
BEGIN
    -- Si el administrador intenta borrar la categoría de respaldo (ID 1), bloqueamos la acción por completo
    IF OLD.id = 1 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error Crítico: No se puede eliminar la categoría de respaldo del sistema.';
    ELSE
        -- Movemos de forma segura todos los productos a la categoría 1 antes de remover la original
        UPDATE `productos` 
        SET `id_categoria` = 1 
        WHERE `id_categoria` = OLD.id;
    END IF;
END$$

DELIMITER ;