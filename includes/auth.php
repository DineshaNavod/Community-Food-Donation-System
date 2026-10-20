<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

function app_url(string $path): string
{
    return APP_BASE_PATH . '/' . ltrim($path, '/');
}

function dashboard_url(string $role): string
{
    $pages = ['donor' => 'donor/index.php', 'organization' => 'organization/index.php',
        'volunteer' => 'volunteer/index.php', 'admin' => 'admin/index.php'];
    return app_url($pages[$role] ?? 'login.php');
}

function destroy_login_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $settings = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $settings['path'],
            'domain' => $settings['domain'], 'secure' => $settings['secure'],
            'httponly' => $settings['httponly'], 'samesite' => $settings['samesite'],
        ]);
    }
    session_destroy();
}

function current_user(): ?array
{
    // Read role and status from the database on each request, never from a form.
    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return null;
    }
    try {
        $query = db()->prepare('SELECT id, full_name, email, phone, address, role, status FROM users WHERE id = ?');
        $query->execute([$_SESSION['user_id']]);
        $user = $query->fetch();
    } catch (PDOException $exception) {
        error_log('Account check failed. Code: ' . $exception->getCode());
        http_response_code(503);
        exit('Account access is temporarily unavailable. Please try again later.');
    }
    if (!$user || $user['status'] !== 'active'
        || !in_array($user['role'], ['donor', 'organization', 'volunteer', 'admin'], true)) {
        destroy_login_session();
        return null;
    }
    return $user;
}

function require_role(string $role): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: ' . app_url('login.php'), true, 303);
        exit;
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        exit('<h1>Access denied</h1><p>This page is reserved for another role.</p><a href="'
            . e(dashboard_url($user['role'])) . '">Return to your dashboard</a>');
    }
    return $user;
}
