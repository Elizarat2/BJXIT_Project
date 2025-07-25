<?php
require_once __DIR__ . '/../../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido', [], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validar datos de entrada
if (empty($data['username']) || empty($data['password'])) {
    jsonResponse(false, 'Usuario y contraseña son requeridos', [], 400);
}

try {
    // Buscar usuario en la base de datos
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(':username', $data['username']);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'Usuario no encontrado', [], 404);
    }
    
    // Verificar contraseña (en producción usar password_verify)
    if ($data['password'] !== $user['password']) { // Esto es solo para desarrollo
        jsonResponse(false, 'Contraseña incorrecta', [], 401);
    }
    
    // En producción generaríamos un token JWT real
    $tokenData = [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'exp' => time() + (60 * 60 * 24) // 24 horas
    ];
    
    // Simulación de token (en producción usar JWT)
    $token = base64_encode(json_encode($tokenData));
    
    jsonResponse(true, 'Autenticación exitosa', [
        'token' => $token,
        'user' => [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    
} catch (PDOException $e) {
    jsonResponse(false, 'Error en la autenticación: ' . $e->getMessage(), [], 500);
}
?>