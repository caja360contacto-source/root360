<?php
/**
 * api/clientes.php
 * GET  -> lista clientes (usa la vista resumen_clientes) o un cliente puntual (?id=)
 * POST -> crea un cliente nuevo
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {

    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute(['id' => $_GET['id']]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            http_response_code(404);
            echo json_encode(['error' => 'Cliente no encontrado']);
            exit;
        }

        echo json_encode(['cliente' => $cliente]);
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM resumen_clientes ORDER BY proximo_cobro IS NULL, proximo_cobro ASC");
    echo json_encode(['clientes' => $stmt->fetchAll()]);
    exit;
}

if ($metodo === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true);

    $requeridos = ['nombre_negocio', 'nombre_responsable', 'email'];
    foreach ($requeridos as $campo) {
        if (empty($b[$campo])) {
            http_response_code(400);
            echo json_encode(['error' => "Falta el campo obligatorio: $campo"]);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO clientes (
            contacto_id, nombre_negocio, nombre_responsable, email, telefono,
            direccion, localidad, plan_instalacion, precio_instalacion, fecha_instalacion,
            mantenimiento_activo, precio_mantenimiento, proximo_cobro,
            cantidad_cajas, ip_servidor_local, observaciones, estado
        ) VALUES (
            :contacto_id, :nombre_negocio, :nombre_responsable, :email, :telefono,
            :direccion, :localidad, :plan_instalacion, :precio_instalacion, :fecha_instalacion,
            :mantenimiento_activo, :precio_mantenimiento, :proximo_cobro,
            :cantidad_cajas, :ip_servidor_local, :observaciones, :estado
        )
    ");

    $stmt->execute([
        'contacto_id'          => $b['contacto_id'] ?? null,
        'nombre_negocio'       => $b['nombre_negocio'],
        'nombre_responsable'   => $b['nombre_responsable'],
        'email'                => $b['email'],
        'telefono'             => $b['telefono'] ?? null,
        'direccion'            => $b['direccion'] ?? null,
        'localidad'            => $b['localidad'] ?? null,
        'plan_instalacion'     => $b['plan_instalacion'] ?? 'completo',
        'precio_instalacion'   => $b['precio_instalacion'] ?? 0,
        'fecha_instalacion'    => $b['fecha_instalacion'] ?? null,
        'mantenimiento_activo' => !empty($b['mantenimiento_activo']) ? 1 : 0,
        'precio_mantenimiento' => $b['precio_mantenimiento'] ?? null,
        'proximo_cobro'        => $b['proximo_cobro'] ?? null,
        'cantidad_cajas'       => $b['cantidad_cajas'] ?? 1,
        'ip_servidor_local'    => $b['ip_servidor_local'] ?? null,
        'observaciones'        => $b['observaciones'] ?? null,
        'estado'               => $b['estado'] ?? 'activo',
    ]);

    // Si el contacto venía de la landing, lo marcamos como convertido
    if (!empty($b['contacto_id'])) {
        $pdo->prepare("UPDATE contactos SET estado = 'convertido' WHERE id = :id")
            ->execute(['id' => $b['contacto_id']]);
    }

    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
