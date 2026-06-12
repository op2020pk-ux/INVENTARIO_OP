<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include '../CONEXION/conexiones.php';

// Obtener tasa del dólar actual
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

$mensaje = "";
$tipo_mensaje = "";

// PROCESAR REGISTRO DE UN NUEVO CLIENTE (Vía AJAX / POST tradicional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_cliente_ajax'])) {
    $nombre_cli = trim($_POST['nombre_cliente']);
    $cedula_cli = trim($_POST['cedula_cliente']);
    $telefono_cli = trim($_POST['telefono_cliente']);

    if (!empty($nombre_cli) && !empty($cedula_cli)) {
        try {
            // Verificar si el cliente ya existe por cédula
            $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM clientes WHERE cedula = :cedula");
            $stmt_check->execute([':cedula' => $cedula_cli]);
            
            if ($stmt_check->fetchColumn() == 0) {
                $stmt_cli = $conexion->prepare("INSERT INTO clientes (nombre_apellido, cedula, telefono) VALUES (:nombre, :cedula, :telefono)");
                $stmt_cli->execute([
                    ':nombre'   => $nombre_cli,
                    ':cedula'   => $cedula_cli,
                    ':telefono' => $telefono_cli
                ]);
                $mensaje = "👥 Cliente registrado correctamente en el sistema.";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "ℹ️ El cliente con la cédula ingresada ya se encuentra registrado.";
                $tipo_mensaje = "exito";
            }
        } catch (PDOException $e) {
            try {
                $conexion->exec("CREATE TABLE IF NOT EXISTS clientes (id INT AUTO_INCREMENT PRIMARY KEY, nombre_apellido VARCHAR(150), cedula VARCHAR(30) UNIQUE, telefono VARCHAR(30))");
                $stmt_cli = $conexion->prepare("INSERT INTO clientes (nombre_apellido, cedula, telefono) VALUES (:nombre, :cedula, :telefono)");
                $stmt_cli->execute([
                    ':nombre'   => $nombre_cli,
                    ':cedula'   => $cedula_cli,
                    ':telefono' => $telefono_cli
                ]);
                $mensaje = "👥 Tabla creada y cliente registrado con éxito.";
                $tipo_mensaje = "exito";
            } catch (Exception $ex) {
                $mensaje = "Error al guardar cliente: " . $ex->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// PROCESAR ANULACIÓN / DEVOLUCIÓN DE UNA VENTA ANTERIOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anular_venta'])) {
    $id_venta = intval($_POST['id_venta']);
    if ($id_venta > 0) {
        try {
            $conexion->beginTransaction();
            $stmt_v = $conexion->prepare("SELECT id_producto, cantidad FROM ventas WHERE id = :id LIMIT 1");
            $stmt_v->execute([':id' => $id_venta]);
            $venta_info = $stmt_v->fetch();

            if ($venta_info) {
                $stmt_restaurar = $conexion->prepare("UPDATE productos SET stock_actual = stock_actual + :cantidad WHERE id = :id_prod");
                $stmt_restaurar->execute([
                    ':cantidad' => $venta_info['cantidad'],
                    ':id_prod'  => $venta_info['id_producto']
                ]);

                $stmt_del = $conexion->prepare("DELETE FROM ventas WHERE id = :id");
                $stmt_del->execute([':id' => $id_venta]);

                $conexion->commit();
                $mensaje = "🔄 Venta anulada con éxito. Unidades devueltas al inventario.";
                $tipo_mensaje = "exito";
            } else {
                $conexion->rollBack();
            }
        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "Error al anular: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// PROCESAR REGISTRO DE MULTI-VENTA (EL LOTE COMPLETO DEL CARRITO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_venta'])) {
    $carrito_json = $_POST['carrito_datos'] ?? '';
    $productos_carrito = json_decode($carrito_json, true);
    $id_usuario = $_SESSION['usuario_id'];
    $cedula_cliente = trim($_POST['cedula_cliente_venta'] ?? 'V-00000000');

    if (!empty($productos_carrito) && is_array($productos_carrito)) {
        try {
            $conexion->beginTransaction();
            $errores_stock = [];

            foreach ($productos_carrito as $item) {
                $id_producto = intval($item['id']);
                $cantidad = intval($item['cantidad']);

                $stmt_prod = $conexion->prepare("SELECT nombre, precio_venta_usd, stock_actual FROM productos WHERE id = :id LIMIT 1");
                $stmt_prod->execute([':id' => $id_producto]);
                $producto = $stmt_prod->fetch();

                if ($producto) {
                    if ($producto['stock_actual'] >= $cantidad) {
                        $total_usd = $cantidad * floatval($producto['precio_venta_usd']);

                        $stmt_restar = $conexion->prepare("UPDATE productos SET stock_actual = stock_actual - :cantidad WHERE id = :id");
                        $stmt_restar->execute([':cantidad' => $cantidad, ':id' => $id_producto]);

                        $stmt_venta = $conexion->prepare("INSERT INTO ventas (id_producto, id_usuario, cantidad, total, fecha) 
                                                         VALUES (:id_producto, :id_usuario, :cantidad, :total, NOW())");
                        $stmt_venta->execute([
                            ':id_producto' => $id_producto,
                            ':id_usuario'  => $id_usuario,
                            ':cantidad'    => $cantidad,
                            ':total'       => $total_usd
                        ]);
                    } else {
                        $errores_stock[] = "⚠️ Stock insuficiente de '".$producto['nombre']."'. Disponibles: ".$producto['stock_actual'];
                    }
                }
            }

            if (empty($errores_stock)) {
                $conexion->commit();
                $mensaje = "💵 ¡Venta múltiple registrada! Facturación completada con éxito.";
                $tipo_mensaje = "exito";
                
                // Limpiar carrito en el cliente mediante un script
                echo "<script>sessionStorage.removeItem('carrito_ventas');</script>";
            } else {
                $conexion->rollBack();
                $mensaje = implode("<br>", $errores_stock);
                $tipo_mensaje = "error";
            }
        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "Error en el sistema: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "⚠️ El carrito de ventas está vacío. Agregue productos primero.";
        $tipo_mensaje = "error";
    }
}

// OBTENER PRODUCTOS ACTIVOS
$productos = [];
$ventas_del_dia = [];

try {
    $productos = $conexion->query("SELECT id, nombre, codigo_barras, precio_venta_usd, stock_actual, imagen FROM productos WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Lista de ventas generales del día (Historial inferior fijo)
    $sql_ventas = "SELECT v.*, p.nombre AS producto_nombre, p.imagen AS producto_imagen, u.nombre AS usuario_nombre 
                   FROM ventas v
                   LEFT JOIN productos p ON v.id_producto = p.id
                   LEFT JOIN usuarios u ON v.id_usuario = u.id
                   ORDER BY v.fecha DESC LIMIT 10";
    $ventas_del_dia = $conexion->query($sql_ventas)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Control básico
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja de Ventas - Inventario OP</title>
    <link rel="stylesheet" href="../CSS/ventas.css">
    <style>
        /* Pequeño parche para el botón agregar dentro de tu mismo diseño */
        .btn-agregar-carrito {
            width: 100%;
            padding: 10px;
            background-color: #f59e0b;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.95rem;
            cursor: pointer;
            margin-bottom: 12px;
            transition: background 0.2s;
        }
        .btn-agregar-carrito:hover {
            background-color: #d97706;
        }
        .btn-eliminar-item {
            background: #ef4444;
            color: white;
            border: none;
            padding: 3px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
        }
    </style>
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
                <a href="ventas.php" class="activo">💵 Ventas</a>
                
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
                    <span>Módulo de Facturación Rápida (Caja)</span>
                </div>
                <div class="perfil-usuario">
                    <span class="badge-tasa">💵 Tasa: <?php echo number_format($tasa_dolar, 2); ?> Bs</span>
                    <span class="usuario-nombre">👤 <?php echo $_SESSION['usuario_nombre']; ?></span>
                    <a href="logout.php" class="btn-cerrar-sesion">Salir</a>
                </div>
            </header>

            <div class="area-trabajo">
                <h1 class="titulo-pagina">💵 Facturación y Salida de Productos</h1>

                <?php if (!empty($mensaje)): ?>
                    <div class="alerta <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
                <?php endif; ?>

                <div class="layout-ventas">
                    
                    <div class="bloque-formulario">
                        <h2>Registrar Venta</h2>
                        
                        <div class="buscador-rapido">
                            <label style="display:block; font-size:0.8rem; font-weight:bold; color:#475569; margin-bottom:4px;">🔍 Búsqueda Rápida (Nombre o Código):</label>
                            <input type="text" id="txt_buscar" placeholder="Escribe para buscar automáticamente..." oninput="filtrarProductos()">
                        </div>

                        <form id="form_venta" action="ventas.php" method="POST" autocomplete="off" onsubmit="return enviarFormularioConCarrito()">
                            
                            <input type="hidden" name="cedula_cliente_venta" id="cedula_cliente_venta" value="V-00000000">
                            <input type="hidden" name="carrito_datos" id="carrito_datos">

                            <div class="grupo-campo">
                                <label for="id_producto">Producto Seleccionado:</label>
                                <select name="id_producto" id="id_producto" onchange="calcularPrecios()">
                                    <option value="" data-precio="0">-- Seleccione el artículo --</option>
                                    <?php foreach ($productos as $prod): ?>
                                        <option value="<?php echo $prod['id']; ?>" 
                                                data-precio="<?php echo $prod['precio_venta_usd']; ?>"
                                                data-codigo="<?php echo htmlspecialchars($prod['codigo_barras'] ?? ''); ?>"
                                                data-nombre="<?php echo htmlspecialchars(strtolower($prod['nombre'])); ?>"
                                                data-imagen="<?php echo !empty($prod['imagen']) ? "../IMG/".$prod['imagen'] : '../IMG/default.png'; ?>">
                                            <?php echo $prod['nombre']; ?> ($<?php echo number_format($prod['precio_venta_usd'], 2); ?> - Disp: <?php echo $prod['stock_actual']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grupo-campo">
                                <label for="cantidad">Cantidad:</label>
                                <input type="number" name="cantidad" id="cantidad" min="1" value="1" oninput="calcularPrecios()">
                            </div>

                            <button type="button" class="btn-agregar-carrito" onclick="agregarArticuloAlCarrito()">➕ Agregar a la Lista</button>

                            <div class="pantalla-totales">
                                <div class="total-renglon">Total en USD: <span id="total_usd">$0.00</span></div>
                                <div class="total-renglon bs">Total en Bs: <span id="total_bs">0.00 Bs</span></div>
                            </div>

                            <button type="submit" name="registrar_venta" class="btn-cobrar">Confirmar e Imprimir</button>
                            
                            <div class="fila-botones-extras">
                                <button type="button" class="btn-extra azul" onclick="abrirModalCliente()">👥 Registrar Cliente</button>
                                <button type="button" class="btn-extra rojo" onclick="abrirFacturaDigital()">👁️ Ver Ticket En Vivo</button>
                            </div>
                        </form>
                    </div>

                    <div class="bloque-tabla">
                        <h2>Registro de Ventas del Turno (Ticket Actual)</h2>
                        <div class="tabla-responsiva">
                            <table id="tabla_carrito_ventas">
                                <thead>
                                    <tr>
                                        <th>Imagen</th>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Cant.</th>
                                        <th>Total ($)</th>
                                        <th>Total (Bs)</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpo_carrito_ventas">
                                    <tr>
                                        <td colspan="7" class="tabla-vacia">No se registran transacciones el día de hoy.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <div id="modal_cliente" class="modal-fondo">
        <div class="modal-contenedor">
            <span class="modal-cerrar" onclick="cerrarModalCliente()">&times;</span>
            <h3>👥 Ficha de Registro de Clientes</h3>
            <hr style="margin-bottom:15px; border:1px solid #f1f5f9;">
            <form action="ventas.php" method="POST">
                <div class="grupo-campo">
                    <label>Nombre y Apellido:</label>
                    <input type="text" name="nombre_cliente" required placeholder="Ej: Carlos Mendoza">
                </div>
                <div class="grupo-campo">
                    <label>Cédula de Identidad / RIF:</label>
                    <input type="text" id="cedula_campo" name="cedula_cliente" required placeholder="Ej: V-12345678" oninput="vincularCedula(this.value)">
                </div>
                <div class="grupo-campo">
                    <label>Teléfono de Contacto:</label>
                    <input type="text" name="telefono_cliente" placeholder="Ej: 0412-5555555">
                </div>
                <button type="submit" name="registrar_cliente_ajax" class="btn-cobrar" style="background:#2563eb;">Guardar Cliente en Base de Datos</button>
            </form>
        </div>
    </div>

    <div id="modal_factura" class="modal-fondo">
        <div class="factura-recibo">
            <span class="modal-cerrar" onclick="cerrarFacturaDigital()">&times;</span>
            <div style="text-align: center; margin-bottom: 10px;">
                <h4 style="text-transform: uppercase; font-size: 1.1rem;"><?php echo htmlspecialchars($nombre_local_global ?? 'QUIOSCO INVENTARIO OP'); ?></h4>
                <p style="font-size: 0.8rem; color: #475569;">RIF: <?php echo htmlspecialchars($rif_local_global ?? 'J-00000000-0'); ?></p>
                <p style="font-size: 0.75rem; color: #64748b;">La Victoria, Estado Aragua, Venezuela</p>
            </div>
            <hr style="border-top: 1px dashed #cbd5e1; margin: 10px 0;">
            
            <div style="font-size: 0.8rem; line-height: 1.5; color: #1e293b;">
                <p><strong>Atendido por:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></p>
                <p><strong>Cliente CI:</strong> <span id="fact_cliente_ci">V-00000000</span></p>
                <p><strong>Fecha/Hora:</strong> <?php echo date('d-m-Y g:i a'); ?></p>
            </div>
            <hr style="border-top: 1px dashed #cbd5e1; margin: 10px 0;">

            <table style="width: 100%; font-size: 0.8rem; margin-bottom: 10px;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 4px 0; text-align: left;">Detalle</th>
                        <th style="text-align: center; padding: 4px 0;">Cant</th>
                        <th style="text-align: right; padding: 4px 0;">Total</th>
                    </tr>
                </thead>
                <tbody id="fact_cuerpo_items">
                    </tbody>
            </table>

            <hr style="border-top: 1px dashed #cbd5e1; margin: 10px 0;">
            <div style="text-align: right; font-family: monospace; font-size: 0.95rem;">
                <p><strong>TOTAL USD: <span id="fact_total_usd">$0.00</span></strong></p>
                <p style="color: #2563eb;"><strong>TOTAL BS: <span id="fact_total_bs">0.00 Bs</span></strong></p>
            </div>
            <hr style="border-top: 1px dashed #cbd5e1; margin: 10px 0;">
            <p style="text-align: center; font-size: 0.75rem; color: #64748b; font-style: italic;">*** Gracias por su compra ***</p>
            
            <button class="btn-cobrar" style="margin-top:15px; font-size:0.85rem; padding:8px;" onclick="window.print()">🖨️ Imprimir Comprobante</button>
        </div>
    </div>

    <script>
    const tasaDolar = <?php echo $tasa_dolar; ?>;
    let carrito = JSON.parse(sessionStorage.getItem('carrito_ventas')) || [];

    // Al cargar la página, pintar el estado inicial del carrito
    document.addEventListener("DOMContentLoaded", function() {
        renderizarTablaCarrito();
    });

    function calcularPrecios() {
        // Calcula los acumulados combinados del Carrito + lo que esté seleccionado actualmente
        let subtotalUSD = 0;

        carrito.forEach(item => {
            subtotalUSD += item.precio * item.cantidad;
        });

        const select = document.getElementById('id_producto');
        const cantidadInput = document.getElementById('cantidad');
        if(select.selectedIndex > 0) {
            const precioActual = parseFloat(select.options[select.selectedIndex].getAttribute('data-precio')) || 0;
            const cantActual = parseInt(cantidadInput.value) || 0;
            subtotalUSD += (precioActual * cantActual);
        }
        
        const totalBs = subtotalUSD * tasaDolar;
        document.getElementById('total_usd').innerText = '$' + subtotalUSD.toFixed(2);
        document.getElementById('total_bs').innerText = totalBs.toFixed(2) + ' Bs';
    }

    // NUEVA FUNCIÓN JS PARA EL BOTÓN "AGREGAR"
    function agregarArticuloAlCarrito() {
        const select = document.getElementById('id_producto');
        const cantidadInput = document.getElementById('cantidad');

        if (select.selectedIndex === 0) {
            alert("⚠️ Seleccione primero un artículo válido del catálogo.");
            return;
        }

        const id = select.value;
        const nombre = select.options[select.selectedIndex].text.split('(')[0].trim();
        const codigo = select.options[select.selectedIndex].getAttribute('data-codigo') || 'S/C';
        const precio = parseFloat(select.options[select.selectedIndex].getAttribute('data-precio')) || 0;
        const imagen = select.options[select.selectedIndex].getAttribute('data-imagen');
        const cantidad = parseInt(cantidadInput.value) || 0;

        if (cantidad <= 0) {
            alert("⚠️ La cantidad debe ser mayor o igual a 1.");
            return;
        }

        // Si ya está en la lista temporal, sumamos la cantidad
        const existeIndex = carrito.findIndex(item => item.id === id);
        if (existeIndex !== -1) {
            carrito[existeIndex].cantidad += cantidad;
        } else {
            carrito.push({ id, codigo, nombre, precio, cantidad, imagen });
        }

        // Guardar estado en memoria temporal y limpiar selector
        sessionStorage.setItem('carrito_ventas', JSON.stringify(carrito));
        select.selectedIndex = 0;
        cantidadInput.value = 1;

        renderizarTablaCarrito();
    }

    // PINTAR LA LISTA EN EL REGISTRO DE VENTAS DEL TURNO
    function renderizarTablaCarrito() {
        const cuerpo = document.getElementById('cuerpo_carrito_ventas');
        if (carrito.length === 0) {
            cuerpo.innerHTML = `<tr><td colspan="7" class="tabla-vacia">No se registran transacciones el día de hoy.</td></tr>`;
            calcularPrecios();
            return;
        }

        cuerpo.innerHTML = "";
        let totalAcumuladoUsd = 0;

        carrito.forEach((item, index) => {
            const totalItemUsd = item.precio * item.cantidad;
            const totalItemBs = totalItemUsd * tasaDolar;
            totalAcumuladoUsd += totalItemUsd;

            cuerpo.innerHTML += `
                <tr>
                    <td style="width: 60px; text-align: center;">
                        <img src="${item.imagen}" class="img-producto-tabla" style="width:40px; height:40px; object-fit:cover; border-radius:4px;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/679/679821.png';">
                    </td>
                    <td><small>${item.codigo}</small></td>
                    <td><strong>${item.nombre}</strong></td>
                    <td>${item.cantidad}</td>
                    <td class="monto-usd">$${totalItemUsd.toFixed(2)}</td>
                    <td class="monto-bs">${totalItemBs.toFixed(2)} Bs</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn-eliminar-item" onclick="eliminarItemCarrito(${index})">❌ Quitar</button>
                    </td>
                </tr>
            `;
        });

        calcularPrecios();
    }

    function eliminarItemCarrito(index) {
        carrito.splice(index, 1);
        sessionStorage.setItem('carrito_ventas', JSON.stringify(carrito));
        renderizarTablaCarrito();
    }

    // PREPARAR ENVÍO AL HACER CLIC EN "CONFIRMAR E IMPRIMIR"
    function enviarFormularioConCarrito() {
        if (carrito.length === 0) {
            alert("⚠️ No has agregado ningún producto a la lista todavía. Presiona 'Agregar a la Lista'.");
            return false;
        }
        document.getElementById('carrito_datos').value = JSON.stringify(carrito);
        return true;
    }

    function filtrarProductos() {
        const busqueda = document.getElementById('txt_buscar').value.toLowerCase().trim();
        const select = document.getElementById('id_producto');
        const opciones = select.options;

        if (busqueda === "") return;

        for (let i = 1; i < opciones.length; i++) {
            const nombre = opciones[i].getAttribute('data-nombre') || "";
            const codigo = opciones[i].getAttribute('data-codigo') || "";

            if (codigo === busqueda || nombre.includes(busqueda)) {
                select.selectedIndex = i;
                calcularPrecios();
                break;
            }
        }
    }

    function abrirModalCliente() { document.getElementById('modal_cliente').style.display = 'flex'; }
    function cerrarModalCliente() { document.getElementById('modal_cliente').style.display = 'none'; }
    function vincularCedula(valor) { document.getElementById('cedula_cliente_venta').value = valor; }

    // VER TICKET MULTI-PRODUCTO EN VIVO
    function abrirFacturaDigital() {
        if (carrito.length === 0) {
            alert("El ticket está vacío. Agregue productos al listado primero.");
            return;
        }

        const cedula = document.getElementById('cedula_cliente_venta').value;
        document.getElementById('fact_cliente_ci').innerText = cedula;

        const cuerpoFactura = document.getElementById('fact_cuerpo_items');
        cuerpoFactura.innerHTML = "";

        let totalUsd = 0;

        carrito.forEach(item => {
            const totalItemUsd = item.precio * item.cantidad;
            totalUsd += totalItemUsd;

            cuerpoFactura.innerHTML += `
                <tr>
                    <td style="padding: 6px 0;">${item.nombre}</td>
                    <td style="text-align: center; padding: 6px 0;">${item.cantidad}</td>
                    <td style="text-align: right; padding: 6px 0;">$${totalItemUsd.toFixed(2)}</td>
                </tr>
            `;
        });

        const totalBs = totalUsd * tasaDolar;
        document.getElementById('fact_total_usd').innerText = '$' + totalUsd.toFixed(2);
        document.getElementById('fact_total_bs').innerText = totalBs.toFixed(2) + ' Bs';

        document.getElementById('modal_factura').style.display = 'flex';
    }

    function cerrarFacturaDigital() { document.getElementById('modal_factura').style.display = 'none'; }
    </script>
</body>
</html>