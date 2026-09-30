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
if (!empty($_SESSION['otp_verified'])) {
    $_SESSION['otp_verified']=true; header('Location: ../admin/dashboard.php'); exit;
}
$error=''; $info='';
$attemptKeys=[hash('sha256','otp:'.$user['id'])];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $wait=loginCooldown($pdo,$attemptKeys);
    if ($wait>0) $error="Too many attempts. Try again in $wait seconds.";
    elseif(isset($_POST['resend'])) {
        if(time()-(int)($_SESSION['otp_sent_at']??0)<60) $error='Wait one minute before requesting another code.';
        else {
            $otp=(string)random_int(100000,999999);
            // Expiry is computed in PHP (same clock as the check below and as login.php)
            $pdo->prepare('UPDATE users SET otp_code=?,otp_expires=? WHERE id=?')->execute([$otp,date('Y-m-d H:i:s',time()+300),$user['id']]);
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
// Values for the countdown: seconds left on the code, seconds until resend is allowed
$q->execute([$user['id']]); $fresh=$q->fetch(PDO::FETCH_ASSOC);
$remaining=max(0,(int)(strtotime((string)($fresh['otp_expires']??''))-time()));
$resendIn=max(0,60-(time()-(int)($_SESSION['otp_sent_at']??0)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Code | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#2E7D32; --green-dark:#1B5E20; --green-soft:#E8F3E9;
  --orange:#F57C00; --orange-dark:#E65100;
  --cream:#FFF8E7; --cream-line:#EADFC2; --white:#FFFFFF;
  --ink:#16261A; --ink-2:#44584A; --ink-3:#71857A;
  --error:#B3261E; --error-bg:#FDECEA;
}
html,body{height:100%;font-family:'Inter',sans-serif;background:var(--cream);color:var(--ink)}
h1{font-family:'Fraunces',serif}
:focus-visible{outline:3px solid var(--orange);outline-offset:2px}

.auth-page{min-height:100vh;display:grid;grid-template-columns:1fr 1fr}

/* LEFT */
.auth-left{position:relative;background:var(--green-dark);display:flex;flex-direction:column;justify-content:space-between;padding:48px;overflow:hidden}
.left-bg{position:absolute;inset:0;background:url('../assets/images/market-bg.png') center/cover no-repeat}
.left-overlay{position:absolute;inset:0;background:linear-gradient(120deg,rgba(27,94,32,.94) 0%,rgba(46,125,50,.76) 48%,rgba(46,125,50,.36) 100%)}
.left-top,.left-body,.steps{position:relative;z-index:2}
.left-body{color:var(--white)}
.left-body h2{font-family:'Fraunces',serif;font-weight:600;font-size:2.3rem;line-height:1.2;margin-bottom:14px}
.left-body p{font-size:.95rem;line-height:1.7;color:rgba(255,255,255,.88);max-width:340px}
.steps{list-style:none}
.step{display:flex;align-items:center;gap:14px;padding:13px 0;border-top:1px solid rgba(255,255,255,.2);font-size:.88rem;color:rgba(255,255,255,.7)}
.step:last-child{border-bottom:1px solid rgba(255,255,255,.2)}
.step-num{width:28px;height:28px;flex-shrink:0;border-radius:50%;border:1px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:700}
.step.done .step-num{background:rgba(255,255,255,.18)}
.step.active{color:var(--white);font-weight:600}
.step.active .step-num{background:var(--orange);border-color:var(--orange);color:var(--white)}

/* RIGHT */
.auth-right{position:relative;display:flex;align-items:center;justify-content:center;padding:48px 40px;background:var(--white)}
.back-btn{position:absolute;top:24px;right:24px;display:inline-flex;align-items:center;gap:6px;font-size:.82rem;font-weight:600;color:var(--ink-2);text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--cream-line);background:var(--cream);transition:.2s}
.back-btn:hover{background:var(--green-soft);color:var(--green-dark);border-color:var(--green)}
.otp-wrap{width:100%;max-width:380px}
.shield{width:60px;height:60px;border-radius:16px;background:var(--cream);border:1px solid var(--cream-line);color:var(--green);display:flex;align-items:center;justify-content:center;margin-bottom:22px}
.shield svg{width:28px;height:28px}
.otp-wrap h1{font-weight:600;font-size:1.95rem;color:var(--green-dark);margin-bottom:8px}
.subtitle{font-size:.9rem;line-height:1.6;color:var(--ink-3);margin-bottom:28px}
.subtitle strong{color:var(--ink-2)}

.alert-box{border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:.87rem;display:flex;align-items:center;gap:8px}
.alert-box svg{width:16px;height:16px;flex-shrink:0}
.alert-error{background:var(--error-bg);border:1px solid #F3C3BE;color:var(--error)}
.alert-info{background:var(--cream);border:1px solid var(--cream-line);color:var(--green-dark)}

.otp-boxes{display:flex;gap:10px;margin-bottom:22px}
.otp-digit{flex:1;min-width:0;height:58px;border:1.5px solid var(--cream-line);border-radius:10px;font:700 1.5rem 'Inter',sans-serif;color:var(--ink);text-align:center;background:var(--white);outline:none;transition:border-color .2s,box-shadow .2s;caret-color:var(--orange)}
.otp-digit:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(46,125,50,.16)}
.otp-digit.filled{border-color:var(--green);background:var(--cream)}
.otp-digit:disabled{opacity:.5}

.otp-timer{display:inline-block;font-size:.82rem;color:var(--ink-2);background:var(--cream);border:1px solid var(--cream-line);border-radius:999px;padding:5px 14px;margin-bottom:22px}
.timer-count{font-weight:700;color:var(--orange-dark)}
.timer-count.expired{color:var(--error)}

.btn-submit{width:100%;padding:13px;background:var(--orange);color:var(--white);border:none;border-radius:10px;font:700 .95rem 'Inter',sans-serif;cursor:pointer;transition:.25s ease;margin-bottom:16px}
.btn-submit:hover:not(:disabled){background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 8px 22px rgba(245,124,0,.25)}
.btn-submit:disabled{opacity:.55;cursor:not-allowed}
.resend-form{text-align:center}
.resend-form button{background:none;border:none;font:.87rem 'Inter',sans-serif;color:var(--ink-3);cursor:pointer;padding:4px}
.resend-form button strong{color:var(--green)}
.resend-form button:hover:not(:disabled) strong{text-decoration:underline}
.resend-form button:disabled{opacity:.6;cursor:not-allowed}

@media (max-width:768px){
  .auth-page{grid-template-columns:1fr}
  .auth-left{display:none}
  .auth-right{padding:72px 24px 40px}
  .otp-digit{height:52px;font-size:1.3rem}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
<div class="auth-page">

  <div class="auth-left">
    <div class="left-bg"></div>
    <div class="left-overlay"></div>
    <div class="left-top">
      <div class="market-brand"><span class="market-logo" role="img" aria-label="San Jose Public Market"></span><strong>RPMS</strong></div>
    </div>
    <div class="left-body">
      <h2>Confirm it's you</h2>
      <p>Admin accounts use a one-time code sent to the registered email before opening the dashboard.</p>
    </div>
    <ol class="steps">
      <li class="step done"><span class="step-num">1</span>Enter credentials</li>
      <li class="step active"><span class="step-num">2</span>Enter the code from your email</li>
      <li class="step"><span class="step-num">3</span>Open the dashboard</li>
    </ol>
  </div>

  <div class="auth-right">
    <a href="login.php?role=admin" class="back-btn">← Back to login</a>

    <div class="otp-wrap">
      <div class="shield" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7.5 3v5.25c0 4.556-3.203 8.815-7.5 9.75-4.297-.935-7.5-5.194-7.5-9.75V6l7.5-3z"/></svg>
      </div>
      <h1>Check your email</h1>
      <p class="subtitle">We sent a <strong>6-digit code</strong> to the admin email address. Enter it below to sign in.</p>

      <?php if ($info): ?>
        <div class="alert-box alert-info" role="status">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
          <?= htmlspecialchars($info) ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert-box alert-error" role="alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" id="otpForm">
        <div class="otp-boxes">
          <?php for ($n = 1; $n <= 6; $n++): ?>
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" aria-label="Digit <?= $n ?> of 6"<?= $n === 1 ? ' autocomplete="one-time-code"' : '' ?>>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="otp" id="otpHidden">

        <p class="otp-timer">Code expires in <span class="timer-count" id="timerCount"><?= intdiv($remaining, 60) . ':' . str_pad((string)($remaining % 60), 2, '0', STR_PAD_LEFT) ?></span></p>

        <button type="submit" class="btn-submit" id="verifyBtn" disabled><span id="btnText">Verify and sign in</span></button>
      </form>

      <form method="post" class="resend-form">
        <button type="submit" name="resend" id="resendBtn" disabled>Didn't get it? <strong>Send a new code</strong><span id="resendNote"></span></button>
      </form>
    </div>
  </div>
</div>

<script>
const digits = [...document.querySelectorAll('.otp-digit')];
const hidden = document.getElementById('otpHidden');
const verifyBtn = document.getElementById('verifyBtn');
const timerEl = document.getElementById('timerCount');
const resendBtn = document.getElementById('resendBtn');
const resendNote = document.getElementById('resendNote');
let seconds = <?= (int)$remaining ?>;   // time left on the code (from the server)
let resendIn = <?= (int)$resendIn ?>;   // seconds until a new code can be requested

function sync() {
  const v = digits.map(d => d.value).join('');
  hidden.value = v;
  digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
  verifyBtn.disabled = v.length < 6 || seconds <= 0;
}

digits.forEach((el, i) => {
  el.addEventListener('input', () => {
    el.value = el.value.replace(/\D/g, '').slice(-1);
    sync();
    if (el.value && i < 5) digits[i + 1].focus();
  });
  el.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !el.value && i > 0) { digits[i - 1].value = ''; digits[i - 1].focus(); sync(); }
  });
  el.addEventListener('paste', e => {
    e.preventDefault();
    const t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
    [...t].forEach((c, j) => { digits[j].value = c; });
    sync();
    digits[Math.min(t.length, 5)].focus();
  });
});

function tick() {
  if (seconds > 0) {
    timerEl.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
  } else {
    timerEl.textContent = 'Expired';
    timerEl.classList.add('expired');
    digits.forEach(d => { d.disabled = true; });
  }
  resendBtn.disabled = resendIn > 0;
  resendNote.textContent = resendIn > 0 ? ' (' + resendIn + 's)' : '';
  sync();
}
tick();
setInterval(() => { if (seconds > 0) seconds--; if (resendIn > 0) resendIn--; tick(); }, 1000);

document.getElementById('otpForm').addEventListener('submit', () => {
  document.getElementById('btnText').textContent = 'Verifying…';
  verifyBtn.disabled = true;
});
if (seconds > 0) digits[0].focus();
</script>
</body>
</html>