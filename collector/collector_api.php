<?php
require_once __DIR__ . '/../includes/protected.php';
require_once __DIR__ . '/../includes/payment_service.php';
header('Content-Type: application/json');
try {
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); throw new RuntimeException('Use the payment form.'); }
 $result=recordPayment($pdo,(int)($_POST['vendor_id']??0),(int)$_SESSION['user_id'],(string)($_POST['payment_type']??''),(string)($_POST['request_key']??''));
 echo json_encode(array_merge(['success'=>true],$result));
} catch (Throwable $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e instanceof PDOException?'Payment could not be saved.':$e->getMessage()]); }
