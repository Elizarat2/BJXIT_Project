<?php
require_once __DIR__ . '/../../../config/config.php';

$token = validateToken();
$role = getUserRole();

// Solo admin, staff y vendedores pueden ver pedidos
if (!in_array($role, ['admin', 'staff', 'sales'])) {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

try {
    $orderId = $_GET['id'] ?? null;
    $userId = null;
    
    // Si es vendedor, solo puede ver sus propios pedidos
    if ($role === 'sales') {
        // En producción obtendríamos el user_id del token
        $userId = 1; // Simulado para desarrollo
    }
    
    if ($orderId) {
        // Obtener un pedido específico
        $query = "SELECT o.*, p.name as product_name, u.username as seller 
                  FROM orders o
                  JOIN products p ON o.product_id = p.product_id
                  JOIN users u ON o.user_id = u.user_id
                  WHERE o.order_id = :order_id";
        
        if ($userId) {
            $query .= " AND o.user_id = :user_id";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId);
        
        if ($userId) {
            $stmt->bindParam(':user_id', $userId);
        }
        
        $stmt->execute();
        $order = $stmt->fetch();
        
        if (!$order) {
            jsonResponse(false, 'Pedido no encontrado', [], 404);
        }
        
        jsonResponse(true, 'Pedido encontrado', ['order' => $order]);
    } else {
        // Listar pedidos
        $query = "SELECT o.*, p.name as product_name, u.username as seller 
                  FROM orders o
                  JOIN products p ON o.product_id = p.product_id
                  JOIN users u ON o.user_id = u.user_id";
        
        if ($userId) {
            $query .= " WHERE o.user_id = :user_id";
        }
        
        $query .= " ORDER BY o.order_date DESC";
        
        $stmt = $conn->prepare($query);
        
        if ($userId) {
            $stmt->bindParam(':user_id', $userId);
        }
        
        $stmt->execute();
        $orders = $stmt->fetchAll();
        
        jsonResponse(true, 'Lista de pedidos', ['orders' => $orders]);
    }
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error al obtener pedidos: ' . $e->getMessage(), [], 500);
}
?>