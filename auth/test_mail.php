<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/mailer.php';
$recipient = $argv[1] ?? '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Usage: php auth/test_mail.php recipient@example.com\n"); exit(1); }
$result = sendRpmsMail($recipient, 'Mail test', 'RPMS mail test', '<p>RPMS mail configuration test.</p>');
echo $result['success'] ? "Mail sent.\n" : "Mail delivery failed. Check local SMTP configuration.\n";
exit($result['success'] ? 0 : 1);
