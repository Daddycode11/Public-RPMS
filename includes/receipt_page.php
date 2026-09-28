<?php
require_once __DIR__.'/page.php';
$id=(int)($_GET['id']??0); $role=$_SESSION['role'];
$sql="SELECT p.*,v.stall_number,s.section_name,COALESCE(NULLIF(v.vendor_name,''),NULLIF(TRIM(CONCAT_WS(' ',u.first_name,u.last_name)),''),u.fullname) AS vendor_name,CONCAT_WS(' ',c.first_name,c.last_name) AS collector_name FROM payments p JOIN vendors v ON v.id=p.vendor_id JOIN users u ON u.id=v.user_id LEFT JOIN users c ON c.id=p.collector_id LEFT JOIN sections s ON s.id=v.section_id WHERE p.id=?";
$params=[$id];
if ($role==='collector') { $sql.=' AND p.collector_id=?'; $params[]=$_SESSION['user_id']; }
if ($role==='vendor') { $sql.=' AND v.user_id=?'; $params[]=$_SESSION['user_id']; }
$q=$pdo->prepare($sql); $q->execute($params); $r=$q->fetch(PDO::FETCH_ASSOC);
if (!$r) { http_response_code(404); exit('Receipt not found.'); }
pageStart('Payment Receipt');
?>
<section class="rpms-panel receipt-panel"><h2>San Jose Public Market</h2><p>Receipt #<?= (int)$r['id'] ?> · <?= h($r['payment_date']) ?></p><p><?= h($r['vendor_name']) ?><br>Stall <?= h($r['stall_number']) ?> · <?= h($r['section_name']) ?></p><p>Collector: <?= h($r['collector_name']) ?></p><p>Payment: <?= h(ucfirst($r['payment_type']??'')) ?> · <?= h($r['status']) ?><?= $r['deleted_at']?' (archived)':'' ?></p><p>Covered period: <?= h($r['period_start']??$r['payment_date']) ?> – <?= h($r['period_end']??$r['payment_date']) ?></p>
<table class="rpms-table"><tr><th>Base amount</th><td>₱<?= number_format($r['amount_paid'],2) ?></td></tr><tr><th>Discount</th><td>−₱<?= number_format($r['discount']??0,2) ?></td></tr><tr><th>Penalty</th><td>+₱<?= number_format($r['penalty']??0,2) ?></td></tr><tr><th>Total collected</th><td>₱<?= number_format($r['amount_paid']-($r['discount']??0)+($r['penalty']??0),2) ?></td></tr></table><p><img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&amp;data=RPMS-RECEIPT-<?= (int)$r['id'] ?>" alt="Receipt verification QR" width="80" height="80"><br>Verification code: RPMS-RECEIPT-<?= (int)$r['id'] ?></p></section>
<?php if($role!=='vendor'): ?><button class="rpms-button no-print" onclick="window.print()">Print receipt</button><?php endif ?>
<?php pageEnd(); ?>
