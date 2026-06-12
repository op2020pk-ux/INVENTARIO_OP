<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

// Control estricto: Solo el Administrador puede gestionar las categorías
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Administrador') {
    header("Location: login.php");
    exit();
}

// Incluir la conexión a la base de datos
include '../CONEXION/conexiones.php';

// Obtener la tasa del dólar actual para la cabecera
$tasa_dolar = "1.00";
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

// Variables para el modo edición
$modo_edicion = false;
$id_categoria_editar = "";
$nombre_categoria_editar = "";

// 1. PROCESAR EL REGISTRO O ACTUALIZACIÓN DE UNA CATEGORÍA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_guardar'])) {
    $nombre = trim($_POST['nombre']);
    $id_categoria = isset($_POST['id_categoria']) ? intval($_POST['id_categoria']) : 0;

    if (!empty($nombre)) {
        try {
            if ($id_categoria > 0) {
                // MODO ACTUALIZACIÓN (EDITAR)
                if ($id_categoria === 1) {
                    $mensaje = "❌ Error Crítico: No se permite modificar la categoría base de respaldo.";
                    $tipo_mensaje = "error";
                } else {
                    $sql_up = "UPDATE categorias SET nombre = :nombre WHERE id = :id";
                    $stmt_up = $conexion->prepare($sql_up);
                    $stmt_up->execute([
                        ':nombre' => $nombre,
                        ':id' => $id_categoria
                    ]);
                    $mensaje = "✅ Categoría actualizada de forma exitosa.";
                    $tipo_mensaje = "exito";
                }
            } else {
                // MODO REGISTRO (NUEVA)
                // Verificar si ya existe una categoría con el mismo nombre para evitar duplicados
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM categorias WHERE LOWER(nombre) = LOWER(:nombre)");
                $stmt_check->execute([':nombre' => $nombre]);
                
                if ($stmt_check->fetchColumn() > 0) {
                    $mensaje = "⚠️ La categoría '" . htmlspecialchars($nombre) . "' ya se encuentra registrada.";
                    $tipo_mensaje = "error";
                } else {
                    $sql_ins = "INSERT INTO categorias (nombre) VALUES (:nombre)";
                    $stmt_ins = $conexion->prepare($sql_ins);
                    $stmt_ins->execute([':nombre' => $nombre]);
                    $mensaje = "✅ Nueva categoría registrada de manera exitosa.";
                    $tipo_mensaje = "exito";
                }
            }
        } catch (PDOException $e) {
            $mensaje = "Error en la operación de datos: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ El nombre de la categoría es un campo obligatorio.";
        $tipo_mensaje = "error";
    }
}

// 2. CAPTURAR DATOS PARA EL MODO EDICIÓN (VÍA GET)
if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    if ($id_editar === 1) {
        $mensaje = "❌ Error: La categoría de respaldo del sistema no se puede editar.";
        $tipo_mensaje = "error";
    } else {
        try {
            $stmt_edit = $conexion->prepare("SELECT * FROM categorias WHERE id = :id LIMIT 1");
            $stmt_edit->execute([':id' => $id_editar]);
            $cat_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
            if ($cat_edit) {
                $modo_edicion = true;
                $id_categoria_editar = $cat_edit['id'];
                $nombre_categoria_editar = $cat_edit['nombre'];
            }
        } catch (PDOException $e) {
            $mensaje = "Error al cargar datos de edición: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 3. PROCESAR LA ELIMINACIÓN DE UNA CATEGORÍA (VÍA GET)
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    
    if ($id_eliminar === 1) {
        $mensaje = "❌ Restricción del Sistema: No se puede eliminar la categoría base de respaldo.";
        $tipo_mensaje = "error";
    } else {
        try {
            // El TRIGGER de tu base de datos reubicará automáticamente los productos a la ID 1 antes de borrar
            $sql_del = "DELETE FROM categorias WHERE id = :id";
            $stmt_del = $conexion->prepare($sql_del);
            $stmt_del->execute([':id' => $id_eliminar]);
            
            $mensaje = "✅ Categoría eliminada con éxito. La mercancía asociada fue movida a la categoría general.";
            $tipo_mensaje = "exito";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar categoría: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 4. OBTENER LISTADO COMPLETO DE CATEGORÍAS REGISTRADAS
$lista_categorias = [];
try {
    $lista_categorias = $conexion->query("SELECT * FROM categorias ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Respaldo silencioso
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/proveedores.css">
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
                <a href="categorias.php" class="activo">🏷️ Categorías</a>
                <a href="usuarios.php">👥 Usuarios</a>
                <a href="configuracion.php">⚙️ Configuración</a>
            </nav>
        </aside>

        <main class="contenido-principal">
            
            <header class="cabecera-superior">
                <div class="titulo-cabecera">
                    <span>Panel de Clasificación de Mercancía</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">静态 Tasa: <?php echo number_format(floatval($tasa_dolar), 2); ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">🏷️ Control de Categorías</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>" style="padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; <?php echo ($tipo_mensaje === 'exito') ? 'background-color: #d1fae5; color: #065f46;' : 'background-color: #fee2e2; color: #991b1b;'; ?>">
                        <?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>

                <div class="layout-ventas" style="display: flex; gap: 20px;">
                    
                    <div class="tarjeta-formulario" style="flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <h2><?php echo $modo_edicion ? "✏️ Editar Categoría" : "✨ Nueva Categoría"; ?></h2>
                        <br>
                        <form action="categorias.php" method="POST" autocomplete="off">
                            
                            <?php if ($modo_edicion): ?>
                                <input type="hidden" name="id_categoria" value="<?php echo $id_categoria_editar; ?>">
                            <?php endif; ?>

                            <div class="grupo-campo" style="margin-bottom: 15px;">
                                <label for="nombre" style="display: block; margin-bottom: 5px; color: #64748b; font-weight: 600;">Nombre de la Categoría:</label>
                                <input type="text" name="nombre" id="nombre" required 
                                       placeholder="Ej: Víveres, Bebidas, Limpieza..." 
                                       value="<?php echo htmlspecialchars($nombre_categoria_editar); ?>"
                                       style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </div>

                            <button type="submit" name="btn_guardar" class="btn-cobrar" style="width: 100%; padding: 12px; border: none; border-radius: 4px; background: #1e3a8a; color: white; font-weight: bold; cursor: pointer; transition: background 0.2s;">
                                <?php echo $modo_edicion ? "Guardar Cambios" : "Registrar Categoría"; ?>
                            </button>

                            <?php if ($modo_edicion): ?>
                                <a href="categorias.php" style="display: block; text-align: center; margin-top: 10px; color: #64748b; text-decoration: none; font-size: 0.9rem;">❌ Cancelar Edición</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="tarjeta-tabla" style="flex: 2; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <h2>Categorías del Sistema</h2>
                        <br>
                        <div class="tabla-responsiva">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 12px; text-align: left; color: #64748b;">ID</th>
                                        <th style="padding: 12px; text-align: left; color: #64748b;">Nombre de la Clasificación</th>
                                        <th style="padding: 12px; text-align: center; color: #64748b;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_categorias)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; padding: 20px; color: #94a3b8;">No hay categorías registradas en el sistema.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_categorias as $cat): ?>
                                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                                <td style="padding: 12px;"><span class="badge-id" style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #475569;">#<?php echo $cat['id']; ?></span></td>
                                                <td style="padding: 12px;">
                                                    <strong><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                                                    <?php if ($cat['id'] === 1): ?>
                                                        <small style="color: #3b82f6; display: block; font-size: 0.75rem;">(Base Sistema - Protegida)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 12px; text-align: center;">
                                                    <?php if ($cat['id'] !== 1): ?>
                                                        <a href="categorias.php?editar=<?php echo $cat['id']; ?>" class="btn-accion" style="background-color: #3b82f6; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; margin-right: 5px;">Editar</a>
                                                        <a href="categorias.php?eliminar=<?php echo $cat['id']; ?>" class="btn-accion" style="background-color: #ef4444; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8rem;" onclick="return confirm('¿Está seguro de eliminar esta categoría? Todos los productos asociados serán reubicados automáticamente en la categoría de respaldo.');">Eliminar</a>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8; font-size: 0.85rem; font-style: italic;">Inalterable</span>
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
            </div>
        </main>
    </div>

</body>
</html>