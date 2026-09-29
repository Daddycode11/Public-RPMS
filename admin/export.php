<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

// Handle export requests
if (isset($_GET['export']) && $_GET['export']==='payments') { require __DIR__.'/../includes/payment_records.php'; exit; }
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $format = $_GET['format'] ?? 'csv';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';

    $data = [];
    $headers = [];
    $filename = '';

    switch ($type) {
        case 'vendors':
            $data = $pdo->query("
                SELECT v.id, v.stall_number, CONCAT(u.first_name,' ',u.last_name) AS vendor_name,
                       u.email, v.monthly_rent, v.daily_rent, v.balance, v.status, s.section_name, v.next_due_date
                FROM vendors v
                JOIN users u ON u.id = v.user_id
                LEFT JOIN sections s ON s.id = v.section_id
                WHERE v.deleted_at IS NULL ORDER BY v.stall_number
            ")->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['ID', 'Stall', 'Name', 'Email', 'Monthly Rent', 'Daily Rent', 'Balance', 'Status', 'Section', 'Next Due Date'];
            $filename = 'vendors_export';
            break;

        case 'collections':
            $sql = "SELECT CONCAT(u.first_name,' ',u.last_name) AS collector,
                           COUNT(p.id) AS total_payments,
                           SUM(p.amount_paid) AS total_collected,
                           SUM(p.discount) AS total_discount,
                           SUM(p.penalty) AS total_penalty, SUM(p.amount_paid-COALESCE(p.discount,0)+COALESCE(p.penalty,0)) AS net_collected
                    FROM payments p
                    JOIN users u ON u.id = p.collector_id
                    WHERE p.deleted_at IS NULL";
            $params = [];
            if ($dateFrom) { $sql .= " AND p.payment_date >= ?"; $params[] = $dateFrom; }
            if ($dateTo) { $sql .= " AND p.payment_date <= ?"; $params[] = $dateTo; }
            $sql .= " GROUP BY p.collector_id ORDER BY total_collected DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Collector', 'Total Payments', 'Total Collected', 'Total Discount', 'Total Penalty','Net Collected'];
            $filename = 'collections_export';
            break;

        case 'overdue':
            $data = $pdo->query("
                SELECT v.stall_number, CONCAT(u.first_name,' ',u.last_name) AS vendor_name,
                       u.email, v.monthly_rent, v.balance, v.next_due_date, s.section_name
                FROM vendors v
                JOIN users u ON u.id = v.user_id
                LEFT JOIN sections s ON s.id = v.section_id
                WHERE v.deleted_at IS NULL AND v.balance>0 AND v.next_due_date<CURDATE()
                ORDER BY v.next_due_date ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Stall', 'Vendor', 'Email', 'Monthly Rent', 'Balance', 'Due Date', 'Section'];
            $filename = 'overdue_vendors_export';
            break;
    }

    if ($format === 'xlsx' && $filename !== '') { require_once __DIR__.'/../includes/spreadsheet_export.php'; exportWorkbook($headers,array_map('array_values',$data),$filename); }

    if ($format === 'csv' && $filename !== '') {
        $filename .= '_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($data as $row) {
            fputcsv($output, array_map(static fn($v) => preg_match('/^[\s]*[=+@\-]/u',(string)$v) ? "'".(string)$v : $v, array_values($row)));
        }
        fclose($output);
        exit;
    }

    if ($format === 'html_pdf' && $filename !== '') {
        // Generate printable HTML page for browser PDF printing
        $filename .= '_' . date('Y-m-d');
        ?>
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8">
        <title><?= h($filename) ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            *{font-family:'Inter',Arial,sans-serif}
            body{font-size:12px;margin:20px;color:#2b0d05}
            h1{font-size:18px;color:#ea580c;margin-bottom:4px;font-weight:700}
            .meta{color:#6b8878;font-size:11px;margin-bottom:16px}
            table{width:100%;border-collapse:collapse;margin-top:10px}
            th{background:#ea580c;color:#fff;padding:8px 10px;text-align:left;font-size:11px}
            td{padding:7px 10px;border-bottom:1px solid #eee;font-size:11px}
            tr:nth-child(even) td{background:#fdf6f1}
            .footer{margin-top:20px;font-size:10px;color:#999;text-align:center}
            @media print{.no-print{display:none}}
        </style>
        </head><body>
        <div class="no-print" style="margin-bottom:16px">
            <button onclick="window.print()" style="padding:8px 16px;background:#ea580c;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">Print / Save as PDF</button>
            <button onclick="window.close()" style="padding:8px 16px;background:#f0f4f1;border:none;border-radius:6px;cursor:pointer;font-size:13px;margin-left:8px;font-weight:600">Close</button>
        </div>
        <h1>RPMS - <?= h(ucfirst($type)) ?> Report</h1>
        <div class="meta">Generated: <?= date('F d, Y h:i A') ?><?= $dateFrom ? ' | From: '.h($dateFrom) : '' ?><?= $dateTo ? ' | To: '.h($dateTo) : '' ?></div>
        <table>
            <thead><tr><?php foreach ($headers as $h) echo "<th>$h</th>"; ?></tr></thead>
            <tbody>
            <?php foreach ($data as $row): ?>
                <tr><?php foreach (array_values($row) as $val) echo '<td>' . htmlspecialchars($val) . '</td>'; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="footer">RPMS Report &mdash; <?= date('Y') ?> &mdash; Total Records: <?= count($data) ?></div>
        </body></html>
        <?php exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export Data | Admin - RPMS</title>
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
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --radius:      14px;
  --radius-sm:   10px;
  --error:       #dc2626;
  --error-bg:    #fef2f2;
}
body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Header */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 9px; font-family: inherit; font-size: .87rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: .2s; }
.btn svg { width: 15px; height: 15px; }
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); }
.btn-outline { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--brand-light); color: var(--brand-dark); }
.btn-sm { padding: 8px 14px; font-size: .82rem; border-radius: 7px; }

/* Card */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; flex-wrap: wrap; gap: 10px; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-body { padding: 22px; }

/* Date filters */
.date-filters { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: .73rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ink-3); }
.filter-group input {
  padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px;
  font-family: inherit; font-size: .875rem; color: var(--ink); background: var(--white);
  outline: none; transition: .2s;
}
.filter-group input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }

/* Export grid */
.export-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.export-card { border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; background: var(--white); transition: .25s; display: flex; flex-direction: column; }
.export-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--brand-glow); border-color: var(--brand); }

.export-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.export-icon svg { width: 22px; height: 22px; }
.export-icon.a { background: var(--brand-light); color: var(--brand-dark); }
.export-icon.b { background: #eff6ff; color: #1e40af; }
.export-icon.c { background: #fff7ed; color: #b45309; }
.export-icon.d { background: #fff1f2; color: #be123c; }

.export-card h3 { font-size: 1rem; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
.export-card p { font-size: .82rem; color: var(--ink-3); margin-bottom: 18px; line-height: 1.5; flex-grow: 1; }
.export-card .actions { display: flex; gap: 8px; flex-wrap: wrap; }

.btn-csv { background: var(--brand-light); color: var(--brand-dark); }
.btn-csv:hover { background: var(--brand); color: #fff; }
.btn-pdf { background: var(--ink); color: #fff; }
.btn-pdf:hover { background: #4a1c0e; }

@media (max-width: 700px) { .export-grid { grid-template-columns: 1fr; } .date-filters { flex-direction: column; align-items: stretch; } .filter-group input { width: 100%; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <div>
      <h1>Export Data</h1>
      <p class="sub">Export reports, payment history, and vendor lists to CSV or printable PDF.</p>
    </div>
  </div>

  <!-- DATE FILTER -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Date Range Filter</span>
      <span style="font-size:.82rem;color:var(--ink-3);">Applies to Payments &amp; Collections</span>
    </div>
    <div class="card-body">
      <div class="date-filters">
        <div class="filter-group">
          <label>From</label>
          <input type="date" id="dateFrom">
        </div>
        <div class="filter-group">
          <label>To</label>
          <input type="date" id="dateTo">
        </div>
      </div>
    </div>
  </div>

  <!-- EXPORT CARDS -->
  <div class="export-grid">

    <div class="export-card">
      <div class="export-icon a">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
      </div>
      <h3>Payment History</h3>
      <p>Export all payment records including vendor name, amount, date, and collector info.</p>
      <div class="actions">
        <a class="btn btn-csv btn-sm" onclick="doExport('payments','xlsx')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          Excel Download
        </a>
        <a class="btn btn-pdf btn-sm" onclick="doExport('payments','html_pdf')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.72 13.829a42.415 42.415 0 0110.56 0M6.34 18h11.32M17.66 18l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/></svg>
          View / PDF
        </a>
      </div>
    </div>

    <div class="export-card">
      <div class="export-icon b">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.621 1.622a3.003 3.003 0 01-.621 4.72m-13.5 8.65h3v-3.75h-3v3.75z"/></svg>
      </div>
      <h3>Vendor List</h3>
      <p>Export all registered vendors with stall numbers, contact info, and rental status.</p>
      <div class="actions">
        <a class="btn btn-csv btn-sm" onclick="doExport('vendors','xlsx')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          Excel Download
        </a>
        <a class="btn btn-pdf btn-sm" onclick="doExport('vendors','html_pdf')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.72 13.829a42.415 42.415 0 0110.56 0M6.34 18h11.32M17.66 18l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/></svg>
          View / PDF
        </a>
      </div>
    </div>

    <div class="export-card">
      <div class="export-icon c">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v12m-3.75-9.75h5.25a2.25 2.25 0 010 4.5h-3a2.25 2.25 0 000 4.5h5.25"/></svg>
      </div>
      <h3>Collection Summary</h3>
      <p>Export collector performance data with total amounts and payment counts.</p>
      <div class="actions">
        <a class="btn btn-csv btn-sm" onclick="doExport('collections','xlsx')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          Excel Download
        </a>
        <a class="btn btn-pdf btn-sm" onclick="doExport('collections','html_pdf')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.72 13.829a42.415 42.415 0 0110.56 0M6.34 18h11.32M17.66 18l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/></svg>
          View / PDF
        </a>
      </div>
    </div>

    <div class="export-card">
      <div class="export-icon d">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
      </div>
      <h3>Overdue Vendors</h3>
      <p>Export a list of all vendors with overdue payments for follow-up.</p>
      <div class="actions">
        <a class="btn btn-csv btn-sm" onclick="doExport('overdue','xlsx')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          Excel Download
        </a>
        <a class="btn btn-pdf btn-sm" onclick="doExport('overdue','html_pdf')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.72 13.829a42.415 42.415 0 0110.56 0M6.34 18h11.32M17.66 18l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/></svg>
          View / PDF
        </a>
      </div>
    </div>

  </div>

</div>
</main>

<script>
function doExport(type, format) {
    let url = 'export.php?export=' + type + '&format=' + format;
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    if (from) url += '&date_from=' + from;
    if (to) url += '&date_to=' + to;

    if (format === 'html_pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}
</script>
</body>
</html>