<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$signedInUser = current_user();
if ($signedInUser !== null) {
    header('Location: ' . dashboard_url($signedInUser['role']), true, 303);
    exit;
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$roles = ['donor' => 'Food donor', 'organization' => 'Community organization', 'volunteer' => 'Collection volunteer'];
$values = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => '', 'role' => 'donor', 'organization_name' => ''];
$errors = [];
$success = $_SESSION['registration_success'] ?? '';
unset($_SESSION['registration_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = '';
    foreach ($values as $field => $default) {
        $values[$field] = post_text($field);
    }
    $values['email'] = strtolower($values['email']);
    // Do not trim passwords: spaces are part of the user's password.
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your form session is invalid or has expired. Please submit this form again.';
    } else {
        if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 100) {
            $errors[] = 'Enter a full name between 2 and 100 characters.';
        }
        if (strlen($values['email']) > 191 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address of up to 191 characters.';
        }
        if (!preg_match('/\A[0-9+().\s-]{7,25}\z/', $values['phone'])
            || !preg_match('/\A[0-9]{7,15}\z/', preg_replace('/\D/', '', $values['phone']))) {
            $errors[] = 'Enter a phone number with 7 to 15 digits.';
        }
        if (mb_strlen($values['address']) < 5 || mb_strlen($values['address']) > 255) {
            $errors[] = 'Enter an address between 5 and 255 characters.';
        }
        if (!array_key_exists($values['role'], $roles)) {
            $errors[] = 'Choose donor, community organization, or volunteer.';
        }
        if ($values['role'] === 'organization'
            && (mb_strlen($values['organization_name']) < 2 || mb_strlen($values['organization_name']) > 150)) {
            $errors[] = 'Enter an organization name between 2 and 150 characters.';
        }
        // PASSWORD_DEFAULT uses bcrypt in PHP 8.2, which has a 72-byte limit.
        if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            $errors[] = 'Use a password between 8 and 72 bytes (English letters count as one byte each).';
        }
        if ($password !== $confirmation) {
            $errors[] = 'The password confirmation does not match.';
        }
        foreach ($values as $value) {
            if (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                $errors[] = 'Use valid text without null characters.';
                break;
            }
        }

        if (!$errors) {
            $pdo = db();
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                $statement = $pdo->prepare('INSERT INTO users
                    (full_name, email, password_hash, phone, address, role, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([
                    $values['full_name'], $values['email'], $passwordHash,
                    $values['phone'], $values['address'], $values['role'],
                    $values['role'] === 'donor' ? 'active' : 'pending',
                ]);
                $userId = (int) $pdo->lastInsertId();
                if ($values['role'] === 'organization') {
                    $statement = $pdo->prepare('INSERT INTO organizations (user_id, organization_name) VALUES (?, ?)');
                    $statement->execute([$userId, $values['organization_name']]);
                }
                $pdo->commit();
                $_SESSION['registration_success'] = $values['role'] === 'donor'
                    ? 'Your donor account has been created and is active.'
                    : 'Your account has been created and is pending administrator approval.';
                unset($_SESSION['csrf_token']);
                header('Location: register.php', true, 303);
                exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'That email address is already registered. Please use another address.';
                } else {
                    error_log('Registration failed. Code: ' . $exception->getCode());
                    http_response_code(503);
                    $errors[] = 'Registration is temporarily unavailable. Please try again later.';
                }
            }
        }
        if ($errors && http_response_code() !== 503) {
            http_response_code(422);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/register.js" defer></script>
</head>
<body>
    <?php $activePage = 'register'; require __DIR__ . '/includes/header.php'; ?>
    <main class="container registration-layout" id="main-content">
        <aside class="welcome-panel">
            <span class="eyebrow">GOOD FOOD. SHARED PURPOSE.</span>
            <h1>A little surplus.<br>A lot of possibility.</h1>
            <p>Help good food reach the people who need it. Join a community that makes every donation count.</p>
            <ol class="community-steps">
                <li><span>01</span><div><strong>Share what you can</strong><p>Donors publish available surplus food.</p></div></li>
                <li><span>02</span><div><strong>Connect with your community</strong><p>Organizations request food for the people they support.</p></div></li>
                <li><span>03</span><div><strong>Make the connection happen</strong><p>Volunteers help collect and deliver donations.</p></div></li>
            </ol>
            <div class="panel-note">Community Food Donation Coordination System</div>
        </aside>
        <section class="form-panel" aria-labelledby="form-heading">
            <span class="eyebrow text-green">JOIN THE COMMUNITY</span>
            <h2 id="form-heading">Create your account</h2>
            <p class="form-intro">Choose how you would like to help.</p>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status">
                    <h3 class="h5">Welcome to the community!</h3>
                    <p><?= e($success) ?></p>
                    <p class="mb-0">You can sign in once your account is active.</p>
                </div>
                <a class="btn btn-community" href="login.php">Sign in to your account</a>
                <a class="d-block mt-3 home-link" href="index.php">Return to home</a>
                <a class="d-block mt-3 home-link" href="register.php">Register another account</a>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert" tabindex="-1" id="form-errors">
                        <strong>Please check the following:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form action="register.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="mb-3">
                        <label for="role" class="form-label">I would like to join as</label>
                        <select id="role" name="role" class="form-select" aria-describedby="role-help" required>
                            <?php foreach ($roles as $role => $label): ?>
                                <option value="<?= e($role) ?>" <?= $values['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="role-help" class="form-text">Organization and volunteer accounts need administrator approval.</div>
                    </div>
                    <div class="mb-3" id="organization-field">
                        <label for="organization_name" class="form-label">Organization name <span class="optional">(organizations only)</span></label>
                        <input id="organization_name" name="organization_name" class="form-control" value="<?= e($values['organization_name']) ?>" maxlength="150" autocomplete="organization">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full name</label>
                            <input id="full_name" name="full_name" class="form-control" value="<?= e($values['full_name']) ?>" minlength="2" maxlength="100" autocomplete="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone number</label>
                            <input id="phone" name="phone" type="tel" class="form-control" value="<?= e($values['phone']) ?>" maxlength="25" autocomplete="tel" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" class="form-control" value="<?= e($values['email']) ?>" maxlength="191" autocomplete="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="2" minlength="5" maxlength="255" autocomplete="street-address" required><?= e($values['address']) ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input id="password" name="password" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                        <div id="password-help" class="form-text mt-2">Use at least 8 characters, up to 72 bytes. Passwords are never shown again.</div>
                    </div>
                    <button type="submit" class="btn btn-community w-100">Create account <span aria-hidden="true">&rarr;</span></button>
                </form>
                <p class="registration-note">Start with your details. Make a difference together.</p>
                <p class="registration-note">Already registered? <a class="home-link" href="login.php">Sign in</a></p>
            <?php endif; ?>
        </section>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
