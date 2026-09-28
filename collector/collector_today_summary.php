<?php
require_once __DIR__.'/../includes/protected.php';
header('Content-Type: application/json');
$q=$pdo->prepare("SELECT COUNT(*) AS count,COALESCE(SUM(amount_paid-COALESCE(discount,0)+COALESCE(penalty,0)),0) AS total FROM payments WHERE collector_id=? AND payment_date=CURDATE() AND status='paid' AND deleted_at IS NULL");
$q->execute([$_SESSION['user_id']]); echo json_encode($q->fetch(PDO::FETCH_ASSOC));
