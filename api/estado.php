<?php
require_once __DIR__ . '/../includes/functions.php';
$u = user();
if (!$u) json_out(['error' => 'No autorizado'], 401);
$q = db()->prepare("SELECT p.estado, p.minutos, p.tipo, p.pago_estado, UNIX_TIMESTAMP(p.creado) AS t0, r.nombre AS rep, r.telefono AS rep_tel
    FROM pedidos p LEFT JOIN repartidores r ON r.id = p.repartidor_id WHERE p.id = ? AND p.usuario_id = ?");
$q->execute([(int)($_GET['id'] ?? 0), $u['id']]);
$p = $q->fetch();
if (!$p) json_out(['error' => 'No encontrado'], 404);
$restante = max(0, (int)ceil(($p['t0'] + $p['minutos'] * 60 - time()) / 60));
json_out(['estado' => $p['estado'], 'etiqueta' => ESTADOS[$p['estado']], 'restante' => $restante, 'tipo' => $p['tipo'],
          'pago_estado' => $p['pago_estado'], 'repartidor' => $p['rep'], 'repartidor_wa' => $p['rep_tel'] ? wa_numero($p['rep_tel']) : null]);
