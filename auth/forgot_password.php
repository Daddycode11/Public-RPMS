<?php
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/mailer.php';
ob_start('secureHtml');
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $email=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL);
    if ($email && time()-(int)($_SESSION['last_reset_request']??0)>=60) {
        $_SESSION['last_reset_request']=time();
        $q=$pdo->prepare("SELECT id,email,first_name FROM users WHERE email=? AND deleted_at IS NULL"); $q->execute([$email]); $user=$q->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $token=bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$user['id']]);
            $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$user['id'],hash('sha256',$token)]);
            // Set RPMS_BASE_URL to the public installation URL; never trust an HTTP Host header.
            $base=rtrim(getenv('RPMS_BASE_URL')?:'http://localhost/rpms_finalized','/');
            $link=$base.'/auth/reset_password.php?token='.$token;
            sendRpmsMail($user['email'],$user['first_name']??'Vendor','Reset your RPMS password',rpmsEmailTemplate('Reset password','<p>Use this link within 30 minutes to reset your password:</p><p><a href="'.h($link).'">Reset password</a></p><p>If you did not request this, ignore this email.</p>'));
        }
    }
    $message='If the address belongs to an account, a password reset link will be sent. You can request another link after one minute.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recover Password | RPMS</title>
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

.alert-ok{background:var(--green-soft);border:1px solid #BFDDC1;color:var(--green-dark);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:.87rem;line-height:1.5}

.field{margin-bottom:20px}
.field label{display:block;font-size:.82rem;font-weight:600;color:var(--ink-2);margin-bottom:6px}
.field input{width:100%;padding:11px 14px;border:1.5px solid var(--cream-line);border-radius:10px;font:.92rem 'Inter',sans-serif;color:var(--ink);background:var(--white);outline:none;transition:border-color .2s,box-shadow .2s}
.field input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(46,125,50,.16)}
.field input::placeholder{color:#A9B5AC}

.btn-submit{display:block;width:100%;padding:13px;background:var(--orange);color:var(--white);border:none;border-radius:10px;font:700 .95rem 'Inter',sans-serif;cursor:pointer;transition:.25s ease}
.btn-submit:hover:not(:disabled){background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 8px 22px rgba(245,124,0,.25)}
.btn-submit:disabled{opacity:.6;cursor:not-allowed}
.back-link{display:block;text-align:center;margin-top:20px;font-size:.87rem;color:var(--green);font-weight:600;text-decoration:none}
.back-link:hover{text-decoration:underline}

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
      <h2>Forgot your password?</h2>
      <p>Enter the email you registered with. If it matches an account, we'll send a reset link that works for 30 minutes.</p>
    </div>
    <div class="left-foot">San Jose Public Market</div>
  </div>

  <div class="auth-right">
    <a href="login.php" class="home-btn">← Back to login</a>

    <div class="form-wrap">
      <h1>Recover password</h1>
      <p class="subtitle">We'll email you a link to set a new password.</p>

      <?php if ($message): ?>
        <div class="alert-ok" role="status"><?= h($message) ?></div>
      <?php endif; ?>

      <form method="post" id="forgotForm">
        <?= csrfField() ?>
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" autofocus>
        </div>
        <button type="submit" class="btn-submit" id="sendBtn">Send reset link</button>
      </form>

      <a href="login.php" class="back-link">Back to login</a>
    </div>
  </div>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', () => {
  const b = document.getElementById('sendBtn');
  b.textContent = 'Sending…';
  b.disabled = true;
});
</script>
</body>
</html>