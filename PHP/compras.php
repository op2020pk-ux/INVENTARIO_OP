<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

if (!isset($_SESSION['usuario_id'])) {
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
    // Valor de respaldo
}

// Cargar listas de Proveedores y Productos para los desplegables del formulario
$proveedores = [];
$productos = [];
try {
    $proveedores = $conexion->query("SELECT id, nombre FROM proveedores WHERE estado = 'Activo' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $productos = $conexion->query("SELECT id, nombre, precio_compra FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si las tablas están vacías o no existen aún, no rompe el flujo
}

$mensaje = "";
$tipo_mensaje = "";

// Procesar el registro de la compra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_proveedor = $_POST['id_proveedor'];
    $id_producto = $_POST['id_producto'];
    $cantidad = intval($_POST['cantidad']);
    $precio_usd = floatval($_POST['precio_usd']);
    
    if (!empty($id_proveedor) && !empty($id_producto) && $cantidad > 0 && $precio_usd > 0) {
        $total_usd = $cantidad * $precio_usd;
        $total_bs = $total_usd * $tasa_dolar;
        $fecha_registro = date('Y-m-d H:i:s');
        
        try {
            // Insertar en la tabla compras de tu base de datos
            $sql = "INSERT INTO compras (id_proveedor, id_producto, cantidad, precio_unitario_usd, total_usd, total_bs, fecha, estado, registrado_por) 
                    VALUES (:id_proveedor, :id_producto, :cantidad, :precio_usd, :total_usd, :total_bs, :fecha, 'Solicitado', :usuario)";
            
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':id_proveedor', $id_proveedor);
            $stmt->bindParam(':id_producto', $id_producto);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':precio_usd', $precio_usd);
            $stmt->bindParam(':total_usd', $total_usd);
            $stmt->bindParam(':total_bs', $total_bs);
            $stmt->bindParam(':fecha', $fecha_registro);
            $stmt->bindParam(':usuario', $_SESSION['usuario_id']);
            
            if ($stmt->execute()) {
                $mensaje = "✅ Orden de compra registrada con éxito.";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "⚠️ No se pudo guardar la orden.";
                $tipo_mensaje = "error";
            }
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ Por favor, rellene todos los campos con valores válidos.";
        $tipo_mensaje = "error";
    }
}

// Cargar el historial de compras registradas
$historial_compras = [];
try {
    $sql_historial = "SELECT c.id, p.nombre AS proveedor, pro.nombre AS producto, c.cantidad, c.total_usd, c.total_bs, c.estado, c.fecha 
                      FROM compras c
                      INNER JOIN proveedores p ON c.id_proveedor = p.id
                      INNER JOIN productos pro ON c.id_producto = pro.id
                      ORDER BY c.id DESC LIMIT 10";
    $historial_compras = $conexion->query($sql_historial)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si no hay registros todavía
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Compras - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/compras.css">
</head>
<body>

    <div class="contenedor-sistema">
        
        <aside class="barra-lateral">
            <div class="logo-sistema">
                <div class="logo-sistema">
                    <h2><?php echo htmlspecialchars($nombre_local_global); ?></h2>
                    <span style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($rif_local_global); ?></span>
                    <span class="etiqueta-rol"><?php echo $_SESSION['usuario_rol']; ?></span>
                </div>

            </div>
            <nav class="menu-navegacion">
                <a href="<?php echo ($_SESSION['usuario_rol'] === 'Administrador') ? 'dashboard_admin.php' : 'dashboard_staff.php'; ?>">📊 Dashboard</a>
                <a href="compras.php" class="activo">🛒 Compras</a>
                <a href="recibidos.php">📦 Recibidos</a>
                <a href="devoluciones.php">🔄 Devoluciones</a>
                <a href="stocks.php">📉 Stocks</a>
                <a href="ventas.php">💵 Ventas</a>
                
                <?php if ($_SESSION['usuario_rol'] === 'Administrador'): ?>
                    <div class="seccion-mantenimiento">Mantenimiento</div>
                    <a href="proveedores.php">🚚 Proveedores</a>
                    <a href="productos.php">🍎 Productos</a>
                    <a href="categorias.php">🏷️ Categorías</a>
                    <a href="usuarios.php">👥 Usuarios</a>
                    <a href="configuracion.php">⚙️ Configuración</a>

                    <a href="desarrollador.php">💻 Desarrollador</a>
                <?php endif; ?>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Módulo de Entrada y Adquisiciones</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo $tasa_dolar; ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">🛒 Gestión de Compras</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="grilla-compras">
                    
                    <div class="bloque-formulario">
                        <h2>Nueva Orden de Compra</h2>
                        <form action="compras.php" method="POST" autocomplete="off">
                            <div class="grupo-campo">
                                <label for="id_proveedor">🚚 Proveedor</label>
                                <select id="id_proveedor" name="id_proveedor" required>
                                    <option value="">Seleccione un proveedor</option>
                                    <?php foreach ($proveedores as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo $p['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grupo-campo">
                                <label for="id_producto">🍎 Producto / Artículo</label>
                                <select id="id_producto" name="id_producto" required>
                                    <option value="">Seleccione un producto</option>
                                    <?php foreach ($productos as $pro): ?>
                                        <option value="<?php echo $pro['id']; ?>"><?php echo $pro['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="doble-columna">
                                <div class="grupo-campo">
                                    <label for="cantidad">🔢 Cantidad</label>
                                    <input type="number" id="cantidad" name="cantidad" min="1" required placeholder="0">
                                </div>
                                <div class="grupo-campo">
                                    <label for="precio_usd">💵 Costo Unitario ($)</label>
                                    <input type="number" id="precio_usd" name="precio_usd" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                            </div>

                            <button type="submit" class="btn-guardar">Generar Orden de Compra</button>
                        </form>
                    </div>

                    <div class="bloque-tabla">
                        <h2>Últimas Órdenes Solicitadas</h2>
                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Proveedor</th>
                                        <th>Producto</th>
                                        <th>Cant.</th>
                                        <th>Total ($)</th>
                                        <th>Total (Bs)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($historial_compras)): ?>
                                        <tr>
                                            <td colspan="7" class="tabla-vacia">No hay órdenes registradas recientemente.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($historial_compras as $row): ?>
                                            <tr>
                                                <td>#<?php echo $row['id']; ?></td>
                                                <td><strong><?php echo $row['proveedor']; ?></strong></td>
                                                <td><?php echo $row['producto']; ?></td>
                                                <td><?php echo $row['cantidad']; ?></td>
                                                <td class="txt-verde">$<?php echo number_format($row['total_usd'], 2); ?></td>
                                                <td class="txt-azul"><?php echo number_format($row['total_bs'], 2); ?> Bs</td>
                                                <td><span class="badge-estado <?php echo strtolower($row['estado']); ?>"><?php echo $row['estado']; ?></span></td>
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