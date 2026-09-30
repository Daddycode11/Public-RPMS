<?php
require_once '../config/database.php';
require_once '../includes/security.php';
ob_start('secureHtml');
$token=(string)($_POST['token']??$_GET['token']??''); $message=''; $done=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $password=(string)($_POST['password']??'');
    if (strlen($password)<8 || $password!==($_POST['confirm_password']??'')) $message='Use at least 8 characters and matching passwords.';
    else {
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT r.id,r.user_id FROM password_resets r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() AND u.deleted_at IS NULL FOR UPDATE');
        $q->execute([hash('sha256',$token)]); $reset=$q->fetch(PDO::FETCH_ASSOC);
        if ($reset) {
            $pdo->prepare('UPDATE users SET password=?,auth_version=auth_version+1,otp_code=NULL,otp_expires=NULL WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$reset['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$reset['user_id']]);
            $done=true; $message='Password changed. Sign in using your new password.';
        } else $message='This reset link is invalid, expired, or already used. Request a new link.';
        $pdo->commit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password | RPMS</title>
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
.left-top,.left-body,.left-foot{position:relative;z-index:2}
.left-body{color:var(--white)}
.left-body h2{font-family:'Fraunces',serif;font-weight:600;font-size:2.3rem;line-height:1.2;margin-bottom:14px}
.left-body p{font-size:.95rem;line-height:1.7;color:rgba(255,255,255,.88);max-width:340px}
.left-foot{font-size:.8rem;color:rgba(255,255,255,.7)}

/* RIGHT */
.auth-right{position:relative;display:flex;align-items:center;justify-content:center;padding:48px 40px;background:var(--white)}
.home-btn{position:absolute;top:24px;right:24px;display:inline-flex;align-items:center;gap:6px;font-size:.82rem;font-weight:600;color:var(--ink-2);text-decoration:none;padding:7px 14px;border-radius:8px;border:1px solid var(--cream-line);background:var(--cream);transition:.2s}
.home-btn:hover{background:var(--green-soft);color:var(--green-dark);border-color:var(--green)}
.form-wrap{width:100%;max-width:380px}
.form-wrap h1{font-weight:600;font-size:1.95rem;color:var(--green-dark);margin-bottom:6px}
.subtitle{font-size:.9rem;color:var(--ink-3);margin-bottom:28px;line-height:1.6}

.alert-box{border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:.87rem;line-height:1.5}
.alert-error{background:var(--error-bg);border:1px solid #F3C3BE;color:var(--error)}
.alert-ok{background:var(--green-soft);border:1px solid #BFDDC1;color:var(--green-dark)}

.field{margin-bottom:18px}
.field label{display:block;font-size:.82rem;font-weight:600;color:var(--ink-2);margin-bottom:6px}
.input-wrap{position:relative}
.field input{width:100%;padding:11px 44px 11px 14px;border:1.5px solid var(--cream-line);border-radius:10px;font:.92rem 'Inter',sans-serif;color:var(--ink);background:var(--white);outline:none;transition:border-color .2s,box-shadow .2s}
.field input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(46,125,50,.16)}
.eye-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--ink-3);padding:6px;display:flex}
.eye-btn:hover{color:var(--green)}
.eye-btn svg{width:18px;height:18px}
.eye-btn .slash{display:none}
.eye-btn[aria-pressed="true"] .slash{display:block}
.hint{font-size:.78rem;color:var(--ink-3);margin-top:6px;min-height:1.1em}
#matchHint{color:var(--error)}

.btn-submit,.btn-link{display:block;width:100%;padding:13px;background:var(--orange);color:var(--white);border:none;border-radius:10px;font:700 .95rem 'Inter',sans-serif;text-align:center;text-decoration:none;cursor:pointer;transition:.25s ease}
.btn-submit:hover,.btn-link:hover{background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 8px 22px rgba(245,124,0,.25)}
.links{display:flex;justify-content:space-between;gap:12px;margin-top:20px;font-size:.87rem}
.links a{color:var(--green);font-weight:600;text-decoration:none}
.links a:hover{text-decoration:underline}

@media (max-width:768px){
  .auth-page{grid-template-columns:1fr}
  .auth-left{display:none}
  .auth-right{padding:72px 24px 40px}
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
      <h2>Set a new password</h2>
      <p>Use at least 8 characters, and pick something you don't use on other sites.</p>
    </div>
    <div class="left-foot">San Jose Public Market</div>
  </div>

  <div class="auth-right">
    <a href="login.php" class="home-btn">← Back to login</a>

    <div class="form-wrap">
      <h1><?= $done ? 'Password changed' : 'Reset password' ?></h1>
      <p class="subtitle"><?= $done ? 'You can now sign in with your new password.' : 'Enter a new password for your RPMS account.' ?></p>

      <?php if ($message): ?>
        <div class="alert-box <?= $done ? 'alert-ok' : 'alert-error' ?>" role="<?= $done ? 'status' : 'alert' ?>"><?= h($message) ?></div>
      <?php endif; ?>

      <?php if ($done): ?>
        <a href="login.php" class="btn-link">Go to login</a>
      <?php else: ?>
        <form method="post" id="resetForm">
          <?= csrfField() ?>
          <input type="hidden" name="token" value="<?= h($token) ?>">

          <div class="field">
            <label for="password">New password</label>
            <div class="input-wrap">
              <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
              <button type="button" class="eye-btn" data-target="password" aria-label="Show password" aria-pressed="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path class="slash" d="M4 4l16 16"/></svg>
              </button>
            </div>
            <p class="hint">At least 8 characters.</p>
          </div>

          <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <div class="input-wrap">
              <input type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password">
              <button type="button" class="eye-btn" data-target="confirm_password" aria-label="Show password" aria-pressed="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path class="slash" d="M4 4l16 16"/></svg>
              </button>
            </div>
            <p class="hint" id="matchHint" role="status"></p>
          </div>

          <button type="submit" class="btn-submit">Save new password</button>
        </form>

        <div class="links">
          <a href="login.php">Back to login</a>
          <a href="forgot_password.php">Request a new link</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.eye-btn').forEach(b => b.addEventListener('click', () => {
  const input = document.getElementById(b.dataset.target);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  b.setAttribute('aria-pressed', show);
  b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}));

const pw = document.getElementById('password');
const cf = document.getElementById('confirm_password');
const hint = document.getElementById('matchHint');
if (pw && cf) {
  const check = () => {
    const bad = cf.value !== '' && pw.value !== cf.value;
    hint.textContent = bad ? "Passwords don't match yet." : '';
    cf.setCustomValidity(bad ? "Passwords don't match" : '');
  };
  pw.addEventListener('input', check);
  cf.addEventListener('input', check);
}
</script>
</body>
</html>