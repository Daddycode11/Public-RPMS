<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../auth/login.php");
    exit;
}

/* =========================
   FETCH VENDOR INFO
========================= */
$vendorStmt = $pdo->prepare("
    SELECT v.id AS vendor_id, v.stall_number, v.monthly_rent, v.balance, v.next_due_date, v.status,
           s.section_name, u.first_name, u.last_name
    FROM vendors v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN sections s ON v.section_id = s.id
    WHERE v.user_id = ?
");
$vendorStmt->execute([$_SESSION['user_id']]);
$vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['vendor_id'];

/* =========================
   FETCH PAYMENTS
========================= */
$paymentsStmt = $pdo->prepare("
    SELECT * FROM payments
    WHERE vendor_id = ? AND deleted_at IS NULL AND status='paid'
    ORDER BY paid_at ASC
");
$paymentsStmt->execute([$vendor_id]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   COMPUTATIONS
========================= */
$total_paid = 0;
$payment_dates = [];
$payment_amounts = [];

foreach ($payments as $p) {
    $net = ($p['amount_paid'] ?? 0) - ($p['discount'] ?? 0) + ($p['penalty'] ?? 0);
    $total_paid += $net;
    $payment_dates[] = date('M d', strtotime($p['paid_at'] ?? $p['payment_date'] ?? 'now'));
    $payment_amounts[] = $net;
}

$outstanding_balance = max(0, (float)$vendor['balance']);

/* =========================
   DUE DATE & STATUS
========================= */
$today = new DateTime();
$lastPaymentDate = !empty($payments)
    ? new DateTime(end($payments)['paid_at'] ?? end($payments)['payment_date'])
    : new DateTime('first day of this month');

$nextDueDate = !empty($vendor['next_due_date']) ? new DateTime($vendor['next_due_date']) : null;
$graceEnd = $nextDueDate;

$isOverdue = $nextDueDate && $nextDueDate < new DateTime('today') && $outstanding_balance > 0;
$statusLabel = $isOverdue ? 'Overdue' : 'On-Time';
$statusClass = $isOverdue ? 'danger' : 'success';

$paymentCount = count($payments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Dashboard | RPMS</title>
<?php include __DIR__.'/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #ea580c;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.15);
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --shadow:      0 2px 16px rgba(0,0,0,.06);
  --radius:      14px;
  --radius-sm:   10px;
  --error:       #dc2626;
  --warn:        #d97706;
}

body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }

/* Page wrap */
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Page header */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; color: var(--ink); line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }
.header-actions { display: flex; gap: 10px; }
.btn-sm {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 8px;
  font-family: inherit; font-size: .82rem; font-weight: 600;
  cursor: pointer; border: none; text-decoration: none; transition: .2s;
}
.btn-sm svg { width: 14px; height: 14px; }
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); }
.btn-outline { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--brand-light); color: var(--brand-dark); }

/* KPI cards */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.kpi-card {
  background: var(--white); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 22px 24px;
  position: relative; overflow: hidden; transition: .3s ease;
  animation: fadeUp .5s ease both;
}
.kpi-card:nth-child(1){animation-delay:.05s}
.kpi-card:nth-child(2){animation-delay:.1s}
.kpi-card:nth-child(3){animation-delay:.15s}
.kpi-card:nth-child(4){animation-delay:.2s}
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px var(--brand-glow); border-color: var(--brand); }
.kpi-card::after {
  content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(90deg, var(--brand), #ffb347);
  transform: scaleX(0); transform-origin: left; transition: transform .35s ease;
}
.kpi-card:hover::after { transform: scaleX(1); }
.kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.kpi-icon svg { width: 21px; height: 21px; }
.kpi-icon.green { background: var(--brand-light); color: var(--brand-dark); }
.kpi-icon.blue  { background: #eff6ff; color: #1e40af; }
.kpi-icon.red   { background: #fff1f2; color: #be123c; }
.kpi-icon.amber { background: #fff7ed; color: #b45309; }
.kpi-label { font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 4px; }
.kpi-value { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; color: var(--ink); line-height: 1; }
.kpi-footer { display: flex; align-items: center; gap: 6px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #edf2ee; }
.kpi-sub { font-size: .76rem; color: var(--ink-3); }

/* Status pill */
.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 50px;
  font-size: .74rem; font-weight: 700;
}
.status-pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; }
.status-success { background: var(--brand-light); color: var(--brand-dark); }
.status-success::before { background: var(--brand); }
.status-danger { background: #fff1f2; color: #be123c; }
.status-danger::before { background: #fb7185; }

/* Cards */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-badge { font-size: .72rem; font-weight: 700; letter-spacing: .06em; padding: 3px 10px; border-radius: 50px; }
.card-badge.green { background: var(--brand-light); color: var(--brand-dark); }
.card-body { padding: 18px 22px; }

/* Grid layouts */
.grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }

/* Table */
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
.amount-val { font-family: 'Inter', sans-serif; font-weight: 700; color: var(--ink); }
.disc-val { color: var(--brand-dark); font-size: .82rem; }
.pen-val  { color: var(--error); font-size: .82rem; }

/* Overdue banner */
.overdue-banner {
  background: #fff1f2; border: 1px solid #fecaca; border-radius: var(--radius);
  padding: 18px 24px; display: flex; align-items: center; gap: 14px;
  animation: fadeUp .4s ease both;
}
.overdue-banner-icon { flex-shrink: 0; color: #be123c; }
.overdue-banner-icon svg { width: 28px; height: 28px; }
.overdue-banner-text h3 { font-size: .95rem; font-weight: 700; color: #be123c; margin-bottom: 3px; }
.overdue-banner-text p { font-size: .85rem; color: #9b1c1c; }

/* Chart */
.chart-wrap { position: relative; }

/* Responsive */
@media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }
@media (max-width: 700px) { .kpi-grid { grid-template-columns: 1fr 1fr; } .rpms-main { padding: 16px; } }
@media (max-width: 480px) { .kpi-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include __DIR__.'/vendor_navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h1>Welcome back, <?= htmlspecialchars($vendor['first_name']) ?></h1>
      <p class="sub">Stall <?= htmlspecialchars($vendor['stall_number']) ?> · <?= htmlspecialchars($vendor['section_name']) ?></p>
    </div>
    <div class="header-actions">
      <a href="vendor_payment_history.php" class="btn-sm btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        Payment History
      </a>
    </div>
  </div>

  <!-- OVERDUE BANNER -->
  <?php if ($isOverdue): ?>
  <div class="overdue-banner">
    <div class="overdue-banner-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></div>
    <div class="overdue-banner-text">
      <h3>Payment Overdue</h3>
      <p>Your rent payment is past due. Next due date was <?= ($nextDueDate ? $nextDueDate->format('M d, Y') : 'Not set') ?>. Please settle your balance of ₱<?= number_format($outstanding_balance, 2) ?>.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- KPI CARDS -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg></div>
      <div class="kpi-label">Monthly Rent</div>
      <div class="kpi-value">₱<?= number_format($vendor['monthly_rent'], 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-sub">Your assigned rental rate</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3.75-9.75h5.25a2.25 2.25 0 010 4.5h-3a2.25 2.25 0 000 4.5h5.25"/></svg></div>
      <div class="kpi-label">Total Paid</div>
      <div class="kpi-value">₱<?= number_format($total_paid, 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-sub"><?= $paymentCount ?> payment<?= $paymentCount !== 1 ? 's' : '' ?> made</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M9 3.75h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0115 21.75H9A2.25 2.25 0 016.75 19.5V6A2.25 2.25 0 019 3.75z"/></svg></div>
      <div class="kpi-label">Outstanding</div>
      <div class="kpi-value">₱<?= number_format($outstanding_balance, 2) ?></div>
      <div class="kpi-footer">
        <span class="kpi-sub">Remaining balance</span>
      </div>
    </div>

    <div class="kpi-card">
      <div class="kpi-icon amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg></div>
      <div class="kpi-label">Status</div>
      <div class="kpi-value" style="font-size:1.3rem;">
        <span class="status-pill status-<?= $statusClass ?>"><?= $statusLabel ?></span>
      </div>
      <div class="kpi-footer">
        <span class="kpi-sub">Next due: <?= ($nextDueDate ? $nextDueDate->format('M d, Y') : 'Not set') ?></span>
      </div>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="grid-2">
    <!-- Payment Chart -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Payment Overview</span>
        <span class="card-badge green"><?= $paymentCount ?> payments</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap"><canvas id="paymentChart" height="120"></canvas></div>
      </div>
    </div>

    <!-- Balance Donut -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Balance Breakdown</span>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;align-items:center;">
        <div class="chart-wrap" style="width:100%;max-width:180px;">
          <canvas id="balancePie"></canvas>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:14px;width:100%;">
          <div style="display:flex;justify-content:space-between;font-size:.8rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--ink-2)">
              <span style="width:10px;height:10px;border-radius:50%;background:#ea580c;display:inline-block"></span>Paid
            </span>
            <strong>₱<?= number_format($total_paid, 2) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:.8rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--ink-2)">
              <span style="width:10px;height:10px;border-radius:50%;background:#fb7185;display:inline-block"></span>Outstanding
            </span>
            <strong>₱<?= number_format($outstanding_balance, 2) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RECENT PAYMENTS TABLE -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Payment History</span>
      <a href="vendor_payment_history.php" class="btn-sm btn-outline" style="padding:5px 12px;font-size:.78rem;">View all →</a>
    </div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Amount Paid</th>
            <th>Discount</th>
            <th>Penalty</th>
            <th>Net Total</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($payments)):
            $recent = array_slice(array_reverse($payments), 0, 5);
            foreach ($recent as $p):
              $net = ($p['amount_paid'] ?? 0) - ($p['discount'] ?? 0) + ($p['penalty'] ?? 0);
              $date = $p['paid_at'] ?? $p['payment_date'] ?? '';
          ?>
          <tr>
            <td><?= date('M d, Y', strtotime($date)) ?></td>
            <td class="amount-val">₱<?= number_format($p['amount_paid'], 2) ?></td>
            <td class="disc-val">-₱<?= number_format($p['discount'] ?? 0, 2) ?></td>
            <td class="pen-val">+₱<?= number_format($p['penalty'] ?? 0, 2) ?></td>
            <td class="amount-val">₱<?= number_format($net, 2) ?></td>
            <td>
              <a href="vendor_receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn-sm btn-outline" style="padding:4px 10px;font-size:.76rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.72 13.829c-.24.03-.48.062-.72.096V9.75A2.25 2.25 0 019 7.5h6a2.25 2.25 0 012.25 2.25v4.075M6.72 13.829a48.822 48.822 0 011.472 4.377c.16.554.596.983 1.155 1.108.408.093.822.174 1.24.243m0 0a48.716 48.716 0 007.986 0m0 0c.418-.069.831-.15 1.24-.243a1.128 1.128 0 001.154-1.107 48.807 48.807 0 001.472-4.378M15.75 13.829V9.75A2.25 2.25 0 0013.5 7.5H9M15.75 13.829a48.729 48.729 0 00-7.5 0"/></svg>
                Print
              </a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--ink-3);">No payments yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</main>

<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6b8878';

/* Payment Bar Chart */
new Chart(document.getElementById('paymentChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($payment_dates) ?>,
    datasets: [{
      label: 'Net Payment (₱)',
      data: <?= json_encode($payment_amounts) ?>,
      backgroundColor: 'rgba(234,88,12,.75)',
      borderRadius: 6,
      borderSkipped: false,
      hoverBackgroundColor: '#ea580c',
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

/* Balance Donut */
new Chart(document.getElementById('balancePie'), {
  type: 'doughnut',
  data: {
    labels: ['Paid', 'Outstanding'],
    datasets: [{
      data: [<?= $total_paid ?>, <?= $outstanding_balance ?>],
      backgroundColor: ['#ea580c', '#fb7185'],
      borderWidth: 0,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '70%',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ' ₱' + ctx.raw.toLocaleString('en-PH', {minimumFractionDigits:2}) } }
    }
  }
});
</script>
</body>
</html>