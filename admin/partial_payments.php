<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$stmt = $pdo->query("
    SELECT
        pp.id,
        v.stall_number,
        v.vendor_name,
        pp.amount,
        pp.paid_at
    FROM partial_payments pp
    JOIN vendors v ON v.id = pp.vendor_id
    ORDER BY pp.paid_at DESC
");
$partials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPartial = array_sum(array_column($partials, 'amount'));
$totalCount   = count($partials);
$avgAmount    = $totalCount > 0 ? $totalPartial / $totalCount : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Partial Payments | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #ea580c;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.12);
  --orange:      #f59e0b;
  --orange-light:#fff7ed;
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --radius:      14px;
}

body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }

.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Header */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

.btn-sm {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 8px;
  font-family: inherit; font-size: .82rem; font-weight: 600;
  cursor: pointer; border: none; text-decoration: none; transition: .2s;
}
.btn-outline { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--brand-light); color: var(--brand-dark); }

/* Summary */
.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.sum-card {
  background: var(--white); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 22px 24px; display: flex; align-items: center; gap: 14px; transition: .25s;
}
.sum-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--brand-glow); border-color: var(--brand); }
.sum-icon { width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.sum-icon svg { width: 22px; height: 22px; }
.sum-icon.orange { background: var(--orange-light); color: #b45309; }
.sum-icon.green  { background: var(--brand-light); color: var(--brand-dark); }
.sum-icon.blue   { background: #eff6ff; color: #1e40af; }
.sum-label { font-size: .73rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 3px; }
.sum-val { font-family: 'Inter', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1; }

/* Notice banner */
.notice-banner {
  background: var(--orange-light);
  border: 1px solid #fde68a;
  border-radius: var(--radius);
  padding: 14px 20px;
  display: flex; align-items: center; gap: 12px;
  font-size: .875rem; color: #92400e;
}
.notice-icon { flex-shrink: 0; display: flex; align-items: center; }
.notice-icon svg { width: 20px; height: 20px; }

/* Card */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; flex-wrap: wrap; gap: 10px; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }

/* Filter bar */
.filter-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; padding: 14px 22px; border-bottom: 1px solid #edf2ee; background: var(--cream); }
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-wrap input {
  width: 100%; padding: 8px 14px 8px 36px;
  border: 1.5px solid var(--border); border-radius: 8px;
  font-family: inherit; font-size: .875rem; color: var(--ink);
  background: var(--white); outline: none; transition: .2s;
}
.search-wrap input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
.search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--ink-3); pointer-events: none; display: flex; align-items: center; }
.search-icon svg { width: 15px; height: 15px; }
.filter-select { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: .875rem; color: var(--ink-2); background: var(--white); outline: none; cursor: pointer; }
.filter-select:focus { border-color: var(--brand); }

/* Table */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
  font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
  color: var(--ink-3); padding: 11px 18px; text-align: left;
  background: var(--cream); border-bottom: 1px solid #edf2ee; white-space: nowrap; cursor: pointer;
}
.data-table thead th:hover { color: var(--brand); }
.data-table td { padding: 12px 18px; font-size: .875rem; color: var(--ink-2); border-bottom: 1px solid #f0f4f1; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #fdf6f1; }

.row-num { font-size: .78rem; color: var(--ink-3); font-weight: 600; }
.stall-badge { display: inline-flex; align-items: center; gap: 5px; background: var(--orange-light); color: #b45309; font-size: .78rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; }
.stall-badge svg { width: 12px; height: 12px; }
.vendor-cell { display: flex; align-items: center; gap: 10px; }
.vendor-avatar { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; background: var(--orange-light); color: #b45309; font-weight: 700; font-size: .8rem; display: flex; align-items: center; justify-content: center; }
.amount-cell { font-weight: 700; color: var(--ink); font-family: 'Inter', sans-serif; }

/* Progress bar per row */
.amount-bar { height: 3px; border-radius: 2px; background: #edf2ee; margin-top: 4px; overflow: hidden; }
.amount-bar-fill { height: 100%; background: var(--orange); border-radius: 2px; }

/* Pagination */
.table-footer { display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; border-top: 1px solid #edf2ee; font-size: .82rem; color: var(--ink-3); flex-wrap: wrap; gap: 10px; }
.pagination { display: flex; gap: 4px; }
.page-btn { min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid var(--border); border-radius: 7px; background: var(--white); color: var(--ink-2); font-size: .82rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: .2s; font-family: inherit; }
.page-btn:hover { background: var(--brand-light); border-color: var(--brand); color: var(--brand-dark); }
.page-btn.active { background: var(--brand); border-color: var(--brand); color: #fff; }
.page-btn:disabled { opacity: .4; cursor: not-allowed; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-state .empty-icon { width: 52px; height: 52px; margin: 0 auto 12px; opacity: .4; display: flex; align-items: center; justify-content: center; }
.empty-state .empty-icon svg { width: 30px; height: 30px; }
.empty-state p { font-size: .92rem; color: var(--ink-3); }

@media (max-width: 900px) { .summary-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 500px) { .summary-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <div>
      <h1>Partial Payments</h1>
      <p class="sub">Vendors who made partial rental fee payments — pending full settlement.</p>
    </div>
    <a href="payments.php" class="btn-sm btn-outline">← All Payments</a>
  </div>

  <!-- NOTICE -->
  <div class="notice-banner">
    <span class="notice-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></span>
    <span>Partial payments require follow-up. These vendors have not yet paid their full rental amount for the period.</span>
  </div>

  <!-- SUMMARY -->
  <div class="summary-grid">
    <div class="sum-card">
      <div class="sum-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 3h6m-3-13.5H8.25a2.25 2.25 0 00-2.25 2.25v14.25a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25V8.25L15 3.75z"/></svg></div>
      <div>
        <div class="sum-label">Partial Records</div>
        <div class="sum-val"><?= $totalCount ?></div>
      </div>
    </div>
    <div class="sum-card">
      <div class="sum-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3.75-9.75h5.25a2.25 2.25 0 010 4.5h-3a2.25 2.25 0 000 4.5h5.25"/></svg></div>
      <div>
        <div class="sum-label">Total Partial Collected</div>
        <div class="sum-val">₱<?= number_format($totalPartial, 2) ?></div>
      </div>
    </div>
    <div class="sum-card">
      <div class="sum-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z"/></svg></div>
      <div>
        <div class="sum-label">Average Partial Amount</div>
        <div class="sum-val">₱<?= number_format($avgAmount, 2) ?></div>
      </div>
    </div>
  </div>

  <!-- TABLE CARD -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Partial Payment Records</span>
      <span style="font-size:.82rem;color:var(--ink-3)"><?= $totalCount ?> records</span>
    </div>

    <div class="filter-bar">
      <div class="search-wrap">
        <span class="search-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg></span>
        <input type="text" id="searchInput" placeholder="Search stall, vendor, amount…">
      </div>
      <select class="filter-select" id="rowsPerPage">
        <option value="10">10 / page</option>
        <option value="25">25 / page</option>
        <option value="50">50 / page</option>
      </select>
    </div>

    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th onclick="sortTable(1)">Stall ↕</th>
            <th onclick="sortTable(2)">Vendor ↕</th>
            <th onclick="sortTable(3)">Amount ↕</th>
            <th onclick="sortTable(4)">Date ↕</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php
          $maxAmt = !empty($partials) ? max(array_column($partials, 'amount')) : 1;
          foreach ($partials as $i => $p):
            $pct = $maxAmt > 0 ? round(($p['amount'] / $maxAmt) * 100) : 0;
            $initials = strtoupper(substr($p['vendor_name'] ?? 'V', 0, 1));
          ?>
          <tr>
            <td class="row-num"><?= $i + 1 ?></td>
            <td><span class="stall-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 21h19.5m-18-18v18m16.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125V21"/></svg> <?= htmlspecialchars($p['stall_number']) ?></span></td>
            <td>
              <div class="vendor-cell">
                <div class="vendor-avatar"><?= $initials ?></div>
                <?= htmlspecialchars($p['vendor_name'] ?? '—') ?>
              </div>
            </td>
            <td>
              <div class="amount-cell">₱<?= number_format($p['amount'], 2) ?></div>
              <div class="amount-bar"><div class="amount-bar-fill" style="width:<?= $pct ?>%"></div></div>
            </td>
            <td><?= date('M d, Y h:i A', strtotime($p['paid_at'])) ?></td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($partials)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
              <p>No partial payments found. All vendors are fully paid!</p>
            </div>
          </td></tr>
          <?php endif ?>
        </tbody>
      </table>
      <div class="empty-state" id="emptyState" style="display:none;">
        <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg></div>
        <p>No records match your search.</p>
      </div>
    </div>

    <div class="table-footer">
      <span id="tableInfo">Loading…</span>
      <div class="pagination" id="pagination"></div>
    </div>
  </div>

</div>
</main>

<script>
(function () {
  const allRows  = Array.from(document.querySelectorAll('#tableBody tr'));
  const body     = document.getElementById('tableBody');
  const info     = document.getElementById('tableInfo');
  const pagDiv   = document.getElementById('pagination');
  const empty    = document.getElementById('emptyState');
  const searchEl = document.getElementById('searchInput');
  const rowsEl   = document.getElementById('rowsPerPage');

  let filtered = [...allRows];
  let page = 1, perPage = 10;
  let sortCol = -1, sortAsc = true;

  function getText(row, col) { return row.cells[col]?.textContent.trim().toLowerCase() || ''; }

  function applyFilters() {
    const q = searchEl.value.toLowerCase();
    filtered = allRows.filter(row => !q || [1,2,3,4].some(c => getText(row,c).includes(q)));
    page = 1; render();
  }

  function render() {
    perPage = parseInt(rowsEl.value);
    const total = filtered.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    page = Math.min(page, pages);
    allRows.forEach(r => r.style.display = 'none');
    filtered.slice((page-1)*perPage, page*perPage).forEach(r => r.style.display = '');
    empty.style.display = total === 0 ? 'block' : 'none';
    info.textContent = total === 0 ? 'No records' : `Showing ${(page-1)*perPage+1}–${Math.min(page*perPage,total)} of ${total} records`;
    renderPagination(pages);
  }

  function renderPagination(pages) {
    pagDiv.innerHTML = '';
    const makeBtn = (label, pg, disabled=false, active=false) => {
      const b = document.createElement('button');
      b.className = 'page-btn' + (active ? ' active' : '');
      b.textContent = label; b.disabled = disabled;
      b.onclick = () => { page = pg; render(); };
      pagDiv.appendChild(b);
    };
    makeBtn('‹', page-1, page===1);
    for (let i = Math.max(1,page-2); i <= Math.min(pages,page+2); i++) makeBtn(i,i,false,i===page);
    makeBtn('›', page+1, page===pages);
  }

  window.sortTable = function(col) {
    if (sortCol===col) sortAsc=!sortAsc; else { sortCol=col; sortAsc=true; }
    filtered.sort((a,b) => {
      let va=getText(a,col), vb=getText(b,col);
      if (col===3) { va=parseFloat(va.replace(/[₱,]/g,'')); vb=parseFloat(vb.replace(/[₱,]/g,'')); return sortAsc?va-vb:vb-va; }
      return sortAsc?va.localeCompare(vb):vb.localeCompare(va);
    });
    render();
  };

  searchEl.addEventListener('input', applyFilters);
  rowsEl.addEventListener('change', () => { page=1; render(); });
  applyFilters();
})();
</script>
</body>
</html>