<?php
// Vista previa de totales y validación de cupón (no guarda nada).
require_once __DIR__ . '/../includes/pedido_lib.php';
$u = user();
if (!$u) json_out(['error' => 'Ingresa a tu cuenta para usar cupones.', 'login' => true], 401);
$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf'] ?? '', $in['csrf'] ?? '')) json_out(['error' => 'Sesión expirada. Recarga la página.'], 419);
$P = armar_pedido($in, (int)$u['id']);
if (isset($P['error'])) json_out(['error' => $P['error']], 422);
json_out(['ok' => true, 'subtotal' => $P['subtotal'], 'envio' => $P['envio'], 'descuento' => $P['descuento'], 'total' => $P['total'], 'msg' => $P['msg_cupon']]);
