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
define('GMAIL_APP_PASSWORD', 'pqdz kfat lkli rcxs'); // la de 16 caracteres, sin espacios o con espacios, da igual

define('IMAP_HOST', '{imap.gmail.com:993/imap/ssl}');
define('IMAP_INBOX', IMAP_HOST . 'INBOX');
define('IMAP_SENT', IMAP_HOST . '[Gmail]/Sent Mail');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);

define('NOMBRE_REMITENTE', 'Caja360 Soporte');
