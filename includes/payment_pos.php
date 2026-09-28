<?php
require_once __DIR__.'/page.php';
require_once __DIR__.'/payment_service.php';
$error=''; $quote=null;
$vendorId=(int)($_REQUEST['vendor_id']??0); $type=$_REQUEST['payment_type']??'daily';
if (!is_string($type)) $type='daily';
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    try {
        $result=recordPayment($pdo,$vendorId,(int)$_SESSION['user_id'],$type,(string)($_POST['request_key']??''));
        $receipt=$_SESSION['role']==='admin'?'print_receipt.php':'collector_receipt.php';
        header('Location: '.$receipt.'?id='.$result['payment_id']); exit;
    } catch (Throwable $e) { $error=$e instanceof PDOException?'Payment could not be saved. Please retry.':$e->getMessage(); }
}
$search=trim((string)($_GET['search']??$_GET['stall']??''));
$q=$pdo->prepare("SELECT v.id,v.stall_number,s.section_name,COALESCE(NULLIF(v.vendor_name,''),NULLIF(TRIM(CONCAT_WS(' ',u.first_name,u.last_name)),''),u.fullname) AS name FROM vendors v JOIN users u ON u.id=v.user_id LEFT JOIN sections s ON s.id=v.section_id WHERE v.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status='active' AND v.status<>'inactive' AND (v.stall_number LIKE ? OR v.vendor_name LIKE ? OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE ? OR s.section_name LIKE ?) ORDER BY s.section_name,CAST(SUBSTRING_INDEX(v.stall_number,'-',-1) AS UNSIGNED),v.id");
$like='%'.$search.'%'; $q->execute([$like,$like,$like,$like]); $vendors=$q->fetchAll(PDO::FETCH_ASSOC);
if ($vendorId) { try { $quote=paymentQuote($pdo,$vendorId,$type); } catch (Throwable $e) { $error=$e->getMessage(); } }
pageStart('Submit Payment');
?>
<p>Search a stall, vendor, or section, then choose the matching vendor. Adjustments are calculated using today's date.</p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif ?>
<section class="rpms-panel">
<form method="get" class="rpms-filters"><label>Stall / Vendor / Section<input name="search" value="<?= h($search) ?>" placeholder="V-01 or Dry Goods"></label><button class="rpms-button">Search</button></form>
<form method="get" class="rpms-filters"><input type="hidden" name="search" value="<?= h($search) ?>">
<label>Vendor and section<select name="vendor_id" required><option value="">Select vendor...</option><?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $vendorId===(int)$v['id']?'selected':'' ?>><?= h($v['stall_number'].' · '.$v['section_name'].' · '.$v['name']) ?></option><?php endforeach ?></select></label>
<label>Payment type<select name="payment_type"><option value="daily" <?= $type==='daily'?'selected':'' ?>>Daily</option><option value="monthly" <?= $type==='monthly'?'selected':'' ?>>Monthly</option></select></label><button class="rpms-button">Calculate payment</button></form>
<?php if (!$vendors): ?><p>No matching approved vendors.</p><?php endif ?>
</section>
<?php if ($quote): ?>
<section class="rpms-panel"><h2>Review payment</h2><p><?= h($quote['vendor']['display_name'].' · '.$quote['vendor']['stall_number'].' · '.$quote['vendor']['section_name']) ?></p>
<p><?= h($quote['period_start'].' to '.$quote['period_end']) ?></p>
<table class="rpms-table"><tr><th>Base rent</th><td>₱<?= number_format($quote['base_amount'],2) ?></td></tr><tr><th>Discount (days 1–5: 5%)</th><td>₱<?= number_format($quote['discount'],2) ?></td></tr><tr><th>Penalty (day 21–month end: 20%)</th><td>₱<?= number_format($quote['penalty'],2) ?></td></tr><tr><th>Total to collect</th><td>₱<?= number_format($quote['total'],2) ?></td></tr></table>
<form method="post" onsubmit="this.querySelector('button').disabled=true"><?= csrfField() ?><input type="hidden" name="vendor_id" value="<?= $vendorId ?>"><input type="hidden" name="payment_type" value="<?= h($type) ?>"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(32)) ?>"><button class="rpms-button">Confirm payment and view receipt</button></form></section>
<?php endif; pageEnd(); ?>
