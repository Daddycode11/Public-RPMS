<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

// Get all sections
$sections = $pdo->query("SELECT * FROM sections WHERE deleted_at IS NULL ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);

// Get all vendors with section info
$vendors = $pdo->query("
    SELECT v.*, s.section_name,
           CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
    FROM vendors v
    LEFT JOIN sections s ON v.section_id = s.id
    LEFT JOIN users u ON v.user_id = u.id
    ORDER BY s.section_name, v.stall_number
")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive
    FROM vendors
")->fetch(PDO::FETCH_ASSOC);

// Group vendors by section
$vendorsBySection = [];
foreach ($vendors as $v) {
    $sec = $v['section_name'] ?? 'Unassigned';
    $vendorsBySection[$sec][] = $v;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stall Map | RPMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#ea580c;--brand-dark:#b3260c;--brand-light:#ffe4d1;--ink:#2b0d05;--ink-2:#3a5042;--ink-3:#6b8878;--cream:#f0f4f1;--white:#fff;--border:rgba(234,88,12,.12);--radius:14px}
body{font-family:'Inter',sans-serif;background:var(--cream);color:var(--ink)}
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-header{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2ee}
.card-title{font-size:.95rem;font-weight:700}
.card-body{padding:22px}
.page-header h1{font-family:'Inter',sans-serif;font-size:1.7rem;font-weight:700}
.page-header .sub{font-size:.88rem;color:var(--ink-3);margin-top:4px}

.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:18px;text-align:center}
.stat-num{font-family:'Inter',sans-serif;font-size:1.6rem;font-weight:700}
.stat-num.green{color:var(--brand)}.stat-num.red{color:#dc2626}.stat-num.gray{color:var(--ink-3)}
.stat-lbl{font-size:.75rem;color:var(--ink-3);text-transform:uppercase;letter-spacing:.08em;margin-top:4px}

.legend{display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap}
.legend-item{display:flex;align-items:center;gap:6px;font-size:.82rem;color:var(--ink-2)}
.legend-dot{width:14px;height:14px;border-radius:4px}

.section-group{margin-bottom:24px}
.section-label{font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.section-count{font-size:.72rem;background:var(--cream);padding:2px 8px;border-radius:50px;color:var(--ink-3)}

.stall-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
.stall-card{border:2px solid var(--border);border-radius:10px;padding:12px;text-align:center;cursor:pointer;transition:.2s;position:relative}
.stall-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.08)}
.stall-card.active{border-color:var(--brand);background:#fff7f2}
.stall-card.overdue{border-color:#fca5a5;background:#fef2f2}
.stall-card.inactive{border-color:#d1d5db;background:#f9fafb;opacity:.7}
.stall-number{font-size:.9rem;font-weight:700;color:var(--ink)}
.stall-tenant{font-size:.75rem;color:var(--ink-3);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.stall-rent{font-size:.78rem;font-weight:600;color:var(--brand);margin-top:4px}
.stall-status{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%}
.stall-status.active{background:var(--brand)}.stall-status.overdue{background:#fb7185}.stall-status.inactive{background:#9ca3af}

.filter-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.filter-bar select,.filter-bar input{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem;background:var(--white)}

/* Tooltip */
.stall-tooltip{display:none;position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:var(--ink);color:#fff;padding:10px 14px;border-radius:8px;font-size:.78rem;white-space:nowrap;z-index:10;pointer-events:none}
.stall-tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:var(--ink)}
.stall-card:hover .stall-tooltip{display:block}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header">
        <div>
            <h1>Stall / Section Map</h1>
            <p class="sub">Visual overview of all stalls by section, showing occupancy and payment status.</p>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-lbl">Total Stalls</div></div>
        <div class="stat-card"><div class="stat-num green"><?= $stats['active'] ?></div><div class="stat-lbl">Active / Occupied</div></div>
        <div class="stat-card"><div class="stat-num red"><?= $stats['overdue'] ?></div><div class="stat-lbl">Overdue</div></div>
        <div class="stat-card"><div class="stat-num gray"><?= $stats['inactive'] ?></div><div class="stat-lbl">Inactive / Vacant</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Stall Map</span>
            <div class="legend">
                <div class="legend-item"><div class="legend-dot" style="background:var(--brand)"></div> Active</div>
                <div class="legend-item"><div class="legend-dot" style="background:#fb7185"></div> Overdue</div>
                <div class="legend-item"><div class="legend-dot" style="background:#9ca3af"></div> Inactive</div>
            </div>
        </div>
        <div class="card-body">
            <div class="filter-bar">
                <select id="filterSection" onchange="filterStalls()">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?= htmlspecialchars($s['section_name']) ?>"><?= htmlspecialchars($s['section_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterStatus" onchange="filterStalls()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="overdue">Overdue</option>
                    <option value="inactive">Inactive</option>
                </select>
                <input type="text" id="filterSearch" placeholder="Search stall or vendor..." oninput="filterStalls()">
            </div>

            <?php foreach ($vendorsBySection as $sectionName => $sectionVendors): ?>
            <div class="section-group" data-section="<?= htmlspecialchars($sectionName) ?>">
                <div class="section-label">
                    <?= htmlspecialchars($sectionName) ?>
                    <span class="section-count"><?= count($sectionVendors) ?> stalls</span>
                </div>
                <div class="stall-grid">
                    <?php foreach ($sectionVendors as $v): ?>
                    <div class="stall-card <?= htmlspecialchars($v['status'] ?? 'active') ?>"
                         data-status="<?= htmlspecialchars($v['status'] ?? 'active') ?>"
                         data-search="<?= htmlspecialchars(strtolower($v['stall_number'] . ' ' . $v['tenant_name'])) ?>">
                        <div class="stall-status <?= htmlspecialchars($v['status'] ?? 'active') ?>"></div>
                        <div class="stall-number"><?= htmlspecialchars($v['stall_number']) ?></div>
                        <div class="stall-tenant"><?= htmlspecialchars($v['tenant_name'] ?? 'Vacant') ?></div>
                        <div class="stall-rent">&#8369;<?= number_format($v['monthly_rent'], 2) ?>/mo</div>
                        <div class="stall-tooltip">
                            <strong><?= htmlspecialchars($v['stall_number']) ?></strong><br>
                            Tenant: <?= htmlspecialchars($v['tenant_name'] ?? 'Vacant') ?><br>
                            Rent: &#8369;<?= number_format($v['monthly_rent'], 2) ?><br>
                            Status: <?= ucfirst($v['status'] ?? 'active') ?><br>
                            Balance: &#8369;<?= number_format($v['balance'] ?? 0, 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($vendorsBySection)): ?>
            <p style="text-align:center;color:var(--ink-3);padding:40px">No stalls found.</p>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<script>
function filterStalls() {
    const section = document.getElementById('filterSection').value.toLowerCase();
    const status = document.getElementById('filterStatus').value.toLowerCase();
    const search = document.getElementById('filterSearch').value.toLowerCase();

    document.querySelectorAll('.section-group').forEach(g => {
        const secName = g.dataset.section.toLowerCase();
        if (section && secName !== section) { g.style.display = 'none'; return; }
        g.style.display = '';

        let visibleCount = 0;
        g.querySelectorAll('.stall-card').forEach(c => {
            const matchStatus = !status || c.dataset.status === status;
            const matchSearch = !search || c.dataset.search.includes(search);
            c.style.display = (matchStatus && matchSearch) ? '' : 'none';
            if (matchStatus && matchSearch) visibleCount++;
        });
    });
}
</script>
</body>
</html>