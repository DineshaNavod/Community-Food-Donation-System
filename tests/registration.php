<?php
declare(strict_types=1);

// Run from the project folder: C:\xampp\php\php.exe tests\registration.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../includes/db.php';

$url = 'http://localhost/Community%20Food%20Donation%20System/register.php';
$prefix = 'registration-check-' . bin2hex(random_bytes(6));
$emails = [];
$pdo = db();
$client = curl_init($url);
curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
    CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);

function check(bool $passed, string $label): void
{
    if (!$passed) {
        throw new RuntimeException($label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

function request(CurlHandle $client, ?array $fields = null): array
{
    if ($fields === null) {
        curl_setopt($client, CURLOPT_HTTPGET, true);
    } else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $response = curl_exec($client);
    if ($response === false) {
        throw new RuntimeException('HTTP connection failed: ' . curl_error($client));
    }
    $headerLength = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return [(int) curl_getinfo($client, CURLINFO_HTTP_CODE),
        substr($response, 0, $headerLength), substr($response, $headerLength)];
}

function tokenFrom(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $match)) {
        throw new RuntimeException('CSRF token missing');
    }
    return $match[1];
}

try {
    [$status, $headers, $html] = request($client);
    check($status === 200, 'registration page loads');
    check(stripos($headers, 'HttpOnly') !== false && stripos($headers, 'SameSite=Lax') !== false,
        'session cookie uses HttpOnly and SameSite');
    check(stripos($headers, 'Cache-Control: no-store') !== false, 'form response is not cached');
    check(!str_contains($html, '<option value="admin"'), 'admin is absent from public roles');
    $token = tokenFrom($html);
    $base = ['csrf_token' => $token, 'full_name' => 'Registration Test',
        'email' => $prefix . '-invalid@example.test', 'phone' => '+94 77 123 4567',
        'address' => 'Test address, Matara', 'role' => 'donor', 'organization_name' => '',
        'password' => 'Demo-check-2026!', 'password_confirmation' => 'Demo-check-2026!'];
    $emails[] = $base['email'];

    foreach (['missing CSRF' => '', 'forged CSRF' => str_repeat('0', 64), 'array CSRF' => ['bad']] as $label => $tokenValue) {
        $fields = $base; $fields['csrf_token'] = $tokenValue;
        [$status] = request($client, $fields);
        check($status === 403, $label . ' rejected');
    }
    $otherClient = curl_init($url);
    curl_setopt_array($otherClient, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
    [$status] = request($otherClient, $base);
    curl_close($otherClient);
    check($status === 403, 'token from another session rejected');

    $invalidCases = [
        'forged admin role' => ['role' => 'admin'],
        'array full name' => ['full_name' => ['bad']],
        'invalid email' => ['email' => 'not-an-email'],
        'invalid phone' => ['phone' => 'not-a-phone'],
        'short password' => ['password' => 'short', 'password_confirmation' => 'short'],
        'mismatched passwords' => ['password_confirmation' => 'Different-password'],
        'overlong password' => ['password' => str_repeat('x', 73), 'password_confirmation' => str_repeat('x', 73)],
        'password null byte' => ['password' => "Password\0value", 'password_confirmation' => "Password\0value"],
        'missing organization name' => ['role' => 'organization'],
        'overlong full name' => ['full_name' => str_repeat('x', 101)],
    ];
    foreach ($invalidCases as $label => $changes) {
        [$status] = request($client, array_replace($base, $changes));
        check($status === 422, $label . ' rejected');
    }
    [$status, , $html] = request($client, array_replace($base,
        ['full_name' => '<script>alert(1)</script>', 'email' => 'invalid']));
    check($status === 422 && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
        && !str_contains($html, '<script>alert(1)</script>'), 'redisplayed user input escaped');
    check(!str_contains($html, 'value="Demo-check-2026!"'), 'password not redisplayed');
    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $statement->execute([$base['email']]);
    check((int) $statement->fetchColumn() === 0, 'rejected forms created no account');

    foreach (['donor', 'organization', 'volunteer'] as $role) {
        [, , $html] = request($client);
        $fields = $base;
        $fields['csrf_token'] = tokenFrom($html);
        $fields['role'] = $role;
        $fields['organization_name'] = 'Community Test Organization';
        $fields['email'] = $prefix . '-' . $role . '@example.test';
        $emails[] = $fields['email'];
        // Confirm status input cannot override server-side approval rules.
        $fields['status'] = 'active';
        [$status, $headers] = request($client, $fields);
        check($status === 303 && str_contains($headers, 'Location: register.php'), $role . ' registration redirects');
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $statement->execute([$fields['email']]);
        $user = $statement->fetch();
        check($user && $user['role'] === $role && $user['status'] === ($role === 'donor' ? 'active' : 'pending'),
            $role . ' account has correct role and approval status');
        check($user['password_hash'] !== $fields['password'] && password_verify($fields['password'], $user['password_hash']),
            $role . ' password stored as a verifiable hash');
        $statement = $pdo->prepare('SELECT organization_name FROM organizations WHERE user_id = ?');
        $statement->execute([$user['id']]);
        check($statement->fetchColumn() === ($role === 'organization' ? $fields['organization_name'] : false),
            $role . ' organization profile is correct');
        [$status, , $html] = request($client);
        check($status === 200 && str_contains($html, 'Your ' . ($role === 'donor' ? 'donor account' : 'account') . ' has been created'),
            $role . ' success confirmation displayed');
        [, , $html] = request($client);
        $newToken = tokenFrom($html);
        check($newToken !== $fields['csrf_token'], 'CSRF token rotated after successful registration');
        $fields['csrf_token'] = $newToken;
        [$status, , $html] = request($client, $fields);
        check($status === 422 && str_contains($html, 'already registered'), 'duplicate ' . $role . ' email rejected');
        $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $statement->execute([$fields['email']]);
        check((int) $statement->fetchColumn() === 1, 'duplicate submission did not create another user');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    // Delete only the uniquely named accounts created by this test run.
    $pdo->beginTransaction();
    foreach ($emails as $email) {
        $statement = $pdo->prepare('DELETE o FROM organizations o JOIN users u ON u.id = o.user_id WHERE u.email = ?');
        $statement->execute([$email]);
        $statement = $pdo->prepare('DELETE FROM users WHERE email = ?');
        $statement->execute([$email]);
    }
    $pdo->commit();
    curl_close($client);
    echo 'Test accounts cleaned up.' . PHP_EOL;
}
exit(isset($failed) ? 1 : 0);
