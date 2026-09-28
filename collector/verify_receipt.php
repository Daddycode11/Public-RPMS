<?php
require_once __DIR__ . '/../includes/protected.php';
require_once '../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) exit('Invalid receipt');

$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        v.stall_number,
        v.monthly_rent,
        u.first_name, u.last_name
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    WHERE p.id = ? AND p.collector_id = ?
");
$stmt->execute([$id,$_SESSION['user_id']]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) exit('Receipt not found');

$total = $p['amount_paid'] - $p['discount'] + $p['penalty'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Receipt Verification</title>
<style>
body{font-family:Arial;background:#f8f9fa;padding:20px}
.card{max-width:420px;margin:auto;background:#fff;padding:20px;border-radius:10px}
.ok{color:green;font-weight:bold}
</style>
</head>
<body>

<div class="card">
<h3>✅ Receipt Verified</h3>
<p class="ok">Valid POS Transaction</p>
<hr>
<p><strong>Vendor:</strong> <?= h($p['first_name'].' '.$p['last_name']) ?></p>
<p><strong>Stall #:</strong> <?= h($p['stall_number']) ?></p>
<p><strong>Payment Type:</strong> <?= ucfirst($p['payment_type']) ?></p>
<p><strong>Date:</strong> <?= date('M d, Y', strtotime($p['payment_date'])) ?></p>
<p><strong>Total Paid:</strong> ₱<?= number_format($total,2) ?></p>
</div>

</body>
</html>
