<?php
// ======================================================================
// PROYECTO: Inventario_OP
// ARCHIVO: login.php
// AUTOR: Omar Pinto
// DESCRIPCIÓN: Formulario de Acceso Seguro con Validación de Roles
// ======================================================================

// Iniciar el sistema de sesiones de PHP
session_start();

// Si el usuario ya está logueado, lo redirige directamente a su panel correspondiente
if (isset($_SESSION['usuario_rol'])) {
    if ($_SESSION['usuario_rol'] === 'Administrador') {
        header("Location: dashboard_admin.php");
        exit();
    } else {
        header("Location: dashboard_staff.php");
        exit();
    }
}

// Incluir el archivo de conexión saliendo de la carpeta PHP/
include '../CONEXION/conexiones.php';

$error_login = "";

// Procesar el formulario cuando el usuario presione el botón de ingresar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_usuario = trim($_POST['usuario']);
    $input_password = trim($_POST['password']);

    if (!empty($input_usuario) && !empty($input_password)) {
        try {
            // Consultar si el usuario existe y está activo
            $stmt = $conexion->prepare("SELECT id, usuario, password, nombre, rol FROM usuarios WHERE usuario = :usuario AND estado = 'Activo' LIMIT 1");
            $stmt->bindParam(':usuario', $input_usuario);
            $stmt->execute();
            
            $usuario_db = $stmt->fetch();

            // Validación de credenciales
            if ($usuario_db && $input_password === $usuario_db['password']) {
                // Guardar los datos del usuario en la sesión global
                $_SESSION['usuario_id'] = $usuario_db['id'];
                $_SESSION['usuario_login'] = $usuario_db['usuario'];
                $_SESSION['usuario_nombre'] = $usuario_db['nombre'];
                $_SESSION['usuario_rol'] = $usuario_db['rol'];

                // Redirección inteligente basada en el Rol del usuario
                if ($usuario_db['rol'] === 'Administrador') {
                    header("Location: dashboard_admin.php");
                } else {
                    header("Location: dashboard_staff.php");
                }
                exit();
            } else {
                $error_login = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error_login = "Error en el servidor: " . $e->getMessage();
        }
    } else {
        $error_login = "Por favor, complete todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<style>
    .cuerpo-login {
        background: url('<?php echo $url_login_img_global; ?>') no-repeat center center fixed !important;
        background-size: cover !important;
    }
</style>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/login.css">
</head>
<body class="cuerpo-login">

    <div class="tarjeta-login">
        <div class="encabezado-login">
            <img src="../IMG/logo.png" alt="Inventario OP Logo" class="logo-login" onerror="this.style.display='none'">
            <div class="encabezado-login">
                <img src="../IMG/logo.png" alt="Logo" class="logo-login" onerror="this.style.display='none'">
                <h2><?php echo htmlspecialchars($nombre_local_global); ?></h2>
                <p>RIF: <?php echo htmlspecialchars($rif_local_global); ?></p>
        </div>
            <p>Gestión y Control de Mercancía</p>
        </div>

        <?php if (!empty($error_login)): ?>
            <div class="alerta-error">
                ⚠️ <?php echo $error_login; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <div class="grupo-entrada">
                <label for="usuario">Nombre de Usuario</label>
                <input type="text" id="usuario" name="usuario" required autofocus>
            </div>

            <div class="grupo-entrada">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-ingresar">Iniciar Sesión</button>
            
            <div class="opcion-registro">
                <span>¿No tienes una cuenta? </span><a href="registro_usuario.php">Regístrate aquí</a>
            </div>
        </form>

        <div class="pie-login">
            <p>&copy; <?php echo date('Y'); ?> - Diseñado por Omar Pinto</p>
        </div>
    </div>

</body>
</html>