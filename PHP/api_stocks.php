<?php
// api_stocks.php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autorizado']);
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

try {
    // Consulta adaptada a tus columnas reales de la base de datos unificada
    $sql = "SELECT id, nombre, codigo_barras, precio_compra, precio_venta, stock 
            FROM productos 
            ORDER BY stock ASC, nombre ASC";
    $lista_productos = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $total_articulos = 0;
    $articulos_criticos = 0;
    $valor_total_usd = 0.00;

    foreach ($lista_productos as &$prod) {
        $total_articulos += intval($prod['stock']);
        if (intval($prod['stock']) <= 10) {
            $articulos_criticos++;
        }
        $valor_total_usd += (intval($prod['stock']) * floatval($prod['precio_compra']));
        
        // Calcular dinámicamente el precio en bolívares para la respuesta
        $prod['precio_venta_bs'] = floatval($prod['precio_venta']) * $tasa_dolar;
    }

    $valor_total_bs = $valor_total_usd * $tasa_dolar;

    // Enviar todo empaquetado al Frontend
    echo json_encode([
        'total_articulos' => $total_articulos,
        'articulos_criticos' => $articulos_criticos,
        'valor_total_usd' => number_format($valor_total_usd, 2),
        'valor_total_bs' => number_format($valor_total_bs, 2),
        'productos' => $lista_productos,
        'tasa_dolar' => number_format($tasa_dolar, 2)
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}