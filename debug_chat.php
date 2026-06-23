<?php
/**
 * debug_chat.php — raíz de caja360-root
 * http://localhost/caja360-root/debug_chat.php?email=TU_EMAIL
 * BORRARLO después de diagnosticar.
 */

$email = $_GET['email'] ?? 'test@gmail.com';

ob_start();
$_GET['email'] = $email;
$_SERVER['REQUEST_METHOD'] = 'GET';

include __DIR__ . '/api/chat_mensajes.php';
$raw = ob_get_clean();

echo '<h2>Largo total: ' . strlen($raw) . ' bytes</h2>';

echo '<h2>Primeros 120 bytes (hex):</h2>';
echo '<pre style="background:#111;color:#ff0;padding:20px;">';
for ($i = 0; $i < min(120, strlen($raw)); $i++) {
    $byte = ord($raw[$i]);
    if ($byte < 0x20 || $byte > 0x7E) {
        echo '[0x' . strtoupper(dechex($byte)) . ']';
    } else {
        echo $raw[$i];
    }
}
echo '</pre>';

echo '<h2>Output RAW (primeros 2000 chars):</h2>';
echo '<pre style="background:#111;color:#0f0;padding:20px;white-space:pre-wrap;">';
echo htmlspecialchars(substr($raw, 0, 2000));
echo '</pre>';

echo '<h2>¿Es JSON válido?</h2>';
$decoded = json_decode($raw, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo '<p style="color:green">✅ JSON válido. Mensajes: ' . count($decoded['mensajes'] ?? []) . '</p>';
} else {
    echo '<p style="color:red">❌ ' . json_last_error_msg() . '</p>';
    $pos = 629;
    $start = max(0, $pos - 80);
    echo '<h3>Contexto alrededor del byte 629 (hex):</h3>';
    echo '<pre style="background:#111;color:#f90;padding:20px;">';
    for ($i = $start; $i < min(strlen($raw), $start + 200); $i++) {
        $byte = ord($raw[$i]);
        if ($byte < 0x20 || $byte > 0x7E) {
            echo '[0x' . strtoupper(dechex($byte)) . ']';
        } else {
            echo $raw[$i];
        }
    }
    echo '</pre>';
}
