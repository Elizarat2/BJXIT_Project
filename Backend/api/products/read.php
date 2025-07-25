<?php
require_once __DIR__ . '/../../../config/config.php';

$token = validateToken();
$role = getUserRole();

// Solo admin y staff pueden ver productos
if (!in_array($role, ['admin', 'staff'])) {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

try {
    $productId = $_GET['id'] ?? null;
    
    if ($productId) {
        // Obtener un producto específico
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = :product_id");
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        $product = $stmt->fetch();
        
        if (!$product) {
            jsonResponse(false, 'Producto no encontrado', [], 404);
        }
        
        jsonResponse(true, 'Producto encontrado', ['product' => $product]);
    } else {
        // Listar todos los productos
        $stmt = $conn->query("SELECT * FROM products");
        $products = $stmt->fetchAll();
        
        jsonResponse(true, 'Lista de productos', ['products' => $products]);
    }
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al obtener productos: ' . $e->getMessage(), [], 500);
}
?>