<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Please use the Sign out button on your dashboard.');
}
if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Your form session is invalid or has expired. Reload your dashboard and try again.');
}
destroy_login_session();
header('Location: ' . app_url('login.php?logged_out=1'), true, 303);
exit;
