<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Validar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir la conexión a la base de datos
include '../CONEXION/conexiones.php';

// Obtener la tasa del dólar actual desde la configuración
$tasa_dolar = "1.00";
try {
    $stmt_tasa = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar' LIMIT 1");
    $stmt_tasa->execute();
    $reg_tasa = $stmt_tasa->fetch();
    if ($reg_tasa) {
        $tasa_dolar = $reg_tasa['valor'];
    }
} catch (PDOException $e) {
    // Respaldo en caso de error
}

$mensaje = "";
$tipo_mensaje = "";

// PROCESAR EL REGISTRO DE UNA DEVOLUCIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_devolucion'])) {
    $id_producto = intval($_POST['id_producto']);
    $id_proveedor = intval($_POST['id_proveedor']);
    $cantidad = intval($_POST['cantidad']);
    $motivo = trim($_POST['motivo']);
    $id_usuario = $_SESSION['usuario_id'];

    if ($id_producto > 0 && $id_proveedor > 0 && $cantidad > 0 && !empty($motivo)) {
        try {
            // Iniciar transacción de seguridad
            $conexion->beginTransaction();

            // 1. Verificar si hay suficiente stock disponible para realizar la devolución
            $stmt_stock = $conexion->prepare("SELECT stock, nombre FROM productos WHERE id = :id LIMIT 1");
            $stmt_stock->execute([':id' => $id_producto]);
            $producto = $stmt_stock->fetch();

            if ($producto) {
                if ($producto['stock'] >= $cantidad) {
                    
                    // 2. Restar la cantidad del stock del producto
                    $stmt_update = $conexion->prepare("UPDATE productos SET stock = stock - :cantidad WHERE id = :id");
                    $stmt_update->execute([
                        ':cantidad' => $cantidad,
                        ':id' => $id_producto
                    ]);

                    // 3. Insertar el registro en la tabla de devoluciones
                    $stmt_ins = $conexion->prepare("INSERT INTO devoluciones (id_producto, id_proveedor, id_usuario, cantidad, motivo, fecha) 
                                                    VALUES (:id_producto, :id_proveedor, :id_usuario, :cantidad, :motivo, NOW())");
                    $stmt_ins->execute([
                        ':id_producto'  => $id_producto,
                        ':id_proveedor' => $id_proveedor,
                        ':id_usuario'   => $id_usuario,
                        ':cantidad'     => $cantidad,
                        ':motivo'       => $motivo
                    ]);

                    // Confirmar todas las operaciones en la base de datos
                    $conexion->commit();
                    $mensaje = "🔄 Devolución procesada con éxito. El inventario ha sido actualizado.";
                    $tipo_mensaje = "exito";
                } else {
                    // Deshacer si no alcanza el stock
                    $conexion->rollBack();
                    $mensaje = "⚠️ Error: No puedes devolver una cantidad mayor al stock disponible de '".$producto['nombre']."' (Stock actual: ".$producto['stock'].").";
                    $tipo_mensaje = "error";
                }
            } else {
                $conexion->rollBack();
                $mensaje = "⚠️ El producto seleccionado no existe.";
                $tipo_mensaje = "error";
            }

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "Error en el sistema: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ Por favor, complete todos los campos obligatorios con valores válidos.";
        $tipo_mensaje = "error";
    }
}

// OBTENER LISTADOS PARA LOS SELECTS DEL FORMULARIO
$productos = [];
$proveedores = [];
$devoluciones_registradas = [];

try {
    // Productos activos
    $productos = $conexion->query("SELECT id, nombre, stock FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    // Proveedores activos
    $proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Listado de devoluciones realizadas históricamente
    $sql_dev = "SELECT d.*, p.nombre AS producto_nombre, prov.nombre AS proveedor_nombre, u.nombre AS usuario_nombre 
                FROM devoluciones d
                LEFT JOIN productos p ON d.id_producto = p.id
                LEFT JOIN proveedores prov ON d.id_proveedor = prov.id
                LEFT JOIN usuarios u ON d.id_usuario = u.id
                ORDER BY d.fecha DESC, d.id DESC";
    $devoluciones_registradas = $conexion->query($sql_dev)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control de errores de base de datos
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluciones a Proveedores - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/devoluciones.css">
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
                <a href="recibidos.php">📦 Recibidos</a>
                <a href="devoluciones.php" class="activo">🔄 Devoluciones</a>
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
                    <span>Salidas por Devolución de Mercancía</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo $tasa_dolar; ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">🔄 Gestión de Devoluciones a Proveedores</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="layout-devoluciones">
                    
                    <div class="bloque-formulario">
                        <h2>Registrar Nueva Devolución</h2>
                        <form action="devoluciones.php" method="POST" autocomplete="off">
                            
                            <div class="grupo-campo">
                                <label for="id_producto">Producto a Devolver:</label>
                                <select name="id_producto" id="id_producto" required>
                                    <option value="">-- Seleccione un producto --</option>
                                    <?php foreach ($productos as $prod): ?>
                                        <option value="<?php echo $prod['id']; ?>">
                                            <?php echo $prod['nombre']; ?> (Disponible: <?php echo $prod['stock']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grupo-campo">
                                <label for="id_proveedor">Proveedor de Destino:</label>
                                <select name="id_proveedor" id="id_proveedor" required>
                                    <option value="">-- Seleccione el proveedor --</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?php echo $prov['id']; ?>"><?php echo $prov['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grupo-campo">
                                <label for="cantidad">Cantidad a Devolver:</label>
                                <input type="number" name="cantidad" id="cantidad" min="1" placeholder="Ej. 10" required>
                            </div>

                            <div class="grupo-campo">
                                <label for="motivo">Motivo de la Devolución:</label>
                                <textarea name="motivo" id="motivo" rows="4" placeholder="Describa el motivo (Ej: Producto vencido, empaque roto, etc.)" required></textarea>
                            </div>

                            <button type="submit" name="registrar_devolucion" class="btn-guardar">Procesar Salida</button>
                        </form>
                    </div>

                    <div class="bloque-tabla">
                        <h2>Historial de Devoluciones Emitidas</h2>
                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha / Hora</th>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Proveedor</th>
                                        <th>Motivo / Observación</th>
                                        <th>Operador</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($devoluciones_registradas)): ?>
                                        <tr>
                                            <td colspan="6" class="tabla-vacia">No se registran devoluciones en el sistema.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($devoluciones_registradas as $dev): ?>
                                            <tr>
                                                <td><small><?php echo date('d/m/Y g:i a', strtotime($dev['fecha'])); ?></small></td>
                                                <td><strong><?php echo $dev['producto_nombre']; ?></strong></td>
                                                <td class="cant-devolucion"><?php echo $dev['cantidad']; ?></td>
                                                <td><?php echo $dev['proveedor_nombre']; ?></td>
                                                <td><span class="txt-motivo"><?php echo htmlspecialchars($dev['motivo']); ?></span></td>
                                                <td><small>👤 <?php echo $dev['usuario_nombre']; ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>

</body>
</html>