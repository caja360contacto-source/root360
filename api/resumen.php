<?php
/**
 * api/resumen.php
 * Datos agregados para las tarjetas del dashboard:
 *  - cuántos planes "completo" / "básico" se vendieron (instalación)
 *  - cuántos clientes tienen suscripción de mantenimiento activa
 *  - próximos cobros de mantenimiento (los más cercanos primero)
 *  - tickets de soporte abiertos
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

// Servicios vendidos por tipo de plan de instalación
$planes = $pdo->query("
    SELECT plan_instalacion, COUNT(*) AS total
    FROM clientes
    WHERE estado != 'dado_de_baja'
    GROUP BY plan_instalacion
")->fetchAll();

$serviciosPlanes = ['basico' => 0, 'completo' => 0];
foreach ($planes as $p) {
    $serviciosPlanes[$p['plan_instalacion']] = (int) $p['total'];
}

// Suscripciones de mantenimiento activas (el servicio recurrente)
$suscripciones = (int) $pdo->query("
    SELECT COUNT(*) AS total FROM clientes
    WHERE mantenimiento_activo = 1 AND estado != 'dado_de_baja'
")->fetch()['total'];

// Total de clientes activos
$totalClientes = (int) $pdo->query("
    SELECT COUNT(*) AS total FROM clientes WHERE estado = 'activo'
")->fetch()['total'];

// Próximos cobros (mantenimiento) - los 8 más cercanos a vencer
$proximosCobros = $pdo->query("
    SELECT id, nombre_negocio, nombre_responsable, proximo_cobro, precio_mantenimiento,
           DATEDIFF(proximo_cobro, CURDATE()) AS dias_restantes
    FROM clientes
    WHERE mantenimiento_activo = 1
      AND proximo_cobro IS NOT NULL
      AND estado = 'activo'
    ORDER BY proximo_cobro ASC
    LIMIT 8
")->fetchAll();

// Mensajes nuevos de la landing
$mensajesNuevos = (int) $pdo->query("
    SELECT COUNT(*) AS total FROM contactos WHERE estado = 'nuevo'
")->fetch()['total'];

// Tickets de soporte abiertos
$ticketsAbiertos = (int) $pdo->query("
    SELECT COUNT(*) AS total FROM tickets_soporte WHERE estado IN ('abierto','en_proceso')
")->fetch()['total'];

echo json_encode([
    'servicios_instalacion' => $serviciosPlanes,   // {basico: n, completo: n}
    'suscripciones_activas' => $suscripciones,
    'total_clientes'        => $totalClientes,
    'proximos_cobros'       => $proximosCobros,
    'mensajes_nuevos'       => $mensajesNuevos,
    'tickets_abiertos'      => $ticketsAbiertos,
]);
