<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Método no permitido'));
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$requiredFields = array('username', 'email', 'password');
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => "El campo $field es requerido"));
        exit;
    }
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Formato de email inválido'));
    exit;
}

if (strlen($data['password']) < 8 || !preg_match('/[0-9]/', $data['password']) || !preg_match('/[a-zA-Z]/', $data['password'])) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres e incluir números y letras'));
    exit;
}

$role = isset($data['role']) ? $data['role'] : 'sales';
$allowedRoles = array('admin', 'staff', 'sales');

if (!in_array($role, $allowedRoles)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Rol no válido'));
    exit;
}

try {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $data['username'], $data['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(array('success' => false, 'message' => 'El nombre de usuario o correo electrónico ya está en uso'));
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Error al verificar usuario existente'));
    exit;
}

$hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $data['username'], $data['email'], $hashedPassword, $role);
    $stmt->execute();
    
    $userId = $stmt->insert_id;
    
    $token = null;
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $tokenData = array(
            'user_id' => $userId,
            'username' => $data['username'],
            'role' => $role,
            'exp' => time() + (60 * 60 * 24)
        );
        
        $token = base64_encode(json_encode($tokenData));
    }
    
    http_response_code(201);
    echo json_encode(array(
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'user_id' => $userId,
        'token' => $token,
        'role' => $role
    ));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Error al registrar usuario: ' . $e->getMessage()));
}
?>