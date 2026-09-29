<?php
require_once __DIR__.'/../includes/protected.php';
header('Content-Type: application/json');
$queries=[
    'vendors'=>"SELECT COUNT(*) FROM vendors WHERE deleted_at IS NULL",
    'sections'=>"SELECT COUNT(*) FROM sections WHERE deleted_at IS NULL",
    'collectors'=>"SELECT COUNT(*) FROM users WHERE role='collector' AND deleted_at IS NULL",
    'collection'=>"SELECT COALESCE(SUM(amount_paid-COALESCE(discount,0)+COALESCE(penalty,0)),0) FROM payments WHERE deleted_at IS NULL AND status='paid'",
];
$metric=$_GET['metric']??'';
if(!isset($queries[$metric])) { http_response_code(400); exit(json_encode(['error'=>'Unknown dashboard metric.'])); }
echo json_encode(['value'=>(float)$pdo->query($queries[$metric])->fetchColumn()]);
