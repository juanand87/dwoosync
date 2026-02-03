<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
startSecureSession();

// Simular subscriber_id = 1
$_SESSION['subscriber_id'] = 1;

$db = getDatabase();

echo "=== DEBUG BILLING STATUS ===\n";

// Verificar todos los ciclos para subscriber_id = 1
$stmt = $db->prepare('SELECT id, subscriber_id, plan_type, status, amount, created_at FROM billing_cycles WHERE subscriber_id = ? ORDER BY created_at DESC');
$stmt->execute([1]);
$cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Ciclos de facturación para subscriber_id = 1:\n";
foreach ($cycles as $cycle) {
    echo "ID: {$cycle['id']}, Plan: {$cycle['plan_type']}, Status: {$cycle['status']}, Amount: {$cycle['amount']}, Created: {$cycle['created_at']}\n";
}

// Verificar específicamente los ciclos con status = 'paid'
$stmt = $db->prepare('SELECT COUNT(*) as count FROM billing_cycles WHERE subscriber_id = ? AND status = ?');
$stmt->execute([1, 'paid']);
$paidCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "\nCiclos con status = 'paid': {$paidCount}\n";

// Verificar la lógica de la notificación
$stmt = $db->prepare('SELECT * FROM billing_cycles WHERE subscriber_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([1, 'paid']);
$billing_cycle_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($billing_cycle_data) {
    echo "NOTIFICACIÓN: NO se mostrará (hay factura pagada)\n";
} else {
    echo "NOTIFICACIÓN: SÍ se mostrará (no hay factura pagada)\n";
}

echo "\n=== FIN DEBUG ===\n";
?>
