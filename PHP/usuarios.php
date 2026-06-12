<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Control de seguridad: Solo el Administrador puede gestionar las credenciales de usuarios
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Administrador') {
    header("Location: login.php");
    exit();
}

include '../CONEXION/conexiones.php';

$mensaje = "";
$tipo_mensaje = "";

// ======================================================================
// 1. ACCIÓN: REGISTRAR UN NUEVO USUARIO / EMPLEADO
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_usuario'])) {
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    $rol = $_POST['rol'];
    $estado = $_POST['estado'];

    if (empty($nombre) || empty($usuario) || empty($password)) {
        $mensaje = "⚠️ Todos los campos son obligatorios para el registro.";
        $tipo_mensaje = "error";
    } else {
        try {
            // Verificar si el nombre de usuario ya existe en la Base de Datos
            $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = :usuario");
            $stmt_check->execute([':usuario' => $usuario]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $mensaje = "❌ El nombre de usuario '$usuario' ya está registrado por otra persona.";
                $tipo_mensaje = "error";
            } else {
                // Insertar nuevo registro (con contraseña limpia)
                $sql_ins = "INSERT INTO usuarios (nombre, usuario, password, rol, estado) 
                            VALUES (:nombre, :usuario, :password, :rol, :estado)";
                $stmt_ins = $conexion->prepare($sql_ins);
                $stmt_ins->execute([
                    ':nombre'   => $nombre,
                    ':usuario'  => $usuario,
                    ':password' => $password, // Ajusta si usas password_hash en tu login
                    ':rol'      => $rol,
                    ':estado'   => $estado
                ]);
                $mensaje = "✅ Usuario '$usuario' registrado exitosamente en el sistema.";
                $tipo_mensaje = "exito";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al registrar usuario: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// ======================================================================
// 2. ACCIÓN: ELIMINAR UN USUARIO
// ======================================================================
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);

    // Evitar que el administrador se elimine a sí mismo
    if ($id_eliminar === intval($_SESSION['usuario_id'])) {
        $mensaje = "⚠️ Operación cancelada: No puedes eliminar tu propia cuenta de Administrador.";
        $tipo_mensaje = "error";
    } else {
        try {
            $stmt_del = $conexion->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt_del->execute([':id' => $id_eliminar]);
            $mensaje = "🗑️ Usuario eliminado correctamente del sistema.";
            $tipo_mensaje = "exito";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar usuario: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// ======================================================================
// 3. CONSULTAR LA LISTA DE USUARIOS ACTIVOS E INACTIVOS
// ======================================================================
$lista_usuarios = [];
try {
    $lista_usuarios = $conexion->query("SELECT id, nombre, usuario, password, rol, estado FROM usuarios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control de contingencia de conexión
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/usuarios.css">
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
                <a href="usuarios.php" class="activo">👥 Usuarios</a>
                <a href="configuracion.php">⚙️ Configuración</a>

                <a href="desarrollador.php">💻 Desarrollador</a>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Control de Personal y Seguridad de Accesos</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo number_format($tasa_dolar ?? 1.00, 2); ?> Bs</span>
                    <span class="usuario-nombre">👤 Admin: <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">👥 Registro y Mantenimiento de Usuarios</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="layout-usuarios">
                    
                    <div class="tarjeta-usuario-form">
                        <h2>Agregar Nuevo Personal</h2>
                        <form action="usuarios.php" method="POST" autocomplete="off">
                            
                            <div class="grupo-campo">
                                <label for="nombre">Nombre Completo:</label>
                                <input type="text" name="nombre" id="nombre" required placeholder="Ej: Juan Pérez">
                            </div>

                            <div class="grupo-campo">
                                <label for="usuario">Nombre de Usuario (Login):</label>
                                <input type="text" name="usuario" id="usuario" required placeholder="Ej: jperez2026">
                            </div>

                            <div class="grupo-campo">
                                <label for="password">Contraseña de Acceso:</label>
                                <input type="password" name="password" id="password" required placeholder="Asigne una clave segura">
                            </div>

                            <div class="grupo-campo">
                                <label for="rol">Rol de Sistema:</label>
                                <select name="rol" id="rol" class="select-form">
                                    <option value="Staff">Staff (Cajero / Empleado)</option>
                                    <option value="Administrador">Administrador</option>
                                </select>
                            </div>

                            <div class="grupo-campo">
                                <label for="estado">Estado Inicial:</label>
                                <select name="estado" id="estado" class="select-form">
                                    <option value="Activo">✔️ Activo (Acceso Inmediato)</option>
                                    <option value="Inactivo">❌ Inactivo (Bloqueado)</option>
                                </select>
                            </div>

                            <button type="submit" name="registrar_usuario" class="btn-guardar-user">Registrar y Dar Acceso</button>
                        </form>
                    </div>

                    <div class="tarjeta-usuario-tabla">
                        <h2>Personal Registrado en el Sistema</h2>
                        
                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre Real</th>
                                        <th>Usuario</th>
                                        <th>Contraseña</th>
                                        <th>Rol asignado</th>
                                        <th>Estado</th>
                                        <th style="text-align: center;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_usuarios)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding:20px; color:#64748b;">No hay cuentas creadas.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_usuarios as $user): ?>
                                            <tr>
                                                <td><span class="badge-id">#<?php echo $user['id']; ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($user['nombre']); ?></strong></td>
                                                <td><span class="txt-user"><?php echo htmlspecialchars($user['usuario']); ?></span></td>
                                                <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:0.8rem;"><?php echo htmlspecialchars($user['password']); ?></code></td>
                                                <td>
                                                    <span class="badge-rol <?php echo ($user['rol'] === 'Administrador') ? 'admin' : 'staff'; ?>">
                                                        <?php echo $user['rol']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="estado-indicador <?php echo ($user['estado'] === 'Activo') ? 'activo' : 'inactivo'; ?>">
                                                        <?php echo ($user['estado'] === 'Activo') ? '● Activo' : '● Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <a href="usuarios.php?eliminar=<?php echo $user['id']; ?>" class="btn-eliminar-user" onclick="return confirm('¿Seguro que deseas eliminar permanentemente a este usuario del sistema?');">🗑️ Eliminar</a>
                                                </td>
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