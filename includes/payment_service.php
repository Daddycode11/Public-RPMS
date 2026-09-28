<?php
require_once __DIR__.'/payment_rules.php';
function paymentQuote(PDO $pdo, int $vendorId, string $type): array {
    if (!in_array($type,['daily','monthly'],true)) throw new RuntimeException('Choose Daily or Monthly.');
    $q=$pdo->prepare("SELECT v.*, s.section_name, COALESCE(NULLIF(v.vendor_name,''),NULLIF(TRIM(CONCAT_WS(' ',u.first_name,u.last_name)),''),u.fullname) AS display_name FROM vendors v JOIN users u ON u.id=v.user_id LEFT JOIN sections s ON s.id=v.section_id WHERE v.id=? AND v.deleted_at IS NULL AND u.deleted_at IS NULL AND u.status='active' AND v.status<>'inactive'");
    $q->execute([$vendorId]); $vendor=$q->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) throw new RuntimeException('Choose an active, approved vendor.');
    $base=$vendor[$type.'_rent'];
    if ($base === null || (float)$base<=0) throw new RuntimeException('The administrator must configure a positive '.$type.' rent before collection.');
    $date=new DateTimeImmutable('today');
    return array_merge(calculatePaymentAdjustment((float)$base,$date),[
        'vendor'=>$vendor,'payment_type'=>$type,'payment_date'=>$date->format('Y-m-d'),
        'period_start'=>$type==='monthly'?$date->format('Y-m-01'):$date->format('Y-m-d'),
        'period_end'=>$type==='monthly'?$date->format('Y-m-t'):$date->format('Y-m-d')
    ]);
}
function recordPayment(PDO $pdo, int $vendorId, int $collectorId, string $type, string $key): array {
    if (!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new RuntimeException('Refresh the payment form before submitting.');
    $pdo->beginTransaction();
    try {
        $lock=$pdo->prepare('SELECT id FROM vendors WHERE id=? FOR UPDATE'); $lock->execute([$vendorId]);
        $existing=$pdo->prepare('SELECT id,vendor_id,collector_id,payment_type FROM payments WHERE request_key=?'); $existing->execute([$key]);
        if ($row=$existing->fetch(PDO::FETCH_ASSOC)) {
            if ((int)$row['vendor_id']!==$vendorId || (int)$row['collector_id']!==$collectorId || $row['payment_type']!==$type) throw new RuntimeException('Payment request already used.');
            $pdo->commit(); return ['payment_id'=>(int)$row['id']];
        }
        $quote=paymentQuote($pdo,$vendorId,$type);
        $pdo->prepare("INSERT INTO payments (vendor_id,collector_id,amount_paid,discount,penalty,payment_date,paid_at,payment_type,period_start,period_end,status,request_key) VALUES (?,?,?,?,?,?,NOW(),?,?,?,'paid',?)")
            ->execute([$vendorId,$collectorId,$quote['base_amount'],$quote['discount'],$quote['penalty'],$quote['payment_date'],$type,$quote['period_start'],$quote['period_end'],$key]);
        $id=(int)$pdo->lastInsertId();
        // A discount settles its base rent; it must not leave a false unpaid balance.
        $pdo->prepare("UPDATE vendors SET balance=GREATEST(0,balance-?), next_due_date=CASE WHEN balance<=0 THEN GREATEST(COALESCE(next_due_date,'1000-01-01'),?) ELSE next_due_date END, status=CASE WHEN balance<=0 THEN 'active' ELSE status END WHERE id=?")
            ->execute([$quote['base_amount'],(new DateTimeImmutable($quote['period_end']))->modify('+1 day')->format('Y-m-d'),$vendorId]);
        $pdo->commit();
        return array_merge($quote,['payment_id'=>$id]);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
