<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';
$routeRole = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
if (in_array($routeRole, ['admin', 'collector', 'vendor'], true)) {
    requireRole($pdo, $routeRole);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') verifyCsrf();
}
if (!defined('RPMS_HTML_SECURITY')) {
    define('RPMS_HTML_SECURITY', true);
    ob_start('secureHtml');
}
