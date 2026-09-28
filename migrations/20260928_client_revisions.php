<?php
// Run with: php migrations/20260928_client_revisions.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/database.php';
// Some imported schemas lost AUTO_INCREMENT. Preserve even legacy zero IDs.
$sqlMode=$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
$pdo->exec('SET SESSION sql_mode='.$pdo->quote($sqlMode.',NO_AUTO_VALUE_ON_ZERO'));
foreach (['announcements','audit_logs','collector_assignments','maintenance_requests','notifications','partial_payments','payment_imports','payments','penalty_rules','receipts','sections','settings'] as $table) {
    $id=$pdo->query("SHOW COLUMNS FROM `$table` WHERE Field='id'")->fetch(PDO::FETCH_ASSOC);
    if ($id && strpos($id['Extra'],'auto_increment')===false) {
        $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN id ".$id['Type'].' NOT NULL AUTO_INCREMENT');
    }
}
$pdo->exec('SET SESSION sql_mode='.$pdo->quote($sqlMode));
foreach ([
    'users' => ['deleted_at' => 'DATETIME NULL', 'auth_version' => 'INT NOT NULL DEFAULT 1'],
    'vendors' => ['daily_rent' => 'DECIMAL(10,2) NULL', 'deleted_at' => 'DATETIME NULL'],
    'payments' => ['deleted_at' => 'DATETIME NULL', 'request_key' => 'VARCHAR(64) NULL'],
    'vendor_documents' => ['deleted_at' => 'DATETIME NULL', 'review_status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'"],
    'activity_logs' => ['deleted_at' => 'DATETIME NULL'],
    'penalty_rules' => ['deleted_at' => 'DATETIME NULL'],
] as $table => $columns) {
    $existing = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $column => $definition) {
        if (!in_array($column, $existing, true)) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) PRIMARY KEY, failures INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB");
$indexes = $pdo->query('SHOW INDEX FROM payments')->fetchAll(PDO::FETCH_ASSOC);
if (!in_array('payment_request_key', array_column($indexes, 'Key_name'), true)) {
    $pdo->exec('ALTER TABLE payments ADD UNIQUE KEY payment_request_key (request_key)');
}
echo "Client revision migration complete. Existing records preserved.\n";
