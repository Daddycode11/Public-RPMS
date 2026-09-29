<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
if (empty($_SESSION['otp_verified'])) {
    header("Location: ../auth/otp_verify.php");
    exit;
}

require_once __DIR__.'/../includes/archive.php';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='remove_activity') {
    $pdo->prepare('UPDATE activity_logs SET deleted_at=NOW() WHERE id=?')->execute([(int)($_POST['id']??0)]);
}

/* =========================
   DASHBOARD DATA
========================= */
try {
    $total_vendors    = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE deleted_at IS NULL")->fetchColumn();
    $total_sections   = (int)$pdo->query("SELECT COUNT(*) FROM sections WHERE deleted_at IS NULL")->fetchColumn();
    $total_collectors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='collector' AND deleted_at IS NULL")->fetchColumn();
    $total_collection = (float)$pdo
        ->query("SELECT COALESCE(SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)),0) FROM payments WHERE deleted_at IS NULL AND status='paid'")
        ->fetchColumn();

    $monthlyData = $pdo->query("
        SELECT DATE_FORMAT(paid_at,'%b') AS month,
               SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
        FROM payments WHERE deleted_at IS NULL AND status='paid' AND YEAR(paid_at)=YEAR(CURDATE())
        GROUP BY MONTH(paid_at)
        ORDER BY MONTH(paid_at)
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentPayments = $pdo->query("
        SELECT p.id, p.payment_date,
               CONCAT(u.first_name,' ',u.last_name) AS vendor,
               (p.amount_paid-COALESCE(p.discount,0)+COALESCE(p.penalty,0)) AS amount_paid,
               COALESCE(p.status,'paid') AS status
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.deleted_at IS NULL
        ORDER BY p.paid_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $topVendors = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.status='paid' AND p.deleted_at IS NULL
        GROUP BY v.id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $logs = $pdo->query("
        SELECT id, action, created_at
        FROM activity_logs WHERE deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    $overdueCount = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE deleted_at IS NULL AND balance>0 AND next_due_date<CURDATE()")->fetchColumn();
    $bestDay = $pdo->query("
        SELECT DATE_FORMAT(paid_at,'%W')
        FROM payments WHERE deleted_at IS NULL AND status='paid'
        GROUP BY DATE_FORMAT(paid_at,'%W')
        ORDER BY SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) DESC
        LIMIT 1
    ")->fetchColumn() ?: 'N/A';

    $topCollector = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name)
        FROM payments p
        JOIN users u ON u.id = p.collector_id
        WHERE p.deleted_at IS NULL AND p.status='paid'
        GROUP BY p.collector_id
        ORDER BY SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) DESC
        LIMIT 1
    ")->fetchColumn() ?: 'N/A';

    $avgDaily = (float)$pdo->query("
        SELECT AVG(total) FROM (
            SELECT SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
            FROM payments WHERE deleted_at IS NULL AND status='paid' GROUP BY DATE(paid_at)
        ) t
    ")->fetchColumn();

    $vendorStatusCounts = $pdo->query("
        SELECT
            SUM(CASE WHEN balance<=0 THEN 1 ELSE 0 END) AS paid,
            SUM(CASE WHEN balance>0 AND (next_due_date IS NULL OR next_due_date>=CURDATE()) THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN balance>0 AND next_due_date<CURDATE() THEN 1 ELSE 0 END) AS overdue
        FROM vendors WHERE deleted_at IS NULL
    ")->fetch(PDO::FETCH_ASSOC);
    $vendorStatusCounts = array_values($vendorStatusCounts);

    $collectorPerformance = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total
        FROM payments p
        JOIN users u ON u.id = p.collector_id
        WHERE p.status='paid' AND p.deleted_at IS NULL
        GROUP BY p.collector_id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(503); exit('Dashboard data is temporarily unavailable.');
}

$annualTarget       = 2000000;
$collectionProgress = $annualTarget > 0 ? min(100, round(($total_collection / $annualTarget) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* ============================================================
   ROOT & RESET
============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #F57C00;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.15);
  --ink:         #0d1f14;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --shadow:      0 2px 16px rgba(0,0,0,.06);
  --radius:      14px;
  --radius-sm:   10px;
}

body {
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--ink);
}

/* ============================================================
   MAIN CONTENT
============================================================ */
.page-wrap {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ============================================================
   PAGE HEADER
============================================================ */
.page-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
}
.page-header h1 {
  font-family: 'Inter', sans-serif;
  font-size: 1.7rem; font-weight: 700;
  color: var(--ink); line-height: 1;
}
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }
.header-actions { display: flex; gap: 10px; }
.btn-sm {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 8px;
  font-family: inherit; font-size: .82rem; font-weight: 600;
  cursor: pointer; border: none; text-decoration: none;
  transition: .2s;
}
.btn-sm svg { width: 14px; height: 14px; }
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); }
.btn-outline { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--brand-light); color: var(--brand-dark); }

/* ============================================================
   KPI CARDS
============================================================ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.kpi-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 22px 24px;
  position: relative;
  overflow: hidden;
  transition: .3s ease;
  animation: fadeUp .5s ease both;
}
.kpi-card:nth-child(1){animation-delay:.05s}
.kpi-card:nth-child(2){animation-delay:.1s}
.kpi-card:nth-child(3){animation-delay:.15s}
.kpi-card:nth-child(4){animation-delay:.2s}
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 28px var(--brand-glow);
  border-color: var(--brand);
}
.kpi-card::after {
  content: "";
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, var(--brand), #ffb347);
  transform: scaleX(0); transform-origin: left;
  transition: transform .35s ease;
}
.kpi-card:hover::after { transform: scaleX(1); }

.kpi-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
.kpi-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: var(--brand-light);
  display: flex; align-items: center; justify-content: center;
  color: var(--brand-dark);
}
.kpi-icon svg { width: 21px; height: 21px; }
.kpi-menu-btn {
  background: none; border: none; cursor: pointer;
  color: var(--ink-3); padding: 4px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  transition: .2s; position: relative;
}
.kpi-menu-btn svg { width: 16px; height: 16px; }
.kpi-menu-btn:hover { background: var(--cream); color: var(--ink); }

/* KPI dropdown */
.kpi-dropdown {
  position: absolute; top: calc(100% + 4px); right: 0;
  background: var(--white); border: 1px solid var(--border);
  border-radius: 10px; box-shadow: var(--shadow);
  min-width: 150px; z-index: 50; display: none; overflow: hidden;
}
.kpi-menu-btn.open + .kpi-dropdown { display: block; }
.kpi-dropdown a {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 16px;
  font-size: .83rem; color: var(--ink-2);
  text-decoration: none; transition: background .15s;
}
.kpi-dropdown a svg { width: 13px; height: 13px; flex-shrink: 0; }
.kpi-dropdown a:hover { background: var(--cream); }

.kpi-label { font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 4px; }
.kpi-value { font-family: 'Inter', sans-serif; font-size: 1.9rem; font-weight: 700; color: var(--ink); line-height: 1; }
.kpi-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 14px; border-top: 1px solid #edf2ee; }
.kpi-trend { font-size: .78rem; font-weight: 600; display: flex; align-items: center; gap: 4px; }
.kpi-trend svg { width: 12px; height: 12px; }
.kpi-trend.up { color: var(--brand); }
.kpi-trend.warn { color: #d97706; }
.kpi-trend.down { color: #dc2626; }
.kpi-sub { font-size: .76rem; color: var(--ink-3); }

/* ============================================================
   CARDS / PANELS
============================================================ */
.card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}
.card-header {
  padding: 18px 22px 14px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid #edf2ee;
}
.card-title {
  font-size: .95rem; font-weight: 700; color: var(--ink);
}
.card-badge {
  font-size: .72rem; font-weight: 700; letter-spacing: .06em;
  padding: 3px 10px; border-radius: 50px;
}
.card-badge.green { background: var(--brand-light); color: var(--brand-dark); }
.card-badge.orange { background: #fff7ed; color: #b45309; }
.card-body { padding: 18px 22px; }

/* ============================================================
   2-COL GRID
============================================================ */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.grid-3-1 { display: grid; grid-template-columns: 3fr 1fr; gap: 20px; }
.grid-2-1 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }

/* ============================================================
   TABLE
============================================================ */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
  font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
  color: var(--ink-3); padding: 10px 16px; text-align: left;
  background: var(--cream); border-bottom: 1px solid #edf2ee;
}
.data-table td {
  padding: 11px 16px; font-size: .875rem; color: var(--ink-2);
  border-bottom: 1px solid #f0f4f1;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fdf6f1; }

/* Status pill */
.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 50px;
  font-size: .74rem; font-weight: 700;
}
.status-pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; }
.status-paid    { background: var(--brand-light); color: var(--brand-dark); }
.status-paid::before { background: var(--brand); }
.status-pending { background: #fff7ed; color: #b45309; }
.status-pending::before { background: #f59e0b; }
.status-overdue { background: #fff1f2; color: #be123c; }
.status-overdue::before { background: #fb7185; }

/* ============================================================
   TOP VENDORS LIST
============================================================ */
.vendor-rank {
  display: flex; flex-direction: column; gap: 4px;
  padding: 6px 0;
  border-bottom: 1px solid #f0f4f1;
}
.vendor-rank:last-child { border-bottom: none; }
.vendor-rank-top { display: flex; justify-content: space-between; align-items: center; }
.vendor-rank-name { font-size: .875rem; font-weight: 600; color: var(--ink-2); }
.vendor-rank-amount { font-size: .875rem; font-weight: 700; color: var(--ink); }
.vendor-rank-bar { height: 4px; background: #edf2ee; border-radius: 2px; margin-top: 5px; overflow: hidden; }
.vendor-rank-fill { height: 100%; background: var(--brand); border-radius: 2px; }

/* ============================================================
   ACTIVITY LOG
============================================================ */
.log-list { display: flex; flex-direction: column; }
.log-item {
  display: flex; gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #f0f4f1;
  position: relative;
}
.log-item:last-child { border-bottom: none; }
.log-dot {
  width: 9px; height: 9px; flex-shrink: 0;
  border-radius: 50%; background: var(--brand);
  margin-top: 5px;
  box-shadow: 0 0 0 3px var(--brand-light);
}
.log-text { font-size: .84rem; color: var(--ink-2); line-height: 1.5; }
.log-time { font-size: .75rem; color: var(--ink-3); margin-top: 2px; }

/* ============================================================
   INSIGHTS CARDS
============================================================ */
.insight-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.insight-item {
  background: var(--cream);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
}
.insight-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  background: var(--white);
  display: flex; align-items: center; justify-content: center;
  color: var(--brand-dark);
  margin-bottom: 8px;
}
.insight-icon svg { width: 15px; height: 15px; }
.insight-item.danger-item .insight-icon { color: #dc2626; }
.insight-label { font-size: .72rem; color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 3px; }
.insight-val { font-size: .95rem; font-weight: 700; color: var(--ink); }
.insight-val.danger { color: #dc2626; }

/* ============================================================
   PROGRESS BAR
============================================================ */
.progress-wrap { margin-top: 8px; }
.progress-labels { display: flex; justify-content: space-between; font-size: .8rem; color: var(--ink-3); margin-bottom: 8px; }
.progress-bar-outer {
  background: var(--brand-light); border-radius: 6px; height: 12px; overflow: hidden;
}
.progress-bar-inner {
  height: 100%;
  background: linear-gradient(90deg, var(--brand), #ffb347);
  border-radius: 6px;
  transition: width 1.5s cubic-bezier(.25,.46,.45,.94);
  display: flex; align-items: center; justify-content: flex-end; padding-right: 6px;
  font-size: .65rem; font-weight: 700; color: #fff;
}
.progress-meta { display: flex; justify-content: space-between; font-size: .8rem; margin-top: 8px; }
.progress-meta .collected { font-weight: 700; color: var(--ink); }
.progress-meta .target { color: var(--ink-3); }

/* ============================================================
   CHART CONTAINER
============================================================ */
.chart-wrap { position: relative; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1100px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .grid-3-1, .grid-2-1 { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; }
  .grid-2 { grid-template-columns: 1fr; }
  .rpms-main { padding: 16px; }
  .insight-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h1>Dashboard</h1>
      <p class="sub">Welcome back, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Admin') ?>. Here's what's happening today.</p>
    </div>
    <div class="header-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:6px;">
        <label style="font-size:.78rem;color:var(--ink-3);font-weight:600">From:</label>
        <input type="date" id="dashDateFrom" style="padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:.82rem;font-family:inherit">
        <label style="font-size:.78rem;color:var(--ink-3);font-weight:600">To:</label>
        <input type="date" id="dashDateTo" style="padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:.82rem;font-family:inherit">
        <button onclick="applyDateFilter()" class="btn-sm btn-outline" style="padding:6px 12px;cursor:pointer;border:1px solid var(--border);border-radius:8px;background:var(--white);font-family:inherit;font-size:.78rem;font-weight:600">Filter</button>
        <button onclick="clearDateFilter()" class="btn-sm btn-outline" style="padding:6px 12px;cursor:pointer;border:1px solid var(--border);border-radius:8px;background:var(--white);font-family:inherit;font-size:.78rem;color:var(--ink-3)">Clear</button>
      </div>
      <a href="reports.php" class="btn-sm btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
        View Reports
      </a>
      <a href="collect_payment.php" class="btn-sm btn-primary">+ New Payment</a>
    </div>
  </div>

  <!-- KPI CARDS -->
  <div class="kpi-grid" id="kpiGrid">

    <!-- Vendors -->
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 21h19.5m-18-18v18m16.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125V21"/></svg>
        </div>
        <div style="position:relative">
          <button class="kpi-menu-btn" onclick="toggleKpiMenu(this)" title="Options">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
          </button>
          <div class="kpi-dropdown">
            <a href="vendors.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/></svg>View Vendors</a>
            <a href="#" onclick="refreshKPI('vendors',this);return false;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>Refresh</a>
          </div>
        </div>
      </div>
      <div class="kpi-label">Total Vendors</div>
      <div class="kpi-value" id="kpi_vendors"><?= $total_vendors ?></div>
      <div class="kpi-footer">
        <span class="kpi-trend up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12l4-4 4 4M12 8v8"/></svg> Active</span>
        <span class="kpi-sub">Registered stall holders</span>
      </div>
    </div>

    <!-- Sections -->
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 3.75h6v6h-6v-6zM14.25 3.75h6v6h-6v-6zM3.75 14.25h6v6h-6v-6zM14.25 14.25h6v6h-6v-6z"/></svg>
        </div>
        <div style="position:relative">
          <button class="kpi-menu-btn" onclick="toggleKpiMenu(this)" title="Options">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
          </button>
          <div class="kpi-dropdown">
            <a href="sections.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 3.75h6v6h-6v-6zM14.25 3.75h6v6h-6v-6zM3.75 14.25h6v6h-6v-6zM14.25 14.25h6v6h-6v-6z"/></svg>View Sections</a>
            <a href="#" onclick="refreshKPI('sections',this);return false;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>Refresh</a>
          </div>
        </div>
      </div>
      <div class="kpi-label">Total Sections</div>
      <div class="kpi-value" id="kpi_sections"><?= $total_sections ?></div>
      <div class="kpi-footer">
        <span class="kpi-trend up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12l4-4 4 4M12 8v8"/></svg> Mapped</span>
        <span class="kpi-sub">Market sections</span>
      </div>
    </div>

    <!-- Collectors -->
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        </div>
        <div style="position:relative">
          <button class="kpi-menu-btn" onclick="toggleKpiMenu(this)" title="Options">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
          </button>
          <div class="kpi-dropdown">
            <a href="collector_approvals.php?role=collector"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>View Collectors</a>
            <a href="#" onclick="refreshKPI('collectors',this);return false;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>Refresh</a>
          </div>
        </div>
      </div>
      <div class="kpi-label">Total Collectors</div>
      <div class="kpi-value" id="kpi_collectors"><?= $total_collectors ?></div>
      <div class="kpi-footer">
        <span class="kpi-trend up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12l4-4 4 4M12 8v8"/></svg> Active</span>
        <span class="kpi-sub">Fee collectors</span>
      </div>
    </div>

    <!-- Total Collection -->
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3.75-9.75h5.25a2.25 2.25 0 010 4.5h-3a2.25 2.25 0 000 4.5h5.25"/></svg>
        </div>
        <div style="position:relative">
          <button class="kpi-menu-btn" onclick="toggleKpiMenu(this)" title="Options">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
          </button>
          <div class="kpi-dropdown">
            <a href="payments.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>View Payments</a>
            <a href="#" onclick="refreshKPI('collection',this);return false;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>Refresh</a>
          </div>
        </div>
      </div>
      <div class="kpi-label">Total Collection</div>
      <div class="kpi-value" id="kpi_collection">₱<?= number_format($total_collection, 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-trend up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12l4-4 4 4M12 8v8"/></svg> <?= $collectionProgress ?>% of target</span>
        <span class="kpi-sub">All-time collected</span>
      </div>
    </div>

  </div>

  <!-- CHARTS ROW -->
  <div class="grid-3-1">

    <!-- Monthly Chart -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Monthly Collection</span>
        <span class="card-badge green">This Year</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap"><canvas id="monthlyChart" height="120"></canvas></div>
      </div>
    </div>

    <!-- Vendor Status Donut -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Payment Status</span>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;align-items:center;">
        <div class="chart-wrap" style="width:100%;max-width:180px;">
          <canvas id="vendorStatusChart"></canvas>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:14px;width:100%;">
          <div style="display:flex;justify-content:space-between;font-size:.8rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--ink-2)"><span style="width:10px;height:10px;border-radius:50%;background:#F57C00;display:inline-block"></span>Paid</span>
            <strong><?= $vendorStatusCounts[0] ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:.8rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--ink-2)"><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block"></span>Pending</span>
            <strong><?= $vendorStatusCounts[1] ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:.8rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--ink-2)"><span style="width:10px;height:10px;border-radius:50%;background:#fb7185;display:inline-block"></span>Overdue</span>
            <strong><?= $vendorStatusCounts[2] ?></strong>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- RECENT PAYMENTS + TOP VENDORS -->
  <div class="grid-2">

    <!-- Recent Payments -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Recent Payments</span>
        <a href="payments.php" class="btn-sm btn-outline" style="padding:5px 12px;font-size:.78rem;">View all →</a>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Vendor</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentPayments as $p): ?>
          <tr>
            <td><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
            <td><a href="print_receipt.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['vendor']) ?></a></td>
            <td><strong>₱<?= number_format($p['amount_paid'], 2) ?></strong></td>
            <td>
              <span class="status-pill status-<?= strtolower($p['status']) ?>">
                <?= ucfirst($p['status']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <!-- Top Vendors -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Top Vendors</span>
        <span class="card-badge green">By Payment</span>
      </div>
      <div class="card-body">
        <?php
        $maxVendorTotal = !empty($topVendors) ? max(array_column($topVendors, 'total')) : 1;
        foreach ($topVendors as $v):
          $pct = $maxVendorTotal > 0 ? round(($v['total'] / $maxVendorTotal) * 100) : 0;
        ?>
        <div class="vendor-rank">
          <div class="vendor-rank-top">
            <span class="vendor-rank-name"><?= htmlspecialchars($v['name']) ?></span>
            <span class="vendor-rank-amount">₱<?= number_format($v['total'], 2) ?></span>
          </div>
          <div class="vendor-rank-bar"><div class="vendor-rank-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>

  </div>

  <!-- COLLECTOR CHART + ACTIVITY LOG -->
  <div class="grid-2-1">

    <!-- Collector Performance Chart -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Collector Performance</span>
        <a href="collector_performance.php" class="btn-sm btn-outline" style="padding:5px 12px;font-size:.78rem;">Details →</a>
      </div>
      <div class="card-body">
        <div class="chart-wrap"><canvas id="collectorChart" height="110"></canvas></div>
      </div>
    </div>

    <!-- Activity Log -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Activity Log</span>
        
      </div>
      <div class="card-body">
        <div class="log-list">
          <?php foreach ($logs as $l): ?>
          <div class="log-item">
            <div class="log-dot"></div>
            <div>
              <div class="log-text"><?= htmlspecialchars($l['action']) ?></div>
              <div class="log-time"><?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></div><?= removeForm('activity',(int)$l['id']) ?>
            </div>
          </div>
          <?php endforeach ?>
          <?php if (empty($logs)): ?>
            <p style="font-size:.85rem;color:var(--ink-3);text-align:center;padding:20px 0">No activity yet.</p>
          <?php endif ?>
        </div>
      </div>
    </div>

  </div>

  <!-- PROGRESS + INSIGHTS -->
  <div class="grid-2">

    <!-- Annual Progress -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Annual Collection Progress</span>
        <span class="card-badge <?= $collectionProgress >= 80 ? 'green' : 'orange' ?>">
          <?= $collectionProgress ?>% Complete
        </span>
      </div>
      <div class="card-body">
        <div class="progress-wrap">
          <div class="progress-labels">
            <span>₱0</span>
            <span>Target: ₱<?= number_format($annualTarget) ?></span>
          </div>
          <div class="progress-bar-outer">
            <div class="progress-bar-inner" id="annualBar" style="width:0%">
              <?= $collectionProgress ?>%
            </div>
          </div>
          <div class="progress-meta">
            <span class="collected">₱<?= number_format($total_collection, 2) ?> collected</span>
            <span class="target">₱<?= number_format($annualTarget - $total_collection, 2) ?> remaining</span>
          </div>
        </div>
      </div>
    </div>

    <!-- System Insights -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">System Insights</span>
      </div>
      <div class="card-body">
        <div class="insight-grid">
          <div class="insight-item danger-item">
            <div class="insight-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></div>
            <div class="insight-label">Overdue</div>
            <div class="insight-val danger"><?= $overdueCount ?> vendors</div>
          </div>
          <div class="insight-item">
            <div class="insight-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg></div>
            <div class="insight-label">Best Day</div>
            <div class="insight-val"><?= htmlspecialchars($bestDay) ?></div>
          </div>
          <div class="insight-item">
            <div class="insight-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.29 0 4.544.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/></svg></div>
            <div class="insight-label">Top Collector</div>
            <div class="insight-val" style="font-size:.82rem"><?= htmlspecialchars($topCollector) ?></div>
          </div>
          <div class="insight-item">
            <div class="insight-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg></div>
            <div class="insight-label">Avg Daily</div>
            <div class="insight-val">₱<?= number_format($avgDaily, 0) ?></div>
          </div>
        </div>
      </div>
    </div>

  </div>

</div><!-- /page-wrap -->
</main>

<script>
/* ---- Date Range Filter ---- */
let monthlyChartInstance, collectorChartInstance;
function escapeCell(value) { const node=document.createElement('span'); node.textContent=String(value ?? ''); return node.innerHTML; }

function applyDateFilter() {
  const from = document.getElementById('dashDateFrom').value;
  const to = document.getElementById('dashDateTo').value;
  if (!from && !to) return;

  fetch('dashboard_filter_ajax.php?date_from=' + from + '&date_to=' + to)
    .then(r => r.json())
    .then(d => {
      if (d.error) return;
      // Update collection KPI
      document.getElementById('kpi_collection').textContent = '₱' + parseFloat(d.total_collection).toLocaleString('en-PH', {minimumFractionDigits:2});

      // Update monthly chart
      if (monthlyChartInstance) {
        monthlyChartInstance.data.labels = d.monthly_labels;
        monthlyChartInstance.data.datasets[0].data = d.monthly_data;
        monthlyChartInstance.update();
      }

      // Update collector chart
      if (collectorChartInstance) {
        collectorChartInstance.data.labels = d.collector_performance.map(c => c.name);
        collectorChartInstance.data.datasets[0].data = d.collector_performance.map(c => c.total);
        collectorChartInstance.update();
      }

      // Update recent payments table
      const tbody = document.querySelector('.data-table tbody');
      if (tbody && d.recent_payments) {
        tbody.innerHTML = d.recent_payments.map(p => `
          <tr>
            <td>${new Date(p.payment_date).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'})}</td>
            <td><a href="print_receipt.php?id=${Number(p.id)}">${escapeCell(p.vendor)}</a></td>
            <td><strong>₱${parseFloat(p.amount_paid).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong></td>
            <td><span class="status-pill status-${['paid','pending','cancelled'].includes(p.status) ? p.status : 'pending'}">${escapeCell(p.status)}</span></td>
          </tr>
        `).join('');
      }
    })
    .catch(e => console.error('Filter error:', e));
}

function clearDateFilter() {
  document.getElementById('dashDateFrom').value = '';
  document.getElementById('dashDateTo').value = '';
  window.location.reload();
}

/* ---- KPI Dropdown toggle ---- */
function toggleKpiMenu(btn) {
  document.querySelectorAll('.kpi-menu-btn.open').forEach(b => { if (b !== btn) b.classList.remove('open'); });
  btn.classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.kpi-menu-btn')) {
    document.querySelectorAll('.kpi-menu-btn.open').forEach(b => b.classList.remove('open'));
  }
});

/* ---- KPI AJAX refresh ---- */
function refreshKPI(metric, el) {
  const btn = el.closest('.kpi-dropdown').previousElementSibling;
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>';
  fetch('dashboard_kpi_ajax.php?metric=' + metric)
    .then(r => r.json())
    .then(d => {
      if (metric === 'vendors')    document.getElementById('kpi_vendors').textContent    = d.value;
      if (metric === 'sections')   document.getElementById('kpi_sections').textContent   = d.value;
      if (metric === 'collectors') document.getElementById('kpi_collectors').textContent = d.value;
      if (metric === 'collection') document.getElementById('kpi_collection').textContent = '₱' + parseFloat(d.value).toLocaleString('en-PH', {minimumFractionDigits:2});
      btn.innerHTML = originalHTML;
    })
    .catch(() => { btn.innerHTML = originalHTML; });
}

/* ---- Progress bar ---- */
setTimeout(() => {
  document.getElementById('annualBar').style.width = '<?= $collectionProgress ?>%';
}, 400);

/* ---- Chart.js Global Defaults ---- */
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6b8878';

/* ---- Monthly Collection Chart ---- */
monthlyChartInstance = new Chart(document.getElementById('monthlyChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($monthlyData, 'month')) ?>,
    datasets: [{
      label: 'Collection (₱)',
      data:  <?= json_encode(array_column($monthlyData, 'total')) ?>,
      backgroundColor: 'rgba(234,88,12,.08)',
      borderColor: '#F57C00',
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointBackgroundColor: '#F57C00',
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, border: { display: false } },
      y: {
        beginAtZero: true,
        grid: { color: '#edf2ee', lineWidth: 1 },
        border: { display: false },
        ticks: { callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }
      }
    }
  }
});

/* ---- Vendor Status Donut ---- */
new Chart(document.getElementById('vendorStatusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Paid', 'Pending', 'Overdue'],
    datasets: [{
      data: <?= json_encode($vendorStatusCounts) ?>,
      backgroundColor: ['#F57C00', '#f59e0b', '#fb7185'],
      borderWidth: 0,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '70%',
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}` }
      }
    }
  }
});

/* ---- Collector Performance Bar ---- */
collectorChartInstance = new Chart(document.getElementById('collectorChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($collectorPerformance, 'name')) ?>,
    datasets: [{
      label: 'Total Collection (₱)',
      data:  <?= json_encode(array_column($collectorPerformance, 'total')) ?>,
      backgroundColor: 'rgba(234,88,12,.75)',
      borderRadius: 6,
      borderSkipped: false,
      hoverBackgroundColor: '#F57C00',
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, border: { display: false } },
      y: {
        beginAtZero: true,
        grid: { color: '#edf2ee' },
        border: { display: false },
        ticks: { callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }
      }
    }
  }
});
</script>
</body>
</html>