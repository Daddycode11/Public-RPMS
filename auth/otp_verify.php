<?php
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/login_security.php';
require_once '../includes/mailer.php';
ob_start('secureHtml');
$q=$pdo->prepare("SELECT id,email,first_name,status,role,deleted_at,auth_version,two_factor_enabled,otp_code,otp_expires FROM users WHERE id=?");
$q->execute([$_SESSION['user_id']??0]); $user=$q->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role']!=='admin' || $user['status']!=='active' || $user['deleted_at'] || (int)$user['auth_version']!==(int)($_SESSION['auth_version']??1)) {
    header('Location: login.php?role=admin'); exit;
}
if (!$user['two_factor_enabled'] || !empty($_SESSION['otp_verified'])) {
    $_SESSION['otp_verified']=true; header('Location: ../admin/dashboard.php'); exit;
}
$error=''; $info=''; $otp_success=false;
$attemptKeys=[hash('sha256','otp:'.$user['id'])];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $wait=loginCooldown($pdo,$attemptKeys);
    if ($wait>0) $error="Too many attempts. Try again in $wait seconds.";
    elseif(isset($_POST['resend'])) {
        if(time()-(int)($_SESSION['otp_sent_at']??0)<60) $error='Wait one minute before requesting another code.';
        else {
            $otp=(string)random_int(100000,999999);
            $pdo->prepare('UPDATE users SET otp_code=?,otp_expires=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?')->execute([$otp,$user['id']]);
            $_SESSION['otp_sent_at']=time();
            $sent=sendRpmsMail($user['email'],$user['first_name']??'Admin','Your RPMS Admin OTP','<p>Your verification code is <strong>'.$otp.'</strong>. It expires in five minutes.</p>');
            $info=$sent['success']?'A new OTP has been sent to your email.':'The verification email could not be delivered. Please try again or contact support.';
        }
    } else {
        $input=trim((string)($_POST['otp']??''));
        if(empty($user['otp_code']) || empty($user['otp_expires']) || strtotime($user['otp_expires'])<=time()) $error='The code has expired. Request a new code.';
        elseif(preg_match('/^[0-9]{6}$/D',$input) && hash_equals((string)$user['otp_code'],$input)) {
            $pdo->prepare('UPDATE users SET otp_code=NULL,otp_expires=NULL WHERE id=?')->execute([$user['id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key=?')->execute([$attemptKeys[0]]);
            session_regenerate_id(true); $_SESSION['otp_verified']=true;
            header('Location: ../admin/dashboard.php'); exit;
        } else { recordLoginFailure($pdo,$attemptKeys); $error='Invalid verification code.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OTP Verification | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">

<style>
/* ============================================================
   ROOT & RESET
============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #ea580c;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.22);
  --ink:         #0d1f14;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f5f9f6;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.15);
  --error:       #dc2626;
  --error-bg:    #fef2f2;
}

html, body {
  min-height: 100%;
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--ink);
}

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
  background: var(--ink);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px;
  overflow: hidden;
}
.auth-left::before {
  content: "";
  position: absolute; top: -140px; right: -140px;
  width: 420px; height: 420px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,179,71,.3), transparent 65%);
  pointer-events: none;
  z-index: 1;
}
.auth-left::after {
  content: "";
  position: absolute; bottom: -80px; left: -80px;
  width: 280px; height: 280px; border-radius: 50%;
  background: radial-gradient(circle, rgba(234,88,12,.2), transparent 65%);
  pointer-events: none;
  z-index: 1;
}
.left-bg {
  position: absolute; inset: 0;
  background: url('../assets/images/market-bg.png') center/cover no-repeat;
  opacity: .75;
}
.left-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(234,88,12,.55) 0%, rgba(179,38,12,.5) 55%, rgba(43,13,5,.4) 100%);
}
.left-top { position: relative; z-index: 2; }
.left-top img { height: 52px; filter: brightness(0) invert(1); opacity: .9; }

.left-body { position: relative; z-index: 2; color: #fff; }
.left-body .badge-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22);
  color: rgba(255,255,255,.9); font-size: .75rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
  padding: 5px 14px; border-radius: 50px; margin-bottom: 20px;
}
.badge-pill .dot { width: 7px; height: 7px; background: #ffe4d1; border-radius: 50%; animation: blink 1.5s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.25} }
.left-body h2 {
  font-family: 'Inter', sans-serif;
  font-size: 2.3rem; font-weight: 700; line-height: 1.2; margin-bottom: 14px;
  text-shadow: 0 2px 16px rgba(0,0,0,.3);
}
.left-body h2 em { font-style: italic; color: #ffe4d1; }
.left-body p { font-size: .92rem; color: rgba(255,255,255,.88); line-height: 1.7; max-width: 320px; text-shadow: 0 1px 10px rgba(0,0,0,.25); }

/* Security steps */
.security-steps { position: relative; z-index: 2; }
.sec-step {
  display: flex; align-items: center; gap: 14px;
  padding: 13px 0; border-top: 1px solid rgba(255,255,255,.15);
}
.sec-step:last-child { border-bottom: 1px solid rgba(255,255,255,.15); }
.sec-num {
  width: 30px; height: 30px; flex-shrink: 0;
  border-radius: 50%;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.22);
  color: rgba(255,255,255,.65);
  font-size: .78rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.sec-num svg { width: 14px; height: 14px; }
.sec-step.active .sec-num {
  background: var(--brand); border-color: var(--brand);
  color: #fff; box-shadow: 0 0 0 4px rgba(234,88,12,.28);
}
.sec-text { font-size: .87rem; color: rgba(255,255,255,.65); }
.sec-step.active .sec-text { color: #fff; font-weight: 500; }

/* ============================================================
   RIGHT PANEL
============================================================ */
.auth-right {
  display: flex; align-items: center; justify-content: center;
  padding: 48px 40px;
  background: var(--white);
  position: relative;
}
.back-btn {
  position: absolute; top: 24px; left: 24px;
  display: inline-flex; align-items: center; gap: 6px;
  font-size: .82rem; font-weight: 600; color: var(--ink-3);
  text-decoration: none; padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--cream); transition: .2s;
}
.back-btn:hover { background: var(--brand-light); color: var(--brand-dark); border-color: var(--brand); }

.otp-wrap { width: 100%; max-width: 380px; text-align: center; }

/* Shield icon */
.shield-icon {
  width: 72px; height: 72px; margin: 0 auto 24px;
  background: var(--brand-light);
  border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  color: var(--brand-dark);
  position: relative;
}
.shield-icon svg { width: 32px; height: 32px; }
.shield-icon::after {
  content: "";
  position: absolute; inset: -6px;
  border-radius: 26px;
  border: 1.5px solid var(--border);
  animation: pulse-ring 2s ease infinite;
}
@keyframes pulse-ring {
  0%   { transform: scale(1);   opacity: .7; }
  50%  { transform: scale(1.05);opacity: .3; }
  100% { transform: scale(1);   opacity: .7; }
}

.otp-wrap .eyebrow {
  font-size: .72rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  color: var(--brand); margin-bottom: 8px;
}
.otp-wrap h1 {
  font-family: 'Inter', sans-serif;
  font-size: 2rem; font-weight: 700; color: var(--ink); margin-bottom: 8px;
}
.otp-wrap .subtitle {
  font-size: .9rem; color: var(--ink-3); line-height: 1.6; margin-bottom: 32px;
}
.otp-wrap .subtitle strong { color: var(--ink-2); }

/* ---- OTP DIGIT BOXES ---- */
.otp-boxes {
  display: flex; gap: 10px; justify-content: center;
  margin-bottom: 28px;
}
.otp-digit {
  width: 52px; height: 60px;
  border: 1.5px solid #e0e8e3;
  border-radius: 12px;
  font-family: 'Inter', sans-serif;
  font-size: 1.6rem; font-weight: 700;
  color: var(--ink);
  text-align: center;
  background: var(--white);
  outline: none;
  transition: border-color .2s, box-shadow .2s, transform .15s;
  caret-color: var(--brand);
}
.otp-digit:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 4px var(--brand-glow);
  transform: translateY(-2px);
}
.otp-digit.filled { border-color: var(--brand); background: var(--brand-light); }

/* Hidden real input */
#otpHidden { display: none; }

/* TIMER */
.otp-timer {
  font-size: .82rem; color: var(--ink-3); margin-bottom: 24px;
}
.otp-timer .timer-count { font-weight: 700; color: var(--brand); }
.otp-timer .timer-count.expired { color: var(--error); }

/* ALERTS */
.alert-box {
  border-radius: 10px; padding: 11px 16px; margin-bottom: 20px;
  font-size: .87rem; display: flex; align-items: center; gap: 8px;
  text-align: left;
}
.alert-box svg { width: 16px; height: 16px; flex-shrink: 0; }
.alert-error   { background: var(--error-bg); border: 1px solid #fecaca; color: var(--error); animation: shake .35s ease; }
.alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
.alert-success { background: var(--brand-light); border: 1px solid #fdba8c; color: var(--brand-dark); }
@keyframes shake {
  0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)}
}

/* SUBMIT */
.btn-submit {
  width: 100%; padding: 13px;
  background: var(--brand); color: #fff;
  border: none; border-radius: 10px;
  font-family: inherit; font-size: .95rem; font-weight: 700;
  cursor: pointer; transition: .25s ease;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  margin-bottom: 14px;
}
.btn-submit:hover:not(:disabled) { background: var(--brand-dark); transform: translateY(-1px); box-shadow: 0 6px 22px var(--brand-glow); }
.btn-submit:disabled { opacity: .6; cursor: not-allowed; }

.spinner {
  width: 17px; height: 17px;
  border: 2.5px solid rgba(255,255,255,.35); border-top-color: #fff;
  border-radius: 50%; animation: spin .65s linear infinite; display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* RESEND */
.resend-form button {
  background: none; border: none; cursor: pointer;
  font-family: inherit; font-size: .87rem; color: var(--ink-3);
  transition: color .2s; padding: 0;
}
.resend-form button:hover:not(:disabled) { color: var(--brand); text-decoration: underline; }
.resend-form button:disabled { opacity: .5; cursor: not-allowed; }

/* SUCCESS OVERLAY */
.success-overlay {
  position: fixed; inset: 0;
  background: rgba(13,31,20,.7);
  backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999;
  opacity: 0; visibility: hidden;
  transition: .4s ease;
}
.success-overlay.show { opacity: 1; visibility: visible; }
.success-card {
  background: var(--white); border-radius: 24px;
  padding: 48px 40px; text-align: center;
  max-width: 360px; width: 90%;
  animation: popIn .4s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes popIn { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
.success-ring {
  width: 80px; height: 80px; border-radius: 50%;
  background: var(--brand-light);
  border: 3px solid var(--brand);
  color: var(--brand-dark);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
  animation: successPop .5s .2s cubic-bezier(.34,1.56,.64,1) both;
}
.success-ring svg { width: 36px; height: 36px; }
@keyframes successPop { from{transform:scale(0)} to{transform:scale(1)} }
.success-card h2 {
  font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700;
  color: var(--ink); margin-bottom: 8px;
}
.success-card p { font-size: .9rem; color: var(--ink-3); line-height: 1.6; margin-bottom: 24px; }
.progress-bar-wrap {
  background: var(--brand-light); border-radius: 4px; height: 5px; overflow: hidden;
}
.progress-bar-fill {
  height: 100%; background: var(--brand); border-radius: 4px;
  animation: fillBar 2s linear forwards;
}
@keyframes fillBar { from{width:0} to{width:100%} }

/* RESPONSIVE */
@media (max-width: 768px) {
  .auth-page { grid-template-columns: 1fr; }
  .auth-left  { display: none; }
  .auth-right { padding: 40px 24px; }
  .otp-digit  { width: 44px; height: 54px; font-size: 1.4rem; }
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
      <img src="../assets/images/logo.png" alt="RPMS Logo">
    </div>
    <div class="left-body">
      <div class="badge-pill"><div class="dot"></div> Two-Factor Auth</div>
      <h2>Secure <em>admin</em><br>access</h2>
      <p>An OTP has been sent to the registered admin email to verify your identity before accessing the dashboard.</p>
    </div>
    <div class="security-steps">
      <div class="sec-step">
        <div class="sec-num"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg></div>
        <div class="sec-text">Enter credentials</div>
      </div>
      <div class="sec-step active">
        <div class="sec-num">2</div>
        <div class="sec-text">Verify OTP code</div>
      </div>
      <div class="sec-step">
        <div class="sec-num">3</div>
        <div class="sec-text">Access dashboard</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <a href="login.php?role=admin" class="back-btn">← Back to Login</a>

    <div class="otp-wrap">
      <div class="shield-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7.5 3v5.25c0 4.556-3.203 8.815-7.5 9.75-4.297-.935-7.5-5.194-7.5-9.75V6l7.5-3z"/></svg>
      </div>
      <p class="eyebrow">Admin Verification</p>
      <h1>Check your email</h1>
      <p class="subtitle">We sent a <strong>6-digit OTP</strong> to the admin email address. Enter it below to continue.</p>

      <?php if ($info): ?>
        <div class="alert-box alert-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
          <?= htmlspecialchars($info) ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert-box alert-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" id="otpForm">
        <!-- Visual digit boxes -->
        <div class="otp-boxes" id="otpBoxes">
          <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
          <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        </div>
        <!-- Hidden real input -->
        <input type="hidden" name="otp" id="otpHidden">

        <!-- Timer -->
        <p class="otp-timer">
          Code expires in <span class="timer-count" id="timerCount">5:00</span>
        </p>

        <button type="submit" class="btn-submit" id="verifyBtn">
          <span id="btnText">Verify OTP</span>
          <div class="spinner" id="btnSpinner"></div>
        </button>
      </form>

      <form method="post" class="resend-form" id="resendForm">
        <button type="submit" name="resend" id="resendBtn" disabled>
          Didn't receive it? <strong>Resend OTP</strong>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- SUCCESS OVERLAY -->
<div class="success-overlay" id="successOverlay">
  <div class="success-card">
    <div class="success-ring">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
    </div>
    <h2>Verified!</h2>
    <p>Identity confirmed. Redirecting you to the admin dashboard now…</p>
    <div class="progress-bar-wrap">
      <div class="progress-bar-fill"></div>
    </div>
  </div>
</div>

<?php if ($otp_success): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('successOverlay').classList.add('show');
  setTimeout(() => window.location.href = '../admin/dashboard.php', 2200);
});
</script>
<?php endif; ?>

<script>
/* ---- OTP Digit Box Logic ---- */
const digits   = Array.from(document.querySelectorAll('.otp-digit'));
const hidden   = document.getElementById('otpHidden');
const verifyBtn = document.getElementById('verifyBtn');

function syncHidden() {
  const val = digits.map(d => d.value).join('');
  hidden.value = val;
  digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
  verifyBtn.disabled = val.length < 6;
}

digits.forEach((el, i) => {
  el.addEventListener('input', e => {
    // Allow only digits
    el.value = el.value.replace(/\D/g, '').slice(-1);
    syncHidden();
    if (el.value && i < digits.length - 1) digits[i + 1].focus();
  });

  el.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !el.value && i > 0) {
      digits[i - 1].value = '';
      digits[i - 1].focus();
      syncHidden();
    }
  });

  el.addEventListener('paste', e => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
    text.split('').slice(0, 6).forEach((ch, j) => {
      if (digits[j]) digits[j].value = ch;
    });
    syncHidden();
    const next = Math.min(text.length, 5);
    digits[next].focus();
  });
});

// Disable verify until filled
verifyBtn.disabled = true;

/* ---- Countdown Timer ---- */
let seconds = 300; // 5 min
const timerEl  = document.getElementById('timerCount');
const resendBtn = document.getElementById('resendBtn');

const countdown = setInterval(() => {
  seconds--;
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  timerEl.textContent = `${m}:${s.toString().padStart(2,'0')}`;
  if (seconds <= 0) {
    clearInterval(countdown);
    timerEl.textContent = 'Expired';
    timerEl.classList.add('expired');
    resendBtn.disabled = false;
    verifyBtn.disabled = true;
    // Grey out boxes
    digits.forEach(d => { d.disabled = true; d.style.opacity = '.5'; });
  }
}, 1000);

// Enable resend after 30s
setTimeout(() => { if (seconds > 0) resendBtn.disabled = false; }, 30000);

/* ---- Submit loading state ---- */
document.getElementById('otpForm').addEventListener('submit', () => {
  document.getElementById('btnText').textContent = 'Verifying…';
  document.getElementById('btnSpinner').style.display = 'block';
  verifyBtn.disabled = true;
});

/* ---- Auto-focus first box ---- */
digits[0].focus();
</script>
</body>
</html>