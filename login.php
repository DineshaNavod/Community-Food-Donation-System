<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if ($user !== null) {
    header('Location: ' . dashboard_url($user['role']), true, 303);
    exit;
}
// An account revoked during an existing session may have destroyed that session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$email = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(post_text('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Your form session is invalid or has expired. Please submit this form again.';
    } elseif (($_SESSION['login_blocked_until'] ?? 0) > time()) {
        http_response_code(429);
        header('Retry-After: ' . ($_SESSION['login_blocked_until'] - time()));
        $error = 'Too many unsuccessful attempts. Please wait five minutes before trying again.';
    } elseif (strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
        http_response_code(422);
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $query = db()->prepare('SELECT id, password_hash, role, status FROM users WHERE email = ?');
            $query->execute([$email]);
            $account = $query->fetch();
            // A fixed valid hash also performs password verification for unknown emails.
            $hash = $account ? $account['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $correctPassword = password_verify($password, $hash);
            if (!$account || !$correctPassword) {
                $attempts = ($_SESSION['login_blocked_until'] ?? 0) <= time()
                    && isset($_SESSION['login_blocked_until']) ? 0 : ($_SESSION['login_failures'] ?? 0);
                $_SESSION['login_failures'] = $attempts + 1;
                if ($_SESSION['login_failures'] >= 5) {
                    $_SESSION['login_blocked_until'] = time() + 300;
                }
                http_response_code(401);
                $error = 'The email address or password is incorrect.';
            } elseif ($account['status'] !== 'active') {
                http_response_code(403);
                $error = $account['status'] === 'pending'
                    ? 'Your account is pending administrator approval.'
                    : 'Your account is not active. Please contact the administrator.';
            } elseif (!in_array($account['role'], ['donor', 'organization', 'volunteer', 'admin'], true)) {
                http_response_code(403);
                $error = 'This account cannot access the application. Please contact the administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION = ['user_id' => (int) $account['id']];
                header('Location: ' . dashboard_url($account['role']), true, 303);
                exit;
            }
        } catch (PDOException $exception) {
            error_log('Login failed. Code: ' . $exception->getCode());
            http_response_code(503);
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php $activePage = 'login'; require __DIR__ . '/includes/header.php'; ?>
    <main class="container registration-layout login-layout" id="main-content">
        <aside class="welcome-panel">
            <span class="eyebrow">GOOD TO HAVE YOU BACK</span>
            <h1>Small actions.<br>Shared impact.</h1>
            <p>Sign in to connect with your community and help good food go further.</p>
            <div class="panel-note">One community. A shared purpose.</div>
        </aside>
        <section class="form-panel" aria-labelledby="login-heading">
            <span class="eyebrow text-green">WELCOME BACK</span>
            <h2 id="login-heading">Sign in to your account</h2>
            <p class="form-intro">Use the email address you registered with.</p>
            <?php if (isset($_GET['logged_out'])): ?>
                <div class="alert alert-info" role="status">You have been signed out.</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" name="email" type="email" class="form-control" value="<?= e($email) ?>" maxlength="191" autocomplete="username" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" class="form-control" maxlength="72" autocomplete="current-password" required>
                </div>
                <button class="btn btn-community w-100" type="submit">Sign in &rarr;</button>
            </form>
            <p class="registration-note">New here? <a class="home-link" href="register.php">Create an account</a></p>
        </section>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
