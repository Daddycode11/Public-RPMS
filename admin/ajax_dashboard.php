<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // KPI
    $total_vendors = (int)$pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    $total_sections = (int)$pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
    $total_collectors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='collector'")->fetchColumn();
    $total_collection = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)),0) FROM payments")->fetchColumn();

    // Monthly Collection
    $monthlyData = $pdo->query("
        SELECT DATE_FORMAT(paid_at,'%b') AS month,
               SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
        FROM payments
        GROUP BY MONTH(paid_at)
        ORDER BY MONTH(paid_at)
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent Payments
    $recentPayments = $pdo->query("
        SELECT p.id,p.payment_date,
               CONCAT(u.first_name,' ',u.last_name) AS vendor,
               p.amount_paid,
               COALESCE(p.status,'paid') AS status
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        ORDER BY p.paid_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top Vendors
    $topVendors = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.deleted_at IS NULL AND p.status='paid'
        GROUP BY v.id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Insights
    $overdueCount = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE status='overdue'")->fetchColumn();
    $bestDay = $pdo->query("
        SELECT DATE_FORMAT(paid_at,'%W')
        FROM payments
        GROUP BY DATE_FORMAT(paid_at,'%W')
        ORDER BY SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) DESC
        LIMIT 1
    ")->fetchColumn() ?: 'N/A';

    $topCollector = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name)
        FROM payments p
        JOIN users u ON u.id = p.collector_id
        GROUP BY p.collector_id
        ORDER BY SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) DESC
        LIMIT 1
    ")->fetchColumn() ?: 'N/A';

    $avgDaily = (float)$pdo->query("
        SELECT AVG(total) FROM (
            SELECT SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
            FROM payments
            GROUP BY DATE(paid_at)
        ) t
    ")->fetchColumn();

    echo json_encode([
        'total_vendors'=>$total_vendors,
        'total_sections'=>$total_sections,
        'total_collectors'=>$total_collectors,
        'total_collection'=>$total_collection,
        'monthlyData'=>$monthlyData,
        'recentPayments'=>$recentPayments,
        'topVendors'=>$topVendors,
        'overdueCount'=>$overdueCount,
        'bestDay'=>$bestDay,
        'topCollector'=>$topCollector,
        'avgDaily'=>$avgDaily
    ]);

} catch (PDOException $e) {
    echo json_encode(['error'=>$e->getMessage()]);
}
