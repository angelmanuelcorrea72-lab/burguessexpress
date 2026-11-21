<?php
// === Base de datos (ajusta a tu servidor / XAMPP) ===
define('DB_HOST', 'localhost');
define('DB_NAME', 'comida_rapida');
define('DB_USER', 'root');
define('DB_PASS', '');
date_default_timezone_set('America/Asuncion'); // cambia a tu zona horaria

// === Correo (recuperar contraseña) ===
define('MAIL_FROM', 'no-responder@tudominio.com');
define('SMTP_HOST', '');          // vacío = usa mail() de PHP. Gmail: smtp.gmail.com
define('SMTP_PORT', 587);         // 587 (tls) o 465 (ssl)
define('SMTP_SECURE', 'tls');     // 'tls', 'ssl' o '' (sin cifrado)
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('MAIL_DEBUG', false);      // true SOLO en pruebas locales: muestra el enlace de recuperación en pantalla

// === Pagos en línea Bancard (déjalo vacío para desactivar) ===
define('BANCARD_PUBLIC_KEY', '');
define('BANCARD_PRIVATE_KEY', '');
define('BANCARD_STAGING', true);  // true = ambiente de pruebas; false = producción
