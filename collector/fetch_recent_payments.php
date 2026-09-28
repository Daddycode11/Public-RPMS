<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    echo json_encode([]);
    exit;
}

$collector_id = $_SESSION['user_id'];
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$limit = 10;

$paymentsStmt = $pdo->prepare("
    SELECT p.*, v.vendor_name, v.stall_number
    FROM payments p
    LEFT JOIN vendors v ON v.id = p.vendor_id
    WHERE p.collector_id = :collector_id
      AND DATE(p.paid_at) BETWEEN :from AND :to
    ORDER BY p.paid_at DESC
    LIMIT :limit
");
$paymentsStmt->bindValue(':collector_id', $collector_id, PDO::PARAM_INT);
$paymentsStmt->bindValue(':from', $from);
$paymentsStmt->bindValue(':to', $to);
$paymentsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$paymentsStmt->execute();
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($payments);
