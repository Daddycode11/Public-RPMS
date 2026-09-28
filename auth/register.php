<?php
require_once '../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../includes/security.php';
require_once '../includes/documents.php';
ob_start('secureHtml');

$error   = "";
$success = "";
$sections = $pdo->query("SELECT id, section_name FROM sections WHERE deleted_at IS NULL ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);
if (empty($_SESSION['registration_csrf'])) {
  $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accountType = $_POST['account_type'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
  $vendorName = trim($_POST['vendor_name'] ?? '');
  $stallNumber = trim($_POST['stall_number'] ?? '');
  $sectionId = (int)($_POST['section_id'] ?? 0);
  $contact = trim($_POST['contact'] ?? '');
  $documents = $_FILES['documents'] ?? null;

  if (!hash_equals($_SESSION['registration_csrf'], $_POST['csrf_token'] ?? '')) {
    $error = "Invalid registration request. Please refresh and try again.";
  } elseif (!in_array($accountType, ['collector', 'vendor'], true)) {
    $error = "Please select Collector or Vendor before registering.";
  } elseif (!$first_name || !$last_name || !$email || !$password || strlen($first_name)>100 || strlen($last_name)>100 || strlen((string)$email)>150) {
    $error = "All required account fields must be completed and the email must be valid.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
  } elseif ($accountType === 'vendor' && (!$vendorName || !$stallNumber || !$sectionId || !$contact || strlen($vendorName)>150 || strlen($stallNumber)>50 || strlen($contact)>100)) {
    $error = "Vendor name, stall number, section, and contact information are required.";
  } elseif ($accountType === 'vendor' && (!$documents || empty($documents['name'][0]))) {
    $error = "At least one vendor document is required.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
    if ($stmt->fetch()) {
            $error = "This email is already registered.";
    } else {
      $stmt = $pdo->prepare("SELECT id FROM vendors WHERE stall_number = ? AND section_id = ? AND deleted_at IS NULL LIMIT 1");
      $stmt->execute([$stallNumber, $sectionId]);
      if ($accountType === 'vendor' && $stmt->fetch()) {
        $error = "This stall number is already registered in the selected section.";
      }
    }
    if (!$error) {
      $sectionCheck = $pdo->prepare('SELECT id FROM sections WHERE id=? AND deleted_at IS NULL');
      $sectionCheck->execute([$sectionId]);
      if ($accountType === 'vendor' && !$sectionCheck->fetchColumn()) { $error = 'Select an available section.'; }
      $storedFiles = [];
      try {
        if ($error) throw new RuntimeException($error);
        $pdo->beginTransaction();
        if ($accountType === 'vendor') {
          $lock=$pdo->prepare('SELECT id FROM sections WHERE id=? AND deleted_at IS NULL FOR UPDATE'); $lock->execute([$sectionId]);
          if (!$lock->fetchColumn()) throw new RuntimeException('Select an available section.');
          $duplicate=$pdo->prepare('SELECT id FROM vendors WHERE stall_number=? AND section_id=? AND deleted_at IS NULL'); $duplicate->execute([$stallNumber,$sectionId]);
          if ($duplicate->fetchColumn()) throw new RuntimeException('This stall is already registered within this section.');
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare("INSERT INTO users (role, first_name, last_name, fullname, email, contact_information, password, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $insert->execute([$accountType, $first_name, $last_name, trim($first_name.' '.$last_name), $email, $accountType === 'vendor' ? $contact : null, $hashed]);
        $userId = (int)$pdo->lastInsertId();

        if ($accountType === 'vendor') {
          $insertVendor = $pdo->prepare("INSERT INTO vendors (user_id, section_id, stall_number, vendor_name, status, balance, created_at) VALUES (?, ?, ?, ?, 'inactive', 0, NOW())");
          $insertVendor->execute([$userId, $sectionId, $stallNumber, $vendorName]);
          $vendorId = (int)$pdo->lastInsertId();
          foreach ($documents['name'] as $index => $originalName) {
            $file = [];
            foreach (['name','tmp_name','error','size'] as $key) $file[$key] = $documents[$key][$index] ?? null;
            $storedFiles[] = storeVendorDocument($pdo, $vendorId, $userId, $file, 'Registration Document');
          }
          if (!$storedFiles) throw new RuntimeException('At least one valid document is required.');
        }
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
          ->execute([$userId, 'Registration submitted', 'Account type: ' . $accountType, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
        $pdo->commit();
        $success = "Registration submitted. Your account is pending admin approval.";
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
      } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($storedFiles as $storedFile) @unlink($storedFile);
        $error = $exception instanceof PDOException ? 'Registration could not be saved. Check your details and try again.' : $exception->getMessage();
      }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   ROOT & RESET
============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --forest:      #163527;
  --green:       #2d6a4f;
  --green-mid:   #3f8b64;
  --leaf:        #8fcfa8;
  --leaf-pale:   #e3f3e7;
  --gold:        #e2a33d;
  --gold-dark:   #b97b1f;
  --gold-glow:   rgba(226,163,61,.22);
  --ink:         #14251b;
  --ink-2:       #3f5449;
  --ink-3:       #71857a;
  --cream:       #faf6ec;
  --white:       #ffffff;
  --border:      rgba(22,53,39,.14);
  --error:       #c0374b;
  --error-bg:    #fbe9ec;
  --success:     #1c6b45;
  --success-bg:  #e3f3e7;
}

html, body {
  height: 100%;
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--ink);
}
h1, h2 { font-family: 'Fraunces', serif; }

/* ============================================================
   LAYOUT
============================================================ */
.auth-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

/* ============================================================
   LEFT PANEL
============================================================ */
.auth-left {
  position: relative;
  background: var(--forest);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px;
  overflow: hidden;
}

.auth-left::before {
  content: "";
  position: absolute; bottom: -100px; right: -100px;
  width: 400px; height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(226,163,61,.28), transparent 65%);
  z-index: 1;
}
.auth-left::after {
  content: "";
  position: absolute; top: -60px; left: -60px;
  width: 250px; height: 250px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(143,207,168,.16), transparent 65%);
  z-index: 1;
}

.left-bg {
  position: absolute; inset: 0;
  background: url('../assets/images/market-bg.png') center/cover no-repeat;
}
.left-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(120deg, rgba(20,45,33,.94) 0%, rgba(22,53,39,.75) 45%, rgba(22,53,39,.35) 100%);
}

.left-top { position: relative; z-index: 1; }
.left-top .logo-card {
  display: inline-flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,.94);
  border-radius: 14px;
  padding: 10px 18px;
  box-shadow: 0 6px 20px rgba(0,0,0,.15);
}
.left-top img { height: 40px; display: block; }

.left-body { position: relative; z-index: 1; color: #fff; }
.left-body .role-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.2);
  color: rgba(255,255,255,.9);
  font-size: .78rem; font-weight: 600;
  padding: 5px 14px; border-radius: 50px;
  margin-bottom: 20px;
}
.left-body .role-badge span { width: 7px; height: 7px; background: var(--gold); border-radius: 50%; }
.left-body h2 {
  font-weight: 600;
  font-size: 2.1rem;
  line-height: 1.2; margin-bottom: 14px;
}
.left-body p { font-size: .92rem; color: rgba(255,255,255,.82); line-height: 1.7; max-width: 320px; }

.left-perks { position: relative; z-index: 1; }
.perk-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 0;
  border-top: 1px solid rgba(255,255,255,.1);
  color: rgba(255,255,255,.78);
  font-size: .88rem;
}
.perk-icon {
  width: 34px; height: 34px; flex-shrink: 0;
  background: rgba(255,255,255,.1);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: var(--gold);
}
.perk-icon svg { width: 17px; height: 17px; }

/* ============================================================
   RIGHT PANEL
============================================================ */
.auth-right {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 40px;
  background: var(--white);
  position: relative;
  overflow-y: auto;
}

.home-btn {
  position: absolute; top: 24px; right: 24px;
  display: inline-flex; align-items: center; gap: 6px;
  font-size: .82rem; font-weight: 600;
  color: var(--ink-3); text-decoration: none;
  padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--cream);
  transition: .2s;
}
.home-btn:hover { background: var(--leaf-pale); color: var(--green); border-color: var(--green); }

.auth-form-wrap { width: 100%; max-width: 400px; padding: 20px 0; }

.auth-form-wrap .eyebrow {
  font-size: .8rem; font-weight: 600;
  color: var(--gold-dark); margin-bottom: 8px;
}
.auth-form-wrap h1 {
  font-weight: 600;
  font-size: 1.85rem;
  color: var(--forest); margin-bottom: 6px;
}
.auth-form-wrap .subtitle {
  font-size: .9rem; color: var(--ink-3); margin-bottom: 30px;
}

/* FIELDS */
.field { margin-bottom: 18px; }
.field label {
  display: block;
  font-size: .82rem; font-weight: 600; color: var(--ink-2); margin-bottom: 6px;
}
.field input {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid #dfe6e0;
  border-radius: 10px;
  font-family: inherit; font-size: .92rem;
  color: var(--ink); background: var(--white);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.field input:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 4px rgba(45,106,79,.14);
}
.field input::placeholder { color: #a9bbb0; }

/* name row */
.name-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* strength meter */
.strength-bar-wrap { margin-top: 8px; display: flex; gap: 4px; }
.strength-seg { flex: 1; height: 4px; border-radius: 2px; background: #dfe6e0; transition: background .3s; }
.strength-label { font-size: .75rem; color: var(--ink-3); margin-top: 5px; }

/* password eye */
.input-wrap { position: relative; }
.input-wrap input { padding-right: 44px; }
.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--ink-3); padding: 4px;
  display: flex; align-items: center;
  transition: color .2s;
}
.eye-btn:hover { color: var(--green); }
.eye-btn svg { width: 18px; height: 18px; }

/* TERMS */
.terms-row {
  display: flex; align-items: flex-start; gap: 10px;
  margin-bottom: 22px;
}
.terms-row input[type="checkbox"] {
  width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px;
  accent-color: var(--green); cursor: pointer;
}
.terms-row label { font-size: .82rem; color: var(--ink-3); line-height: 1.5; cursor: pointer; }
.terms-row a { color: var(--green); font-weight: 600; text-decoration: none; }

/* SUBMIT */
.btn-submit {
  width: 100%;
  padding: 13px;
  background: var(--gold);
  color: var(--forest);
  border: none; border-radius: 10px;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer;
  transition: .25s ease;
  display: flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-submit:hover { background: var(--gold-dark); color: #fff; transform: translateY(-1px); box-shadow: 0 8px 22px var(--gold-glow); }
.btn-submit:disabled { opacity: .7; cursor: not-allowed; transform: none; }

/* SPINNER */
.spinner {
  width: 17px; height: 17px;
  border: 2.5px solid rgba(22,53,39,.3);
  border-top-color: var(--forest);
  border-radius: 50%;
  animation: spin .65s linear infinite;
  display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ALERTS */
.alert-box {
  border-radius: 10px; padding: 12px 16px;
  margin-bottom: 20px; font-size: .87rem;
  display: flex; align-items: center; gap: 8px;
}
.alert-box svg { width: 16px; height: 16px; flex-shrink: 0; }
.alert-error { background: var(--error-bg); border: 1px solid #f3c3cb; color: var(--error); animation: shake .35s ease; }
.alert-success { background: var(--success-bg); border: 1px solid #bfe3cc; color: var(--success); }
@keyframes shake {
  0%,100%{transform:translateX(0)}
  25%{transform:translateX(-6px)}
  75%{transform:translateX(6px)}
}

/* DIVIDER */
.auth-divider { text-align: center; font-size: .82rem; color: var(--ink-3); margin-top: 20px; }
.auth-divider a { color: var(--green); font-weight: 600; text-decoration: none; }
.auth-divider a:hover { text-decoration: underline; }

/* RESPONSIVE */
@media (max-width: 768px) {
  .auth-page { grid-template-columns: 1fr; }
  .auth-left { display: none; }
  .auth-right { padding: 40px 24px; }
  .name-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="auth-page">

  <!-- LEFT PANEL -->
  <div class="auth-left">
    <div class="left-bg"></div>
    <div class="left-overlay"></div>

    <div class="left-top">
      <div class="market-brand"><span class="market-logo" role="img" aria-label="San Jose Public Market"></span><strong>RPMS</strong></div>
    </div>

    <div class="left-body">
      <div class="role-badge"><span></span> RPMS Registration</div>
      <h2>Create your market account</h2>
      <p>Choose the account type that matches your role. Every new account is reviewed by an administrator.</p>
    </div>

    <div class="left-perks">
      <div class="perk-item">
        <div class="perk-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
        </div>
        <span>Access the POS payment interface</span>
      </div>
      <div class="perk-item">
        <div class="perk-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </div>
        <span>Issue digital & printable receipts</span>
      </div>
      <div class="perk-item">
        <div class="perk-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
        </div>
        <span>View your daily collection summary</span>
      </div>
      <div class="perk-item">
        <div class="perk-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        </div>
        <span>Secure role-based account access</span>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <a href="../index.php" class="home-btn">← Home</a>

    <div class="auth-form-wrap">
      <p class="eyebrow">San Jose Public Market</p>
      <h1>Create your account</h1>
      <p class="subtitle">Select Collector or Vendor to begin.</p>

      <?php if ($error): ?>
        <div class="alert-box alert-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert-box alert-success" style="flex-direction:column;align-items:flex-start;gap:4px">
          <span style="display:flex;align-items:center;gap:8px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
            <?= htmlspecialchars($success) ?>
          </span>
          <a href="login.php" style="color:var(--green);font-weight:700;font-size:.82rem">← Back to login</a>
        </div>
      <?php endif; ?>

      <form method="post" id="registerForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['registration_csrf']) ?>">

        <div class="field">
          <label for="account_type">Account Type</label>
          <select id="account_type" name="account_type" required style="width:100%;padding:11px 13px;border:1.5px solid #dfe6e0;border-radius:9px;font:inherit;color:var(--ink);background:var(--white)">
            <option value="">Select account type...</option>
            <option value="collector" <?= (($_POST['account_type'] ?? '') === 'collector') ? 'selected' : '' ?>>Collector</option>
            <option value="vendor" <?= (($_POST['account_type'] ?? '') === 'vendor') ? 'selected' : '' ?>>Vendor</option>
          </select>
        </div>

        <!-- Name Row -->
        <div class="name-row">
          <div class="field">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name"
              value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
              placeholder="Maria" required>
          </div>
          <div class="field">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name"
              value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
              placeholder="Santos" required>
          </div>
        </div>

        <!-- Email -->
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            placeholder="you@example.com" required autocomplete="email">
        </div>

        <div id="vendorFields" style="display:none">
          <div class="field">
            <label for="vendor_name">Vendor Name</label>
            <input type="text" id="vendor_name" name="vendor_name" value="<?= htmlspecialchars($_POST['vendor_name'] ?? '') ?>" placeholder="Registered business or vendor name">
          </div>
          <div class="name-row">
            <div class="field">
              <label for="stall_number">Stall Number</label>
              <input type="text" id="stall_number" name="stall_number" value="<?= htmlspecialchars($_POST['stall_number'] ?? '') ?>" placeholder="V-01">
            </div>
            <div class="field">
              <label for="section_id">Section</label>
              <select id="section_id" name="section_id" style="width:100%;padding:11px 13px;border:1.5px solid #dfe6e0;border-radius:9px;font:inherit;color:var(--ink);background:var(--white)">
                <option value="">Select section...</option>
                <?php foreach ($sections as $section): ?>
                  <option value="<?= $section['id'] ?>" <?= ((int)($_POST['section_id'] ?? 0) === (int)$section['id']) ? 'selected' : '' ?>><?= htmlspecialchars($section['section_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="contact">Contact Information</label>
            <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" placeholder="Phone number or other contact detail">
          </div>
          <div class="field">
            <label for="documents">Required Vendor Documents</label>
            <input type="file" id="documents" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%;padding:10px;border:1.5px solid #dfe6e0;border-radius:9px;background:var(--white)">
            <small style="display:block;margin-top:5px;color:var(--ink-3)">Upload at least one PDF, JPG, PNG, DOC, or DOCX file. Maximum 10MB each.</small>
          </div>
        </div>

        <!-- Password -->
        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password"
              placeholder="At least 8 characters"
              required oninput="checkStrength(this.value)">
            <button type="button" class="eye-btn" onclick="togglePw('password', 'eye1')" aria-label="Toggle password">
              <svg id="eye1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </button>
          </div>
          <div class="strength-bar-wrap">
            <div class="strength-seg" id="s1"></div>
            <div class="strength-seg" id="s2"></div>
            <div class="strength-seg" id="s3"></div>
            <div class="strength-seg" id="s4"></div>
          </div>
          <p class="strength-label" id="strengthLabel"></p>
        </div>

        <!-- Confirm Password -->
        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <div class="input-wrap">
            <input type="password" id="confirm_password" name="confirm_password"
              placeholder="Repeat your password" required>
            <button type="button" class="eye-btn" onclick="togglePw('confirm_password', 'eye2')" aria-label="Toggle password">
              <svg id="eye2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="regBtn">
          <span id="btnText">Create Account</span>
          <div class="spinner" id="btnSpinner"></div>
        </button>

      </form>

      <p class="auth-divider">
        Already have an account? <a href="login.php">Sign in</a>
      </p>
    </div>
  </div>

</div>

<script>
const eyeOpenPath = '<path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>';
const eyeOffPath = '<path d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>';

function togglePw(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  if (input.type === 'password') { input.type = 'text'; icon.innerHTML = eyeOffPath; }
  else { input.type = 'password'; icon.innerHTML = eyeOpenPath; }
}

function checkStrength(val) {
  let score = 0;
  if (val.length >= 8)  score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const colors = ['', '#c0374b', '#e2a33d', '#3f8b64', '#2d6a4f'];
  const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
  const segs = ['s1','s2','s3','s4'];

  segs.forEach((id, i) => {
    document.getElementById(id).style.background = i < score ? colors[score] : '#dfe6e0';
  });
  document.getElementById('strengthLabel').textContent = val.length ? labels[score] : '';
  document.getElementById('strengthLabel').style.color = colors[score] || 'var(--ink-3)';
}

function updateAccountFields() {
  const isVendor = document.getElementById('account_type').value === 'vendor';
  document.getElementById('vendorFields').style.display = isVendor ? 'block' : 'none';
  ['vendor_name', 'stall_number', 'section_id', 'contact', 'documents'].forEach((id) => {
    document.getElementById(id).required = isVendor;
  });
}

document.getElementById('account_type').addEventListener('change', updateAccountFields);
updateAccountFields();

document.getElementById('registerForm').addEventListener('submit', function(e) {
  const pw  = document.getElementById('password').value;
  const cpw = document.getElementById('confirm_password').value;
  if (pw !== cpw) {
    e.preventDefault();
    alert('Passwords do not match.');
    return;
  }
  document.getElementById('btnText').textContent = 'Creating account…';
  document.getElementById('btnSpinner').style.display = 'block';
  document.getElementById('regBtn').disabled = true;
});
</script>
</body>
</html>