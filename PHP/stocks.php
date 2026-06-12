<?php
// stocks.php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../CONEXION/conexiones.php';

// Variables globales por defecto para evitar errores de renderizado inicial
$nombre_local_global = "Establecimiento"; 
$rif_local_global = "V-00000000-0";

try {
    $stmt = $conexion->prepare("SELECT clave, valor FROM configuracion WHERE clave IN ('rif_negocio', 'direccion_negocio')");
    $stmt->execute();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if($row['clave'] == 'rif_negocio') $rif_local_global = $row['valor'];
    }
} catch(PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Stock - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/stocks.css">
</head>
<body>

    <div class="contenedor-sistema">
        
        <aside class="barra-lateral">
            <div class="logo-sistema">
                <h2>S</h2>
                <span style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 5px;">
                <?php echo htmlspecialchars($rif_local_global); ?></span>
                <span class="etiqueta-rol"><?php echo htmlspecialchars($_SESSION['usuario_rol']); ?></span>
            </div>
            <nav class="menu-navegacion">
                <a href="<?php echo ($_SESSION['usuario_rol'] === 'Administrador') ? 'dashboard_admin.php' : 'dashboard_staff.php'; ?>">📊 Dashboard</a>
                <a href="compras.php">🛒 Compras</a>
                <a href="recibidos.php">📦 Recibidos</a>
                <a href="devoluciones.php">🔄 Devoluciones</a>
                <a href="stocks.php" class="activo">📉 Stocks</a>
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
                    <span>Estado Actual de los Almacenes (Tiempo Real activado)</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa" id="live-tasa">💵 Tasa: Cargando...</span>
                    <span class="usuario-nombre">👤 <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">📉 Monitoreo y Control de Stocks</h1>

                <div id="contenedor-error"></div>

                <div class="paneles-resumen">
                    <div class="tarjeta-metrica m-total">
                        <div class="info">
                            <span class="numero" id="live-total-articulos">0</span>
                            <span class="etiqueta">Unidades en Stock</span>
                        </div>
                        <div class="icono">📦</div>
                    </div>

                    <div class="tarjeta-metrica" id="panel-critico">
                        <div class="info">
                            <span class="numero" id="live-articulos-criticos">0</span>
                            <span class="etiqueta">Productos Críticos (&lt;=10)</span>
                        </div>
                        <div class="icono">⚠️</div>
                    </div>

                    <div class="tarjeta-metrica m-valor">
                        <div class="info">
                            <span class="numero" id="live-valor-usd">$0.00</span>
                            <span class="etiqueta" id="live-valor-bs">Valoración en Bs: 0.00</span>
                        </div>
                        <div class="icono">💰</div>
                    </div>
                </div>

                <div class="bloque-existencias">
                    <h2>Inventario Físico Disponible</h2>
                    <div class="tabla-responsiva">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción del Producto</th>
                                    <th>Precio Compra</th>
                                    <th>Precio Venta (USD)</th>
                                    <th>Precio Venta (Bs)</th>
                                    <th style="text-align: center;">Cantidad Disponible</th>
                                    <th style="text-align: center;">Estado / Nivel</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-stock-body">
                                <tr>
                                    <td colspan="7" class="tabla-vacia">Sincronizando con los almacenes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function actualizarStock() {
            fetch('api_stocks.php')
                .then(response => response.json())
                .then(data => {
                    if(data.error) {
                        document.getElementById('contenedor-error').innerHTML = `<div class="alerta error">${data.error}</div>`;
                        return;
                    }

                    // 1. Actualizar Indicadores de las Tarjetas
                    document.getElementById('live-tasa').innerText = `💵 Tasa: ${data.tasa_dolar} Bs`;
                    document.getElementById('live-total-articulos').innerText = data.total_articulos;
                    document.getElementById('live-articulos-criticos').innerText = data.articulos_criticos;
                    document.getElementById('live-valor-usd').innerText = `$${data.valor_total_usd}`;
                    document.getElementById('live-valor-bs').innerText = `Valoración en Bs: ${data.valor_total_bs}`;

                    // Cambiar color de la tarjeta crítica dinámicamente
                    const panelCritico = document.getElementById('panel-critico');
                    if(data.articulos_criticos > 0) {
                        panelCritico.className = "tarjeta-metrica m-alerta";
                    } else {
                        panelCritico.className = "tarjeta-metrica m-limpio";
                    }

                    // 2. Reconstruir las filas de la tabla
                    const tbody = document.getElementById('tabla-stock-body');
                    if(data.productos.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="7" class="tabla-vacia">No hay productos registrados en el inventario.</td></tr>`;
                        return;
                    }

                    let filasHTML = '';
                    data.productos.forEach(prod => {
                        let claseStock = '';
                        let textoStock = '';
                        const stockActual = parseInt(prod.stock);

                        if (stockActual === 0) {
                            claseStock = 'agotado';
                            textoStock = 'Sin Existencias';
                        } else if (stockActual <= 10) {
                            claseStock = 'bajo';
                            textoStock = 'Inventario Bajo';
                        } else {
                            claseStock = 'optimo';
                            textoStock = 'Óptimo';
                        }

                        filasHTML += `
                            <tr>
                                <td><span class="badge-codigo">${prod.codigo_barras || prod.id}</span></td>
                                <td><strong>${prod.nombre}</strong></td>
                                <td>$${parseFloat(prod.precio_compra).toFixed(2)}</td>
                                <td class="txt-venta">$${parseFloat(prod.precio_venta).toFixed(2)}</td>
                                <td class="txt-bs">${parseFloat(prod.precio_venta_bs).toFixed(2)} Bs</td>
                                <td style="text-align: center; font-weight: bold; font-size: 1.1rem;">${stockActual}</td>
                                <td style="text-align: center;">
                                    <span class="badge-estado ${claseStock}">${textoStock}</span>
                                end_span</td>
                            </tr>
                        `;
                    });

                    tbody.innerHTML = filasHTML;
                })
                .catch(err => {
                    console.error("Error cargando stocks: ", err);
                });
        }

        // Ejecutar inmediatamente al abrir el módulo
        actualizarStock();

        // Configurar el temporizador automático para ejecutarse cada 5 segundos (5000 ms)
        setInterval(actualizarStock, 5000);
    </script>
</body>
</html>