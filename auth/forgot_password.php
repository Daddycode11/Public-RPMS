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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recover password | RPMS</title></head><body><main class="rpms-panel" style="max-width:480px;margin:10vh auto"><h1>Recover password</h1><p><?= h($message) ?></p><form method="post" class="rpms-filters"><?= csrfField() ?><label>Email<input type="email" name="email" required></label><button class="rpms-button">Send reset link</button></form><a href="login.php">Back to login</a></main></body></html>
