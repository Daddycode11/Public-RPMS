<?php
require_once __DIR__.'/page.php';
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='remove') {
    $pdo->prepare('UPDATE penalty_rules SET deleted_at=NOW(),is_active=0 WHERE id=?')->execute([(int)($_POST['id']??0)]);
}
$rules=$pdo->query('SELECT * FROM penalty_rules WHERE deleted_at IS NULL ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
pageStart('Payment Rules'); ?>
<section class="rpms-panel"><p>Adjustments are applied automatically when a payment is recorded, using the payment date.</p><table class="rpms-table"><tr><th>Calendar days</th><th>Adjustment</th></tr><tr><td>1–5</td><td>5% discount</td></tr><tr><td>6–20</td><td>Regular payment</td></tr><tr><td>21–last day of month</td><td>20% penalty</td></tr></table><p>February and 30/31-day months use their actual last day. Existing payments retain their recorded amounts.</p><a href="payment_calendar.php">View payment calendar</a></section>
<section class="rpms-panel"><h2>Legacy rules</h2><p>These older settings do not override the automatic calendar rule. Remove obsolete entries as needed.</p><table class="rpms-table"><tr><th>Name</th><th>Value</th><th>Actions</th></tr><?php foreach($rules as $rule): ?><tr><td><?= h($rule['name']) ?></td><td><?= h($rule['penalty_value'].' '.$rule['penalty_type']) ?></td><td><form method="post" onsubmit="return confirm('Remove this legacy rule?')"><?= csrfField() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $rule['id'] ?>"><button class="rpms-button danger">Remove</button></form></td></tr><?php endforeach; if(!$rules): ?><tr><td colspan="3">No legacy rules.</td></tr><?php endif ?></table></section><?php pageEnd(); ?>
