<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Manila');
foreach (array_merge($_GET, $_POST) as $input) {
    if (!is_scalar($input) && $input !== null) { http_response_code(400); exit('Invalid form field.'); }
}

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">'; }
function verifyCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'message' => 'Invalid request. Refresh the page and try again.']));
    }
}
function requireRole(PDO $pdo, string $role): array {
    $stmt = $pdo->prepare('SELECT id, role, status, deleted_at, two_factor_enabled, auth_version FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== $role || $user['status'] !== 'active' || $user['deleted_at']
        || (int)($_SESSION['auth_version'] ?? 1) !== (int)$user['auth_version']) {
        http_response_code(403);
        exit('Access denied. Sign in with an approved, active account.');
    }
    if ($role === 'admin' && $user['two_factor_enabled'] && empty($_SESSION['otp_verified'])) {
        header('Location: ../auth/otp_verify.php'); exit;
    }
    return $user;
}

// Add tokens to legacy forms while they are progressively maintained.
function secureHtml(string $html): string {
    if (stripos($html, '<html') === false) return $html;
    $html = preg_replace_callback('/<form\b[^>]*>/i', static function ($match) {
        return preg_match('/method\s*=\s*["\x27]?post\b/i', $match[0]) ? $match[0] . csrfField() : $match[0];
    }, $html);
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (preg_match('~/(admin|collector|vendor|auth)$~', $base)) $base = dirname($base);
    $assets = rtrim($base, '/') . '/assets';
    $tags = '<meta name="csrf-token" content="' . h(csrfToken()) . '"><link rel="stylesheet" href="' . h($assets) . '/css/revisions.css">';
    $tags .= '<script src="' . h($assets) . '/js/revisions.js" defer></script>';
    return str_ireplace('</head>', $tags . '</head>', $html);
}
