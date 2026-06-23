<?php
/**
 * Conexión a la base de datos caja360_root
 * Ajustá host / usuario / password según tu entorno (XAMPP por defecto: root sin password)
 */

$DB_HOST = 'localhost';
$DB_NAME = 'caja360_root';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No se pudo conectar a la base de datos', 'detalle' => $e->getMessage()]);
    exit;
}
