<?php
require_once __DIR__.'/../includes/protected.php';
http_response_code(403);
header('Content-Type: application/json');
echo json_encode(['success'=>false,'message'=>'Vendor account changes require administrator review.']);
