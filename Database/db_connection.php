<?php
// Configuración de la base de datos
$host = 'localhost';
$username = 'root';
$password = ' '; 
$database = 'BJXIT_db';  

$conn = new mysqli($host, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    echo "Error en conexión: " . $conn->connect_error;
    die(json_encode([
        'success' => false, 
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Configurar charset
$conn->set_charset("utf8");
?>