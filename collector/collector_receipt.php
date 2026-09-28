<?php
require_once __DIR__ . '/../includes/protected.php';
require __DIR__ . '/../includes/' . (isset($_GET['id']) ? 'receipt_page.php' : 'payment_records.php');
