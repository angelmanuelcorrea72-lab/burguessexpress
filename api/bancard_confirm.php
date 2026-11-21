<?php
// Webhook: Bancard avisa aquí (POST JSON) el resultado de cada pago. Configura esta URL en tu panel de Bancard.
require_once __DIR__ . '/../includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !bancard_activo()) { http_response_code(403); exit; }
$in = json_decode(file_get_contents('php://input'), true);
$op = $in['operation'] ?? null;
if (!$op || !isset($op['shop_process_id'], $op['amount'], $op['token'])) { http_response_code(403); exit; }

$sp = (int)$op['shop_process_id'];
$esperado = md5(BANCARD_PRIVATE_KEY . $sp . 'confirm' . $op['amount'] . ($op['currency'] ?? 'PYG'));
if (!hash_equals($esperado, (string)$op['token'])) { http_response_code(403); exit; }

$q = db()->prepare("SELECT * FROM pedidos WHERE id = ? AND pago = 'bancard'");
$q->execute([intdiv($sp, 100)]);
$p = $q->fetch();
if (!$p || bancard_monto($p['total']) !== bancard_monto($op['amount'])) { http_response_code(403); exit; }

$ok = ($op['response'] ?? '') === 'S' && ($op['response_code'] ?? '') === '00';
if ($p['pago_estado'] !== 'pagado')   // un pago ya confirmado no se revierte desde aquí
    db()->prepare("UPDATE pedidos SET pago_estado = ? WHERE id = ?")->execute([$ok ? 'pagado' : 'fallido', $p['id']]);
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
