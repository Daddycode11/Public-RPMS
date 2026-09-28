<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    $collectorId = intval($_POST['collector_id'] ?? 0);
    $sectionId = intval($_POST['section_id'] ?? 0);
    $date = $_POST['assigned_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($collectorId && $sectionId) {
        // Check existing active assignment
        $exists = $pdo->prepare("SELECT id FROM collector_assignments WHERE collector_id = ? AND section_id = ? AND is_active = 1");
        $exists->execute([$collectorId, $sectionId]);
        if ($exists->fetch()) {
            $message = 'This collector is already assigned to this section.';
            $messageType = 'error';
        } else {
            $pdo->prepare("INSERT INTO collector_assignments (collector_id, section_id, assigned_date, notes) VALUES (?, ?, ?, ?)")
                ->execute([$collectorId, $sectionId, $date, $notes]);
            $message = 'Assignment created.';
            $messageType = 'success';
        }
    }
}

// Handle remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $pdo->prepare("UPDATE collector_assignments SET is_active = 0 WHERE id = ?")->execute([intval($_POST['id'])]);
    $message = 'Assignment removed.';
    $messageType = 'success';
}

// Get data
$collectors = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role='collector' ORDER BY first_name")->fetchAll(PDO::FETCH_ASSOC);
$sections = $pdo->query("SELECT id, section_name FROM sections WHERE deleted_at IS NULL ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);

$assignments = $pdo->query("
    SELECT ca.*, CONCAT(u.first_name,' ',u.last_name) AS collector_name, s.section_name,
           (SELECT COUNT(*) FROM vendors v WHERE v.section_id = ca.section_id) AS vendor_count
    FROM collector_assignments ca
    JOIN users u ON ca.collector_id = u.id
    JOIN sections s ON ca.section_id = s.id
    WHERE ca.is_active = 1
    ORDER BY u.first_name, s.section_name
")->fetchAll(PDO::FETCH_ASSOC);

// Group by collector
$byCollector = [];
foreach ($assignments as $a) {
    $byCollector[$a['collector_name']][] = $a;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Assignments | Admin - RPMS</title>
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

.form-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
.form-group{display:flex;flex-direction:column;gap:6px;flex:1;min-width:150px}
.form-group label{font-size:.82rem;font-weight:600;color:var(--ink-2)}
.form-group select,.form-group input{padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem;outline:none;transition:.2s;background:var(--white);color:var(--ink)}
.form-group select:focus,.form-group input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow)}

.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;border:none;transition:.2s}
.btn svg{width:14px;height:14px}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-dark)}
.btn-danger{background:#fee2e2;color:#dc2626;padding:6px 12px;font-size:.78rem}.btn-danger:hover{background:#fecaca}

.alert{padding:14px 18px;border-radius:10px;font-size:.88rem;margin-bottom:16px}
.alert-success{background:#fff7ed;color:var(--brand-dark);border:1px solid var(--brand-light)}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

.collector-group{margin-bottom:24px}
.collector-name{font-size:1rem;font-weight:700;color:var(--ink);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.collector-name .count{font-size:.72rem;background:var(--brand-light);color:var(--brand-dark);padding:2px 8px;border-radius:50px}

.assignment-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.assignment-card{border:1px solid var(--border);border-radius:10px;padding:16px;transition:.2s}
.assignment-card:hover{border-color:var(--brand)}
.assignment-card h4{font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:4px}
.assignment-card .meta{font-size:.78rem;color:var(--ink-3);line-height:1.6}
.assignment-card .actions{margin-top:10px}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header">
        <div>
            <h1>Collector Route / Assignment</h1>
            <p class="sub">Assign collectors to specific sections for organized collection routes.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><span class="card-title">New Assignment</span></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="form-row">
                    <div class="form-group">
                        <label>Collector</label>
                        <select name="collector_id" required>
                            <option value="">Select collector...</option>
                            <?php foreach ($collectors as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section_id" required>
                            <option value="">Select section...</option>
                            <?php foreach ($sections as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="assigned_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Optional notes...">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Assign
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Active Assignments</span><span style="font-size:.82rem;color:var(--ink-3)"><?= count($assignments) ?> active</span></div>
        <div class="card-body">
            <?php if (empty($byCollector)): ?>
                <p style="text-align:center;color:var(--ink-3)">No active assignments.</p>
            <?php else: ?>
                <?php foreach ($byCollector as $name => $assigns): ?>
                <div class="collector-group">
                    <div class="collector-name"><?= htmlspecialchars($name) ?> <span class="count"><?= count($assigns) ?> sections</span></div>
                    <div class="assignment-grid">
                        <?php foreach ($assigns as $a): ?>
                        <div class="assignment-card">
                            <h4><?= htmlspecialchars($a['section_name']) ?></h4>
                            <div class="meta">
                                Vendors: <?= $a['vendor_count'] ?><br>
                                Assigned: <?= date('M d, Y', strtotime($a['assigned_date'])) ?>
                                <?= $a['notes'] ? '<br>Notes: ' . htmlspecialchars($a['notes']) : '' ?>
                            </div>
                            <div class="actions">
                                <form method="POST" style="display:inline" onsubmit="return confirm('Remove this assignment?')">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-danger">Remove</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>
</body>
</html>