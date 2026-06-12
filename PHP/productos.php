<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Control de rol: Solo el Administrador puede gestionar el catálogo de productos
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Administrador') {
    header("Location: login.php");
    exit();
}

// Incluir la conexión
include '../CONEXION/conexiones.php';

// Obtener la tasa del dólar actual
$tasa_dolar = "1.00";
try {
    $stmt_tasa = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar' LIMIT 1");
    $stmt_tasa->execute();
    $reg_tasa = $stmt_tasa->fetch();
    if ($reg_tasa) {
        $tasa_dolar = $reg_tasa['valor'];
    }
} catch (PDOException $e) {
    // Respaldo
}

$mensaje = "";
$tipo_mensaje = "";

// 1. PROCESAR EL REGISTRO DE UN NUEVO PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_guardar'])) {
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $id_proveedor = intval($_POST['id_proveedor']);
    $precio_compra = floatval($_POST['precio_compra']);
    $precio_venta = floatval($_POST['precio_venta']);
    $stock_inicial = intval($_POST['stock']);

    if (!empty($codigo) && !empty($nombre) && $id_proveedor > 0) {
        try {
            // Verificar si el código ya existe en la columna 'codigo'
            $check = $conexion->prepare("SELECT id FROM productos WHERE codigo = :codigo LIMIT 1");
            $check->execute([':codigo' => $codigo]);
            
            if ($check->fetch()) {
                $mensaje = "⚠️ El código de producto ya se encuentra registrado.";
                $tipo_mensaje = "error";
            } else {
                // Ajustado a las columnas reales de tu BD: id_categoria=1 (respaldo), precio_costo_usd, precio_venta_usd, stock_actual
                $sql = "INSERT INTO productos (codigo, nombre, id_categoria, id_proveedor, precio_costo_usd, precio_venta_usd, stock_actual, imagen) 
                        VALUES (:codigo, :nombre, 1, :id_proveedor, :precio_compra, :precio_venta, :stock_inicial, 'defecto.png')";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ':codigo' => $codigo,
                    ':nombre' => $nombre,
                    ':id_proveedor' => $id_proveedor,
                    ':precio_compra' => $precio_compra,
                    ':precio_venta' => $precio_venta,
                    ':stock_inicial' => $stock_inicial
                ]);
                $mensaje = "✅ Producto agregado al catálogo exitosamente.";
                $tipo_mensaje = "exito";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al guardar el producto: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ Por favor complete todos los campos obligatorios.";
        $tipo_mensaje = "error";
    }
}

// 2. CARGAR LISTA DE PROVEEDORES ACTIVOS PARA EL SELECTOR
$proveedores = [];
try {
    $proveedores = $conexion->query("SELECT id, nombre FROM proveedores WHERE estado = 'Activo' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control de error
}

// 3. OBTENER EL LISTADO DE PRODUCTOS REGISTRADOS
$lista_productos = [];
try {
    $sql_prod = "SELECT p.*, prov.nombre AS proveedor_nombre 
                 FROM productos p 
                 LEFT JOIN proveedores prov ON p.id_proveedor = prov.id 
                 ORDER BY p.nombre ASC";
    $lista_productos = $conexion->query($sql_prod)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control de error
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Productos - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/productos.css">
</head>
<body>

    <div class="contenedor-sistema">
        
        <aside class="barra-lateral">
            <div class="logo-sistema">
                    <h2><?php echo htmlspecialchars($nombre_local_global ?? 'Mi Trabajo OP 2026'); ?></h2>
                    <span style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($rif_local_global ?? '20.175.020'); ?></span>
                    <span class="etiqueta-rol"><?php echo $_SESSION['usuario_rol']; ?></span>
                </div>
                
            <nav class="menu-navegacion">
                <a href="dashboard_admin.php">📊 Dashboard</a>
                <a href="compras.php">🛒 Compras</a>
                <a href="recibidos.php">📦 Recibidos</a>
                <a href="devoluciones.php">🔄 Devoluciones</a>
                <a href="stocks.php">📉 Stocks</a>
                <a href="ventas.php">💵 Ventas</a>
                
                <div class="seccion-mantenimiento">Mantenimiento</div>
                <a href="proveedores.php">🚚 Proveedores</a>
                <a href="productos.php" class="activo">🍎 Productos</a>
                <a href="categorias.php">🏷️ Categorías</a>
                <a href="usuarios.php">👥 Usuarios</a>
                <a href="configuracion.php">⚙️ Configuración</a>

                <a href="desarrollador.php">💻 Desarrollador</a>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Área de Configuración de Artículos</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo $tasa_dolar; ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">🍎 Inventario de Productos</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="grilla-productos">
                    
                    <div class="bloque-formulario">
                        <h2>Nuevo Artículo</h2>
                        <form action="productos.php" method="POST" autocomplete="off">
                            <div class="grupo-campo">
                                <label for="codigo">Código de Barra / SKU</label>
                                <input type="text" id="codigo" name="codigo" placeholder="Ej: 759123456789" required>
                            </div>

                            <div class="grupo-campo">
                                <label for="nombre">Descripción / Nombre del Producto</label>
                                <input type="text" id="nombre" name="nombre" placeholder="Ej: Harina Pan 1kg" required>
                            </div>

                            <div class="grupo-campo">
                                <label for="id_proveedor">🚚 Proveedor Habitual</label>
                                <select id="id_proveedor" name="id_proveedor" required>
                                    <option value="">Seleccione un distribuidor</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?php echo $prov['id']; ?>"><?php echo $prov['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="doble-columna">
                                <div class="grupo-campo">
                                    <label for="precio_compra">Costo ($)</label>
                                    <input type="number" id="precio_compra" name="precio_compra" step="0.01" min="0.00" required placeholder="0.00">
                                </div>
                                <div class="grupo-campo">
                                    <label for="precio_venta">P.V.P ($)</label>
                                    <input type="number" id="precio_venta" name="precio_venta" step="0.01" min="0.00" required placeholder="0.00">
                                </div>
                            </div>

                            <div class="grupo-campo">
                                <label for="stock">Stock Inicial</label>
                                <input type="number" id="stock" name="stock" min="0" value="0" required>
                            </div>

                            <button type="submit" name="btn_guardar" class="btn-guardar">Guardar Producto</button>
                        </form>
                    </div>

                    <div class="bloque-tabla">
                        <h2>Catálogo General</h2>
                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Proveedor</th>
                                        <th>Costo / PVP</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_productos)): ?>
                                        <tr>
                                            <td colspan="6" class="tabla-vacia">No hay productos en el catálogo.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_productos as $prod): ?>
                                            <tr>
                                                <td><small class="txt-codigo"><?php echo htmlspecialchars($prod['codigo'] ?? ''); ?></small></td>
                                                <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                                <td><span class="txt-proveedor"><?php echo !empty($prod['proveedor_nombre']) ? htmlspecialchars($prod['proveedor_nombre']) : 'Sin asignar'; ?></span></td>
                                                <td>
                                                    <span class="txt-compra">C: $<?php echo number_format($prod['precio_costo_usd'], 2); ?></span><br>
                                                    <span class="txt-venta">V: $<?php echo number_format($prod['precio_venta_usd'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge-stock <?php echo ($prod['stock_actual'] <= $prod['stock_minimo']) ? 'critico' : 'normal'; ?>">
                                                        <?php echo $prod['stock_actual']; ?> unds
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge-estado activo">
                                                        Activo
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div> </div>
        </main>
    </div>

</body>
</html>