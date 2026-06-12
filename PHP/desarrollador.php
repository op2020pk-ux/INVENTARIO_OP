<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../CONEXION/conexiones.php';

// Obtener tasa del dólar actual para la cabecera
$tasa_dolar = 1.00;
try {
    $stmt_tasa = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar' LIMIT 1");
    $stmt_tasa->execute();
    $reg_tasa = $stmt_tasa->fetch();
    if ($reg_tasa) {
        $tasa_dolar = floatval($reg_tasa['valor']);
    }
} catch (PDOException $e) {
    // Respaldo
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desarrollador del Sistema - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/desarrollador.css">
</head>
<body>

    <div class="contenedor-sistema">
        
        <aside class="barra-lateral">
            <div class="logo-sistema">
                <h2><?php echo htmlspecialchars($nombre_local_global ?? 'SIS-INVENTARIOS'); ?></h2>
                <span style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($rif_local_global ?? ''); ?></span>
                <span class="etiqueta-rol"><?php echo $_SESSION['usuario_rol']; ?></span>
            </div>
            <nav class="menu-navegacion">
                <a href="<?php echo ($_SESSION['usuario_rol'] === 'Administrador') ? 'dashboard_admin.php' : 'dashboard_staff.php'; ?>">📊 Dashboard</a>
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
                <a href="configuracion.php">⚙️ Configuración</a>
                
                <a href="desarrollador.php" class="activo">💻 Desarrollador</a>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Ficha de Autoría e Ingeniería de Software</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo number_format($tasa_dolar, 2); ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">💻 Perfil del Desarrollador Principal</h1>

                <div class="tarjeta-portafolio">
                    
                    <div class="perfil-header">
                        <div class="contenedor-avatar">
                            <img src="../IMG/desarrollador.jpg" alt="Foto Desarrollador Omar Pinto" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3135/3135715.png';">
                        </div>
                        <div class="perfil-titulos">
                            <h2>Omar Pinto</h2>
                            <p class="subtitulo">Desarrollador de Software & Estudiante de Informática</p>
                            <span class="badge-tecnologia">Python</span>
                            <span class="badge-tecnologia">PHP / MySQL</span>
                            <span class="badge-tecnologia">SQLite3</span>
                            <span class="badge-tecnologia">CustomTkinter</span>
                        </div>
                    </div>

                    <div class="perfil-detalles">
                        
                        <div class="columna-info">
                            <h3>📋 Resumen Profesional</h3>
                            <p>Desarrollador especializado en el diseño e implementación de sistemas de información relacionales, arquitecturas de bases de datos y desarrollo de aplicaciones de escritorio y entornos web.<br> Enfocado en optimizar procesos comerciales, automatización de almacenes y control de inventarios mediante código estructurado, eficiente y seguro.</p>
                            
                            <h3 style="margin-top: 20px;">🎓 Formación Académica</h3>
                            <ul class="lista-perfil">
                                
                                <li>✅ Sistema de Control de Inventarios y Almacenes</li> <br>
                                <li>✅ Sistema de Gestión de Compras y Ventas</li> <br>
                                <li>✅ Sistema Biblotecario Caminos de los Libertadores</li> <br>
                                <li>✅ Sistema Bio Control De Finanza PRO OP V-3.1.0 </li><br>
                                <li><strong>Agradesimiento:</strong></li><br>
                                <li>🙏 Agradezco a mis profesores, compañeros y colaboradores por su apoyo y guía en mi formación como desarrollador. Su experiencia y conocimientos han sido fundamentales para mi crecimiento profesional.</li>
                            
                            <div class="contenedor-avatar">
                                <img src="../IMG/op1.jpeg" alt="Logo del Desarrollador y sistemas" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3135/3135715.png';">
                            </div>
                            
                        </ul>
                        </div>

                        <div class="columna-info">
                            <h3>🛠️ Especialidades Técnicas</h3>
                            <div class="habilidad-item">
                                <span>Backend (Python / PHP / SQL)</span>
                                <div class="barra-progreso"><div class="progreso" style="width: 90%;"></div></div>
                            </div>
                            <div class="habilidad-item">
                                <span>Bases de Datos (MySQL / SQLite3 / Access)</span>
                                <div class="barra-progreso"><div class="progreso" style="width: 88%;"></div></div>
                            </div>
                            <div class="habilidad-item">
                                <span>Frontend & UI Design (CSS / CustomTkinter)</span>
                                <div class="barra-progreso"><div class="progreso" style="width: 85%;"></div></div>
                            </div>

                            <h3 style="margin-top: 25px;">🤝 Contacto</h3>
                            <p style="font-size: 0.9rem; color: #475569; line-height: 1.4;">

                                <strong>✅ Desarrollador Técnico:</strong> Omar Pinto<br>
                                <strong>✅ Whastapp:</strong> +58 412-367-9938<br>
                                <strong>✅ GitHub:</strong> https://github.com/op2020pk-ux <br>
                                <strong>✅ Correo Electrónico:</strong> <a href="mailto:op2020pk@gmail.com">op2020pk@gmail.com</a><br><br>

                            <h3 style="margin-top: 25px;">🤝 Centro de Formación</h3>
                            <p style="font-size: 0.9rem; color: #475569; line-height: 1.4;">

                                <strong>✅ Aldea Universitaria:</strong> UPTA Aragua, Misión Sucre<br>
                                <strong>✅ Universidad Politécnica Territorial de Aragua</strong><br>
                                <strong>✅ Ubicación:</strong> La Victoria, Aragua, Venezuela<br>

                            <h3 style="margin-top: 25px;">🤝 Herramientas y Tecnologías mas usadas:</h3>
                            <p style="font-size: 0.9rem; color: #475569; line-height: 1.4;">
                                <strong>✅ Python:</strong><br>
                                <strong>✅ SQL</strong><br>
                                <strong>✅ PHP</strong><br>
                                <strong>✅ CSS</strong><br>
                                <strong>✅ C</strong><br>
                                <strong>✅ ACCESS</strong><br>

                            <h3 style="margin-top: 25px;">🤝 Repositorios en GitHub:</h3>
                            <p style="font-size: 0.9rem; color: #475569; line-height: 1.4;">
                                <strong>✅ Bio Control De Finanza PRO OP V-3.1.0:</strong><br>
                                <strong>✅ Sistema de Control de Inventarios y Almacenes:</strong><br>
                                <strong>✅ Sistema de Gestión de Compras y Ventas:</strong><br>
                                <strong>✅ Sistema Biblotecario Caminos de los Libertadores:</strong><br>

                            <h3 style="margin-top: 25px;">🤝 Profesor Guía:</h3>
                            <p style="font-size: 0.9rem; color: #475569; line-height: 1.4;">
                                <strong>✅Profesor Guía:</strong> <br>
                                <strong>✅Nombre:</strong> Ing. Jorge Acosta <br>
                                <strong>✅Ubicación académica:</strong> (Misión Sucre - UPTA Aragua)<br>

                            </p>
                        </div>

                    </div>

                    <div class="perfil-footer">
                        <p>© 2026 Omar Pinto · Desarrollador Independiente · La Victoria, Aragua, Venezuela.</p>
                    </div>

                </div>
            </div>
        </main>
    </div>

</body>
</html>