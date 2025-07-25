<?php
require_once __DIR__ . '/../../../config/config.php';

$token = validateToken();
$role = getUserRole();

// Solo admin y staff pueden eliminar productos
if (!in_array($role, ['admin', 'staff'])) {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

$productId = $_GET['id'] ?? null;

if (!$productId) {
    jsonResponse(false, 'ID de producto no proporcionado', [], 400);
}

try {
    // Verificar si hay pedidos asociados al producto
    $stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $productId);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['order_count'] > 0) {
        jsonResponse(false, 'No se puede eliminar el producto porque tiene pedidos asociados', [], 400);
    }
    
    // Eliminar producto
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $productId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Producto no encontrado', [], 404);
    }
    
    jsonResponse(true, 'Producto eliminado exitosamente');
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al eliminar producto: ' . $e->getMessage(), [], 500);
}
?>