<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Control estricto: Solo el Administrador puede entrar a este archivo
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Administrador') {
    header("Location: login.php");
    exit();
}

include '../CONEXION/conexiones.php';

$mensaje = "";
$tipo_mensaje = "";

// ======================================================================
// 1. PROCESAR CAMBIOS DE AJUSTES GENERALES (Tasa, Local, RIF, Imagen)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ajustes'])) {
    // Recibir y limpiar datos del formulario
    $ajustes = [
        'tasa_dolar'   => trim($_POST['tasa_dolar']),
        'nombre_local' => trim($_POST['nombre_local']),
        'rif_local'    => trim($_POST['rif_local']),
        'url_login_img'=> trim($_POST['url_login_img'])
    ];

    try {
        $conexion->beginTransaction();

        foreach ($ajustes as $clave => $valor) {
            // Verificar si la clave ya existe en la tabla configuración
            $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM configuracion WHERE clave = :clave");
            $stmt_check->execute([':clave' => $clave]);
            
            if ($stmt_check->fetchColumn() > 0) {
                // Si existe, se actualiza
                $stmt_upd = $conexion->prepare("UPDATE configuracion SET valor = :valor WHERE clave = :clave");
                $stmt_upd->execute([':valor' => $valor, ':clave' => $clave]);
            } else {
                // Si no existe (por ejemplo la primera vez), se inserta
                $stmt_ins = $conexion->prepare("INSERT INTO configuracion (clave, valor) VALUES (:clave, :valor)");
                $stmt_ins->execute([':clave' => $clave, ':valor' => $valor]);
            }
        }

        $conexion->commit();
        $mensaje = "⚙️ Parámetros del sistema actualizados con éxito.";
        $tipo_mensaje = "exito";
    } catch (Exception $e) {
        $conexion->rollBack();
        $mensaje = "Error al guardar la configuración: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// ======================================================================
// 2. PROCESAR ACCIONES DE EMPLEADOS (Cambiar Rol / Activar Cuenta)
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_empleado'])) {
    $id_usuario = intval($_POST['id_usuario']);
    $nuevo_rol = trim($_POST['nuevo_rol']);
    $nuevo_estado = trim($_POST['nuevo_estado']);

    // Evitar que el administrador se desactive a sí mismo accidentalmente
    if ($id_usuario === intval($_SESSION['usuario_id'])) {
        $mensaje = "⚠️ No puedes alterar tu propio rol o estado desde este panel.";
        $tipo_mensaje = "error";
    } else {
        try {
            $stmt_user = $conexion->prepare("UPDATE usuarios SET rol = :rol, estado = :estado WHERE id = :id");
            $stmt_user->execute([
                ':rol'    => $nuevo_rol,
                ':estado' => $nuevo_estado,
                ':id'     => $id_usuario
            ]);
            $mensaje = "👤 Cuenta de empleado actualizada correctamente.";
            $tipo_mensaje = "exito";
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar empleado: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// ======================================================================
// 3. CARGAR DATOS ACTUALES PARA MOSTRARLOS EN PANTALLA
// ======================================================================
// Cargar configuraciones (Valores por defecto si está vacía la tabla)
$config = [
    'tasa_dolar'   => '1.00',
    'nombre_local' => 'Mi Negocio OP',
    'rif_local'    => 'J-00000000-0',
    'url_login_img'=> '../IMG/Quiosco_1.png'
];

try {
    $res_config = $conexion->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res_config as $row) {
        if (array_key_exists($row['clave'], $config)) {
            $config[$row['clave']] = $row['valor'];
        }
    }

    // Cargar todos los usuarios/empleados registrados para la gestión de cuentas
    $usuarios = $conexion->query("SELECT id, usuario, nombre, rol, estado FROM usuarios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control básico
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración General - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/configuracion.css">
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
                <a href="dashboard_admin.php">📊 Dashboard</a>
                <a href="compras.php">🛒 Compras</a>
                <a href="recibidos.php">📦 Recibidos</a>
                <a href="devoluciones.php">🔄 Devoluciones</a>
                <a href="stocks.php">📉 Stocks</a>
                <a href="ventas.php">💵 Ventas</a>
                
                <div class="seccion-mantenimiento">Mantenimiento</div>
                <a href="proveedores.php">🚚 Proveedores</a>
                <a href="productos.php">🍎 Productos</a>
                <a href="categorias.php">🏷️ Categorías</a>
                <a href="usuarios.php">👥 Usuarios</a>
                <a href="configuracion.php" class="activo">⚙️ Configuración</a>
                
                <a href="desarrollador.php">💻 Desarrollador</a>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Panel de Control Administrativo Global</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo number_format(floatval($config['tasa_dolar']), 2); ?> Bs</span>
                    <span class="usuario-nombre">👤 Admin: <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">⚙️ Configuración General de la Empresa</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="layout-config">
                    
                    <div class="tarjeta-config">
                        <h2>Identidad del Local y Divisas</h2>
                        <form action="configuracion.php" method="POST" autocomplete="off">
                            
                            <div class="grupo-campo">
                                <label for="tasa_dolar">Tasa del Dólar Oficial (Bs):</label>
                                <input type="number" step="0.01" name="tasa_dolar" id="tasa_dolar" value="<?php echo htmlspecialchars($config['tasa_dolar']); ?>" required style="font-weight: bold; color: #10b981; font-size: 1.1rem;">
                            </div>

                            <div class="grupo-campo">
                                <label for="nombre_local">Nombre Comercial del Local:</label>
                                <input type="text" name="nombre_local" id="nombre_local" value="<?php echo htmlspecialchars($config['nombre_local']); ?>" required>
                            </div>

                            <div class="grupo-campo">
                                <label for="rif_local">RIF de la Empresa:</label>
                                <input type="text" name="rif_local" id="rif_local" value="<?php echo htmlspecialchars($config['rif_local']); ?>" required placeholder="Ej: J-12345678-9">
                            </div>

                            <div class="grupo-campo">
                                <label for="url_login_img">Ruta o Enlace de Imagen para el Login:</label>
                                <input type="text" name="url_login_img" id="url_login_img" value="<?php echo htmlspecialchars($config['url_login_img']); ?>" required>
                                <small style="color: #64748b; display:block; margin-top:4px;">Por defecto: `../IMG/Quiosco_1.png` o coloca un link web directo (URL).</small>
                            </div>

                            <button type="submit" name="guardar_ajustes" class="btn-guardar">Guardar Cambios de Identidad</button>
                        </form>
                    </div>

                    <div class="tarjeta-config grande">
                        <h2>Gestión de Cuentas y Activación de Usuarios</h2>
                        <p style="font-size:0.85rem; color:#64748b; margin-bottom:15px;">Aquí puedes activar a los usuarios recién registrados para darles acceso e intercambiar sus roles.</p>
                        
                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Nombre Real</th>
                                        <th>Rol del Usuario</th>
                                        <th>Estado / Acceso</th>
                                        <th style="text-align: center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <form action="configuracion.php" method="POST" style="margin:0;">
                                                <input type="hidden" name="id_usuario" value="<?php echo $u['id']; ?>">
                                                
                                                <td><strong><?php echo htmlspecialchars($u['usuario']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                                
                                                <td>
                                                    <select name="nuevo_rol" class="select-tabla">
                                                        <option value="Staff" <?php echo ($u['role'] ?? $u['rol'] === 'Staff') ? 'selected' : ''; ?>>Staff (Empleado)</option>
                                                        <option value="Administrador" <?php echo ($u['role'] ?? $u['rol'] === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                                                    </select>
                                                </td>

                                                <td>
                                                    <select name="nuevo_estado" class="select-tabla <?php echo ($u['estado'] === 'Activo') ? 'txt-activo' : 'txt-inactivo'; ?>">
                                                        <option value="Inactivo" <?php echo ($u['estado'] === 'Inactivo') ? 'selected' : ''; ?>>❌ Inactivo</option>
                                                        <option value="Activo" <?php echo ($u['estado'] === 'Activo') ? 'selected' : ''; ?>>✔️ Activo</option>
                                                    </select>
                                                </td>

                                                <td style="text-align: center;">
                                                    <button type="submit" name="actualizar_empleado" class="btn-actualizar-user">⚡ Aplicar</button>
                                                </td>
                                            </form>
                                        </tr>
                                    <?php endforeach; ?>
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