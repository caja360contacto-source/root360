<?php
/**
 * api/contactos.php
 * GET  -> lista los contactos (mensajes de la landing), filtrables por estado
 * POST -> actualiza el estado / notas internas de un contacto (?accion=actualizar)
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {

    $estado = $_GET['estado'] ?? null;

    if ($estado && in_array($estado, ['nuevo', 'contactado', 'convertido', 'descartado'], true)) {
        $stmt = $pdo->prepare("SELECT * FROM contactos WHERE estado = :estado ORDER BY creado_en DESC");
        $stmt->execute(['estado' => $estado]);
    } else {
        $stmt = $pdo->query("SELECT * FROM contactos ORDER BY creado_en DESC LIMIT 200");
    }

    $contactos = $stmt->fetchAll();

    // contador rápido para el badge de "nuevos" en el dashboard
    $nuevos = $pdo->query("SELECT COUNT(*) AS total FROM contactos WHERE estado = 'nuevo'")->fetch()['total'];

    echo json_encode([
        'contactos' => $contactos,
        'nuevos'    => (int) $nuevos,
    ]);
    exit;
}

if ($metodo === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $id     = $body['id']     ?? null;
    $estado = $body['estado'] ?? null;
    $notas  = $body['notas_internas'] ?? null;

    if (!$id || !$estado) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos: id y estado son obligatorios']);
        exit;
    }

    if (!in_array($estado, ['nuevo', 'contactado', 'convertido', 'descartado'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Estado inválido']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE contactos SET estado = :estado, notas_internas = :notas WHERE id = :id"
    );
    $stmt->execute([
        'estado' => $estado,
        'notas'  => $notas,
        'id'     => $id,
    ]);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
