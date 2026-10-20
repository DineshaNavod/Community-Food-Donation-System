<?php
declare(strict_types=1);

// Internal helper for the CLI setup tool; public registration never calls it.
function create_initial_admin(PDO $pdo, array $details, string $password): void
{
    foreach (['full_name', 'email', 'phone', 'address'] as $field) {
        if (!isset($details[$field]) || !is_string($details[$field])
            || !mb_check_encoding($details[$field], 'UTF-8') || str_contains($details[$field], "\0")) {
            throw new InvalidArgumentException('Provide valid name, email, phone, and address values.');
        }
        $details[$field] = trim($details[$field]);
    }
    $details['email'] = strtolower($details['email']);
    if (mb_strlen($details['full_name']) < 2 || mb_strlen($details['full_name']) > 100) {
        throw new InvalidArgumentException('Name must contain 2 to 100 characters.');
    }
    if (strlen($details['email']) > 191 || !filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Provide a valid email address of up to 191 characters.');
    }
    if (!preg_match('/\A[0-9+().\s-]{7,25}\z/', $details['phone'])
        || !preg_match('/\A[0-9]{7,15}\z/', preg_replace('/\D/', '', $details['phone']))) {
        throw new InvalidArgumentException('Phone must contain 7 to 15 digits.');
    }
    if (mb_strlen($details['address']) < 5 || mb_strlen($details['address']) > 255) {
        throw new InvalidArgumentException('Address must contain 5 to 255 characters.');
    }
    if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new InvalidArgumentException('Password must contain 8 to 72 bytes without null characters.');
    }

    // Serialize two simultaneous setup commands, including when users is empty.
    $lockName = 'food_admin_setup_' . sha1((string) $pdo->query('SELECT DATABASE()')->fetchColumn());
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another administrator setup is running. Try again shortly.');
    }
    try {
        $pdo->beginTransaction();
        if ($pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() !== false) {
            throw new RuntimeException('An administrator already exists. No account was created or changed.');
        }
        $query = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $query->execute([$details['email']]);
        if ($query->fetchColumn() !== false) {
            throw new RuntimeException('That email is already registered. Existing accounts will not be promoted.');
        }
        $query = $pdo->prepare('INSERT INTO users
            (full_name, email, password_hash, phone, address, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $query->execute([$details['full_name'], $details['email'], password_hash($password, PASSWORD_DEFAULT),
            $details['phone'], $details['address'], 'admin', 'active']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
