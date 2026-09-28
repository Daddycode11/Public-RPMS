<?php
require_once __DIR__.'/page.php';
require_once __DIR__.'/archive.php';
$role=$_SESSION['role']; $message='';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='remove_payment') {
    try {
        if ($role==='vendor') throw new RuntimeException('Vendors may only view payment history.');
        archivePayment($pdo,(int)($_POST['id']??0),$role==='collector'?(int)$_SESSION['user_id']:null);
        $message='Payment removed from current lists. Its financial record is preserved.';
    } catch(Throwable $e) { $message=$e->getMessage(); }
}
$from=(string)($_GET['from']??$_GET['date_from']??date('Y-m-01'));
$to=(string)($_GET['to']??$_GET['date_to']??date('Y-m-d'));
$search=trim((string)($_GET['search']??'')); $collector=(int)($_GET['collector_id']??0); $vendor=(int)($_GET['vendor_id']??0);
$status=(string)($_GET['status']??''); $group=(string)($_GET['group_by']??'none');
$where=['p.deleted_at IS NULL']; $params=[];
foreach (['>='=>$from,'<='=>$to] as $op=>$date) {
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$parsed || $parsed->format('Y-m-d')!==$date) { http_response_code(400); exit('Use valid YYYY-MM-DD dates.'); }
    $where[]='p.payment_date '.$op.' ?'; $params[]=$date;
}
if ($from>$to) { http_response_code(400); exit('The start date must not be after the end date.'); }
if ($role==='collector') $collector=(int)$_SESSION['user_id'];
if ($role==='vendor') { $where[]='v.user_id=?'; $params[]=$_SESSION['user_id']; }
if ($collector) { $where[]='p.collector_id=?'; $params[]=$collector; }
if ($vendor) { $where[]='p.vendor_id=?'; $params[]=$vendor; }
if (in_array($status,['paid','pending','cancelled'],true)) { $where[]='p.status=?'; $params[]=$status; }
if ($search!=='') { $where[]="(v.vendor_name LIKE ? OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE ? OR v.stall_number LIKE ? OR s.section_name LIKE ? OR CONCAT_WS(' ',c.first_name,c.last_name) LIKE ?)"; array_push($params,...array_fill(0,5,'%'.$search.'%')); }
$q=$pdo->prepare("SELECT p.*,COALESCE(NULLIF(v.vendor_name,''),NULLIF(TRIM(CONCAT_WS(' ',u.first_name,u.last_name)),''),u.fullname) AS vendor_name,v.stall_number,s.section_name,CONCAT_WS(' ',c.first_name,c.last_name) AS collector_name,(p.amount_paid-COALESCE(p.discount,0)+COALESCE(p.penalty,0)) AS net FROM payments p JOIN vendors v ON v.id=p.vendor_id JOIN users u ON u.id=v.user_id LEFT JOIN users c ON c.id=p.collector_id LEFT JOIN sections s ON s.id=v.section_id WHERE ".implode(' AND ',$where).' ORDER BY p.payment_date DESC,p.id DESC');
$q->execute($params); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
$format=$_GET['format']??'';
if ($format==='csv' && $role!=='vendor') {
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="rpms-payments.csv"');
    $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['ID','Date','Vendor','Stall','Section','Collector','Type','Base amount','Discount','Penalty','Net','Status']);
    foreach ($rows as $r) {
        $record=[$r['id'],$r['payment_date'],$r['vendor_name'],$r['stall_number'],$r['section_name'],$r['collector_name'],$r['payment_type'],$r['amount_paid'],$r['discount'],$r['penalty'],$r['net'],$r['status']];
        fputcsv($out,array_map(static function($v) { $v=(string)$v; return preg_match('/^[\s]*[=+@\-]/u',$v)?"'".$v:$v; },$record));
    }
    fclose($out); exit;
}
$preview=in_array($format,['html_pdf','pdf'],true) && $role!=='vendor';
pageStart($role==='vendor'?'Payment History':($role==='collector'?'My Collections':'Payment Reports'));
echo '<p role="status">'.h($message).'</p>';
?>
<form class="rpms-filters no-print"><label>From<input type="date" name="from" value="<?= h($from) ?>" required></label><label>To<input type="date" name="to" value="<?= h($to) ?>" required></label><label>Vendor / Stall / Section / Collector<input name="search" value="<?= h($search) ?>"></label>
<?php if($role==='admin'): ?><label>Collector<select name="collector_id"><option value="">All collectors</option><?php foreach($pdo->query("SELECT id,CONCAT_WS(' ',first_name,last_name) AS name FROM users WHERE role='collector' ORDER BY first_name") as $c): ?><option value="<?= $c['id'] ?>" <?= $collector===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach ?></select></label><?php endif ?>
<label>Status<select name="status"><option value="">All</option><?php foreach(['paid','pending','cancelled'] as $s): ?><option <?= $s===$status?'selected':'' ?>><?= $s ?></option><?php endforeach ?></select></label>
<label>Group summary<select name="group_by"><?php foreach(['none','vendor','collector'] as $g): ?><option <?= $g===$group?'selected':'' ?>><?= $g ?></option><?php endforeach ?></select></label><?php if($vendor): ?><input type="hidden" name="vendor_id" value="<?= $vendor ?>"><?php endif ?><button class="rpms-button">Apply filters</button></form>
<?php $settled=array_filter($rows,fn($r)=>$r['status']==='paid'); ?>
<section class="rpms-panel"><strong><?= count($rows) ?> records</strong> · Paid base: ₱<?= number_format(array_sum(array_column($settled,'amount_paid')),2) ?> · Discount: ₱<?= number_format(array_sum(array_column($settled,'discount')),2) ?> · Penalty: ₱<?= number_format(array_sum(array_column($settled,'penalty')),2) ?> · Net collected: <strong>₱<?= number_format(array_sum(array_column($settled,'net')),2) ?></strong></section>
<?php if ($role!=='vendor'): ?><p class="no-print"><a class="rpms-button" href="?<?= h(http_build_query(array_merge($_GET,['format'=>'csv']))) ?>">Export Excel (CSV)</a> <a class="rpms-button" href="?<?= h(http_build_query(array_merge($_GET,['format'=>'html_pdf']))) ?>">View PDF / Print preview</a><?php if ($preview): ?> <button class="rpms-button" onclick="window.print()">Print / Save as PDF</button><?php endif ?></p><?php endif ?>
<?php if (in_array($group,['vendor','collector'],true)):
$groups=[]; foreach($settled as $r) { $key=$r[$group.'_id']; if(!isset($groups[$key])) $groups[$key]=['name'=>$r[$group.'_name'],'count'=>0,'net'=>0]; $groups[$key]['count']++; $groups[$key]['net']+=(float)$r['net']; } ?>
<section class="rpms-panel"><h2><?= h(ucfirst($group)) ?> totals</h2><table class="rpms-table"><tr><th>Name</th><th>Transactions</th><th>Net collected</th></tr><?php foreach($groups as $g): ?><tr><td><?= h($g['name']) ?></td><td><?= $g['count'] ?></td><td>₱<?= number_format($g['net'],2) ?></td></tr><?php endforeach ?></table></section><?php endif ?>
<div class="rpms-panel rpms-table-wrap"><table class="rpms-table"><thead><tr><th>Date</th><th>Vendor</th><th>Stall</th><th>Section</th><th>Collector</th><th>Type</th><th>Base</th><th>Discount</th><th>Penalty</th><th>Net</th><th>Status</th><th class="no-print">Actions</th></tr></thead><tbody>
<?php $page=max(1,min(max(1,(int)ceil(count($rows)/20)),(int)($_GET['page']??1))); foreach($preview?$rows:array_slice($rows,($page-1)*20,20) as $r): ?>
<tr><td><?= h($r['payment_date']) ?></td><td><?= h($r['vendor_name']) ?></td><td><?= h($r['stall_number']) ?></td><td><?= h($r['section_name']) ?></td><td><?= h($r['collector_name']) ?></td><td><?= h($r['payment_type']) ?></td><?php foreach(['amount_paid','discount','penalty','net'] as $key): ?><td>₱<?= number_format((float)$r[$key],2) ?></td><?php endforeach ?><td><?= h($r['status']) ?></td><td class="no-print"><a href="<?= $role==='admin'?'print_receipt.php':($role==='collector'?'collector_receipt.php':'vendor_receipt.php') ?>?id=<?= $r['id'] ?>">View</a> <?php if($role!=='vendor') echo removeForm('payment',(int)$r['id']); ?></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="12">No payments match these filters.</td></tr><?php endif ?></tbody></table><?php if(!$preview) pagination(count($rows),$page); ?></div>
<?php pageEnd(); ?>
