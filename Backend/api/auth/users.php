<?php
require_once __DIR__ . '/../../../config/config.php';

// Validar token y rol de administrador
$token = validateToken();
$role = getUserRole();

if ($role !== 'admin') {
    jsonResponse(false, 'No autorizado', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Obtener parámetros de consulta
        $userId = $_GET['id'] ?? null;
        
        if ($userId) {
            // Obtener un usuario específico
            $stmt = $conn->prepare("SELECT user_id, username, email, role, created_at FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, 'Usuario no encontrado', [], 404);
            }
            
            jsonResponse(true, 'Usuario encontrado', ['user' => $user]);
        } else {
            // Listar todos los usuarios
            $stmt = $conn->query("SELECT user_id, username, email, role, created_at FROM users");
            $users = $stmt->fetchAll();
            
            jsonResponse(true, 'Lista de usuarios', ['users' => $users]);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al obtener usuarios: ' . $e->getMessage(), [], 500);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Actualizar usuario
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        jsonResponse(false, 'ID de usuario no proporcionado', [], 400);
    }
    
    try {
        // Construir consulta dinámica
        $fields = [];
        $params = [':user_id' => $userId];
        
        if (!empty($data['username'])) {
            $fields[] = 'username = :username';
            $params[':username'] = $data['username'];
        }
        
        if (!empty($data['email'])) {
            $fields[] = 'email = :email';
            $params[':email'] = $data['email'];
        }
        
        if (!empty($data['role'])) {
            $fields[] = 'role = :role';
            $params[':role'] = $data['role'];
        }
        
        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params[':password'] = $data['password']; // En producción usar password_hash()
        }
        
        if (empty($fields)) {
            jsonResponse(false, 'No hay datos para actualizar', [], 400);
        }
        
        $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        
        $stmt->execute();
        
        jsonResponse(true, 'Usuario actualizado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar usuario: ' . $e->getMessage(), [], 500);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Eliminar usuario
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        jsonResponse(false, 'ID de usuario no proporcionado', [], 400);
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            jsonResponse(false, 'Usuario no encontrado', [], 404);
        }
        
        jsonResponse(true, 'Usuario eliminado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al eliminar usuario: ' . $e->getMessage(), [], 500);
    }
} else {
    jsonResponse(false, 'Método no permitido', [], 405);
}
?>
<?php
