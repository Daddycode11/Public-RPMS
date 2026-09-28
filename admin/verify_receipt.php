<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$result  = null;
$notFound = false;
$searched = false;

if (isset($_GET['code']) && trim($_GET['code']) !== '') {
    $searched = true;
    $code = trim($_GET['code']);
    $stmt = $pdo->prepare("
        SELECT p.*, v.vendor_name, v.stall_number,
               CONCAT(u.first_name,' ',u.last_name) AS collector_name
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        LEFT JOIN users u ON u.id = p.collector_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $receiptId=preg_match('/^(?:RPMS-RECEIPT-)?([0-9]+)$/D',$code,$match)?(int)$match[1]:0;
    $stmt->execute([$receiptId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) $notFound = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Receipt Verification | RPMS</title>
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
  --brand-glow:  rgba(234,88,12,.15);
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --error:       #dc2626;
  --error-bg:    #fef2f2;
  --radius:      14px;
}

body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }
.page-wrap { display: flex; flex-direction: column; gap: 24px; }
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* ---- VERIFY CARD ---- */
.verify-layout {
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: 24px;
  align-items: start;
}

.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; border-bottom: 1px solid #edf2ee; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-sub { font-size: .82rem; color: var(--ink-3); margin-top: 3px; }
.card-body { padding: 24px 22px; }

/* Search form */
.search-form { display: flex; flex-direction: column; gap: 14px; }
.field label { display: block; font-size: .82rem; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
.field input {
  width: 100%; padding: 11px 14px;
  border: 1.5px solid #e0e8e3; border-radius: 10px;
  font-family: inherit; font-size: .92rem; color: var(--ink);
  outline: none; transition: .2s;
  letter-spacing: .05em;
}
.field input:focus { border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-glow); }
.field input::placeholder { color: #b0c4b8; letter-spacing: normal; }

.btn-verify {
  width: 100%; padding: 12px; border: none; border-radius: 10px;
  background: var(--brand); color: #fff;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer; transition: .2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-verify svg { width: 16px; height: 16px; }
.btn-verify:hover { background: var(--brand-dark); transform: translateY(-1px); box-shadow: 0 6px 20px var(--brand-glow); }

/* QR scanner mock */
.qr-scan-area {
  border: 2px dashed var(--border);
  border-radius: var(--radius);
  padding: 28px;
  text-align: center;
  margin-bottom: 14px;
  cursor: pointer;
  transition: .2s;
  background: var(--cream);
}
.qr-scan-area:hover { border-color: var(--brand); background: var(--brand-light); }
.qr-icon { width: 56px; height: 56px; margin: 0 auto 8px; color: var(--ink-3); opacity: .6; display: flex; align-items: center; justify-content: center; }
.qr-icon svg { width: 32px; height: 32px; }
.qr-text { font-size: .85rem; color: var(--ink-3); }
.qr-divider { display: flex; align-items: center; gap: 10px; margin: 14px 0; }
.qr-divider span { font-size: .78rem; color: var(--ink-3); white-space: nowrap; }
.qr-divider::before, .qr-divider::after { content:""; flex:1; height:1px; background:#e0e8e3; }

/* ---- RESULT PANEL ---- */
.result-valid {
  border: 2px solid var(--brand);
  border-radius: var(--radius);
  overflow: hidden;
}
.result-header-valid {
  background: var(--brand);
  padding: 18px 22px;
  display: flex; align-items: center; gap: 12px;
}
.result-check {
  width: 44px; height: 44px; border-radius: 50%;
  background: rgba(255,255,255,.2);
  display: flex; align-items: center; justify-content: center;
  animation: popIn .4s cubic-bezier(.34,1.56,.64,1) both;
}
.result-check svg { width: 22px; height: 22px; color: #fff; }
@keyframes popIn { from{transform:scale(0)} to{transform:scale(1)} }
.result-header-valid h3 { color: #fff; font-family: 'Inter', sans-serif; font-size: 1.3rem; margin: 0; }
.result-header-valid p { color: rgba(255,255,255,.85); font-size: .83rem; margin: 2px 0 0; }

.receipt-details { padding: 22px; background: var(--white); }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.detail-item { }
.detail-label { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 3px; }
.detail-val { font-size: .92rem; font-weight: 600; color: var(--ink-2); }
.detail-val.big { font-family: 'Inter', sans-serif; font-size: 1.4rem; color: var(--ink); }
.receipt-code-box {
  margin-top: 18px; padding: 14px 16px;
  background: var(--cream); border-radius: 10px;
  display: flex; align-items: center; justify-content: space-between;
  border: 1px solid var(--border);
}
.receipt-code-val { font-family: monospace; font-size: 1rem; letter-spacing: .12em; color: var(--ink); font-weight: 700; }
.copy-btn {
  background: none; border: 1px solid var(--border); border-radius: 7px;
  padding: 5px 10px; font-size: .78rem; color: var(--ink-3);
  cursor: pointer; font-family: inherit; transition: .2s;
}
.copy-btn:hover { background: var(--brand-light); color: var(--brand-dark); border-color: var(--brand); }

/* Invalid result */
.result-invalid {
  border: 2px solid #fecaca;
  border-radius: var(--radius);
  overflow: hidden;
  animation: shake .3s ease;
}
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
.result-header-invalid {
  background: var(--error-bg);
  padding: 18px 22px;
  display: flex; align-items: center; gap: 12px;
  border-bottom: 1px solid #fecaca;
}
.result-header-invalid .invalid-icon { display: flex; align-items: center; }
.result-header-invalid .invalid-icon svg { width: 30px; height: 30px; color: var(--error); }
.result-header-invalid h3 { color: var(--error); font-family: 'Inter', sans-serif; font-size: 1.2rem; margin: 0; }
.result-header-invalid p { color: #9b1c1c; font-size: .83rem; margin: 2px 0 0; }
.invalid-body { padding: 20px 22px; background: var(--white); font-size: .88rem; color: var(--ink-3); line-height: 1.6; }

/* Idle state */
.idle-state { text-align: center; padding: 48px 20px; }
.idle-icon { width: 60px; height: 60px; margin: 0 auto 12px; opacity: .35; display: flex; align-items: center; justify-content: center; }
.idle-icon svg { width: 36px; height: 36px; }
.idle-state p { font-size: .88rem; color: var(--ink-3); }

/* Recent verified */
.recent-list { display: flex; flex-direction: column; gap: 0; }
.recent-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 22px; border-bottom: 1px solid #f0f4f1; font-size: .875rem; }
.recent-item:last-child { border-bottom: none; }
.recent-code { font-family: monospace; font-size: .82rem; color: var(--ink-3); }
.recent-vendor { font-weight: 600; color: var(--ink-2); }
.recent-status { font-size: .74rem; font-weight: 700; padding: 2px 8px; border-radius: 50px; background: var(--brand-light); color: var(--brand-dark); }

@media (max-width: 900px) { .verify-layout { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <div>
      <h1>QR Receipt Verification</h1>
      <p class="sub">Verify the authenticity of a payment receipt by entering the receipt code or scanning the QR.</p>
    </div>
  </div>

  <div class="verify-layout">

    <!-- LEFT: Search Form -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Enter Receipt Code</div>
        <div class="card-sub">Type or scan a receipt code to verify</div>
      </div>
      <div class="card-body">
        <!-- QR Scan mock -->
        <div class="qr-scan-area" onclick="document.getElementById('codeInput').focus()">
          <div class="qr-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.04l-.821 1.315z"/><path d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg></div>
          <div class="qr-text">Click to activate QR scanner<br><small>or type the code below</small></div>
        </div>

        <div class="qr-divider"><span>or enter manually</span></div>

        <form method="GET" class="search-form">
          <div class="field">
            <label for="codeInput">Receipt Code</label>
            <input
              type="text"
              id="codeInput"
              name="code"
              value="<?= htmlspecialchars($_GET['code'] ?? '') ?>"
              placeholder="e.g. RCP-2025-000824"
              required
              autocomplete="off"
              autofocus
            >
          </div>
          <button type="submit" class="btn-verify">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            Verify Receipt
          </button>
        </form>

        <?php if ($searched): ?>
        <div style="margin-top:16px;padding:10px 14px;border-radius:8px;background:var(--cream);font-size:.8rem;color:var(--ink-3);display:flex;align-items:center;gap:8px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
          Searched for: <strong style="color:var(--ink-2);font-family:monospace"><?= htmlspecialchars($_GET['code']) ?></strong>
        </div>
        <?php endif ?>
      </div>
    </div>

    <!-- RIGHT: Result Panel -->
    <div>
      <?php if ($result): ?>
        <!-- VALID -->
        <div class="result-valid">
          <div class="result-header-valid">
            <div class="result-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg></div>
            <div>
              <h3>Valid Receipt</h3>
              <p>This receipt is authentic and verified.</p>
            </div>
          </div>
          <div class="receipt-details">
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Amount Paid</div>
                <div class="detail-val big">₱<?= number_format($result['amount_paid'], 2) ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Status</div>
                <div class="detail-val"><span style="background:var(--brand-light);color:var(--brand-dark);padding:3px 10px;border-radius:50px;font-size:.82rem;font-weight:700"><?= ucfirst($result['status']) ?></span></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Vendor</div>
                <div class="detail-val"><?= htmlspecialchars($result['vendor_name']) ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Stall Number</div>
                <div class="detail-val"><?= htmlspecialchars($result['stall_number'] ?? '—') ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Payment Date</div>
                <div class="detail-val"><?= date('M d, Y h:i A', strtotime($result['paid_at'])) ?></div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Collector</div>
                <div class="detail-val"><?= htmlspecialchars($result['collector_name'] ?? '—') ?></div>
              </div>
            </div>
            <div class="receipt-code-box">
              <div>
                <div style="font-size:.7rem;color:var(--ink-3);margin-bottom:3px;text-transform:uppercase;letter-spacing:.08em">Receipt Code</div>
                <span class="receipt-code-val"><?= htmlspecialchars(('RPMS-RECEIPT-'.$result['id'])) ?></span>
              </div>
              <button class="copy-btn" onclick="copyCode('<?= htmlspecialchars(('RPMS-RECEIPT-'.$result['id'])) ?>')">Copy</button>
            </div>
          </div>
        </div>

      <?php elseif ($notFound): ?>
        <!-- INVALID -->
        <div class="result-invalid">
          <div class="result-header-invalid">
            <span class="invalid-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
            <div>
              <h3>Invalid Receipt</h3>
              <p>No payment found with this receipt code.</p>
            </div>
          </div>
          <div class="invalid-body">
            The code <strong style="font-family:monospace;color:var(--ink)">"<?= htmlspecialchars($_GET['code']) ?>"</strong> does not match any receipt in the system.
            Please check the code and try again, or contact the market administrator.
          </div>
        </div>

      <?php else: ?>
        <!-- IDLE -->
        <div class="card">
          <div class="card-body">
            <div class="idle-state">
              <div class="idle-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M9 3.75h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0115 21.75H9A2.25 2.25 0 016.75 19.5V6A2.25 2.25 0 019 3.75z"/></svg></div>
              <p>Enter a receipt code on the left to verify a payment receipt.</p>
            </div>
          </div>
        </div>
      <?php endif ?>
    </div>

  </div>

</div>
</main>

<script>
function copyCode(code) {
  navigator.clipboard.writeText(code).then(() => {
    const btn = document.querySelector('.copy-btn');
    btn.textContent = 'Copied!';
    btn.style.background = 'var(--brand-light)';
    btn.style.color = 'var(--brand-dark)';
    setTimeout(() => { btn.textContent = 'Copy'; btn.style.background = ''; btn.style.color = ''; }, 2000);
  });
}
</script>
</body>
</html>