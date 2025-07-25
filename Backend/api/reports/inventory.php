<?php
require_once __DIR__ . '/../../../config/config.php';

$token = validateToken();
$role = getUserRole();

// Solo admin y staff pueden ver reportes
if (!in_array($role, ['admin', 'staff'])) {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

try {
    // Reporte de inventario con productos bajos en stock
    $stmt = $conn->query("
        SELECT p.product_id, p.product_key, p.name, p.stock, 
               COUNT(o.order_id) as total_orders,
               SUM(CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as recent_orders
        FROM products p
        LEFT JOIN orders o ON p.product_id = o.product_id
        GROUP BY p.product_id, p.product_key, p.name, p.stock
        ORDER BY p.stock ASC
    ");
    
    $inventory = $stmt->fetchAll();
    
    // Agregar bandera para productos bajos en stock
    foreach ($inventory as &$item) {
        $item['low_stock'] = $item['stock'] < 10;
    }
    
    jsonResponse(true, 'Reporte de inventario', ['inventory' => $inventory]);
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al generar reporte: ' . $e->getMessage(), [], 500);
}
?>