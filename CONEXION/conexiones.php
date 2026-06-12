<?php
// ======================================================================
// PROYECTO: Inventario_OP
// ARCHIVO: conexiones.php
// DESCRIPCIÓN: Conexión centralizada a la base de datos mediante PDO.
// ======================================================================

$servidor = "127.0.0.1";
$usuario  = "root";
$password = "";
$bd       = "DB_inventario_op";

try {
    // Configuración de la conexión PDO con codificación UTF-8
    $conexion = new PDO("mysql:host=$servidor;dbname=$bd;charset=utf8", $usuario, $password);
    
    // Activar el manejo de excepciones para reportar errores de SQL de forma segura
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar el modo de obtención por defecto a Arreglo Asociativo
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si la conexión falla, detiene el sistema y muestra el error (ideal para desarrollo)
    die("Error crítico de conexión en el sistema: " . $e->getMessage());
}


// ======================================================================
// CONFIGURACIÓN GLOBAL DINÁMICA
// ======================================================================
// Valores por defecto en caso de que la tabla esté vacía
$nombre_local_global = "Mi Negocio OP";
$rif_local_global = "J-00000000-0";
$url_login_img_global = "../IMG/Quiosco_1.png";

try {
    // Buscar todos los registros de la tabla configuración
    $stmt_global = $conexion->prepare("SELECT clave, valor FROM configuracion");
    $stmt_global->execute();
    $datos_config = $stmt_global->fetchAll(PDO::FETCH_ASSOC);

    foreach ($datos_config as $reg) {
        if ($reg['clave'] === 'nombre_local') {
            $nombre_local_global = $reg['valor'];
        }
        if ($reg['clave'] === 'rif_local') {
            $rif_local_global = $reg['valor'];
        }
        if ($reg['clave'] === 'url_login_img') {
            $url_login_img_global = $reg['valor'];
        }
    }
} catch (PDOException $e) {
    // Si la tabla no existe aún, se usan los valores por defecto sin romper el sistema
}

?>