<?php
require_once __DIR__ . '/security.php';
$stmt = $pdo->prepare('SELECT d.*, v.user_id FROM vendor_documents d JOIN vendors v ON v.id=d.vendor_id WHERE d.id=? AND d.deleted_at IS NULL');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc || ($_SESSION['role'] !== 'admin' && (int)$doc['user_id'] !== (int)$_SESSION['user_id'])) { http_response_code(404); exit('Document not found.'); }
$dir = realpath(__DIR__ . '/../uploads/documents');
$path = realpath($dir . DIRECTORY_SEPARATOR . basename(str_replace('\\','/',$doc['file_path'])));
if (!$path || !is_file($path) || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0) { http_response_code(404); exit('Document unavailable.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$inline = in_array($mime,['application/pdf','image/jpeg','image/png'],true) && !isset($_GET['download']);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="document.'.pathinfo($path,PATHINFO_EXTENSION).'"; filename*=UTF-8\'\''.rawurlencode(basename($doc['file_name'])));
readfile($path);
exit;
