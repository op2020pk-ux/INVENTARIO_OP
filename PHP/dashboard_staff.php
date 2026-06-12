<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Si no hay sesión iniciada o el rol no es Staff, lo rebota al login por seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header("Location: login.php");
    exit();
}

// Incluir la conexión saliendo de la carpeta PHP/
include '../CONEXION/conexiones.php';

// Obtener la tasa del dólar actual desde la tabla configuracion
$tasa_dolar = "0.00";
try {
    $stmt_tasa = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar' LIMIT 1");
    $stmt_tasa->execute();
    $reg_tasa = $stmt_tasa->fetch();
    if ($reg_tasa) {
        $tasa_dolar = $reg_tasa['valor'];
    }
} catch (PDOException $e) {
    // Si falla, se queda con el valor por defecto
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Empleado - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/dashboard_staff.css">
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
                <a href="dashboard_staff.php" class="activo">📊 Dashboard</a>
                <a href="compras.php">🛒 Compras</a>
                <a href="recibidos.php">📦 Recibidos</a>
                <a href="devoluciones.php">🔄 Devoluciones</a>
                <a href="stocks.php">📉 Stocks</a>
                <a href="ventas.php">💵 Ventas</a>

                <a href="desarrollador.php">💻 Desarrollador</a>
                
                </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Sistema web de inventarios (Operaciones)</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo $tasa_dolar; ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Empleado'; ?> (Staff)</span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">Panel de Control Operativo</h1>
                
                <div class="grilla-tarjetas">
                    
                    <div class="tarjeta-indicador azul">
                        <div class="icono-tarjeta">📋</div>
                        <div class="info-tarjeta">
                            <h3>Órdenes de Compra</h3>
                            <p class="contador">0</p>
                        </div>
                    </div>

                    <div class="tarjeta-indicador turquesa">
                        <div class="icono-tarjeta">📦</div>
                        <div class="info-tarjeta">
                            <h3>Compras Recibidas</h3>
                            <p class="contador">0</p>
                        </div>
                    </div>

                    <div class="tarjeta-indicador amarillo">
                        <div class="icono-tarjeta">🔄</div>
                        <div class="info-tarjeta">
                            <h3>Devoluciones</h3>
                            <p class="contador">0</p>
                        </div>
                    </div>

                    <div class="tarjeta-indicador oscuro">
                        <div class="icono-tarjeta">💵</div>
                        <div class="info-tarjeta">
                            <h3>Ventas Realizadas</h3>
                            <p class="contador">0</p>
                        </div>
                    </div>

                </div> </div>

        </main>
    </div>

</body>
</html>