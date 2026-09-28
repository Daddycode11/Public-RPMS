<?php
require_once __DIR__.'/includes/security.php';
ob_start('secureHtml');

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit;
        case 'collector':
            header("Location: collector/dashboard.php");
            exit;
        case 'vendor':
            header("Location: vendor/dashboard.php");
            exit;
        default:
            session_destroy();
            header("Location: auth/login.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RPMS | Rental Payment Management System</title>
<?php include __DIR__ . '/includes/favicon.php'; ?>
<meta http-equiv="refresh" content="2.2;url=auth/login.php">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --forest:    #163527;
  --green:     #2d6a4f;
  --leaf:      #8fcfa8;
  --leaf-pale: #e3f3e7;
  --gold:      #e2a33d;
  --cream:     #faf6ec;
  --ink-3:     #71857a;
}

html, body { height: 100%; }
body {
  font-family: 'Inter', sans-serif;
  background: var(--forest);
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.splash-bg {
  position: absolute; inset: 0;
  background: url('assets/images/market-bg.png') center/cover no-repeat;
  opacity: .18;
}
.splash-overlay {
  position: absolute; inset: 0;
  background: radial-gradient(circle at 50% 40%, rgba(45,106,79,.55), var(--forest) 70%);
}

.splash-content {
  position: relative; z-index: 1;
  display: flex; flex-direction: column; align-items: center;
  animation: fadeUp .6s ease both;
}

.splash-logo { height: 56px; margin-bottom: 28px; filter: brightness(0) invert(1); opacity: .95; }

.loader-ring {
  width: 46px; height: 46px;
  border: 3px solid rgba(255,255,255,.15);
  border-top-color: var(--gold);
  border-radius: 50%;
  animation: spin .75s linear infinite;
  margin-bottom: 18px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.loader-text { font-family: 'Fraunces', serif; font-weight: 500; font-size: 1rem; color: rgba(255,255,255,.9); letter-spacing: .02em; min-height: 1.3em; }
.loader-text .cursor { display: inline-block; width: 2px; margin-left: 2px; background: var(--gold); animation: blink 0.85s step-end infinite; }
@keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0; } }
.loader-sub { font-size: .8rem; color: rgba(255,255,255,.5); margin-top: 6px; opacity: 0; transition: opacity .4s ease; }
.loader-sub.show { opacity: 1; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

@media (prefers-reduced-motion: reduce) {
  .loader-ring { animation-duration: 1.2s; }
  .splash-content { animation: none; }
}
</style>
</head>
<body>

<div class="splash-bg"></div>
<div class="splash-overlay"></div>

<div class="splash-content">
  <div class="market-brand"><span class="market-logo" role="img" aria-label="San Jose Public Market"></span><strong>RPMS</strong></div>
  <div class="loader-ring"></div>
  <p class="loader-text" id="typedText"><span class="cursor">&nbsp;</span></p>
  <p class="loader-sub" id="loaderSub">Taking you to login…</p>
</div>

<script>
const text = "Rental Payment Management System";
const el = document.getElementById('typedText');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let i = 0;
function typeChar() {
  if (i < text.length) {
    el.innerHTML = text.slice(0, i + 1) + '<span class="cursor">&nbsp;</span>';
    i++;
    setTimeout(typeChar, 32);
  } else {
    document.getElementById('loaderSub').classList.add('show');
  }
}
if (reduceMotion) {
  el.textContent = text;
  document.getElementById('loaderSub').classList.add('show');
} else {
  setTimeout(typeChar, 250);
}

setTimeout(() => { window.location.href = 'auth/login.php'; }, 2200);
</script>
</body>
</html>
