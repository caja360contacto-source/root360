<?php
/**
 * api/chat_mensajes.php?email=alguien@dominio.com
 * Lee INBOX + Sent Mail de la casilla de Gmail configurada en config/mail.php,
 * busca todos los correos cruzados con ese email, y devuelve un hilo tipo chat.
 *
 * Requiere la extensión "imap" de PHP habilitada (php.ini -> extension=imap).
 */

require_once __DIR__ . '/../config/mail.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('imap_open')) {
    http_response_code(500);
    echo json_encode(['error' => 'La extensión PHP "imap" no está habilitada. Activala en php.ini (extension=imap) y reiniciá Apache.']);
    exit;
}

$emailContacto = $_GET['email'] ?? null;
if (!$emailContacto) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro email']);
    exit;
}

function abrirBuzon(string $mailbox)
{
    $conn = @imap_open($mailbox, GMAIL_EMAIL, GMAIL_APP_PASSWORD);
    if (!$conn) {
        throw new Exception('No se pudo conectar a Gmail: ' . imap_last_error());
    }
    return $conn;
}

function decodificarCuerpo($conn, $num, $structure)
{
    // Si es texto plano simple
    if (!isset($structure->parts)) {
        $body = imap_body($conn, $num);
        return limpiarCuerpo($body, $structure->encoding ?? 0);
    }

    // Multipart: buscamos la parte text/plain (o si no hay, text/html sin tags)
    foreach ($structure->parts as $i => $part) {
        if ($part->subtype === 'PLAIN') {
            $body = imap_fetchbody($conn, $num, (string)($i + 1));
            return limpiarCuerpo($body, $part->encoding ?? 0);
        }
    }
    foreach ($structure->parts as $i => $part) {
        if ($part->subtype === 'HTML') {
            $body = imap_fetchbody($conn, $num, (string)($i + 1));
            $body = limpiarCuerpo($body, $part->encoding ?? 0);
            return trim(preg_replace('/\s+/', ' ', strip_tags($body)));
        }
    }
    return '(sin contenido de texto)';
}

function limpiarCuerpo($body, $encoding)
{
    if ($encoding == 3) { // BASE64
        $body = base64_decode($body);
    } elseif ($encoding == 4) { // QUOTED-PRINTABLE
        $body = quoted_printable_decode($body);
    }
    return trim($body);
}

function extraerEmail(string $direccion): string
{
    if (preg_match('/<(.+?)>/', $direccion, $m)) {
        return strtolower($m[1]);
    }
    return strtolower(trim($direccion));
}

try {
    $mensajes = [];

    // ---- Entrantes (lo que el contacto te escribió) ----
    $conn = abrirBuzon(IMAP_INBOX);
    $ids = imap_search($conn, 'FROM "' . $emailContacto . '"', SE_UID);
    if ($ids) {
        foreach ($ids as $uid) {
            $num = imap_msgno($conn, $uid);
            $head = imap_headerinfo($conn, $num);
            $structure = imap_fetchstructure($conn, $num);
            $mensajes[] = [
                'direccion' => 'entrante',
                'de'        => $head->fromaddress ?? $emailContacto,
                'asunto'    => imap_utf8($head->subject ?? '(sin asunto)'),
                'fecha'     => date('c', strtotime($head->date)),
                'message_id'=> $head->message_id ?? null,
                'cuerpo'    => decodificarCuerpo($conn, $num, $structure),
            ];
        }
    }
    imap_close($conn);

    // ---- Enviados (lo que vos le respondiste desde Gmail) ----
    $conn = abrirBuzon(IMAP_SENT);
    $ids = imap_search($conn, 'TO "' . $emailContacto . '"', SE_UID);
    if ($ids) {
        foreach ($ids as $uid) {
            $num = imap_msgno($conn, $uid);
            $head = imap_headerinfo($conn, $num);
            $structure = imap_fetchstructure($conn, $num);
            $mensajes[] = [
                'direccion' => 'saliente',
                'de'        => GMAIL_EMAIL,
                'asunto'    => imap_utf8($head->subject ?? '(sin asunto)'),
                'fecha'     => date('c', strtotime($head->date)),
                'message_id'=> $head->message_id ?? null,
                'cuerpo'    => decodificarCuerpo($conn, $num, $structure),
            ];
        }
    }
    imap_close($conn);

    usort($mensajes, fn($a, $b) => strtotime($a['fecha']) <=> strtotime($b['fecha']));

    echo json_encode(['mensajes' => $mensajes, 'casilla' => GMAIL_EMAIL]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
