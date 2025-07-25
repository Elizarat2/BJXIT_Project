<?php
require_once __DIR__ . '/../../../config/config.php';

$token = validateToken();
$role = getUserRole();

// Solo admin y staff pueden actualizar productos
if (!in_array($role, ['admin', 'staff'])) {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$productId = $_GET['id'] ?? null;

if (!$productId) {
    jsonResponse(false, 'ID de producto no proporcionado', [], 400);
}

try {
    // Construir consulta dinámica
    $fields = [];
    $params = [':product_id' => $productId];
    
    if (!empty($data['product_key'])) {
        $fields[] = 'product_key = :product_key';
        $params[':product_key'] = $data['product_key'];
    }
    
    if (!empty($data['name'])) {
        $fields[] = 'name = :name';
        $params[':name'] = $data['name'];
    }
    
    if (isset($data['stock'])) {
        if (!is_numeric($data['stock']) || $data['stock'] < 0) {
            jsonResponse(false, 'La existencia debe ser un número positivo', [], 400);
        }
        $fields[] = 'stock = :stock';
        $params[':stock'] = $data['stock'];
    }
    
    if (empty($fields)) {
        jsonResponse(false, 'No hay datos para actualizar', [], 400);
    }
    
    $query = "UPDATE products SET " . implode(', ', $fields) . " WHERE product_id = :product_id";
    $stmt = $conn->prepare($query);
    
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'No se realizaron cambios o producto no encontrado', [], 404);
    }
    
    jsonResponse(true, 'Producto actualizado exitosamente');
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al actualizar producto: ' . $e->getMessage(), [], 500);
}
?>