<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    exit(json_encode(['error' => 'Unauthorized']));
}

// --- Get vendor ID ---
$stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vendor) exit(json_encode(['error'=>'Vendor not found']));
$vendor_id = $vendor['id'];

// --- Add payment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(403); exit(json_encode(['error'=>'Payments must be recorded by an authorized collector.'])); }

// --- Get payments table HTML for AJAX ---
if (isset($_GET['action']) && $_GET['action'] === 'get_payments') {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id=? AND deleted_at IS NULL AND status='paid' ORDER BY payment_date ASC");
    $stmt->execute([$vendor_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($payments as $p){
        $total = $p['amount_paid'] - $p['discount'] + $p['penalty'];
        echo "<tr>
            <td>".htmlspecialchars($p['payment_date'])."</td>
            <td>₱".number_format($p['amount_paid'],2)."</td>
            <td>₱".number_format($p['discount'],2)."</td>
            <td>₱".number_format($p['penalty'],2)."</td>
            <td>₱".number_format($total,2)."</td>
            <td><a href='vendor_receipt.php?id={$p['id']}' target='_blank' class='btn btn-sm btn-primary'>View</a></td>
        </tr>";
    }
    exit;
}

// --- Get outstanding balance and latest payment for chart ---
if (isset($_GET['action']) && $_GET['action'] === 'get_balance') {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id=? AND deleted_at IS NULL AND status='paid' ORDER BY payment_date ASC");
    $stmt->execute([$vendor_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT balance FROM vendors WHERE id=?");
    $stmt->execute([$vendor_id]);
    $monthly_rent = floatval($stmt->fetchColumn());

    $total_paid = 0;
    $latest_payment = 0;
    $latest_date = '';
    foreach($payments as $p){
        $amount = $p['amount_paid'] - $p['discount'] + $p['penalty'];
        $total_paid += $amount;
        $latest_payment = $amount;
        $latest_date = $p['payment_date'];
    }
    $outstanding = max(0, $monthly_rent);
    echo json_encode(['outstanding'=>$outstanding,'latest_payment'=>$latest_payment,'latest_date'=>$latest_date]);
    exit;
}
