<?php
require_once __DIR__.'/page.php';
require_once __DIR__.'/archive.php';
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $id=(int)($_POST['id']??0); $action=$_POST['action']??'';
        $q=$pdo->prepare("SELECT * FROM users WHERE id=? AND role IN ('vendor','collector') AND deleted_at IS NULL"); $q->execute([$id]); $account=$q->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new RuntimeException('Account not found.');
        if ($action==='remove') {
            if($account['role']==='vendor') { $q=$pdo->prepare('SELECT id FROM vendors WHERE user_id=?'); $q->execute([$id]); archiveVendor($pdo,(int)$q->fetchColumn()); }
            else $pdo->prepare("UPDATE users SET deleted_at=NOW(),status='inactive' WHERE id=?")->execute([$id]);
            $message='Account removed. Previous transactions are preserved.';
        } elseif (in_array($action,['approve','reject','deactivate'],true)) {
            $pdo->beginTransaction();
            $state=$action==='approve'?'active':'inactive';
            $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$state,$id]);
            if($account['role']==='vendor') $pdo->prepare('UPDATE vendors SET status=? WHERE user_id=? AND deleted_at IS NULL')->execute([$state,$id]);
            $pdo->commit(); $message='Account '.($action==='approve'?'approved':'deactivated / rejected').'.';
            $pdo->prepare('INSERT INTO notifications(user_id,type,title,message) VALUES (?,?,?,?)')->execute([$id,'account','Account update',$message]);
            require_once __DIR__.'/mailer.php';
            $mail=sendRpmsMail($account['email'],trim($account['first_name'].' '.$account['last_name']),'RPMS account update',rpmsEmailTemplate('Account update','<p>Your '.h($account['role']).' account has been '.($action==='approve'?'approved. You can now sign in.':'deactivated / rejected. Please contact the market administrator for details.').'</p>'));
            if (!$mail['success']) $message.=' The email notification could not be delivered.';
        } elseif($action==='edit') {
            $first=trim((string)($_POST['first_name']??'')); $last=trim((string)($_POST['last_name']??'')); $email=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL); $password=(string)($_POST['password']??'');
            if (!$first || !$last || !$email || strlen($first)>100 || strlen($last)>100) throw new RuntimeException('Enter valid names and email.');
            if($password!=='' && strlen($password)<8) throw new RuntimeException('Use at least 8 characters for the new password.');
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET first_name=?,last_name=?,fullname=?,email=? WHERE id=?')->execute([$first,$last,$first.' '.$last,$email,$id]);
            if($password!=='') $pdo->prepare('UPDATE users SET password=?,auth_version=auth_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            $pdo->commit(); $message='Account updated.';
        }
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); $message=$e instanceof PDOException?'Could not save changes. The email may already be registered.':$e->getMessage(); }
}
$search=trim((string)($_GET['search']??'')); $status=(string)($_GET['status']??''); $role=(string)($_GET['role']??'');
$q=$pdo->prepare("SELECT * FROM users WHERE role IN ('vendor','collector') AND deleted_at IS NULL AND (?='' OR status=?) AND (?='' OR role=?) AND (CONCAT_WS(' ',first_name,last_name) LIKE ? OR email LIKE ?) ORDER BY status='pending' DESC,created_at DESC");
$q->execute([$status,$status,$role,$role,'%'.$search.'%','%'.$search.'%']); $accounts=$q->fetchAll(PDO::FETCH_ASSOC);
pageStart('Account Approvals'); ?>
<p role="status"><?= h($message) ?></p><form class="rpms-filters"><label>Name / Email<input name="search" value="<?= h($search) ?>"></label><label>Role<select name="role"><option value="">All</option><?php foreach(['vendor','collector'] as $s): ?><option <?= $role===$s?'selected':'' ?>><?= $s ?></option><?php endforeach ?></select></label><label>Status<select name="status"><option value="">All</option><?php foreach(['pending','active','inactive'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach ?></select></label><button class="rpms-button">Filter</button></form>
<div class="rpms-panel rpms-table-wrap"><table class="rpms-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php $page=max(1,min(max(1,(int)ceil(count($accounts)/20)),(int)($_GET['page']??1))); foreach(array_slice($accounts,($page-1)*20,20) as $a): ?><tr><td><?= h(trim($a['first_name'].' '.$a['last_name'])) ?></td><td><?= h($a['email']) ?></td><td><?= h($a['role']) ?></td><td><?= h($a['status']) ?></td><td>
<?php if($a['role']==='vendor'): $v=$pdo->prepare('SELECT id FROM vendors WHERE user_id=?'); $v->execute([$a['id']]); $vid=(int)$v->fetchColumn(); ?><a href="vendor_documents.php?vendor_id=<?= $vid ?>">Review documents</a> · <a href="vendors.php?edit=<?= $vid ?>">Set rent / stall</a><?php endif ?>
<form method="post" onsubmit="return confirm('Apply this account change?')"><?= csrfField() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><?php if($a['status']!=='active'): ?><button name="action" value="approve">Approve / Activate</button><button name="action" value="reject">Reject</button><?php else: ?><button name="action" value="deactivate">Deactivate</button><?php endif ?><button name="action" value="remove">Remove</button></form>
<details><summary>Edit account</summary><form method="post" class="rpms-filters"><?= csrfField() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="action" value="edit"><label>First name<input name="first_name" value="<?= h($a['first_name']) ?>" required maxlength="100"></label><label>Last name<input name="last_name" value="<?= h($a['last_name']) ?>" required maxlength="100"></label><label>Email<input type="email" name="email" value="<?= h($a['email']) ?>" required></label><label>New password (optional)<input type="password" name="password" minlength="8" autocomplete="new-password"></label><button class="rpms-button">Save</button></form></details></td></tr><?php endforeach; if(!$accounts): ?><tr><td colspan="5">No matching accounts.</td></tr><?php endif ?></tbody></table><?php pagination(count($accounts),$page) ?></div><?php pageEnd(); ?>
