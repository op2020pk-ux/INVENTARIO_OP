<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Validar que el usuario esté logueado
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
    // Respaldo
}

$mensaje = "";
$tipo_mensaje = "";

// PROCESAR ENTRADA DE MERCANCÍA (PROCESAR COMPRA)
if (isset($_GET['procesar_id'])) {
    $id_compra = intval($_GET['procesar_id']);
    
    try {
        // Iniciar una transacción para asegurar consistencia
        $conexion->beginTransaction();

        // 1. Obtener los datos de la compra para verificar que exista y esté 'Pendiente'
        $stmt_compra = $conexion->prepare("SELECT estado FROM compras WHERE id = :id LIMIT 1");
        $stmt_compra->execute([':id' => $id_compra]);
        $compra = $stmt_compra->fetch();

        if ($compra && $compra['estado'] === 'Pendiente') {
            
            // 2. Obtener los artículos adjuntos en el detalle de esta compra
            $stmt_detalles = $conexion->prepare("SELECT id_producto, cantidad FROM detalle_compras WHERE id_compra = :id_compra");
            $stmt_detalles->execute([':id_compra' => $id_compra]);
            $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

            // 3. Sumar las cantidades al stock de cada producto
            $stmt_update_stock = $conexion->prepare("UPDATE productos SET stock = stock + :cantidad WHERE id = :id_producto");
            
            foreach ($detalles as $item) {
                $stmt_update_stock->execute([
                    ':cantidad' => $item['cantidad'],
                    ':id_producto' => $item['id_producto']
                ]);
            }

            // 4. Cambiar el estado de la compra a 'Recibido'
            $stmt_finalizar = $conexion->prepare("UPDATE compras SET estado = 'Recibido' WHERE id = :id");
            $stmt_finalizar->execute([':id' => $id_compra]);

            // Confirmar cambios en la base de datos
            $conexion->commit();
            $mensaje = "📦 ¡Mercancía recibida! El stock se ha actualizado correctamente.";
            $tipo_mensaje = "exito";
        } else {
            $conexion->rollBack();
            $mensaje = "⚠️ La orden ya fue procesada o no existe.";
            $tipo_mensaje = "error";
        }

    } catch (Exception $e) {
        // Deshacer cambios en caso de error de servidor
        $conexion->rollBack();
        $mensaje = "Error al procesar la entrada: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// OBTENER HISTORIAL DE ÓRDENES DE COMPRA (PENDIENTES Y RECIBIDAS)
$compras_registradas = [];
try {
    $sql = "SELECT c.*, p.nombre AS proveedor_nombre, u.nombre AS usuario_nombre 
            FROM compras c
            LEFT JOIN proveedores p ON c.id_proveedor = p.id
            LEFT JOIN usuarios u ON c.id_usuario = u.id
            ORDER BY c.fecha DESC, c.id DESC";
    $compras_registradas = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control de tabla inexistente
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras Recibidas - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/recibidos.css">
</head>
<body>

    <div class="contenedor-sistema">
        
        <aside class="barra-lateral">
            <div class="logo-sistema">
                    <h2><?php echo htmlspecialchars($nombre_local_global); ?></h2>
                    <span style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($rif_local_global); ?></span>
                    <span class="etiqueta-rol"><?php echo $_SESSION['usuario_rol']; ?></span>
                </div>
            <nav class="menu-navegacion">
                <a href="<?php echo ($_SESSION['usuario_rol'] === 'Administrador') ? 'dashboard_admin.php' : 'dashboard_staff.php'; ?>">📊 Dashboard</a>
                <a href="compras.php">🛒 Compras</a>
                <a href="recibidos.php" class="activo">📦 Recibidos</a>
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
                    <span>Control de Entradas e Inventario</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo $tasa_dolar; ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">📦 Recepción de Órdenes de Compra</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="bloque-tabla-general">
                    <h2>Historial de Órdenes Emitidas</h2>
                    <div class="tabla-responsiva">
                        <table>
                            <thead>
                                <tr>
                                    <th>N° Orden</th>
                                    <th>Fecha</th>
                                    <th>Proveedor</th>
                                    <th>Total ($)</th>
                                    <th>Registrado Por</th>
                                    <th>Estado</th>
                                    <th style="text-align: center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($compras_registradas)): ?>
                                    <tr>
                                        <td colspan="7" class="tabla-vacia">No se han registrado órdenes de compra en el sistema.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($compras_registradas as $compra): ?>
                                        <tr>
                                            <td><code>#<?php echo str_pad($compra['id'], 5, "0", STR_PAD_LEFT); ?></code></td>
                                            <td><?php echo date('d/m/Y g:i a', strtotime($compra['fecha'])); ?></td>
                                            <td><strong><?php echo $compra['proveedor_nombre']; ?></strong></td>
                                            <td class="monto-total">$<?php echo number_format($compra['total'], 2); ?></td>
                                            <td><small>👤 <?php echo $compra['usuario_nombre']; ?></small></td>
                                            <td>
                                                <span class="badge-estado <?php echo strtolower($compra['estado']); ?>">
                                                    <?php echo $compra['estado']; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($compra['estado'] === 'Pendiente'): ?>
                                                    <a href="recibidos.php?procesar_id=<?php echo $compra['id']; ?>" 
                                                       class="btn-procesar-entrada"
                                                       onclick="return confirm('¿Seguro que llegó esta mercancía? Se sumará automáticamente al inventario actual.');">
                                                        Procesar Entrada
                                                    </a>
                                                <?php else: ?>
                                                    <span class="txt-completado">✅ Procesado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>