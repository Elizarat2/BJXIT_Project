  <?php
require_once __DIR__ . '/../../../config/config.php';

$token = validateToken();
$role = getUserRole();

// Solo vendedores pueden crear pedidos
if ($role !== 'sales') {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validar datos de entrada
if (empty($data['product_id']) || empty($data['client_name']) || !isset($data['quantity'])) {
    jsonResponse(false, 'Producto, cliente y cantidad son requeridos', [], 400);
}

if (!is_numeric($data['quantity']) || $data['quantity'] <= 0) {
    jsonResponse(false, 'La cantidad debe ser un número positivo', [], 400);
}

try {
    // Obtener información del producto
    $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $data['product_id']);
    $stmt->execute();
    
    $product = $stmt->fetch();
    
    if (!$product) {
        jsonResponse(false, 'Producto no encontrado', [], 404);
    }
    
    // Validar existencia
    if ($product['stock'] < $data['quantity']) {
        jsonResponse(false, 'No hay suficiente stock disponible', [], 400);
    }
    
    // Obtener user_id del token (en producción)
    // Aquí simulamos con un valor fijo
    $userId = 1;
    
    // Crear pedido
    $stmt = $conn->prepare("INSERT INTO orders (client_name, product_id, quantity, user_id) VALUES (:client_name, :product_id, :quantity, :user_id)");
    $stmt->bindParam(':client_name', $data['client_name']);
    $stmt->bindParam(':product_id', $data['product_id']);
    $stmt->bindParam(':quantity', $data['quantity']);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $orderId = $conn->lastInsertId();
    
    // Actualizar stock del producto
    $newStock = $product['stock'] - $data['quantity'];
    $stmt = $conn->prepare("UPDATE products SET stock = :stock WHERE product_id = :product_id");
    $stmt->bindParam(':stock', $newStock);
    $stmt->bindParam(':product_id', $data['product_id']);
    $stmt->execute();
    
    jsonResponse(true, 'Pedido creado exitosamente', ['order_id' => $orderId], 201);
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al crear pedido: ' . $e->getMessage(), [], 500);
}
?>  
    
