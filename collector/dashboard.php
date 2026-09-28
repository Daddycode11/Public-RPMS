<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

/* ===================== AUTH ===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$collector_id = $_SESSION['user_id'];

/* ===================== DATE FILTER ===================== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

/* ===================== COLLECTOR INFO ===================== */
$collectorStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$collectorStmt->execute([$collector_id]);
$collector = $collectorStmt->fetch(PDO::FETCH_ASSOC);

/* ===================== PAGINATION ===================== */
$limit = 10;
$page = max(1,(int)($_GET['page']??1));
$start = ($page - 1) * $limit;

/* ===================== PAYMENTS COUNT ===================== */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM payments
    WHERE deleted_at IS NULL AND status='paid' AND collector_id = ? AND DATE(paid_at) BETWEEN ? AND ?
");
$countStmt->execute([$collector_id, $from, $to]);
$totalPayments = $countStmt->fetchColumn();
$totalPages    = ceil($totalPayments / $limit);

/* ===================== PAGINATED PAYMENTS ===================== */
$paymentsStmt = $pdo->prepare("
    SELECT p.*, v.vendor_name, v.stall_number
    FROM payments p
    LEFT JOIN vendors v ON v.id = p.vendor_id
    WHERE p.deleted_at IS NULL AND p.status='paid' AND p.collector_id = :cid
      AND DATE(p.paid_at) BETWEEN :from AND :to
    ORDER BY p.paid_at DESC
    LIMIT :start, :lim
");
$paymentsStmt->bindValue(':cid',   $collector_id, PDO::PARAM_INT);
$paymentsStmt->bindValue(':from',  $from);
$paymentsStmt->bindValue(':to',    $to);
$paymentsStmt->bindValue(':start', $start, PDO::PARAM_INT);
$paymentsStmt->bindValue(':lim',   $limit, PDO::PARAM_INT);
$paymentsStmt->execute();
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/* ===================== TOTAL COLLECTED ===================== */
$total_collected = array_sum(array_map(fn($p) =>
    ($p['amount_paid'] ?? 0) - ($p['discount'] ?? 0) + ($p['penalty'] ?? 0), $payments));

/* ===================== BEST VENDOR ===================== */
$bestVendor = $pdo->query("
    SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
           SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total,
           AVG(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS avg_daily
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    WHERE p.status = 'paid' AND p.deleted_at IS NULL
    GROUP BY v.id
    ORDER BY total DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

/* ===================== VENDOR STATUS ===================== */
$vendorStatus = $pdo->query("
    SELECT SUM(status='active') AS active,
           SUM(status='inactive') AS inactive,
           SUM(status='overdue') AS overdue
    FROM vendors
")->fetch(PDO::FETCH_ASSOC);

/* ===================== VENDOR RANKINGS ===================== */
$vendorRankingStmt = $pdo->prepare("
    SELECT v.id, v.stall_number, s.section_name,
           CONCAT(u.first_name,' ',u.last_name) AS name,
           COUNT(p.id) AS transactions,
           COALESCE(SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)),0) AS total_collected
    FROM vendors v
    LEFT JOIN payments p ON p.vendor_id = v.id AND p.deleted_at IS NULL AND p.status='paid' AND p.collector_id=$collector_id AND DATE(p.paid_at) BETWEEN :from AND :to
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN sections s ON s.id=v.section_id
    WHERE v.deleted_at IS NULL
    GROUP BY v.id
    ORDER BY total_collected DESC
");
$vendorRankingStmt->execute([':from' => $from, ':to' => $to]);
$rankings = $vendorRankingStmt->fetchAll(PDO::FETCH_ASSOC);
$rankSearch=trim((string)($_GET['rank_search']??''));
$rankings=array_values(array_filter($rankings,fn($v)=>$rankSearch==='' || stripos($v['name'].' '.$v['stall_number'].' '.$v['section_name'],$rankSearch)!==false));
$rankCount=count($rankings); $rankPage=max(1,min(max(1,(int)ceil($rankCount/20)),(int)($_GET['rank_page']??1)));
$rankings=array_slice($rankings,($rankPage-1)*20,20);
$totalQuery=$pdo->prepare("SELECT COALESCE(SUM(amount_paid-COALESCE(discount,0)+COALESCE(penalty,0)),0) FROM payments WHERE collector_id=? AND deleted_at IS NULL AND status='paid' AND DATE(paid_at) BETWEEN ? AND ?");
$totalQuery->execute([$collector_id,$from,$to]); $total_collected=(float)$totalQuery->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Dashboard | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
/* ============================================================
   ROOT
============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --brand:       #ea580c;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.14);
  --ink:         #0d1f14;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.13);
  --radius:      14px;
  --radius-sm:   10px;
  --shadow:      0 2px 16px rgba(0,0,0,.06);
  --error:       #dc2626;
}
body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }

/* ============================================================
   LAYOUT
============================================================ */
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* ============================================================
   HEADER
============================================================ */
.dash-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  flex-wrap: wrap; gap: 14px;
}
.dash-header-left h1 {
  font-family: 'Inter', sans-serif; font-weight: 700;
  font-size: 1.75rem; font-weight: 700; line-height: 1;
  color: var(--ink);
}
.dash-header-left .sub { font-size: .88rem; color: var(--ink-3); margin-top: 5px; }

/* Date filter */
.date-filter-form {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.date-filter-form input[type="date"] {
  padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px;
  font-family: inherit; font-size: .87rem; color: var(--ink);
  background: var(--white); outline: none; transition: .2s;
}
.date-filter-form input[type="date"]:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 8px; font-family: inherit; font-size: .87rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: .2s; }
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); }
.btn-outline { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--brand-light); color: var(--brand-dark); }
.btn-receipt { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 5px 12px; font-size: .79rem; border-radius: 7px; }
.btn-receipt:hover { background: #bfdbfe; }
.btn-lg { padding: 13px 28px; font-size: .95rem; border-radius: 10px; }

/* ============================================================
   KPI CARDS
============================================================ */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.kpi-card {
  background: var(--white); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 20px 22px;
  position: relative; overflow: hidden; transition: .3s;
}
.kpi-card::after {
  content:""; position:absolute; top:0; left:0; right:0; height:3px;
  background: linear-gradient(90deg,var(--brand),#ffb347);
  transform:scaleX(0); transform-origin:left; transition:.35s;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px var(--brand-glow); }
.kpi-card:hover::after { transform: scaleX(1); }
.kpi-icon { width:44px;height:44px;border-radius:12px;background:var(--brand-light);display:flex;align-items:center;justify-content:center;color:var(--brand-dark);margin-bottom:14px; }
.kpi-icon svg { width:22px;height:22px; }
.kpi-label { font-size:.73rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-3);margin-bottom:4px; }
.kpi-val { font-family:'Inter',sans-serif;font-weight:700;font-size:1.65rem;color:var(--ink);line-height:1; }
.kpi-val.sm { font-size:1.1rem; }

/* ============================================================
   CHARTS ROW
============================================================ */
.charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.card { background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden; }
.card-header { padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2ee;flex-wrap:wrap;gap:8px; }
.card-title { font-size:.95rem;font-weight:700;color:var(--ink); }
.card-body { padding:20px 22px; }

/* Donut legend */
.donut-legend { display:flex;flex-direction:column;gap:8px;margin-top:16px; }
.dl-item { display:flex;align-items:center;justify-content:space-between;font-size:.83rem; }
.dl-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-right:8px; }

/* ============================================================
   POS FORM
============================================================ */
.pos-card {
  background: var(--white); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
}
.pos-header {
  padding: 18px 24px 16px;
  display: flex; align-items: center; gap: 12px;
  border-bottom: 1px solid #edf2ee;
  background: linear-gradient(135deg, var(--ink) 0%, #1a3020 100%);
}
.pos-header-icon { width:40px;height:40px;border-radius:10px;background:rgba(234,88,12,.3);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0; }
.pos-header h2 { font-family:'Inter',sans-serif;font-weight:700;font-size:1.2rem;color:#fff; }
.pos-header p  { font-size:.8rem;color:rgba(255,255,255,.55); }
.pos-body { padding: 24px; }

/* Stall lookup */
.stall-lookup-wrap { position:relative;margin-bottom:24px; }
.stall-input {
  width:100%; padding:14px 18px 14px 52px;
  border:2px solid var(--border); border-radius:12px;
  font-family:'Inter',sans-serif;font-weight:700; font-size:1.5rem;
  color:var(--ink); background:var(--white); outline:none; transition:.25s;
  letter-spacing:.05em;
}
.stall-input:focus { border-color:var(--brand); box-shadow:0 0 0 4px var(--brand-glow); }
.stall-icon { position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:1.4rem;color:var(--ink-3); }
.stall-status { margin-top:8px;font-size:.85rem;display:flex;align-items:center;gap:6px; }
.icon-inline { display:inline-flex;flex-shrink:0; }
.stall-status.found  { color:var(--brand-dark); }
.stall-status.error  { color:var(--error); }
.stall-status.search { color:var(--ink-3); }

/* Vendor info card */
.vendor-info-card {
  background:var(--cream);border:1px solid var(--border);
  border-radius:var(--radius-sm);padding:16px 18px;
  margin-bottom:20px;
  display:none;
  animation: fadeIn .3s ease;
}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.vendor-info-card.show { display:block; }
.vi-row { display:flex;flex-wrap:wrap;gap:16px; }
.vi-item { flex:1;min-width:120px; }
.vi-label { font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3);margin-bottom:3px; }
.vi-val { font-size:.95rem;font-weight:700;color:var(--ink); }
.vi-tag { display:inline-flex;align-items:center;gap:4px;background:var(--brand-light);color:var(--brand-dark);font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:6px; }

/* Form grid */
.form-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px; }
.form-grid-3 { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px; }
.field label { display:block;font-size:.8rem;font-weight:600;color:var(--ink-2);margin-bottom:5px; }
.field input, .field select {
  width:100%;padding:10px 13px;
  border:1.5px solid #e0e8e3;border-radius:9px;
  font-family:inherit;font-size:.9rem;color:var(--ink);
  outline:none;transition:.2s;background:var(--white);
}
.field input:focus,.field select:focus { border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow); }
.field input[readonly] { background:var(--cream);color:var(--ink-2); }
.field input::placeholder { color:#b0c4b8; }

/* Total display */
.total-display {
  background:var(--ink);border-radius:var(--radius-sm);padding:16px 20px;
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:20px;
}
.total-display .tl-label { font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.5); }
.total-display .tl-val { font-family:'Inter',sans-serif;font-weight:700;font-size:1.8rem;color:#ffb37a; }
.total-display .tl-breakdown { font-size:.78rem;color:rgba(255,255,255,.45); }

/* Offline banner */
.offline-banner {
  display:none;background:#fff7ed;border:1px solid #fde68a;
  border-radius:8px;padding:10px 16px;font-size:.85rem;color:#92400e;
  margin-bottom:16px;
}

/* ============================================================
   RECENT PAYMENTS TABLE
============================================================ */
.data-table { width:100%;border-collapse:collapse; }
.data-table thead th { font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-3);padding:11px 16px;text-align:left;background:var(--cream);border-bottom:1px solid #edf2ee;white-space:nowrap; }
.data-table td { padding:11px 16px;font-size:.875rem;color:var(--ink-2);border-bottom:1px solid #f0f4f1; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover td { background:#f7faf8; }
.amount-val { font-family:'Inter',sans-serif;font-weight:700;color:var(--ink); }
.stall-badge { display:inline-flex;align-items:center;background:#eff6ff;color:#1e40af;font-size:.75rem;font-weight:700;padding:3px 9px;border-radius:6px; }

/* Discount / Penalty inline */
.disc-val { color:var(--brand-dark);font-size:.82rem; }
.pen-val  { color:var(--error);font-size:.82rem; }

/* ============================================================
   PAGINATION
============================================================ */
.table-footer { display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-top:1px solid #edf2ee;font-size:.82rem;color:var(--ink-3);flex-wrap:wrap;gap:10px; }
.pagination { display:flex;gap:4px; }
.page-btn { min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:7px;background:var(--white);color:var(--ink-2);font-size:.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;font-family:inherit;text-decoration:none; }
.page-btn:hover { background:var(--brand-light);border-color:var(--brand);color:var(--brand-dark); }
.page-btn.active { background:var(--brand);border-color:var(--brand);color:#fff; }
.page-btn.disabled { opacity:.4;pointer-events:none; }

/* ============================================================
   RANKINGS TABLE
============================================================ */
.rank-num { width:28px;height:28px;border-radius:50%;background:var(--cream);color:var(--ink-3);font-size:.78rem;font-weight:700;display:flex;align-items:center;justify-content:center; }
.rank-num.top { background:var(--brand-light);color:var(--brand-dark); }
.rank-name { font-weight:600;color:var(--ink); }
.best-badge { background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#78350f;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:50px; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width:1100px) { .kpi-grid{grid-template-columns:repeat(2,1fr);} .charts-row{grid-template-columns:1fr;} }
@media (max-width:800px)  { .form-grid{grid-template-columns:1fr 1fr;} .form-grid-3{grid-template-columns:1fr 1fr;} }
@media (max-width:550px)  { .kpi-grid{grid-template-columns:1fr 1fr;} .form-grid,.form-grid-3{grid-template-columns:1fr;} .dash-header{flex-direction:column;align-items:flex-start;} }
</style>
</head>
<body>

<?php include __DIR__ . '/collector_navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- ====== HEADER ====== -->
  <div class="dash-header">
    <div class="dash-header-left">
      <h1>Welcome, <?= htmlspecialchars($collector['first_name']) ?></h1>
      <p class="sub">Collection dashboard — <?= date('l, F j, Y') ?></p>
    </div>
    <form class="date-filter-form" method="GET">
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
      <span style="font-size:.82rem;color:var(--ink-3)">to</span>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
      <button type="submit" class="btn btn-primary">Filter</button>
    </form>
  </div>

  <!-- ====== KPI CARDS ====== -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m4-9.5a2.5 2.5 0 00-2.5-2.5h-2A2.5 2.5 0 009 8.5c0 1.38 1.12 2.5 2.5 2.5h1a2.5 2.5 0 012.5 2.5c0 1.38-1.12 2.5-2.5 2.5h-2A2.5 2.5 0 018 13.5"/></svg></div>
      <div class="kpi-label">Total Collected</div>
      <div class="kpi-val">₱<?= number_format($total_collected, 2) ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></div>
      <div class="kpi-label">Transactions</div>
      <div class="kpi-val"><?= (int)$totalPayments ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg></div>
      <div class="kpi-label">Avg Daily Collection</div>
      <div class="kpi-val">
        <?= ($bestVendor && isset($bestVendor['avg_daily']))
          ? '₱' . number_format($bestVendor['avg_daily'], 2) : '—' ?>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/></svg></div>
      <div class="kpi-label">Best Vendor</div>
      <div class="kpi-val sm"><?= $bestVendor ? htmlspecialchars($bestVendor['name']) : '—' ?></div>
    </div>
  </div>

  <!-- ====== CHARTS ====== -->
  <div class="charts-row">
    <!-- Line chart -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Payments Over Time</span>
        <span style="font-size:.8rem;color:var(--ink-3)"><?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></span>
      </div>
      <div class="card-body">
        <canvas id="paymentsChart" height="110"></canvas>
      </div>
    </div>
    <!-- Donut chart -->
    <div class="card">
      <div class="card-header"><span class="card-title">Vendor Status</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;align-items:center;">
        <div style="max-width:180px;width:100%;"><canvas id="vendorChart"></canvas></div>
        <div class="donut-legend">
          <div class="dl-item"><span style="display:flex;align-items:center;"><span class="dl-dot" style="background:#ea580c"></span>Active</span><strong><?= $vendorStatus['active'] ?></strong></div>
          <div class="dl-item"><span style="display:flex;align-items:center;"><span class="dl-dot" style="background:#6b8878"></span>Inactive</span><strong><?= $vendorStatus['inactive'] ?></strong></div>
          <div class="dl-item"><span style="display:flex;align-items:center;"><span class="dl-dot" style="background:#fb7185"></span>Overdue</span><strong><?= $vendorStatus['overdue'] ?></strong></div>
        </div>
      </div>
    </div>
  </div>

  <p><a class="btn btn-primary" href="collector_payments.php">Submit payment</a></p>
  <!-- ====== RECENT PAYMENTS ====== -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Payments</span>
      <span style="font-size:.82rem;color:var(--ink-3)"><?= $totalPayments ?> total</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Vendor</th>
            <th>Stall</th>
            <th>Amount</th>
            <th>Discount</th>
            <th>Penalty</th>
            <th>Net Total</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($payments)): ?>
            <?php foreach ($payments as $p):
              $net = ($p['amount_paid'] ?? 0) - ($p['discount'] ?? 0) + ($p['penalty'] ?? 0);
            ?>
            <tr>
              <td><?= date('M d, Y h:i A', strtotime($p['paid_at'])) ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($p['vendor_name']) ?></td>
              <td><span class="stall-badge"><?= htmlspecialchars($p['stall_number']) ?></span></td>
              <td class="amount-val">₱<?= number_format($p['amount_paid'], 2) ?></td>
              <td class="disc-val">–₱<?= number_format($p['discount'], 2) ?></td>
              <td class="pen-val">+₱<?= number_format($p['penalty'], 2) ?></td>
              <td class="amount-val">₱<?= number_format($net, 2) ?></td>
              <td>
                <a href="collector_receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn-receipt btn">View</a>
              </td>
            </tr>
            <?php endforeach ?>
          <?php else: ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--ink-3);">No payments in this date range.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <div class="table-footer">
      <span>Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
      <div class="pagination">
        <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
           href="?page=<?= $page-1 ?>&from=<?= $from ?>&to=<?= $to ?>">‹</a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages, $page+2); $i++): ?>
          <a class="page-btn <?= $i === $page ? 'active' : '' ?>"
             href="?page=<?= $i ?>&from=<?= $from ?>&to=<?= $to ?>"><?= $i ?></a>
        <?php endfor ?>
        <a class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
           href="?page=<?= $page+1 ?>&from=<?= $from ?>&to=<?= $to ?>">›</a>
      </div>
    </div>
  </div>

  <!-- ====== VENDOR RANKINGS ====== -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Vendor Performance Ranking</span>
<form method="get" class="rpms-filters"><input type="hidden" name="from" value="<?= h($from) ?>"><input type="hidden" name="to" value="<?= h($to) ?>"><input name="rank_search" value="<?= h($rankSearch) ?>" placeholder="Vendor, stall, section"><button>Search</button></form>
<nav>Page <?= $rankPage ?> of <?= max(1,(int)ceil($rankCount/20)) ?> ? <a href="?<?= h(http_build_query(array_merge($_GET,['rank_page'=>max(1,$rankPage-1)]))) ?>">Previous</a> ? <a href="?<?= h(http_build_query(array_merge($_GET,['rank_page'=>min(max(1,(int)ceil($rankCount/20)),$rankPage+1)]))) ?>">Next</a></nav>
      <span style="font-size:.82rem;color:var(--ink-3)"><?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Vendor</th><th>Stall</th><th>Section</th><th>Transactions</th><th>Total Collected</th></tr>
        </thead>
        <tbody>
          <?php if (!empty($rankings)): ?>
            <?php foreach ($rankings as $i => $v): ?>
            <tr>
              <td><div class="rank-num <?= $i === 0 ? 'top' : '' ?>"><?= $i + 1 ?></div></td>
              <td>
                <span class="rank-name"><?= htmlspecialchars($v['name']) ?></span>
                <?php if ($i === 0): ?> <span class="best-badge">BEST</span><?php endif ?>
              </td>
              <td><?= h($v['stall_number']) ?></td><td><?= h($v['section_name']) ?></td><td><?= $v['transactions'] ?></td>
              <td class="amount-val">₱<?= number_format($v['total_collected'] ?? 0, 2) ?></td>
            </tr>
            <?php endforeach ?>
          <?php else: ?>
            <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--ink-3)">No vendor data.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</main>

<!-- Beep sound -->
<audio id="beepSound" preload="auto">
  <source src="../assets/sounds/beep.mp3" type="audio/mpeg">
</audio>

<script>
$(document).ready(function () {

  /* ---- Charts ---- */
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.color = '#6b8878';

  new Chart(document.getElementById('paymentsChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode(array_map(fn($p) => date('M d', strtotime($p['paid_at'])), $payments)) ?>,
      datasets: [{
        label: 'Net Collected (₱)',
        data: <?= json_encode(array_map(fn($p) => ($p['amount_paid'] - $p['discount'] + $p['penalty']), $payments)) ?>,
        borderColor: '#ea580c',
        backgroundColor: 'rgba(234,88,12,.08)',
        borderWidth: 2.5, fill: true, tension: 0.4,
        pointRadius: 4, pointBackgroundColor: '#ea580c',
        pointBorderColor: '#fff', pointBorderWidth: 2,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, border: { display: false } },
        y: { beginAtZero: true, grid: { color: '#edf2ee' }, border: { display: false },
             ticks: { callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) } }
      }
    }
  });

  new Chart(document.getElementById('vendorChart'), {
    type: 'doughnut',
    data: {
      labels: ['Active', 'Inactive', 'Overdue'],
      datasets: [{
        data: [<?= $vendorStatus['active'] ?>, <?= $vendorStatus['inactive'] ?>, <?= $vendorStatus['overdue'] ?>],
        backgroundColor: ['#ea580c', '#6b8878', '#fb7185'],
        borderWidth: 0, hoverOffset: 6
      }]
    },
    options: { cutout: '70%', plugins: { legend: { display: false } } }
  });

});
</script>
</body>
</html>