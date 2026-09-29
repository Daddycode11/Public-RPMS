<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = '';
$params = [];
if ($dateFrom) { $where .= " AND p.payment_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND p.payment_date <= ?"; $params[] = $dateTo; }

try {
    // Total collection for date range
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)),0) FROM payments p WHERE p.deleted_at IS NULL AND p.status='paid' $where");
    $stmt->execute($params);
    $totalCollection = (float)$stmt->fetchColumn();

    // Payment count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p WHERE p.deleted_at IS NULL AND p.status='paid' $where");
    $stmt->execute($params);
    $totalPayments = (int)$stmt->fetchColumn();

    // Monthly data
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(p.paid_at,'%b %Y') AS month,
               SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total
        FROM payments p WHERE p.deleted_at IS NULL AND p.status='paid' $where
        GROUP BY YEAR(p.paid_at),MONTH(p.paid_at) ORDER BY YEAR(p.paid_at),MONTH(p.paid_at)
    ");
    $stmt->execute($params);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent payments
    $stmt = $pdo->prepare("
        SELECT p.id,p.payment_date, CONCAT(u.first_name,' ',u.last_name) AS vendor,
               (p.amount_paid-COALESCE(p.discount,0)+COALESCE(p.penalty,0)) AS amount_paid, COALESCE(p.status,'paid') AS status
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.deleted_at IS NULL AND p.status='paid' $where
        ORDER BY p.paid_at DESC LIMIT 5
    ");
    $stmt->execute($params);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top vendors
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.deleted_at IS NULL AND p.status='paid' $where
        GROUP BY v.id ORDER BY total DESC LIMIT 5
    ");
    $stmt->execute($params);
    $topVendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Collector performance
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total
        FROM payments p
        JOIN users u ON u.id = p.collector_id
        WHERE p.deleted_at IS NULL AND p.status='paid' $where
        GROUP BY p.collector_id ORDER BY total DESC LIMIT 5
    ");
    $stmt->execute($params);
    $collectorPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'total_collection' => $totalCollection,
        'total_payments' => $totalPayments,
        'monthly_labels' => array_column($monthlyData, 'month'),
        'monthly_data' => array_column($monthlyData, 'total'),
        'recent_payments' => $recentPayments,
        'top_vendors' => $topVendors,
        'collector_performance' => $collectorPerformance,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Report unavailable.']);
}
