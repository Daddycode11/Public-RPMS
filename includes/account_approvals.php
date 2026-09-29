<?php
require_once __DIR__ . '/page.php';
require_once __DIR__ . '/archive.php';

const ACCOUNTS_PER_PAGE = 20;

/* ---------- Actions ---------- */

const STATE_ACTIONS = [
    'approve'    => ['state' => 'active',   'past' => 'approved'],
    'reject'     => ['state' => 'inactive', 'past' => 'rejected'],
    'deactivate' => ['state' => 'inactive', 'past' => 'deactivated'],
];

function findAccount(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        "SELECT u.*, (SELECT v.id FROM vendors v WHERE v.user_id = u.id AND v.deleted_at IS NULL LIMIT 1) AS vendor_id
         FROM users u
         WHERE u.id = ? AND u.role IN ('vendor','collector') AND u.deleted_at IS NULL"
    );
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('Account not found.');
}

function removeAccount(PDO $pdo, array $account): string
{
    if ($account['role'] === 'vendor' && $account['vendor_id']) {
        archiveVendor($pdo, (int)$account['vendor_id']);
    } else {
        $pdo->prepare("UPDATE users SET deleted_at = NOW(), status = 'inactive' WHERE id = ?")
            ->execute([$account['id']]);
    }

    return 'Account removed. Previous transactions are preserved.';
}

function changeAccountStatus(PDO $pdo, array $account, string $action): string
{
    ['state' => $state, 'past' => $past] = STATE_ACTIONS[$action];
    $message = 'Account ' . $past . '.';

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$state, $account['id']]);
    if ($account['role'] === 'vendor') {
        $pdo->prepare('UPDATE vendors SET status = ? WHERE user_id = ? AND deleted_at IS NULL')
            ->execute([$state, $account['id']]);
    }
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)')
        ->execute([$account['id'], 'account', 'Account update', $message]);
    $pdo->commit();

    // Email is sent after commit so a mail failure never rolls back the change.
    require_once __DIR__ . '/mailer.php';
    $body = $action === 'approve'
        ? 'approved. You can now sign in.'
        : $past . '. Please contact the market administrator for details.';

    $mail = sendRpmsMail(
        $account['email'],
        trim($account['first_name'] . ' ' . $account['last_name']),
        'RPMS account update',
        rpmsEmailTemplate('Account update', '<p>Your ' . h($account['role']) . ' account has been ' . $body . '</p>')
    );

    return $message . ($mail['success'] ? '' : ' The email notification could not be delivered.');
}

function editAccount(PDO $pdo, int $id, array $post): string
{
    $first    = trim((string)($post['first_name'] ?? ''));
    $last     = trim((string)($post['last_name'] ?? ''));
    $email    = filter_var($post['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = (string)($post['password'] ?? '');

    if ($first === '' || $last === '' || !$email || strlen($first) > 100 || strlen($last) > 100) {
        throw new RuntimeException('Enter valid names and email.');
    }
    if ($password !== '' && strlen($password) < 8) {
        throw new RuntimeException('Use at least 8 characters for the new password.');
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, fullname = ?, email = ? WHERE id = ?')
        ->execute([$first, $last, $first . ' ' . $last, $email, $id]);
    if ($password !== '') {
        $pdo->prepare('UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
    $pdo->commit();

    return 'Account updated.';
}

/* ---------- Request handling (Post/Redirect/Get) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id      = (int)($_POST['id'] ?? 0);
        $action  = (string)($_POST['action'] ?? '');
        $account = findAccount($pdo, $id);

        $_SESSION['flash'] = match (true) {
            $action === 'remove'                  => removeAccount($pdo, $account),
            isset(STATE_ACTIONS[$action])         => changeAccountStatus($pdo, $account, $action),
            $action === 'edit'                    => editAccount($pdo, $id, $_POST),
            default                               => throw new RuntimeException('Unknown action.'),
        };
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = $e instanceof PDOException
            ? 'Could not save changes. The email may already be registered.'
            : $e->getMessage();
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$message = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

/* ---------- Data ---------- */

$search = trim((string)($_GET['search'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$role   = (string)($_GET['role'] ?? '');
$like   = '%' . $search . '%';

$stmt = $pdo->prepare(
    "SELECT u.*, (SELECT v.id FROM vendors v WHERE v.user_id = u.id AND v.deleted_at IS NULL LIMIT 1) AS vendor_id
     FROM users u
     WHERE u.role IN ('vendor','collector')
       AND u.deleted_at IS NULL
       AND (? = '' OR u.status = ?)
       AND (? = '' OR u.role = ?)
       AND (CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? OR u.email LIKE ?)
     ORDER BY u.status = 'pending' DESC, u.created_at DESC"
);
$stmt->execute([$status, $status, $role, $role, $like, $like]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = max(1, (int)ceil(count($accounts) / ACCOUNTS_PER_PAGE));
$page       = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$pageRows   = array_slice($accounts, ($page - 1) * ACCOUNTS_PER_PAGE, ACCOUNTS_PER_PAGE);

/** Renders one action button that posts to the row's form. */
function actionButton(string $action, string $label, string $class, string $confirm): string
{
    return sprintf(
        '<button type="submit" name="action" value="%s" class="apr-btn %s" data-confirm="%s">%s</button>',
        h($action), h($class), h($confirm), h($label)
    );
}

pageStart('Account Approvals');
?>
<style>
    .apr-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.8rem; font-weight:600; text-transform:capitalize; }
    .apr-badge.pending  { background:#fef3c7; color:#92400e; }
    .apr-badge.active   { background:#dcfce7; color:#166534; }
    .apr-badge.inactive { background:#e5e7eb; color:#374151; }
    .apr-cell    { display:flex; flex-direction:column; gap:.5rem; min-width:220px; }
    .apr-links   { display:flex; flex-wrap:wrap; gap:.75rem; font-size:.9rem; }
    .apr-actions { display:flex; flex-wrap:wrap; gap:.4rem; margin:0; }
    .apr-btn     { padding:.35rem .75rem; border:1px solid transparent; border-radius:6px; font-size:.85rem; font-weight:600; cursor:pointer; }
    .apr-btn.approve { background:#16a34a; color:#fff; }
    .apr-btn.warn    { background:#fff; color:#b45309; border-color:#f59e0b; }
    .apr-btn.danger  { background:#fff; color:#b91c1c; border-color:#ef4444; }
    .apr-btn:hover   { filter:brightness(.95); }
    .apr-edit summary { cursor:pointer; font-size:.9rem; color:#2563eb; }
    .apr-edit form    { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.75rem; margin-top:.5rem; }
    .apr-flash        { padding:.6rem .9rem; border-radius:6px; background:#eff6ff; color:#1e40af; }
    .apr-flash:empty  { display:none; }
</style>

<p role="status" class="apr-flash"><?= h($message) ?></p>

<form class="rpms-filters">
    <label>Name / Email
        <input name="search" value="<?= h($search) ?>">
    </label>
    <label>Role
        <select name="role">
            <option value="">All</option>
            <?php foreach (['vendor', 'collector'] as $opt): ?>
                <option <?= $role === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <label>Status
        <select name="status">
            <option value="">All</option>
            <?php foreach (['pending', 'active', 'inactive'] as $opt): ?>
                <option <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <button class="rpms-button">Filter</button>
</form>

<div class="rpms-panel rpms-table-wrap">
    <table class="rpms-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pageRows as $a): ?>
            <tr>
                <td><?= h(trim($a['first_name'] . ' ' . $a['last_name'])) ?></td>
                <td><?= h($a['email']) ?></td>
                <td><?= h($a['role']) ?></td>
                <td><span class="apr-badge <?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
                <td>
                    <div class="apr-cell">
                        <?php if ($a['role'] === 'vendor' && $a['vendor_id']): ?>
                            <div class="apr-links">
                                <a href="vendor_documents.php?vendor_id=<?= (int)$a['vendor_id'] ?>">Review documents</a>
                                <a href="vendors.php?edit=<?= (int)$a['vendor_id'] ?>">Set rent / stall</a>
                            </div>
                        <?php endif ?>

                        <form method="post" class="apr-actions">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <?php if ($a['status'] === 'pending'): ?>
                                <?= actionButton('approve', 'Approve', 'approve', 'Approve this account?') ?>
                                <?= actionButton('reject', 'Reject', 'warn', 'Reject this account?') ?>
                            <?php elseif ($a['status'] === 'inactive'): ?>
                                <?= actionButton('approve', 'Activate', 'approve', 'Activate this account?') ?>
                            <?php else: ?>
                                <?= actionButton('deactivate', 'Deactivate', 'warn', 'Deactivate this account? They will no longer be able to sign in.') ?>
                            <?php endif ?>
                            <?= actionButton('remove', 'Remove', 'danger', 'Remove this account? Previous transactions are preserved.') ?>
                        </form>

                        <details class="apr-edit">
                            <summary>Edit account</summary>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <input type="hidden" name="action" value="edit">
                                <label>First name
                                    <input name="first_name" value="<?= h($a['first_name']) ?>" required maxlength="100">
                                </label>
                                <label>Last name
                                    <input name="last_name" value="<?= h($a['last_name']) ?>" required maxlength="100">
                                </label>
                                <label>Email
                                    <input type="email" name="email" value="<?= h($a['email']) ?>" required>
                                </label>
                                <label>New password (optional)
                                    <input type="password" name="password" minlength="8" autocomplete="new-password">
                                </label>
                                <button class="rpms-button">Save</button>
                            </form>
                        </details>
                    </div>
                </td>
            </tr>
        <?php endforeach ?>

        <?php if (!$accounts): ?>
            <tr><td colspan="5">No matching accounts.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
    <?php pagination(count($accounts), $page) ?>
</div>

<script>
    // One confirm dialog per button, with a message specific to the action.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (btn && !confirm(btn.dataset.confirm)) e.preventDefault();
    });
</script>

<?php pageEnd(); ?>