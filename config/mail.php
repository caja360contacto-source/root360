<?php
/**
 * Credenciales de la casilla de Gmail que vas a usar para hablar con los contactos.
 *
 * IMPORTANTE - Gmail sin Workspace (cuenta gratuita):
 * 1) Activá la verificación en 2 pasos en myaccount.google.com/security
 * 2) Generá una "contraseña de aplicación" en myaccount.google.com/apppasswords
 *    (elegí app "Correo" / "Otra", te da un código de 16 letras)
 * 3) Usá ESE código acá abajo, NO tu contraseña normal de Gmail.
 */

define('GMAIL_EMAIL', 'caja360.contacto@gmail.com');
define('GMAIL_APP_PASSWORD', 'pqdz kfat lkli rcxs');

define('IMAP_HOST', '{imap.gmail.com:993/imap/ssl}');
define('IMAP_INBOX', IMAP_HOST . 'INBOX');

// Detectar automáticamente el nombre correcto del buzón de enviados
function detectarBuzonEnviados(): string
{
    $host = '{imap.gmail.com:993/imap/ssl}';
    $conn = @imap_open($host, GMAIL_EMAIL, GMAIL_APP_PASSWORD, OP_HALFOPEN);
    if (!$conn) return $host . '[Gmail]/Sent Mail';

    $lista = imap_list($conn, $host, '*');
    imap_close($conn);

    if (!$lista) return $host . '[Gmail]/Sent Mail';

    $candidatos = ['Sent Mail', 'Correo enviado', 'Sent', 'Enviados'];
    foreach ($lista as $buzon) {
        foreach ($candidatos as $nombre) {
            if (stripos($buzon, $nombre) !== false) {
                // Extraer solo el nombre del mailbox sin el host
                $nombre_buzon = str_replace($host, '', $buzon);
                return $host . $nombre_buzon;
            }
        }
    }

    return $host . '[Gmail]/Sent Mail';
}

define('IMAP_SENT', detectarBuzonEnviados());

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);

define('NOMBRE_REMITENTE', 'Caja360 Soporte');
