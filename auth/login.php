<?php
require_once '../config/database.php';
require_once __DIR__.'/../includes/security.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

require_once '../includes/security.php';
require_once '../includes/login_security.php';
ob_start('secureHtml');
$error = "";
$rememberEmail = $_COOKIE['remember_email'] ?? "";
$roleFromUrl = $_GET['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $attemptKeys = loginAttemptKeys($email, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $wait = loginCooldown($pdo, $attemptKeys);
    if ($wait > 0) { $error = "Too many attempts. Try again in $wait seconds."; goto render_form; }
    $password = (string)($_POST['password'] ?? '');
    $roleSelected = (string)($_POST['role'] ?? '');

    if (isset($_POST['remember'])) {
        setcookie("remember_email", $email, time() + 86400*30, "/");
    } else {
        setcookie("remember_email", "", time() - 3600, "/");
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password']) && $user['role'] === $roleSelected) {
        // Check account status
        if ($user['status'] === 'pending') {
            $error = "Your account is pending admin approval. Please wait for the administrator to approve your registration.";
            goto render_form;
        }

        if ($user['status'] === 'inactive') {
            $error = "Your account has been deactivated. Please contact the administrator.";
            goto render_form;
        }

        if ($user['status'] !== 'active') {
            $error = "Your account is not active. Please contact the administrator.";
            goto render_form;
        }

        if ($user['role'] !== 'admin' && $user['role'] !== $roleSelected) {
            $error = "Invalid email, password, or role.";
            goto render_form;
        }

        foreach ($attemptKeys as $key) $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?')->execute([$key]);
        session_regenerate_id(true);
        $_SESSION['otp_verified'] = !$user['two_factor_enabled'];
        $_SESSION['auth_version'] = (int)$user['auth_version'];
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'] ?? '';

        if ($user['role'] === 'admin' && $user['two_factor_enabled']) {
            $otp = random_int(100000, 999999);
            $_SESSION['otp_sent_at']=time();
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $stmt = $pdo->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
            $stmt->execute([$otp, $expires, $user['id']]);

            require_once '../includes/mailer.php';
            $sent=sendRpmsMail($user['email'],trim($user['first_name'].' '.$user['last_name']),'Your RPMS Admin OTP','<p>Your verification code is <strong>'.$otp.'</strong>. It expires in five minutes.</p>');
            if (!$sent['success']) { $error='The verification email could not be sent. Please try again or contact the administrator.'; goto render_form; }
            header("Location: otp_verify.php");
            exit;
        }

        switch ($user['role']) {
            case 'collector': header("Location: ../collector/dashboard.php"); exit;
            case 'vendor':    header("Location: ../vendor/dashboard.php");    exit;
            case 'admin':     header("Location: ../admin/dashboard.php");     exit;
        }
    } else {
        recordLoginFailure($pdo, $attemptKeys);
        $error = loginCooldown($pdo, $attemptKeys) > 0 ? "Too many attempts. Wait 15 seconds before trying again." : "Invalid email, password, or role.";
    }
}

render_form:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | RPMS</title>
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
  position: absolute; top: -140px; right: -140px;
  width: 420px; height: 420px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(226,163,61,.28), transparent 65%);
  pointer-events: none;
  z-index: 1;
}
.auth-left::after {
  content: "";
  position: absolute; bottom: -80px; left: -80px;
  width: 280px; height: 280px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(143,207,168,.16), transparent 65%);
  pointer-events: none;
  z-index: 1;
}

/* BG image layer */
.left-bg {
  position: absolute; inset: 0;
  background: url('../assets/images/market-bg.png') center/cover no-repeat;
}
.left-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(120deg, rgba(20,45,33,.94) 0%, rgba(22,53,39,.75) 45%, rgba(22,53,39,.35) 100%);
}

.left-top { position: relative; z-index: 2; }
.left-top .logo-card {
  display: inline-flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,.94);
  border-radius: 14px;
  padding: 10px 18px;
  box-shadow: 0 6px 20px rgba(0,0,0,.15);
}
.left-top img { height: 40px; display: block; }

.left-body {
  position: relative; z-index: 2;
  color: #fff;
}
.left-body h2 {
  font-weight: 600;
  font-size: 2.3rem;
  line-height: 1.2;
  margin-bottom: 14px;
}
.left-body h2 em { font-style: italic; font-weight: 500; color: var(--gold); }
.left-body p { font-size: .95rem; color: rgba(255,255,255,.85); line-height: 1.7; max-width: 340px; }

.left-stats {
  position: relative; z-index: 2;
  display: flex; gap: 28px;
}
.ls-num {
  font-family: 'Fraunces', serif; font-weight: 600;
  font-size: 1.5rem;
  color: var(--gold); line-height: 1;
}
.ls-label { font-size: .75rem; color: rgba(255,255,255,.65); margin-top: 4px; }

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
}

.home-btn {
  position: absolute; top: 24px; right: 24px;
  display: inline-flex; align-items: center; gap: 6px;
  font-size: .82rem; font-weight: 600;
  color: var(--ink-3);
  text-decoration: none;
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--cream);
  transition: .2s;
}
.home-btn:hover { background: var(--leaf-pale); color: var(--green); border-color: var(--green); }

.auth-form-wrap { width: 100%; max-width: 380px; }

.auth-form-wrap .eyebrow {
  font-size: .8rem; font-weight: 600;
  color: var(--gold-dark); margin-bottom: 8px;
}
.auth-form-wrap h1 {
  font-weight: 600;
  font-size: 1.95rem;
  color: var(--forest); margin-bottom: 6px;
}
.auth-form-wrap .subtitle {
  font-size: .9rem; color: var(--ink-3); margin-bottom: 34px;
}

/* FORM FIELDS */
.field { margin-bottom: 20px; }
.field label {
  display: block;
  font-size: .82rem; font-weight: 600; color: var(--ink-2);
  margin-bottom: 6px;
}
.field input,
.field select {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid #dfe6e0;
  border-radius: 10px;
  font-family: inherit; font-size: .92rem;
  color: var(--ink);
  background: var(--white);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  appearance: none;
}
.field input:focus,
.field select:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 4px rgba(45,106,79,.14);
}
.field input::placeholder { color: #a9bbb0; }

/* password toggle */
.input-wrap { position: relative; }
.input-wrap input { padding-right: 44px; }
.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--ink-3); font-size: 1rem; padding: 4px;
  transition: color .2s;
}
.eye-btn:hover { color: var(--green); }

/* ROLE PILLS */
.role-pills {
  display: flex; gap: 8px; margin-bottom: 20px;
}
.role-pill {
  flex: 1;
  padding: 8px 4px;
  border: 1.5px solid #dfe6e0;
  border-radius: 10px;
  background: var(--white);
  font-family: inherit; font-size: .8rem; font-weight: 600;
  color: var(--ink-3);
  cursor: pointer;
  text-align: center;
  transition: .2s;
}
.role-pill:hover { border-color: var(--green); color: var(--green); background: var(--leaf-pale); }
.role-pill.active { border-color: var(--green); background: var(--leaf-pale); color: var(--forest); }
.role-pill { display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
.role-icon { width: 15px; height: 15px; flex-shrink: 0; }
/* hidden actual select */
#roleInput { display: none; }

/* CHECKBOX */
.check-row {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 24px;
}
.check-label {
  display: flex; align-items: center; gap: 8px;
  font-size: .83rem; color: var(--ink-3); cursor: pointer;
}
.check-label input[type="checkbox"] {
  width: 16px; height: 16px;
  accent-color: var(--green);
  cursor: pointer;
}

/* SUBMIT BTN */
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

/* ERROR */
.error-box {
  background: var(--error-bg);
  border: 1px solid #f3c3cb;
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 20px;
  color: var(--error);
  font-size: .87rem;
  display: flex; align-items: center; gap: 8px;
  animation: shake .35s ease;
}
.icon-inline { display: inline-flex; flex-shrink: 0; }
@keyframes shake {
  0%,100%{transform:translateX(0)}
  25%{transform:translateX(-6px)}
  75%{transform:translateX(6px)}
}

/* DIVIDER */
.auth-divider {
  text-align: center; font-size: .82rem; color: var(--ink-3); margin-top: 22px;
}
.auth-divider a { color: var(--green); font-weight: 600; text-decoration: none; }
.auth-divider a:hover { text-decoration: underline; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 768px) {
  .auth-page { grid-template-columns: 1fr; }
  .auth-left { display: none; }
  .auth-right { padding: 40px 24px; }
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
      <h2>Welcome to<br><em>RPMS</em></h2>
      <p>The Rental Payment Management System for San Jose Public Market. Manage collections, stalls, and reports from one place.</p>
    </div>

    <div class="left-stats">
      <div class="ls-item">
        <div class="ls-num">100%</div>
        <div class="ls-label">Digital Records</div>
      </div>
      <div class="ls-item">
        <div class="ls-num">24/7</div>
        <div class="ls-label">Availability</div>
      </div>
      <div class="ls-item">
        <div class="ls-num">Fast</div>
        <div class="ls-label">POS Collection</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <a href="../index.php" class="home-btn">← Home</a>

    <div class="auth-form-wrap">
      <p class="eyebrow">Rental Payment System</p>
      <h1>Welcome back</h1>
      <p class="subtitle">Sign in to your account to continue.</p>

      <?php if ($error): ?>
        <div class="error-box">
          <span class="icon-inline"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></span> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" id="loginForm">

        <!-- Role Selector -->
        <div class="field">
          <label>Login as</label>
          <div class="role-pills">
            <button type="button" class="role-pill <?= ($roleFromUrl === 'admin' || $roleFromUrl === '') ? 'active' : '' ?>"
              onclick="setRole('admin', this)"><svg class="role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7.5 3v5.25c0 4.556-3.203 8.815-7.5 9.75-4.297-.935-7.5-5.194-7.5-9.75V6l7.5-3z"/></svg> Admin</button>
            <button type="button" class="role-pill <?= $roleFromUrl === 'collector' ? 'active' : '' ?>"
              onclick="setRole('collector', this)"><svg class="role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg> Collector</button>
            <button type="button" class="role-pill <?= $roleFromUrl === 'vendor' ? 'active' : '' ?>"
              onclick="setRole('vendor', this)"><svg class="role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 21h19.5m-18-18v18m16.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125V21"/></svg> Vendor</button>
          </div>
          <select name="role" id="roleInput">
            <option value="admin"     <?= ($roleFromUrl === 'admin' || $roleFromUrl === '') ? 'selected' : '' ?>>Admin</option>
            <option value="collector" <?= $roleFromUrl === 'collector' ? 'selected' : '' ?>>Collector</option>
            <option value="vendor"    <?= $roleFromUrl === 'vendor'    ? 'selected' : '' ?>>Vendor</option>
          </select>
        </div>

        <!-- Email -->
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
            value="<?= htmlspecialchars($rememberEmail) ?>"
            placeholder="you@example.com" required autocomplete="email">
        </div>

        <!-- Password -->
        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password"
              placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="eye-btn" onclick="togglePassword()" aria-label="Toggle password">
              <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Remember me -->
        <div class="check-row">
          <label class="check-label">
            <input type="checkbox" name="remember" <?= $rememberEmail ? 'checked' : '' ?>>
            Remember me
          </label>
        </div>

        <button type="submit" class="btn-submit" id="loginBtn">
          <span id="btnText">Sign In</span>
          <div class="spinner" id="btnSpinner"></div>
        </button>

      </form>

      <p><a href="forgot_password.php">Forgot password?</a></p>
      <p class="auth-divider">
        Collector or vendor? <a href="register.php">Create an account</a>
      </p>
    </div>
  </div>

</div>

<script>
// Role pill toggle
function setRole(role, el) {
  document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('roleInput').value = role;
}

// Auto-activate pill from URL
(function() {
  const role = '<?= htmlspecialchars($roleFromUrl) ?>';
  if (role) {
    const map = { admin: 0, collector: 1, vendor: 2 };
    const pills = document.querySelectorAll('.role-pill');
    if (pills[map[role]]) {
      document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
      pills[map[role]].classList.add('active');
    }
  }
})();

// Password toggle
const eyeOpenPath = '<path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>';
const eyeOffPath = '<path d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>';
function togglePassword() {
  const p = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (p.type === 'password') { p.type = 'text'; icon.innerHTML = eyeOffPath; }
  else { p.type = 'password'; icon.innerHTML = eyeOpenPath; }
}

// Loading state
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  document.getElementById('btnText').textContent = 'Signing in…';
  document.getElementById('btnSpinner').style.display = 'block';
  btn.disabled = true;
});
</script>
</body>
</html>