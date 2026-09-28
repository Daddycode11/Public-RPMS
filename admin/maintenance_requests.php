<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');
    $assignedTo = intval($_POST['assigned_to'] ?? 0) ?: null;

    $pdo->prepare("UPDATE maintenance_requests SET status = ?, admin_notes = ?, assigned_to = ? WHERE id = ?")
        ->execute([$status, $notes, $assignedTo, $id]);
    $message = 'Request updated successfully.';
    $messageType = 'success';
}

// Get all requests
$filterStatus = $_GET['status'] ?? '';
$sql = "SELECT m.*, v.stall_number, CONCAT(u.first_name,' ',u.last_name) AS vendor_name,
               CONCAT(a.first_name,' ',a.last_name) AS assigned_name
        FROM maintenance_requests m
        JOIN vendors v ON m.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        LEFT JOIN users a ON m.assigned_to = a.id";
if ($filterStatus) $sql .= " WHERE m.status = " . $pdo->quote($filterStatus);
$sql .= " ORDER BY FIELD(m.priority,'urgent','high','medium','low'), m.created_at DESC";
$requests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get staff for assignment
$staff = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role IN ('admin','collector') ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$reqStats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
    FROM maintenance_requests
")->fetch(PDO::FETCH_ASSOC);
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

.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:18px;text-align:center}
.stat-num{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:700}
.stat-lbl{font-size:.75rem;color:var(--ink-3);text-transform:uppercase;letter-spacing:.08em;margin-top:4px}

.filter-bar{display:flex;gap:10px;margin-bottom:16px}
.filter-bar select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem}

.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;border:none;transition:.2s}
.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:var(--green-dark)}
.btn-sm{padding:6px 12px;font-size:.78rem}

.alert{padding:14px 18px;border-radius:10px;font-size:.88rem;margin-bottom:16px}
.alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}

.req-list{display:flex;flex-direction:column;gap:12px}
.req-item{border:1px solid var(--border);border-radius:12px;padding:18px;transition:.2s}
.req-item:hover{border-color:var(--green)}
.req-item .top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
.req-item h3{font-size:1rem;font-weight:700}
.req-item .desc{font-size:.88rem;color:var(--ink-2);line-height:1.6;margin-bottom:12px}
.req-item .meta{font-size:.78rem;color:var(--ink-3);display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px}

.badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-in_progress{background:#dbeafe;color:#1e40af}
.badge-completed{background:var(--green-light);color:var(--green-dark)}
.badge-rejected{background:#fee2e2;color:#dc2626}
.badge-low{background:var(--cream);color:var(--ink-3)}
.badge-medium{background:#fef3c7;color:#92400e}
.badge-high{background:#fed7aa;color:#c2410c}
.badge-urgent{background:#fee2e2;color:#dc2626}

.update-form{background:var(--cream);border-radius:10px;padding:14px;display:none;margin-top:12px}
.update-form.show{display:block}
.update-form .row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.update-form label{font-size:.78rem;font-weight:600;color:var(--ink-2);display:block;margin-bottom:4px}
.update-form select,.update-form textarea{padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-family:inherit;font-size:.82rem}
.update-form textarea{width:100%;min-height:60px;resize:vertical;margin-top:8px}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header">
        <div>
            <h1>Maintenance Requests</h1>
            <p class="sub">Manage stall repair and maintenance requests from vendors.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-num"><?= $reqStats['total'] ?></div><div class="stat-lbl">Total Requests</div></div>
        <div class="stat-card"><div class="stat-num" style="color:#f59e0b"><?= $reqStats['pending'] ?></div><div class="stat-lbl">Pending</div></div>
        <div class="stat-card"><div class="stat-num" style="color:#3b82f6"><?= $reqStats['in_progress'] ?></div><div class="stat-lbl">In Progress</div></div>
        <div class="stat-card"><div class="stat-num" style="color:var(--green)"><?= $reqStats['completed'] ?></div><div class="stat-lbl">Completed</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">All Requests</span>
            <div class="filter-bar" style="margin:0">
                <select onchange="window.location='maintenance_requests.php'+(this.value?'?status='+this.value:'')">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
                    <option value="in_progress" <?= $filterStatus==='in_progress'?'selected':'' ?>>In Progress</option>
                    <option value="completed" <?= $filterStatus==='completed'?'selected':'' ?>>Completed</option>
                    <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="req-list">
            <?php if (empty($requests)): ?>
                <p style="text-align:center;color:var(--ink-3)">No maintenance requests found.</p>
            <?php else: ?>
                <?php foreach ($requests as $r): ?>
                <div class="req-item">
                    <div class="top">
                        <h3><?= htmlspecialchars($r['title']) ?></h3>
                        <div style="display:flex;gap:6px">
                            <span class="badge badge-<?= $r['priority'] ?>"><?= ucfirst($r['priority']) ?></span>
                            <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span>
                        </div>
                    </div>
                    <div class="meta">
                        <span>Stall: <?= htmlspecialchars($r['stall_number']) ?></span>
                        <span>Vendor: <?= htmlspecialchars($r['vendor_name']) ?></span>
                        <span>Submitted: <?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                        <?php if ($r['assigned_name']): ?><span>Assigned: <?= htmlspecialchars($r['assigned_name']) ?></span><?php endif; ?>
                    </div>
                    <div class="desc"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
                    <?php if ($r['admin_notes']): ?>
                    <div style="background:#f7faf8;padding:10px;border-radius:8px;font-size:.82rem;color:var(--ink-2);margin-bottom:12px">
                        <strong>Admin Notes:</strong> <?= nl2br(htmlspecialchars($r['admin_notes'])) ?>
                    </div>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-primary" onclick="this.nextElementSibling.classList.toggle('show')">Update Status</button>
                    <div class="update-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <div class="row">
                                <div><label>Status</label><select name="status">
                                    <option value="pending" <?= $r['status']==='pending'?'selected':'' ?>>Pending</option>
                                    <option value="in_progress" <?= $r['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                                    <option value="completed" <?= $r['status']==='completed'?'selected':'' ?>>Completed</option>
                                    <option value="rejected" <?= $r['status']==='rejected'?'selected':'' ?>>Rejected</option>
                                </select></div>
                                <div><label>Assign To</label><select name="assigned_to">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staff as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $r['assigned_to']==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </div>
                            <textarea name="admin_notes" placeholder="Admin notes (optional)..."><?= htmlspecialchars($r['admin_notes'] ?? '') ?></textarea>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</main>
</body>
</html>
