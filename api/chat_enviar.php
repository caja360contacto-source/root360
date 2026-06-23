<?php
/**
 * api/chat_enviar.php
 * POST { email, asunto, mensaje, en_respuesta_a? }
 * Envía un correo desde la casilla configurada usando SmtpMailer.
 * Gmail guarda automáticamente una copia en "Sent Mail" al enviar por SMTP autenticado,
 * así que no hace falta guardarlo nosotros aparte.
 */

require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/SmtpMailer.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);

$para     = $body['email']   ?? null;
$mensaje  = $body['mensaje'] ?? null;
$asunto   = $body['asunto']  ?? 'Caja360 - Soporte';
$enRespuestaA = $body['en_respuesta_a'] ?? null;

if (!$para || !$mensaje) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos: email y mensaje son obligatorios']);
    exit;
}

try {
    $mailer = new SmtpMailer(SMTP_HOST, SMTP_PORT, GMAIL_EMAIL, GMAIL_APP_PASSWORD);
    $mailer->enviar($para, $asunto, $mensaje, $enRespuestaA);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
