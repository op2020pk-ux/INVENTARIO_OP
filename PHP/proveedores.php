<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Control estricto: Solo el Administrador puede gestionar proveedores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Administrador') {
    header("Location: login.php");
    exit();
}

// Incluir la conexión
include '../CONEXION/conexiones.php';

// Obtener la tasa del dólar actual
$tasa_dolar = "0.00";
try {
    $stmt_tasa = $conexion->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar' LIMIT 1");
    $stmt_tasa->execute();
    $reg_tasa = $stmt_tasa->fetch();
    if ($reg_tasa) {
        $tasa_dolar = $reg_tasa['valor'];
    }
} catch (PDOException $e) {
    // Respaldo silencioso
}

$mensaje = "";
$tipo_mensaje = "";

// 1. PROCESAR EL REGISTRO DE UN NUEVO PROVEEDOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_guardar'])) {
    $rif = trim($_POST['rif']);
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    if (!empty($rif) && !empty($nombre)) {
        try {
            // Verificar si el RIF ya está registrado para evitar duplicados
            $check = $conexion->prepare("SELECT id FROM proveedores WHERE rif = :rif LIMIT 1");
            $check->execute([':rif' => $rif]);
            
            if ($check->fetch()) {
                $mensaje = "⚠️ El RIF introducido ya pertenece a un proveedor existente.";
                $tipo_mensaje = "error";
            } else {
                $sql = "INSERT INTO proveedores (rif, nombre, telefono, direccion, estado) 
                        VALUES (:rif, :nombre, :telefono, :direccion, 'Activo')";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([
                    ':rif' => $rif,
                    ':nombre' => $nombre,
                    ':telefono' => $telefono,
                    ':direccion' => $direccion
                ]);
                $mensaje = "✅ Proveedor registrado correctamente.";
                $tipo_mensaje = "exito";
            }
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ El RIF y la Razón Social son campos obligatorios.";
        $tipo_mensaje = "error";
    }
}

// 2. PROCESAR CAMBIO DE ESTADO (ACTIVAR / DESACTIVAR)
if (isset($_GET['cambiar_estado']) && isset($_GET['id'])) {
    $id_prov = intval($_GET['id']);
    $nuevo_estado = ($_GET['cambiar_estado'] === 'Activo') ? 'Inactivo' : 'Activo';
    
    try {
        $update = $conexion->prepare("UPDATE proveedores SET estado = :estado WHERE id = :id");
        $update->execute([':estado' => $nuevo_estado, ':id' => $id_prov]);
        header("Location: proveedores.php");
        exit();
    } catch (PDOException $e) {
        $mensaje = "Error al cambiar estado: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// 3. OBTENER EL LISTADO DE TODOS LOS PROVEEDORES
$lista_proveedores = [];
try {
    $lista_proveedores = $conexion->query("SELECT * FROM proveedores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error de tabla inexistente controlado
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Proveedores - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/proveedores.css">
</head>
<body>

    <div class="contenedor-sistema">
        
        <aside class="barra-lateral">
            <div class="logo-sistema">
                    <h2><?php echo htmlspecialchars($nombre_local_global ?? 'SIS-INVENTARIOS'); ?></h2>
                    <span style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($rif_local_global ?? ''); ?></span>
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
                <a href="proveedores.php" class="activo">🚚 Proveedores</a>
                <a href="productos.php">🍎 Productos</a>
                <a href="categorias.php">🏷️ Categorías</a>
                <a href="usuarios.php">👥 Usuarios</a>
                <a href="configuracion.php">⚙️ Configuración</a>

                <a href="desarrollador.php">💻 Desarrollador</a>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Área de Configuración de Entidades</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo number_format(floatval($tasa_dolar), 2); ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">🚚 Registro y Control de Proveedores</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="grilla-proveedores">
                    
                    <div class="bloque-formulario">
                        <h2>Agregar Proveedor</h2>
                        <form action="proveedores.php" method="POST" autocomplete="off">
                            <div class="grupo-campo">
                                <label for="rif">Documento de Identidad / RIF</label>
                                <input type="text" id="rif" name="rif" placeholder="Ej: J-12345678-0" required>
                            </div>

                            <div class="grupo-campo">
                                <label for="nombre">Razón Social / Nombre Comercial</label>
                                <input type="text" id="nombre" name="nombre" placeholder="Ej: Distribuidora Alimentos S.A." required>
                            </div>

                            <div class="grupo-campo">
                                <label for="telefono">Teléfono de Contacto</label>
                                <input type="text" id="telefono" name="telefono" placeholder="Ej: 0412-5555555">
                            </div>

                            <div class="grupo-campo">
                                <label for="direccion">Dirección Fiscal / Ubicación</label>
                                <input type="text" id="direccion" name="direccion" placeholder="Ej: La Victoria, Edo. Aragua">
                            </div>

                            <button type="submit" name="btn_guardar" class="btn-guardar">Registrar Proveedor</button>
                        </form>
                    </div>

                    <div class="bloque-tabla">
                        <h2>Proveedores Registrados</h2>
                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>RIF / ID</th>
                                        <th>Razón Social</th>
                                        <th>Teléfono</th>
                                        <th>Estado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_proveedores)): ?>
                                        <tr>
                                            <td colspan="5" class="tabla-vacia">No hay proveedores en el sistema.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_proveedores as $prov): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($prov['rif']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($prov['nombre']); ?></strong></td>
                                                <td><?php echo !empty($prov['telefono']) ? htmlspecialchars($prov['telefono']) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="badge-estado <?php echo isset($prov['estado']) ? strtolower($prov['estado']) : 'activo'; ?>">
                                                        <?php echo htmlspecialchars($prov['estado'] ?? 'Activo'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="proveedores.php?id=<?php echo $prov['id']; ?>&cambiar_estado=<?php echo htmlspecialchars($prov['estado'] ?? 'Activo'); ?>" 
                                                       class="btn-accion <?php echo (isset($prov['estado']) && $prov['estado'] === 'Inactivo') ? 'activar' : 'desactivar'; ?>">
                                                        <?php echo (isset($prov['estado']) && $prov['estado'] === 'Inactivo') ? 'Habilitar' : 'Inhabilitar'; ?>
                                                    </a>
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