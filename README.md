# Caja360 — Panel Root

## Instalación rápida (XAMPP)

1. Copiá la carpeta `caja360-root` dentro de `htdocs` (ej: `C:\xampp\htdocs\caja360-root`).
2. Importá `caja360_root.sql` en phpMyAdmin (si no lo hiciste ya).
3. Revisá `config/db.php`: por defecto usa usuario `root` sin password (típico de XAMPP). Cambialo si tu MySQL tiene otra configuración.
4. Abrí `http://localhost/caja360-root/` en el navegador.

No requiere `composer` ni librerías externas — es PHP plano + JS plano, corre directo en Apache.

## Qué hace el panel ahora

- **Inicio**: 4 indicadores (mensajes nuevos, clientes activos, suscripciones activas, tickets abiertos), gráfico de servicios vendidos, y lista de próximos cobros de mantenimiento ordenados por urgencia.
- **Mensajes landing**: lista los contactos que llegan por el formulario de la web, con selector para cambiar su estado (nuevo → contactado → convertido/descartado).
- **Clientes**: listado general usando la vista `resumen_clientes` (incluye tickets abiertos por cliente).

## Decisión de diseño sobre "completo" vs "suscripción"

Tu tabla `clientes` tiene dos conceptos separados que mapeé así:

- **"Completo" / "Básico"** → columna `plan_instalacion` (el tipo de instalación que se vendió, pago único).
- **"Suscripción"** → columna `mantenimiento_activo` (el mantenimiento mensual recurrente). Se cuenta como suscripción activa cualquier cliente con `mantenimiento_activo = 1`.

Si en realidad "suscripción" iba a ser un tercer valor dentro de `plan_instalacion` (en vez de ser el mantenimiento), avisame y ajusto el ENUM y las consultas.

## Chat integrado con Gmail (mensajes de la landing)

Al hacer clic en un mensaje en "Mensajes landing" se abre una ventana de chat que lee y responde correos reales desde tu Gmail, usando el email que el contacto dejó en el formulario para encontrar la conversación.

### Configuración (una sola vez)

1. **Activá la verificación en 2 pasos** en tu cuenta de Gmail: https://myaccount.google.com/security
2. **Generá una contraseña de aplicación**: https://myaccount.google.com/apppasswords → elegí "Correo" → te da un código de 16 letras.
3. Editá `config/mail.php` y completá:
   ```php
   define('GMAIL_EMAIL', 'tu_correo@gmail.com');
   define('GMAIL_APP_PASSWORD', 'el código de 16 letras');
   ```
   **No uses tu contraseña normal de Gmail** — con la verificación en 2 pasos activada no va a funcionar, tiene que ser la contraseña de aplicación.
4. **Habilitá la extensión IMAP de PHP** (la necesitamos para leer los correos):
   - Abrí `C:\xampp\php\php.ini`
   - Buscá la línea `;extension=imap` y sacale el `;` de adelante → `extension=imap`
   - Guardá y reiniciá Apache desde el panel de XAMPP.
   - Para confirmar que quedó activa, podés crear un archivo con `<?php phpinfo();` y buscar "imap" en la página.

### Cómo funciona

- **Leer la conversación**: `api/chat_mensajes.php` se conecta por IMAP a tu Gmail, busca en INBOX los correos que te mandó ese email, y en Sent Mail los que vos le respondiste — junta todo ordenado por fecha y lo muestra como burbujas de chat.
- **Responder**: `api/chat_enviar.php` manda el correo por SMTP (con un cliente minimalista incluido en `includes/SmtpMailer.php`, sin librerías externas). Gmail guarda automáticamente una copia en "Sent Mail", así que la próxima vez que abras el chat ya aparece tu respuesta.
- No se guarda nada de esto en la base de datos — el panel lee/escribe directamente sobre tu Gmail en tiempo real. Si más adelante querés un historial propio en la base (por si cambiás de casilla), avisame y lo armamos.

### Limitación a tener en cuenta

Gmail gratuito tiene límites de envío diarios (~500 correos/día) y la app password puede dejar de funcionar si Google detecta actividad "no habitual" — para un volumen bajo de soporte (que es tu caso ahora) no debería ser un problema.



1. **Login real** para `usuarios_root` (ahora el panel es de acceso libre — hay que sumar sesión PHP antes de producción).
2. **Alta de cliente desde el panel** con formulario (el endpoint `POST api/clientes.php` ya está listo, falta el form en pantalla).
3. **Selector de versión al crear cliente** (Supermercado / Kiosco / Restaurante): agregamos una columna `version_producto` en `clientes`, un selector en el alta, y a partir de esa elección se dispara el empaquetado de Electron correspondiente (usando `electron-builder` con configs separadas por versión — esto lo armamos como un paso de build, no algo que corra "en vivo" desde el panel web, salvo que quieras que el panel dispare un script en el servidor).

## Estructura

```
caja360-root/
├── config/
│   ├── db.php              → conexión PDO a MySQL
│   └── mail.php             → credenciales de Gmail (IMAP/SMTP)
├── includes/
│   └── SmtpMailer.php       → cliente SMTP minimalista (sin librerías externas)
├── api/
│   ├── contactos.php        → GET/POST mensajes de landing
│   ├── clientes.php         → GET/POST clientes
│   ├── resumen.php          → GET datos agregados del dashboard
│   ├── chat_mensajes.php    → GET hilo de Gmail con un contacto (IMAP)
│   └── chat_enviar.php      → POST enviar respuesta por Gmail (SMTP)
├── assets/
│   ├── css/style.css
│   └── js/dashboard.js
└── index.php                → vista principal
```
