<?php
require_once __DIR__ . '/../includes/pedido_lib.php';
$u = user();
if (!$u) json_out(['error' => 'Ingresa a tu cuenta para pedir.', 'login' => true], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Método no permitido'], 405);
$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf'] ?? '', $in['csrf'] ?? '')) json_out(['error' => 'Sesión expirada. Recarga la página.'], 419);
if (!estadoLocal()[0]) json_out(['error' => 'El local está cerrado en este momento.'], 409);

$pagos = ['efectivo', 'transferencia', 'tarjeta'];
if (bancard_activo()) $pagos[] = 'bancard';
$pago = $in['pago'] ?? '';
if (!in_array($pago, $pagos, true)) json_out(['error' => 'Elige una forma de pago.'], 422);

$P = armar_pedido($in, (int)$u['id']);
if (isset($P['error'])) json_out(['error' => $P['error']], $P['error'] ? 422 : 500);

$dir = trim($in['direccion'] ?? ''); $lat = $in['lat'] ?? null; $lng = $in['lng'] ?? null;
$lat = is_numeric($lat) && abs($lat) <= 90 ? (float)$lat : null;
$lng = is_numeric($lng) && abs($lng) <= 180 ? (float)$lng : null;
if ($P['tipo'] === 'delivery' && $dir === '' && $lat === null) json_out(['error' => 'Indica tu dirección o comparte tu ubicación.'], 422);

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO pedidos (usuario_id,tipo,pago,subtotal,envio,descuento,cupon,total,direccion,lat,lng,notas,minutos,zona_id,pago_estado)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$u['id'], $P['tipo'], $pago, $P['subtotal'], $P['envio'], $P['descuento'], $P['cupon'], $P['total'],
                   mb_substr($dir, 0, 255) ?: null, $lat, $lng, mb_substr(trim($in['notas'] ?? ''), 0, 255) ?: null,
                   $P['minutos'], $P['zona_id'], $pago === 'bancard' ? 'pendiente' : 'no_aplica']);
    $pid = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare("INSERT INTO pedido_items (pedido_id,producto_id,nombre,precio,cantidad,opciones) VALUES (?,?,?,?,?,?)");
    foreach ($P['items'] as $i) $ins->execute([$pid, $i['id'], $i['nombre'], $i['precio'], $i['q'], $i['opciones'] ?: null]);
    if ($P['cupon']) $pdo->prepare("UPDATE cupones SET usos = usos + 1 WHERE codigo = ?")->execute([$P['cupon']]);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    json_out(['error' => 'No pudimos registrar el pedido. Intenta de nuevo.'], 500);
}
json_out(['ok' => true, 'id' => $pid, 'pagar' => $pago === 'bancard']);
