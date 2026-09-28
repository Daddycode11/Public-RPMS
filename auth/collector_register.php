<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

http_response_code(410);
echo json_encode(['success' => false, 'message' => 'This registration endpoint has been replaced. Use register.php and select Collector.']);
exit;
