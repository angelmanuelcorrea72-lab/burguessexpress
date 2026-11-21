<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function cfg(string $k, string $d = ''): string {
    static $c;
    if ($c === null) $c = db()->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    return $c[$k] ?? $d;
}
function money($n): string { return cfg('moneda', 'Gs.') . ' ' . number_format((float)$n, 0, ',', '.'); }
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(?string $t = null): void {
    $t = $t ?? ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(419); die('Sesión expirada. Recarga la página.'); }
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
}
function require_login(): void { if (!user()) { header('Location: login.php'); exit; } }
function require_staff(): void {
    $u = user();
    if (!$u || !in_array($u['rol'], ['admin', 'empleado'], true)) { header('Location: ../login.php'); exit; }
}
function require_admin(): void { require_staff(); if (user()['rol'] !== 'admin') die('Solo el administrador puede entrar aquí.'); }
function json_out($d, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const ESTADOS = [
    'pendiente' => 'Recibido', 'preparando' => 'Preparando', 'listo' => 'Listo para retirar',
    'en_camino' => 'En camino', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado',
];
/** @return array{0:bool,1:string} [abierto, texto] */
function estadoLocal(): array {
    $modo = cfg('modo', 'auto');
    if ($modo === 'cerrado') return [false, 'Cerrado temporalmente'];
    if ($modo === 'abierto') return [true, 'Abierto ahora'];
    $now = new DateTime();
    $q = db()->prepare("SELECT * FROM horarios WHERE dia = ?");
    $q->execute([(int)$now->format('w')]);
    $h = $q->fetch();
    if ($h && !$h['cerrado']) {
        $t = $now->format('H:i:s'); $a = $h['abre']; $c = $h['cierra'];
        $open = $c > $a ? ($t >= $a && $t < $c) : ($t >= $a || $t < $c);
        if ($open) return [true, 'Abierto · cierra ' . substr($c, 0, 5)];
        if ($t < $a) return [false, 'Cerrado · abre hoy ' . substr($a, 0, 5)];
    }
    return [false, 'Cerrado ahora'];
}
function wa_link(string $msg = ''): string {
    return 'https://wa.me/' . preg_replace('/\D/', '', cfg('whatsapp')) . ($msg ? '?text=' . rawurlencode($msg) : '');
}

/* ======================= v2 ======================= */
foreach (['MAIL_FROM' => 'no-responder@localhost', 'SMTP_HOST' => '', 'SMTP_PORT' => 587, 'SMTP_SECURE' => 'tls', 'SMTP_USER' => '',
          'SMTP_PASS' => '', 'MAIL_DEBUG' => false, 'BANCARD_PUBLIC_KEY' => '', 'BANCARD_PRIVATE_KEY' => '', 'BANCARD_STAGING' => true] as $k => $v)
    defined($k) || define($k, $v);

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $dir = preg_replace('#/(admin|api)$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir;
}
function precio_unit(array $p): int {
    return (isset($p['precio_promo']) && (int)$p['precio_promo'] > 0) ? (int)$p['precio_promo'] : (int)$p['precio'];
}
function bancard_activo(): bool { return BANCARD_PUBLIC_KEY !== '' && BANCARD_PRIVATE_KEY !== ''; }

/** Valida las opciones elegidas de un producto. @return array{0:int,1:string}|string  [extra, texto] o mensaje de error */
function calc_opciones(int $productoId, array $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $g = db()->prepare("SELECT * FROM grupos_opciones WHERE producto_id = ? ORDER BY orden, id");
    $g->execute([$productoId]);
    $grupos = $g->fetchAll();
    $o = db()->prepare("SELECT o.* FROM opciones o JOIN grupos_opciones g ON g.id = o.grupo_id WHERE g.producto_id = ? AND o.activa = 1");
    $o->execute([$productoId]);
    $disp = [];
    foreach ($o->fetchAll() as $op) $disp[$op['id']] = $op;
    foreach ($ids as $id) if (!isset($disp[$id])) return 'Una opción elegida ya no está disponible.';
    $extra = 0; $partes = [];
    foreach ($grupos as $gr) {
        $sel = array_filter($ids, fn($i) => $disp[$i]['grupo_id'] == $gr['id']);
        if ($gr['tipo'] === 'unica' && count($sel) > 1) return 'Elige una sola opción en «' . $gr['nombre'] . '».';
        if ($gr['obligatorio'] && !$sel) return 'Elige una opción en «' . $gr['nombre'] . '».';
        if (!$sel) continue;
        $nombres = [];
        foreach ($sel as $i) { $extra += (int)$disp[$i]['extra']; $nombres[] = $disp[$i]['nombre']; }
        $partes[] = $gr['nombre'] . ': ' . implode(', ', $nombres);
    }
    return [$extra, implode(' | ', $partes)];
}

/** @return array{ok:bool,msg:string,descuento:int,cupon:?array} */
function validar_cupon(string $codigo, int $subtotal, string $tipo, int $envio, int $uid): array {
    $fail = fn($m) => ['ok' => false, 'msg' => $m, 'descuento' => 0, 'cupon' => null];
    $q = db()->prepare("SELECT * FROM cupones WHERE codigo = ? AND activo = 1");
    $q->execute([strtoupper(trim($codigo))]);
    $c = $q->fetch();
    if (!$c) return $fail('Ese cupón no existe o no está activo.');
    if ($c['vence'] && $c['vence'] < date('Y-m-d')) return $fail('Ese cupón ya venció.');
    if ($c['usos_max'] > 0 && $c['usos'] >= $c['usos_max']) return $fail('Ese cupón ya alcanzó su límite de usos.');
    if ($subtotal < (int)$c['minimo']) return $fail('Este cupón requiere un pedido mínimo de ' . money($c['minimo']) . '.');
    if ($c['uno_por_usuario']) {
        $u = db()->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id = ? AND cupon = ? AND estado <> 'cancelado'");
        $u->execute([$uid, $c['codigo']]);
        if ($u->fetchColumn() > 0) return $fail('Ya usaste este cupón.');
    }
    if ($c['tipo'] === 'envio_gratis') {
        if ($tipo !== 'delivery') return $fail('Este cupón es solo para pedidos con delivery.');
        $d = $envio;
    } elseif ($c['tipo'] === 'porcentaje') $d = (int)floor($subtotal * min(100, (int)$c['valor']) / 100);
    else $d = min((int)$c['valor'], $subtotal);
    return ['ok' => true, 'msg' => 'Cupón aplicado: -' . money($d), 'descuento' => $d, 'cupon' => $c];
}

function send_mail(string $to, string $subject, string $body): bool {
    if (SMTP_HOST === '') {
        $h = "From: " . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $h);
    }
    $remote = (SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST;
    $fp = @stream_socket_client($remote . ':' . SMTP_PORT, $en, $es, 10);
    if (!$fp) return false;
    stream_set_timeout($fp, 10);
    $rd = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function ($c) use ($fp, $rd) { fwrite($fp, $c . "\r\n"); return $rd(); };
    $rd(); $cmd('EHLO localhost');
    if (SMTP_SECURE === 'tls') {
        $cmd('STARTTLS');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        $cmd('EHLO localhost');
    }
    if (SMTP_USER !== '') {
        $cmd('AUTH LOGIN'); $cmd(base64_encode(SMTP_USER));
        if (strncmp($cmd(base64_encode(SMTP_PASS)), '235', 3) !== 0) { fclose($fp); return false; }
    }
    $cmd('MAIL FROM:<' . MAIL_FROM . '>'); $cmd('RCPT TO:<' . $to . '>'); $cmd('DATA');
    $msg = "From: " . MAIL_FROM . "\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\n" .
           "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
    fwrite($fp, $msg . "\r\n.\r\n");
    $ok = strncmp($rd(), '250', 3) === 0;
    $cmd('QUIT'); fclose($fp);
    return $ok;
}

/* ---- Bancard vPOS 2.0 (single_buy). NO probado contra el servidor real: valida en el ambiente de pruebas de Bancard. ---- */
function bancard_base(): string {
    if (defined('BANCARD_API_BASE')) return BANCARD_API_BASE;                       // solo para pruebas locales
    return BANCARD_STAGING ? 'https://vpos.infonet.com.py:8888' : 'https://vpos.infonet.com.py';
}
function bancard_monto($n): string { return number_format((float)$n, 2, '.', ''); }
function bancard_post(string $path, array $body): ?array {
    $json = json_encode($body);
    $url = bancard_base() . $path;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
        $r = curl_exec($ch); curl_close($ch);
    } else {
        $r = @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $json, 'timeout' => 20]]));
    }
    return $r ? (json_decode($r, true) ?: null) : null;
}

/* ======================= v3 ======================= */
/** Número listo para wa.me: 0981123456 -> 595981123456 (usa el código de país de Ajustes). */
function wa_numero(string $tel): string {
    $d = preg_replace('/\D/', '', $tel);
    if ($d === '') return '';
    $cc = preg_replace('/\D/', '', cfg('codigo_pais', '595'));
    if ($cc !== '' && $d[0] === '0') return $cc . substr($d, 1);
    if ($cc !== '' && strlen($d) <= 9 && strpos($d, $cc) !== 0) return $cc . $d;
    return $d;
}
/** Devuelve la ficha del repartidor logueado; si no es repartidor activo, corta. */
function require_courier(bool $json = false): array {
    $u = user();
    if ($u) {
        $q = db()->prepare("SELECT r.* FROM repartidores r JOIN usuarios x ON x.id = r.usuario_id WHERE x.id = ? AND x.rol = 'repartidor' AND r.activo = 1");
        $q->execute([$u['id']]);
        if ($r = $q->fetch()) return $r;
    }
    if ($json) json_out(['error' => 'No autorizado'], 401);
    header('Location: ../login.php'); exit;
}
function min_txt($m): string {
    if ($m === null || $m === '') return '—';
    $m = (int)round((float)$m);
    return $m >= 60 ? intdiv($m, 60) . ' h ' . ($m % 60) . ' min' : $m . ' min';
}
function url_mapa(array $p): string {
    if ($p['lat'] !== null && $p['lat'] !== '') return 'https://www.google.com/maps/dir/?api=1&destination=' . $p['lat'] . ',' . $p['lng'];
    return $p['direccion'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($p['direccion']) : '';
}
