<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$announcements = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS author
    FROM announcements a
    JOIN users u ON u.id = a.created_by
    WHERE a.is_active = 1
      AND (a.target_role = 'all' OR a.target_role = 'collector')
      AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())
    ORDER BY FIELD(a.priority,'urgent','important','normal'), a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements | RPMS</title>
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
.ann-list{display:flex;flex-direction:column;gap:16px}
.ann-item{border:1px solid var(--border);border-radius:12px;padding:20px;transition:.2s}
.ann-item:hover{border-color:var(--green)}
.ann-item.urgent{border-left:4px solid #dc2626}
.ann-item.important{border-left:4px solid #f59e0b}
.ann-item h3{font-size:1.05rem;font-weight:700;margin-bottom:8px}
.ann-item .meta{font-size:.78rem;color:var(--ink-3);margin-bottom:10px;display:flex;gap:12px}
.ann-item .content{font-size:.9rem;color:var(--ink-2);line-height:1.7}
.badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700}
.badge-normal{background:var(--cream);color:var(--ink-3)}
.badge-important{background:#fef3c7;color:#92400e}
.badge-urgent{background:#fee2e2;color:#dc2626}
</style>
</head>
<body>
<?php include 'collector_navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">
    <div class="page-header"><div><h1>Announcements</h1><p class="sub">Latest news and updates from administration.</p></div></div>

    <div class="ann-list">
    <?php if (empty($announcements)): ?>
        <div class="card"><div class="card-body" style="text-align:center;color:var(--ink-3)">No announcements at this time.</div></div>
    <?php else: foreach ($announcements as $a): ?>
        <div class="ann-item <?= $a['priority'] ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <h3><?= htmlspecialchars($a['title']) ?></h3>
                <span class="badge badge-<?= $a['priority'] ?>"><?= ucfirst($a['priority']) ?></span>
            </div>
            <div class="meta">
                <span>Posted by <?= htmlspecialchars($a['author']) ?></span>
                <span><?= date('M d, Y', strtotime($a['created_at'])) ?></span>
            </div>
            <div class="content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
        </div>
    <?php endforeach; endif; ?>
    </div>
</div>
</main>
</body>
</html>
