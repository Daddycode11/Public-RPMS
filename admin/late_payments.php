<?php
require_once __DIR__ . '/../includes/protected.php';
// admin/late_payments.php
// This page will display late payments for admin review.


require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

// Fetch late payments from the database using PDO
function getLatePayments($pdo) {
    $sql = "SELECT p.*, v.vendor_name, v.stall_number, v.next_due_date AS due_date
            FROM payments p
            JOIN vendors v ON p.vendor_id = v.id
            WHERE p.deleted_at IS NULL AND p.penalty > 0
            ORDER BY v.next_due_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$latePayments = getLatePayments($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Late Payments - Admin</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<main class="rpms-main"><div class="container mt-4">
    <h2>Late Payments</h2>
    <table class="table table-bordered table-striped mt-3">
        <thead>
            <tr>
                <th>Vendor Name</th>
                <th>Stall Number</th>
                <th>Amount</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($latePayments)): ?>
            <tr><td colspan="5" class="text-center">No late payments found.</td></tr>
        <?php else: ?>
            <?php foreach ($latePayments as $payment): ?>
                <tr>
                    <td><?= htmlspecialchars($payment['vendor_name']) ?></td>
                    <td><?= htmlspecialchars($payment['stall_number']) ?></td>
                    <td><?= htmlspecialchars($payment['amount_paid']) ?></td>
                    <td><?= htmlspecialchars($payment['due_date']) ?></td>
                    <td><?= htmlspecialchars($payment['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</main></body>
</html>
