<?php
/**
 * api/chat_mensajes.php?email=alguien@dominio.com&contacto_id=X
 *
 * 1) Carga el mensaje original de la landing desde la DB (tabla contactos)
 * 2) Busca en Gmail (INBOX + Sent) los correos cruzados con ese email
 * 3) Fusiona todo ordenado por fecha
 *
 * Optimizaciones de velocidad:
 * - Abre INBOX y Sent en la misma conexión IMAP (imap_reopen)
 * - Usa IMAP sequence sets para traer solo los headers necesarios
 * - Timeout de conexión reducido a 10s
 */

ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('imap_open')) {
    http_response_code(500);
    echo json_encode(['error' => 'La extensión PHP "imap" no está habilitada.']);
    exit;
}

$emailContacto = $_GET['email'] ?? null;
$contactoId    = $_GET['contacto_id'] ?? null;

if (!$emailContacto) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro email']);
    exit;
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function limpiarCuerpo(string $body, int $encoding): string
{
    switch ($encoding) {
        case 3: $body = base64_decode($body); break;
        case 4: $body = quoted_printable_decode($body); break;
    }
    if (!mb_check_encoding($body, 'UTF-8')) {
        $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
    }
    $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
    return trim($body);
}

function decodificarCuerpo($conn, int $num, $structure): string
{
    if (!isset($structure->parts)) {
        return limpiarCuerpo(imap_body($conn, $num), $structure->encoding ?? 0);
    }
    // Buscar text/plain directo
    foreach ($structure->parts as $i => $part) {
        if (isset($part->subtype) && strtoupper($part->subtype) === 'PLAIN') {
            return limpiarCuerpo(imap_fetchbody($conn, $num, (string)($i + 1)), $part->encoding ?? 0);
        }
    }
    // Buscar en partes anidadas (multipart/alternative dentro de multipart/mixed)
    foreach ($structure->parts as $i => $part) {
        if (isset($part->parts)) {
            foreach ($part->parts as $j => $sub) {
                if (isset($sub->subtype) && strtoupper($sub->subtype) === 'PLAIN') {
                    return limpiarCuerpo(imap_fetchbody($conn, $num, ($i+1).'.'.($j+1)), $sub->encoding ?? 0);
                }
            }
        }
    }
    // Fallback: HTML sin tags
    foreach ($structure->parts as $i => $part) {
        if (isset($part->subtype) && strtoupper($part->subtype) === 'HTML') {
            $body = limpiarCuerpo(imap_fetchbody($conn, $num, (string)($i + 1)), $part->encoding ?? 0);
            return trim(preg_replace('/\s+/', ' ', strip_tags($body)));
        }
    }
    return '(sin contenido de texto)';
}

function sanitizar(?string $str): string
{
    if ($str === null) return '';
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
}

function decodificarHeader(?string $h): string
{
    if (!$h) return '';
    $parts = imap_mime_header_decode($h);
    $out = '';
    foreach ($parts as $p) {
        $cs = strtoupper($p->charset ?? 'UTF-8');
        if ($cs === 'DEFAULT') $cs = 'UTF-8';
        $out .= ($cs !== 'UTF-8') ? mb_convert_encoding($p->text, 'UTF-8', $cs) : $p->text;
    }
    return $out;
}

function leerBuzon($conn, string $criterio, string $direccion, string $emailContacto): array
{
    $mensajes = [];
    $ids = @imap_search($conn, $criterio, SE_UID);
    if (!$ids) return $mensajes;

    foreach ($ids as $uid) {
        $num = imap_msgno($conn, $uid);
        if (!$num) continue;
        $head = @imap_headerinfo($conn, $num);
        if (!$head) continue;
        $structure = @imap_fetchstructure($conn, $num);
        if (!$structure) continue;

        $mensajes[] = [
            'direccion'  => $direccion,
            'de'         => sanitizar($direccion === 'entrante' ? ($head->fromaddress ?? $emailContacto) : GMAIL_EMAIL),
            'asunto'     => sanitizar(decodificarHeader($head->subject ?? '')),
            'fecha'      => date('c', strtotime($head->date ?? 'now')),
            'message_id' => sanitizar($head->message_id ?? null),
            'cuerpo'     => sanitizar(decodificarCuerpo($conn, $num, $structure)),
        ];
    }
    return $mensajes;
}

// ─── 1. Mensaje original de la landing (DB) ─────────────────────────────────

$mensajes = [];

try {
    if ($contactoId) {
        $stmt = $pdo->prepare("SELECT nombre, email, mensaje, creado_en FROM contactos WHERE id = :id");
        $stmt->execute(['id' => $contactoId]);
        $contacto = $stmt->fetch();
    } else {
        // Buscar por email si no viene el id
        $stmt = $pdo->prepare("SELECT nombre, email, mensaje, creado_en FROM contactos WHERE email = :email ORDER BY creado_en ASC LIMIT 1");
        $stmt->execute(['email' => $emailContacto]);
        $contacto = $stmt->fetch();
    }

    if ($contacto && $contacto['mensaje']) {
        $mensajes[] = [
            'direccion'  => 'entrante',
            'de'         => sanitizar($contacto['nombre'] . ' <' . $contacto['email'] . '>'),
            'asunto'     => 'Consulta desde la landing',
            'fecha'      => date('c', strtotime($contacto['creado_en'])),
            'message_id' => null,
            'cuerpo'     => sanitizar($contacto['mensaje']),
            'origen'     => 'landing', // marca visual opcional
        ];
    }
} catch (Exception $e) {
    // Si falla la DB no rompemos el chat, seguimos con Gmail
}

// ─── 2. Gmail (INBOX + Sent reutilizando la misma conexión) ─────────────────

imap_timeout(IMAP_OPENTIMEOUT, 10);
imap_timeout(IMAP_READTIMEOUT, 10);

$conn = @imap_open(IMAP_INBOX, GMAIL_EMAIL, GMAIL_APP_PASSWORD);

if ($conn) {
    $mensajes = array_merge($mensajes, leerBuzon(
        $conn,
        'FROM "' . $emailContacto . '"',
        'entrante',
        $emailContacto
    ));

    // Reutilizar la misma conexión para Sent (más rápido que abrir una nueva)
    if (@imap_reopen($conn, IMAP_SENT)) {
        $mensajes = array_merge($mensajes, leerBuzon(
            $conn,
            'TO "' . $emailContacto . '"',
            'saliente',
            $emailContacto
        ));
    }

    imap_close($conn);
} else {
    // Gmail no disponible: devolvemos igual lo que tenemos de la DB
    // (no es un error fatal si hay mensajes de landing)
    if (empty($mensajes)) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo conectar a Gmail: ' . imap_last_error()]);
        exit;
    }
}

// ─── 3. Ordenar por fecha y responder ───────────────────────────────────────

usort($mensajes, fn($a, $b) => strtotime($a['fecha']) <=> strtotime($b['fecha']));

ob_clean();

$json = json_encode(
    ['mensajes' => $mensajes, 'casilla' => GMAIL_EMAIL],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);

echo $json;
