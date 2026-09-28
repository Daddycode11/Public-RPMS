<?php
require_once __DIR__.'/../includes/protected.php';
http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['success'=>false,'message'=>'Use the Vendor Accounts form to edit or remove a vendor.']);
