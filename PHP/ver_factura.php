<?php
// Iniciar sesión y validar seguridad de acceso
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir la conexión
include '../CONEXION/conexiones.php';

// Validar que se reciba el ID de la venta por la URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "❌ Error: ID de factura no especificado.";
    exit();
}

$id_venta = intval($_GET['id']);

try {
    // 1. OBTENER LOS DATOS GENERALES DE LA VENTA Y DEL USUARIO QUE LA HIZO
    $sql_venta = "SELECT v.id, v.fecha, v.total_usd, v.total_bs, v.tasa_dolar, u.nombre AS vendedor 
                  FROM ventas v
                  INNER JOIN usuarios u ON v.id_usuario = u.id
                  WHERE v.id = :id_venta LIMIT 1";
    
    $stmt_v = $conexion->prepare($sql_venta);
    $stmt_v->execute([':id_venta' => $id_venta]);
    $venta = $stmt_v->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        echo "❌ Error: La factura solicitada no existe.";
        exit();
    }

    // 2. OBTENER LOS DETALLES DE LOS PRODUCTOS VENDIDOS EN ESA FACTURA
    // Ajustado para traer el nombre del producto de forma relacional
    $sql_detalles = "SELECT p.nombre AS producto_nombre, v.cantidad, v.precio_usd, (v.cantidad * v.precio_usd) AS subtotal_usd
                     FROM ventas v
                     INNER JOIN productos p ON v.id_producto = p.id
                     WHERE v.id = :id_venta";
    
    // Nota: Si tu sistema maneja una tabla 'detalle_ventas', cambia la consulta de arriba. 
    // Como en tu script 'ventas.php' registras directo, agrupamos por el mismo ID recibido.
    $stmt_d = $conexion->prepare($sql_detalles);
    $stmt_d->execute([':id_venta' => $id_venta]);
    $detalles = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "❌ Error en el servidor al cargar la factura: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura #<?php echo $venta['id']; ?> - Inventario OP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        body {
            background-color: #f1f5f9;
            padding: 30px;
            color: #334155;
        }
        .factura-contenedor {
            max-width: 800px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            border: 1px solid #e2e8f0;
        }
        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .datos-empresa h1 {
            font-size: 1.6rem;
            color: #1e3a8a;
            font-weight: 700;
        }
        .datos-empresa p {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 2px;
        }
        .datos-factura {
            text-align: right;
        }
        .datos-factura h2 {
            font-size: 1.4rem;
            color: #0f172a;
        }
        .badge-numero {
            color: #ef4444;
            font-weight: bold;
        }
        .info-bloques {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #1e3a8a;
        }
        .bloque-meta p {
            font-size: 0.95rem;
            margin-bottom: 5px;
        }
        .bloque-meta strong {
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #0f172a;
            color: #ffffff;
            padding: 12px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .totales-bloque {
            float: right;
            width: 300px;
            background-color: #0f172a;
            color: #ffffff;
            padding: 20px;
            border-radius: 6px;
            text-align: right;
        }
        .totales-bloque p {
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #334155;
            padding-bottom: 4px;
        }
        .totales-bloque p:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .monto-resaltado {
            font-size: 1.3rem;
            color: #10b981;
            font-weight: bold;
        }
        .monto-bs {
            color: #38bdf8;
            font-weight: bold;
        }
        .botones-area {
            max-width: 800px;
            margin: 20px auto 0 auto;
            display: flex;
            justify-content: space-between;
        }
        .btn {
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: background 0.2s;
        }
        .btn-volver {
            background-color: #64748b;
            color: white;
        }
        .btn-volver:hover {
            background-color: #475569;
        }
        .btn-imprimir {
            background-color: #10b981;
            color: white;
        }
        .btn-imprimir:hover {
            background-color: #059669;
        }
        
        /* Ajustes estrictos para la impresión en físico o PDF */
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            .factura-contenedor {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
            .botones-area {
                display: none;
            }
            .totales-bloque {
                background-color: #000000 !important;
                color: #ffffff !important;
            }
        }
    </style>
</head>
<body>

    <div class="factura-contenedor">
        <header class="encabezado">
            <div class="datos-empresa">
                <h1>QUIOSCO CONTROL OP</h1>
                <p>La Victoria, Aragua, Venezuela</p>
                <p>Elegancia y Control en tus Manos</p>
            </div>
            <div class="datos-factura">
                <h2>FACTURE DE VENTA</h2>
                <p>N° Factura: <span class="badge-numero">#<?php echo str_pad($venta['id'], 6, "0", STR_PAD_LEFT); ?></span></p>
            </div>
        </header>

        <div class="info-bloques">
            <div class="bloque-meta">
                <p><strong>Fecha de Emisión:</strong> <?php echo date("d/m/Y h:i A", strtotime($venta['fecha'])); ?></p>
                <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($venta['vendedor']); ?></p>
            </div>
            <div class="bloque-meta" style="text-align: right;">
                <p><strong>Tasa de Cambio:</strong> <?php echo number_format($venta['tasa_dolar'], 2); ?> Bs/$</p>
                <p><strong>Estado del Pago:</strong> <span style="color:#10b981; font-weight:bold;">Procesado</span></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Descripción del Producto</th>
                    <th style="text-align: center;">Cant.</th>
                    <th style="text-align: right;">Precio U. ($)</th>
                    <th style="text-align: right;">Total ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['producto_nombre']); ?></strong></td>
                        <td style="text-align: center;"><?php echo $item['cantidad']; ?></td>
                        <td style="text-align: right;">$<?php echo number_format($item['precio_usd'], 2); ?></td>
                        <td style="text-align: right;">$<?php echo number_format($item['subtotal_usd'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales-bloque">
            <p><span>Total USD:</span> <span class="monto-resaltado">$<?php echo number_format($venta['total_usd'], 2); ?></span></p>
            <p style="border-bottom: none; margin-top: 5px;"><span>Total Bs:</span> <span class="monto-bs"><?php echo number_format($venta['total_bs'], 2); ?> Bs</span></p>
        </div>
        
        <div style="clear: both;"></div>
    </div>

    <div class="botones-area">
        <a href="ventas.php" class="btn btn-volver">⬅️ Volver a Ventas</a>
        <button onclick="window.print();" class="btn btn-imprimir">🖨️ Imprimir Factura / Guardar PDF</button>
    </div>

</body>
</html>