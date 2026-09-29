<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Collector performance summary
$data = $pdo->query("
    SELECT
        u.id AS collector_id,
        CONCAT(u.first_name,' ',u.last_name) AS name,
        u.email,
        COUNT(p.id) AS total_transactions,
        COALESCE(SUM(p.amount_paid), 0) AS total_collected,
        COALESCE(SUM(p.discount), 0) AS total_discount,
        COALESCE(SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)), 0) AS net_collected,
        MAX(p.paid_at) AS last_collection
    FROM users u
    LEFT JOIN payments p ON u.id = p.collector_id AND p.deleted_at IS NULL AND p.status='paid'
    WHERE u.role = 'collector' AND u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY total_collected DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Overall stats
$totalCollected = array_sum(array_column($data, 'net_collected'));
$totalTransactions = array_sum(array_column($data, 'total_transactions'));
$activeCollectors = count(array_filter($data, fn($d) => $d['total_transactions'] > 0));
$topCollector = !empty($data) ? $data[0]['name'] : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Performance | Admin - RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #F57C00;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.12);
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --radius:      14px;
  --radius-sm:   10px;
  --error:       #dc2626;
}
body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Header */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* Summary cards */
.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.sum-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 22px; display: flex; align-items: center; gap: 14px; transition: .25s; }
.sum-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--brand-glow); }
.sum-icon { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.sum-icon svg { width: 21px; height: 21px; }
.sum-icon.a { background: var(--brand-light); color: var(--brand-dark); }
.sum-icon.b { background: #eff6ff; color: #1e40af; }
.sum-icon.c { background: #fff7ed; color: #b45309; }
.sum-icon.d { background: #fff7f2; color: var(--brand-dark); }
.sum-label { font-size: .73rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 3px; }
.sum-val { font-family: 'Inter', sans-serif; font-size: 1.4rem; font-weight: 700; color: var(--ink); }

/* Card */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; flex-wrap: wrap; gap: 10px; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-body { padding: 22px; }

/* Chart */
.chart-wrap { padding: 22px; }
.chart-wrap canvas { max-height: 300px; }

/* Grid */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Table */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); padding: 11px 18px; text-align: left; background: var(--cream); border-bottom: 1px solid #edf2ee; white-space: nowrap; }
.data-table td { padding: 12px 18px; font-size: .875rem; color: var(--ink-2); border-bottom: 1px solid #f0f4f1; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #fdf6f1; }
.amount-val { font-family: 'Inter', sans-serif; font-weight: 700; color: var(--ink); }

/* Rank badge */
.rank-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 8px;
  font-size: .78rem; font-weight: 700;
}
.rank-1 { background: #fef3c7; color: #92400e; }
.rank-2 { background: #e5e7eb; color: #374151; }
.rank-3 { background: #fed7aa; color: #9a3412; }
.rank-other { background: var(--cream); color: var(--ink-3); }

/* Collector name cell */
.collector-info { display: flex; align-items: center; gap: 10px; }
.collector-avatar {
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--brand), var(--brand-dark));
  color: #fff; font-weight: 700; font-size: .8rem;
  display: flex; align-items: center; justify-content: center;
}
.collector-name { font-weight: 600; color: var(--ink); }
.collector-email { font-size: .75rem; color: var(--ink-3); }

/* Progress bar */
.perf-bar-wrap { display: flex; align-items: center; gap: 8px; }
.perf-bar { flex: 1; height: 8px; background: var(--cream); border-radius: 50px; overflow: hidden; }
.perf-bar-fill { height: 100%; background: linear-gradient(90deg, var(--brand), var(--brand-dark)); border-radius: 50px; transition: width .5s ease; }
.perf-pct { font-size: .75rem; font-weight: 700; color: var(--ink-3); min-width: 36px; text-align: right; }

/* Empty state */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state .empty-icon { width: 52px; height: 52px; margin: 0 auto 12px; opacity: .4; display: flex; align-items: center; justify-content: center; }
.empty-state .empty-icon svg { width: 30px; height: 30px; }
.empty-state p { font-size: .92rem; color: var(--ink-3); }

@media (max-width: 1100px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .summary-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <div>
      <h1>Collector Performance</h1>
      <p class="sub">Track and compare fee collector collection metrics.</p>
    </div>
  </div>

  <!-- SUMMARY -->
  <div class="summary-grid">
    <div class="sum-card">
      <div class="sum-icon a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3.75-9.75h5.25a2.25 2.25 0 010 4.5h-3a2.25 2.25 0 000 4.5h5.25"/></svg></div>
      <div><div class="sum-label">Total Collected</div><div class="sum-val">&#8369;<?= number_format($totalCollected, 2) ?></div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg></div>
      <div><div class="sum-label">Transactions</div><div class="sum-val"><?= number_format($totalTransactions) ?></div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon c"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.106A12.318 12.318 0 008.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0112.749 0zM15 19.128v-.106c0-1.131.213-2.213.6-3.207M18.75 15a6.001 6.001 0 003-1.5m0 0a3 3 0 10-3-5.196M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
      <div><div class="sum-label">Active Collectors</div><div class="sum-val"><?= $activeCollectors ?></div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon d"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H11.25m2.25 0V9m0 0a3 3 0 00-3-3v0a3 3 0 00-3 3m6 0h-6"/></svg></div>
      <div><div class="sum-label">Top Collector</div><div class="sum-val" style="font-size:1.1rem;"><?= htmlspecialchars($topCollector) ?></div></div>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Collection by Collector</span>
      </div>
      <div class="chart-wrap">
        <canvas id="barChart"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Collection Distribution</span>
      </div>
      <div class="chart-wrap">
        <canvas id="donutChart"></canvas>
      </div>
    </div>
  </div>

  <!-- TABLE -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">All Collectors</span>
      <span style="font-size:.82rem;color:var(--ink-3);"><?= count($data) ?> collectors</span>
    </div>

    <div style="overflow-x:auto;">
      <input placeholder="Search collector name or email" data-search-table="#collectorPerformanceTable" aria-label="Search collectors"><table class="data-table" id="collectorPerformanceTable">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Collector</th>
            <th>Transactions</th>
            <th>Total Collected</th>
            <th>Net Collected</th>
            <th>Performance</th>
            <th>Last Activity</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($data): ?>
            <?php foreach ($data as $i => $d):
              $initials = strtoupper(substr($d['name'], 0, 1) . substr(strstr($d['name'], ' '), 1, 1));
              $pct = $totalCollected > 0 ? round(($d['net_collected'] / $totalCollected) * 100, 1) : 0;
              $rankClass = ($i < 3) ? 'rank-' . ($i + 1) : 'rank-other';
            ?>
            <tr>
              <td><span class="rank-badge <?= $rankClass ?>"><?= $i + 1 ?></span></td>
              <td>
                <div class="collector-info">
                  <div class="collector-avatar"><?= $initials ?></div>
                  <div>
                    <div class="collector-name"><?= htmlspecialchars($d['name']) ?></div>
                    <div class="collector-email"><?= htmlspecialchars($d['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><a href="payments.php?collector_id=<?= (int)$d['collector_id'] ?>&amp;from=1900-01-01"><?= number_format($d['total_transactions']) ?></a></td>
              <td class="amount-val">&#8369;<?= number_format($d['total_collected'], 2) ?></td>
              <td class="amount-val">&#8369;<?= number_format($d['net_collected'], 2) ?></td>
              <td>
                <div class="perf-bar-wrap">
                  <div class="perf-bar"><div class="perf-bar-fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="perf-pct"><?= $pct ?>%</span>
                </div>
              </td>
              <td><?= $d['last_collection'] ? date('M d, Y', strtotime($d['last_collection'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.106A12.318 12.318 0 008.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0112.749 0zM15 19.128v-.106c0-1.131.213-2.213.6-3.207M18.75 15a6.001 6.001 0 003-1.5m0 0a3 3 0 10-3-5.196M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div><p>No collector data found.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</main>

<script>
const names = <?= json_encode(array_column($data, 'name')) ?>;
const amounts = <?= json_encode(array_map('floatval', array_column($data, 'net_collected'))) ?>;
const colors = ['#F57C00','#b3260c','#f97316','#fb923c','#fdba74','#fed7aa','#ffe4d1','#c2410c','#9a3412','#7c2d12'];

// Bar Chart
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: names,
    datasets: [{
      label: 'Net Collected',
      data: amounts,
      backgroundColor: colors.slice(0, names.length),
      borderRadius: 6,
      maxBarThickness: 50
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#2b0d05',
        titleFont: { family: "'Inter', sans-serif" },
        bodyFont: { family: "'Inter', sans-serif" },
        padding: 10, cornerRadius: 8,
        callbacks: { label: ctx => '\u20B1' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }
      }
    },
    scales: {
      y: { beginAtZero: true, grid: { color: '#edf2ee' }, ticks: { font: { family: "'Inter', sans-serif", size: 11 }, color: '#6b8878', callback: v => '\u20B1' + v.toLocaleString() } },
      x: { grid: { display: false }, ticks: { font: { family: "'Inter', sans-serif", size: 11 }, color: '#6b8878' } }
    }
  }
});

// Donut Chart
new Chart(document.getElementById('donutChart'), {
  type: 'doughnut',
  data: {
    labels: names,
    datasets: [{
      data: amounts,
      backgroundColor: colors.slice(0, names.length),
      borderWidth: 2,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
      legend: { position: 'bottom', labels: { font: { family: "'Inter', sans-serif", size: 12 }, color: '#3a5042', padding: 16, usePointStyle: true, pointStyleWidth: 10 } },
      tooltip: {
        backgroundColor: '#2b0d05',
        titleFont: { family: "'Inter', sans-serif" },
        bodyFont: { family: "'Inter', sans-serif" },
        padding: 10, cornerRadius: 8,
        callbacks: { label: ctx => ctx.label + ': \u20B1' + ctx.parsed.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }
      }
    }
  }
});
</script>
</body>
</html>