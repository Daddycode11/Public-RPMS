<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('RPMS_DB_NAME') !== 'rpms_revision_test') {
    throw new RuntimeException('Explicitly select RPMS_DB_NAME=rpms_revision_test.');
}
require __DIR__ . '/../config/database.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rpms_revision_test'
    || $pdo->query('SELECT email FROM users WHERE id=1')->fetchColumn() !== 'admin1@example.test') {
    throw new RuntimeException('Synthetic fixture identity was not verified.');
}
$directory = realpath(__DIR__ . '/../uploads/documents');
foreach ($pdo->query('SELECT file_path FROM vendor_documents')->fetchAll(PDO::FETCH_COLUMN) as $stored) {
    $name = basename(str_replace('\\', '/', $stored));
    if (!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|jpeg|png|doc|docx)$/', $name)) {
        throw new RuntimeException('Unexpected fixture upload name.');
    }
    $path = realpath($directory . DIRECTORY_SEPARATOR . $name);
    if ($path !== false) {
        if (dirname($path) !== $directory) throw new RuntimeException('Upload escaped fixture storage.');
        if (!unlink($path)) throw new RuntimeException('Unable to remove fixture upload.');
    }
}
$pdo->exec('DROP DATABASE `rpms_revision_test`');
echo "Removed synthetic test database and its document uploads.\n";
