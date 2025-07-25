<?php
// Configuración general de la aplicación
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejo de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Constantes de la aplicación
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/Database/db_connection.php';

// Función para manejar respuestas JSON
function jsonResponse($success, $message = '', $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Función para validar token JWT (simplificado)
function validateToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        jsonResponse(false, 'Token de autorización no proporcionado', [], 401);
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    
    // En una implementación real, validaríamos el token JWT
    // Aquí solo verificamos que exista en localStorage
    if (empty($token)) {
        jsonResponse(false, 'Token inválido', [], 401);
    }

    return $token;
}

// Función para obtener el rol del usuario (simulado)
function getUserRole() {
    // En una implementación real, decodificaríamos el token JWT
    // Aquí simulamos obteniendo el rol de un parámetro
    return $_GET['role'] ?? 'staff'; // Solo para desarrollo
}
?>