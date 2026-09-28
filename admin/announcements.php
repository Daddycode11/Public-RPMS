<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $targetRole = $_POST['target_role'] ?? 'all';
    $priority = $_POST['priority'] ?? 'normal';
    $expiresAt = $_POST['expires_at'] ?? null;

    if (empty($title) || empty($content)) {
        $message = 'Title and content are required.';
        $messageType = 'error';
    } else {
        $pdo->prepare("INSERT INTO announcements (title, content, target_role, priority, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$title, $content, $targetRole, $priority, $_SESSION['user_id'], $expiresAt ?: null]);
        $message = 'Announcement posted successfully.';
        $messageType = 'success';
    }
}

// Handle toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    $message = 'Announcement status updated.';
    $messageType = 'success';
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    $message = 'Announcement deleted.';
    $messageType = 'success';
}

$announcements = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS author
    FROM announcements a
    JOIN users u ON u.id = a.created_by
    ORDER BY a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements | Admin - RPMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#ea580c;--brand-dark:#b3260c;--brand-light:#ffe4d1;--brand-glow:rgba(234,88,12,.12);--ink:#2b0d05;--ink-2:#3a5042;--ink-3:#6b8878;--cream:#f0f4f1;--white:#fff;--border:rgba(234,88,12,.12);--radius:14px}
body{font-family:'Inter',sans-serif;background:var(--cream);color:var(--ink)}
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-header{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2ee}
.card-title{font-size:.95rem;font-weight:700}
.card-body{padding:22px}
.page-header h1{font-family:'Inter',sans-serif;font-size:1.7rem;font-weight:700}
.page-header .sub{font-size:.88rem;color:var(--ink-3);margin-top:4px}

.form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:span 3}
.form-group label{font-size:.82rem;font-weight:600;color:var(--ink-2)}
.form-group input,.form-group select,.form-group textarea{padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem;background:var(--white);color:var(--ink);outline:none;transition:.2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow)}
.form-group textarea{min-height:100px;resize:vertical}

.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;border:none;transition:.2s}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-dark)}
.btn-sm{padding:6px 12px;font-size:.78rem}
.btn-danger{background:#fee2e2;color:#dc2626}.btn-danger:hover{background:#fecaca}
.btn-outline{background:var(--white);border:1px solid var(--border);color:var(--ink-2)}.btn-outline:hover{background:var(--brand-light);color:var(--brand-dark)}

.alert{padding:14px 18px;border-radius:10px;font-size:.88rem;margin-bottom:16px}
.alert-success{background:#fff7ed;color:var(--brand-dark);border:1px solid var(--brand-light)}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

.ann-list{display:flex;flex-direction:column;gap:12px}
.ann-item{border:1px solid var(--border);border-radius:12px;padding:18px;transition:.2s}
.ann-item:hover{border-color:var(--brand)}
.ann-item.inactive{opacity:.5}
.ann-item .top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
.ann-item h3{font-size:1rem;font-weight:700;color:var(--ink)}
.ann-item .meta{font-size:.78rem;color:var(--ink-3);margin-bottom:8px;display:flex;gap:12px;flex-wrap:wrap}
.ann-item .content{font-size:.88rem;color:var(--ink-2);line-height:1.6}
.ann-item .actions{display:flex;gap:8px;margin-top:12px}

.badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.badge-all{background:var(--brand-light);color:var(--brand-dark)}
.badge-vendor{background:#dbeafe;color:#1e40af}
.badge-collector{background:#fef3c7;color:#92400e}
.badge-admin{background:#ede9fe;color:#6d28d9}
.badge-normal{background:var(--cream);color:var(--ink-3)}
.badge-important{background:#fef3c7;color:#92400e}
.badge-urgent{background:#fee2e2;color:#dc2626}
.badge-active{background:var(--brand-light);color:var(--brand-dark)}
.badge-expired{background:#fee2e2;color:#dc2626}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header">
        <div>
            <h1>Announcements</h1>
            <p class="sub">Post announcements visible to vendors, collectors, or all users.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><span class="card-title">Post New Announcement</span></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="Announcement title..." required>
                    </div>
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_role">
                            <option value="all">All Users</option>
                            <option value="vendor">Vendors Only</option>
                            <option value="collector">Collectors Only</option>
                            <option value="admin">Admins Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expires On (optional)</label>
                        <input type="date" name="expires_at">
                    </div>
                    <div class="form-group full">
                        <label>Content</label>
                        <textarea name="content" placeholder="Write your announcement here..." required></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:14px">Post Announcement</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">All Announcements</span><span style="font-size:.82rem;color:var(--ink-3)"><?= count($announcements) ?> total</span></div>
        <div class="card-body">
            <div class="ann-list">
            <?php if (empty($announcements)): ?>
                <p style="text-align:center;color:var(--ink-3)">No announcements yet.</p>
            <?php else: ?>
                <?php foreach ($announcements as $a):
                    $isExpired = $a['expires_at'] && strtotime($a['expires_at']) < time();
                ?>
                <div class="ann-item <?= !$a['is_active'] ? 'inactive' : '' ?>">
                    <div class="top">
                        <h3><?= htmlspecialchars($a['title']) ?></h3>
                        <div style="display:flex;gap:6px">
                            <span class="badge badge-<?= $a['target_role'] ?>"><?= ucfirst($a['target_role']) ?></span>
                            <span class="badge badge-<?= $a['priority'] ?>"><?= ucfirst($a['priority']) ?></span>
                            <?php if (!$a['is_active']): ?><span class="badge badge-expired">Inactive</span><?php endif; ?>
                            <?php if ($isExpired): ?><span class="badge badge-expired">Expired</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="meta">
                        <span>By: <?= htmlspecialchars($a['author']) ?></span>
                        <span>Posted: <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></span>
                        <?php if ($a['expires_at']): ?><span>Expires: <?= date('M d, Y', strtotime($a['expires_at'])) ?></span><?php endif; ?>
                    </div>
                    <div class="content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
                    <div class="actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this announcement?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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