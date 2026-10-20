<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_setup.php';

$pdo = db();
// This connection-local table shadows users for the test only. Real accounts
// are untouched, even when an administrator already exists in the application.
$definition = $pdo->query('SHOW CREATE TABLE users')->fetch()['Create Table'];
$pdo->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition, 1));
$details = ['full_name' => 'Setup Test', 'email' => 'setup-check@example.test',
    'phone' => '0700000000', 'address' => 'Local test address'];
$password = 'Setup-check-2026!';
function check(bool $passed, string $label): void
{
    if (!$passed) { throw new RuntimeException($label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
try {
    foreach (['email' => 'invalid', 'phone' => 'invalid', 'full_name' => '', 'address' => ''] as $field => $value) {
        try {
            create_initial_admin($pdo, array_replace($details, [$field => $value]), $password);
            throw new RuntimeException('Invalid input accepted');
        } catch (InvalidArgumentException $exception) {
            echo 'PASS: invalid ' . $field . ' rejected' . PHP_EOL;
        }
    }
    foreach (['short', str_repeat('x', 73), "Null\0password"] as $badPassword) {
        try {
            create_initial_admin($pdo, $details, $badPassword);
            throw new RuntimeException('Invalid password accepted');
        } catch (InvalidArgumentException $exception) {
            echo 'PASS: invalid password rejected' . PHP_EOL;
        }
    }
    $insert = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,'donor','active')");
    $insert->execute([$details['full_name'], $details['email'], password_hash($password, PASSWORD_DEFAULT), $details['phone'], $details['address']]);
    $refused = false;
    try { create_initial_admin($pdo, $details, $password); }
    catch (RuntimeException $exception) { $refused = str_contains($exception->getMessage(), 'not be promoted'); }
    check($refused, 'existing donor email cannot be promoted');
    check($pdo->query('SELECT role FROM users')->fetchColumn() === 'donor', 'existing donor unchanged');
    $details['email'] = 'initial-admin@example.test';
    create_initial_admin($pdo, $details, $password);
    $admin = $pdo->query("SELECT * FROM users WHERE role='admin'")->fetch();
    check($admin && $admin['status'] === 'active', 'active administrator created');
    check($admin['password_hash'] !== $password && password_verify($password, $admin['password_hash']), 'administrator password hashed');
    $refused = false;
    try { create_initial_admin($pdo, array_replace($details, ['email' => 'second-admin@example.test']), $password); }
    catch (RuntimeException $exception) { $refused = str_contains($exception->getMessage(), 'already exists'); }
    check($refused, 'second administrator setup refused');
    $pdo->exec("UPDATE users SET status='suspended' WHERE role='admin'");
    $refused = false;
    try { create_initial_admin($pdo, $details, $password); }
    catch (RuntimeException $exception) { $refused = str_contains($exception->getMessage(), 'already exists'); }
    check($refused, 'suspended administrator cannot be replaced by setup');
    check((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() === 1, 'exactly one administrator remains');
    check(!$pdo->inTransaction(), 'transactions closed after rejected setup');
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    // TEMPORARY is essential: never drop the application's real users table.
    $pdo->exec('DROP TEMPORARY TABLE users');
    echo 'Temporary table removed; real accounts were not modified.' . PHP_EOL;
}
exit(isset($failed) ? 1 : 0);
