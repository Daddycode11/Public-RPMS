<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$vendor = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
$vendor->execute([$userId]);
$vendor = $vendor->fetch(PDO::FETCH_ASSOC);
$vendorId = $vendor['id'] ?? 0;

$message = '';
$messageType = '';

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if (empty($title) || empty($desc)) {
        $message = 'Title and description are required.';
        $messageType = 'error';
    } else {
        $pdo->prepare("INSERT INTO maintenance_requests (vendor_id, title, description, priority) VALUES (?, ?, ?, ?)")
            ->execute([$vendorId, $title, $desc, $priority]);
        $message = 'Request submitted successfully!';
        $messageType = 'success';
    }
}

// Get my requests
$requests = $pdo->prepare("
    SELECT m.*, CONCAT(a.first_name,' ',a.last_name) AS assigned_name
    FROM maintenance_requests m
    LEFT JOIN users a ON m.assigned_to = a.id
    WHERE m.vendor_id = ?
    ORDER BY m.created_at DESC
");
$requests->execute([$vendorId]);
$requests = $requests->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Requests | RPMS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--green:#0e9e52;--green-dark:#077a3c;--green-light:#d4f5e3;--ink:#0d1f14;--ink-2:#3a5042;--ink-3:#6b8878;--cream:#f0f4f1;--white:#fff;--border:rgba(14,158,82,.12);--radius:14px}
body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-header{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2ee}
.card-title{font-size:.95rem;font-weight:700}
.card-body{padding:22px}
.page-header h1{font-family:'Fraunces',serif;font-size:1.7rem;font-weight:700}
.page-header .sub{font-size:.88rem;color:var(--ink-3);margin-top:4px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.form-group label{font-size:.82rem;font-weight:600;color:var(--ink-2)}
.form-group input,.form-group select,.form-group textarea{padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem}
.form-group textarea{min-height:100px;resize:vertical}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;border:none;transition:.2s}
.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:var(--green-dark)}
.alert{padding:14px 18px;border-radius:10px;font-size:.88rem;margin-bottom:16px}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.req-list{display:flex;flex-direction:column;gap:12px}
.req-item{border:1px solid var(--border);border-radius:12px;padding:16px}
.req-item h3{font-size:.95rem;font-weight:700;margin-bottom:6px}
.req-item .desc{font-size:.85rem;color:var(--ink-2);line-height:1.6;margin-bottom:8px}
.req-item .meta{font-size:.78rem;color:var(--ink-3);display:flex;gap:12px;flex-wrap:wrap}
.badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-in_progress{background:#dbeafe;color:#1e40af}
.badge-completed{background:var(--green-light);color:var(--green-dark)}
.badge-rejected{background:#fee2e2;color:#dc2626}
.admin-notes{background:#f7faf8;padding:10px;border-radius:8px;font-size:.82rem;color:var(--ink-2);margin-top:8px}
</style>
</head>
<body>
<?php include 'vendor_navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header"><div><h1>Maintenance Requests</h1><p class="sub">Submit repair or maintenance requests for your stall.</p></div></div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><span class="card-title">Submit New Request</span></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="submit">
                <div class="form-group"><label>Title</label><input type="text" name="title" placeholder="Brief description of the issue..." required></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="form-group"><label>Priority</label><select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" placeholder="Describe the issue in detail..." required></textarea></div>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">My Requests</span><span style="font-size:.82rem;color:var(--ink-3)"><?= count($requests) ?> total</span></div>
        <div class="card-body">
            <div class="req-list">
            <?php if (empty($requests)): ?>
                <p style="text-align:center;color:var(--ink-3)">No requests yet.</p>
            <?php else: foreach ($requests as $r): ?>
                <div class="req-item">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start">
                        <h3><?= htmlspecialchars($r['title']) ?></h3>
                        <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
                    </div>
                    <div class="desc"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
                    <div class="meta">
                        <span>Priority: <?= ucfirst($r['priority']) ?></span>
                        <span>Submitted: <?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                        <?php if ($r['assigned_name']): ?><span>Assigned to: <?= htmlspecialchars($r['assigned_name']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($r['admin_notes']): ?>
                    <div class="admin-notes"><strong>Admin Response:</strong> <?= nl2br(htmlspecialchars($r['admin_notes'])) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

</div>
</main>
</body>
</html>
