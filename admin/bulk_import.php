<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__.'/../includes/payment_rules.php';
$message = '';
$messageType = '';
$importResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload failed.';
        $messageType = 'error';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $message = 'Only CSV files are allowed.';
        $messageType = 'error';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle) ?: [];

        // Normalize headers
        $header = array_map(function($h) { return strtolower(trim($h)); }, $header);
        $required = ['stall_number', 'section', 'amount_paid', 'payment_date'];
        $missing = array_diff($required, $header);

        if (!empty($missing)) {
            $message = 'Missing required columns: ' . implode(', ', $missing);
            $messageType = 'error';
            fclose($handle);
        } else {
            $totalRows = 0;
            $successRows = 0;
            $failedRows = 0;
            $errors = [];

            $stmtVendor = $pdo->prepare("SELECT v.id FROM vendors v JOIN sections s ON s.id=v.section_id WHERE v.stall_number = ? AND s.section_name = ? AND v.deleted_at IS NULL");
            $stmtPayment = $pdo->prepare("
                INSERT INTO payments (vendor_id, collector_id, amount_paid, payment_date, discount, penalty, status, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, 'paid', ?)
            ");

            while (($row = fgetcsv($handle)) !== false) {
                $totalRows++;
                $data = count($header)===count($row) ? array_combine($header, $row) : [];
                if (!$data) { $failedRows++; $errors[]="Row $totalRows: Column count mismatch."; continue; }

                $stallNumber = trim($data['stall_number'] ?? '');
                $amountPaid = floatval($data['amount_paid'] ?? 0);
                $paymentDate = trim($data['payment_date'] ?? '');
                $discount = floatval($data['discount'] ?? 0);
                $penalty = floatval($data['penalty'] ?? 0);

                if (empty($stallNumber) || $amountPaid <= 0 || empty($paymentDate)) {
                    $failedRows++;
                    $errors[] = "Row $totalRows: Invalid data (stall: $stallNumber, amount: $amountPaid)";
                    continue;
                }

                $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$paymentDate);
                if (!$parsed || $parsed->format('Y-m-d')!==$paymentDate || !is_finite($amountPaid) || $amountPaid>99999999.99) { $failedRows++; $errors[]="Row $totalRows: Invalid date or amount."; continue; }
                $adjustment=calculatePaymentAdjustment($amountPaid,$parsed);
                $stmtVendor->execute([$stallNumber,trim($data['section']??'')]);
                $vendor = $stmtVendor->fetch(PDO::FETCH_ASSOC);

                if (!$vendor) {
                    $failedRows++;
                    $errors[] = "Row $totalRows: Vendor with stall '$stallNumber' not found";
                    continue;
                }

                try {
                    $stmtPayment->execute([
                        $vendor['id'],
                        $_SESSION['user_id'],
                        $amountPaid,
                        $paymentDate,
                        $adjustment['discount'],
                        $adjustment['penalty'],
                        $paymentDate.' 12:00:00'
                    ]);
                    $successRows++;
                } catch (PDOException $e) {
                    $failedRows++;
                    $errors[] = "Row $totalRows: Payment could not be imported.";
                }
            }
            fclose($handle);

            // Log the import
            $pdo->prepare("INSERT INTO payment_imports (file_name, total_rows, success_rows, failed_rows, imported_by) VALUES (?, ?, ?, ?, ?)")
                ->execute([$file['name'], $totalRows, $successRows, $failedRows, $_SESSION['user_id']]);

            $importResults = ['total' => $totalRows, 'success' => $successRows, 'failed' => $failedRows, 'errors' => $errors];
            $message = "Import complete: $successRows of $totalRows payments imported successfully.";
            $messageType = $failedRows > 0 ? 'warning' : 'success';

            // Save uploaded file
            // Import metadata is retained; no publicly accessible copy of the CSV is stored.
        }
    }
}

// Get import history
$importHistory = $pdo->query("
    SELECT pi.*, CONCAT(u.first_name, ' ', u.last_name) AS imported_by_name
    FROM payment_imports pi
    JOIN users u ON u.id = pi.imported_by
    ORDER BY pi.created_at DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bulk Payment Import | RPMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#ea580c;--brand-dark:#b3260c;--brand-light:#ffe4d1;--ink:#2b0d05;--ink-2:#3a5042;--ink-3:#6b8878;--cream:#f0f4f1;--white:#fff;--border:rgba(234,88,12,.12);--radius:14px;--shadow:0 2px 16px rgba(0,0,0,.06)}
body{font-family:'Inter',sans-serif;background:var(--cream);color:var(--ink)}
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.card-header{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf2ee}
.card-title{font-size:.95rem;font-weight:700;color:var(--ink)}
.card-body{padding:22px}
.page-header h1{font-family:'Inter',sans-serif;font-size:1.7rem;font-weight:700}
.page-header .sub{font-size:.88rem;color:var(--ink-3);margin-top:4px}

.upload-zone{border:2px dashed var(--border);border-radius:var(--radius);padding:40px;text-align:center;cursor:pointer;transition:.3s;background:#fafcfb}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--brand);background:var(--brand-light)}
.upload-zone .icon{width:56px;height:56px;margin:0 auto 12px;color:var(--ink-3);display:flex;align-items:center;justify-content:center;}
.upload-zone .icon svg{width:32px;height:32px;}
.upload-zone p{font-size:.9rem;color:var(--ink-3)}
.upload-zone .browse{color:var(--brand);font-weight:600;text-decoration:underline;cursor:pointer}

.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;border:none;transition:.2s}
.btn-primary{background:var(--brand);color:#fff}
.btn-primary:hover{background:var(--brand-dark)}
.btn-outline{background:var(--white);color:var(--ink-2);border:1px solid var(--border)}

.alert{padding:14px 18px;border-radius:10px;font-size:.88rem;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px}
.alert svg{width:17px;height:17px;flex-shrink:0;margin-top:1px;}
.alert-success{background:#fff7f2;color:#9a3412;border:1px solid var(--brand-light)}
.alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.alert-warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a}

.results-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0}
.result-card{background:var(--cream);border-radius:10px;padding:16px;text-align:center}
.result-card .num{font-family:'Inter',sans-serif;font-size:1.8rem;font-weight:700}
.result-card .num.success{color:var(--brand)}
.result-card .num.fail{color:#dc2626}
.result-card .lbl{font-size:.78rem;color:var(--ink-3);margin-top:4px}

.error-list{background:#fef2f2;border-radius:8px;padding:12px 16px;max-height:200px;overflow-y:auto;margin-top:12px}
.error-list li{font-size:.82rem;color:#991b1b;margin-bottom:4px}

.data-table{width:100%;border-collapse:collapse}
.data-table th{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-3);padding:10px 16px;text-align:left;background:var(--cream);border-bottom:1px solid #edf2ee}
.data-table td{padding:11px 16px;font-size:.875rem;color:var(--ink-2);border-bottom:1px solid #f0f4f1}
.data-table tr:hover td{background:#fdf6f1}

.template-info{background:var(--cream);border-radius:10px;padding:16px;margin-top:16px}
.template-info code{background:rgba(234,88,12,.1);padding:2px 6px;border-radius:4px;font-size:.82rem;color:var(--brand-dark)}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div style="display:flex;flex-direction:column;gap:24px">

    <div class="page-header">
        <div>
            <h1>Bulk Payment Import</h1>
            <p class="sub">Upload a CSV file to batch-process multiple payments at once.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?php if ($messageType === 'success'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
        <?php elseif ($messageType === 'error'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <?php endif ?>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($importResults): ?>
    <div class="card">
        <div class="card-header"><span class="card-title">Import Results</span></div>
        <div class="card-body">
            <div class="results-grid">
                <div class="result-card">
                    <div class="num"><?= $importResults['total'] ?></div>
                    <div class="lbl">Total Rows</div>
                </div>
                <div class="result-card">
                    <div class="num success"><?= $importResults['success'] ?></div>
                    <div class="lbl">Successful</div>
                </div>
                <div class="result-card">
                    <div class="num fail"><?= $importResults['failed'] ?></div>
                    <div class="lbl">Failed</div>
                </div>
            </div>
            <?php if (!empty($importResults['errors'])): ?>
            <ul class="error-list">
                <?php foreach ($importResults['errors'] as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><span class="card-title">Upload CSV File</span></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="importForm">
                <div class="upload-zone" id="dropZone" onclick="document.getElementById('csv_file').click()">
                    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg></div>
                    <p>Drag & drop your CSV file here, or <span class="browse">browse</span></p>
                    <p style="font-size:.78rem;color:var(--ink-3);margin-top:8px" id="fileName">Accepted: .csv files only</p>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" style="display:none">
                </div>
                <div style="margin-top:16px;display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary" id="importBtn" disabled>Import Payments</button>
                    <a href="#" class="btn btn-outline" onclick="downloadTemplate();return false;">Download Template</a>
                </div>
            </form>
            <div class="template-info">
                <strong>Required CSV columns:</strong><br>
                <code>stall_number</code>, <code>amount_paid</code>, <code>payment_date</code><br>
                <strong>Optional columns:</strong> <code>discount</code>, <code>penalty</code>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Import History</span></div>
        <table class="data-table">
            <thead><tr><th>Date</th><th>File</th><th>Total</th><th>Success</th><th>Failed</th><th>Imported By</th></tr></thead>
            <tbody>
            <?php if (empty($importHistory)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--ink-3)">No imports yet.</td></tr>
            <?php else: ?>
                <?php foreach ($importHistory as $h): ?>
                <tr>
                    <td><?= date('M d, Y h:i A', strtotime($h['created_at'])) ?></td>
                    <td><?= htmlspecialchars($h['file_name']) ?></td>
                    <td><?= $h['total_rows'] ?></td>
                    <td style="color:var(--brand);font-weight:600"><?= $h['success_rows'] ?></td>
                    <td style="color:<?= $h['failed_rows'] > 0 ? '#dc2626' : 'var(--ink-3)' ?>;font-weight:600"><?= $h['failed_rows'] ?></td>
                    <td><?= htmlspecialchars($h['imported_by_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</main>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('csv_file');
const importBtn = document.getElementById('importBtn');
const fileName = document.getElementById('fileName');

fileInput.addEventListener('change', function() {
    if (this.files.length) {
        fileName.textContent = this.files[0].name;
        importBtn.disabled = false;
        dropZone.style.borderColor = 'var(--brand)';
    }
});

['dragover','dragenter'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('dragover'); }));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('dragover'); }));
dropZone.addEventListener('drop', e => {
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

function downloadTemplate() {
    const csv = 'stall_number,section,amount_paid,payment_date,discount,penalty\nS001,500.00,2024-01-15,0,0\nS002,750.00,2024-01-15,50,0';
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'payment_import_template.csv';
    a.click();
}
</script>
</body>
</html>