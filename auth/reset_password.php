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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset password | RPMS</title></head><body><main class="rpms-panel" style="max-width:480px;margin:10vh auto"><h1>Reset password</h1><p role="status"><?= h($message) ?></p><?php if (!$done): ?><form method="post" class="rpms-filters"><?= csrfField() ?><input type="hidden" name="token" value="<?= h($token) ?>"><label>New password<input type="password" name="password" minlength="8" required></label><label>Confirm password<input type="password" name="confirm_password" minlength="8" required></label><button class="rpms-button">Change password</button></form><?php endif ?><a href="login.php">Back to login</a> · <a href="forgot_password.php">Request a new link</a></main></body></html>
