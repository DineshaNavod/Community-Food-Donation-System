<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/db.php';

$base = 'http://localhost/Community%20Food%20Donation%20System/';
$pdo = db();
$prefix = 'auth-check-' . bin2hex(random_bytes(6));
$created = [];
$password = 'Auth-check-2026!';

function check(bool $passed, string $label): void
{
    if (!$passed) { throw new RuntimeException($label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function client(): CurlHandle
{
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
    return $client;
}
function request(CurlHandle $client, string $path, ?array $fields = null): array
{
    global $base;
    curl_setopt($client, CURLOPT_URL, $base . $path);
    if ($fields === null) { curl_setopt($client, CURLOPT_HTTPGET, true); }
    else {
        curl_setopt($client, CURLOPT_POST, true);
        curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $response = curl_exec($client);
    if ($response === false) { throw new RuntimeException(curl_error($client)); }
    $size = curl_getinfo($client, CURLINFO_HEADER_SIZE);
    return [(int) curl_getinfo($client, CURLINFO_HTTP_CODE), substr($response, 0, $size), substr($response, $size)];
}
function token(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $match)) {
        throw new RuntimeException('CSRF token missing');
    }
    return $match[1];
}
function sessionCookie(CurlHandle $client): string
{
    foreach (curl_getinfo($client, CURLINFO_COOKIELIST) as $cookie) {
        $parts = explode("\t", $cookie);
        if (($parts[5] ?? '') === 'food_donation_session') { return $parts[6]; }
    }
    throw new RuntimeException('Session cookie missing');
}
function login(CurlHandle $client, string $email, string $password): array
{
    [, , $html] = request($client, 'login.php');
    return request($client, 'login.php', ['csrf_token' => token($html), 'email' => $email, 'password' => $password, 'role' => 'admin']);
}

try {
    $accounts = [];
    foreach (['donor' => 'active', 'organization' => 'active', 'volunteer' => 'active', 'admin' => 'active',
        'pending-org' => 'pending', 'pending-volunteer' => 'pending', 'suspended' => 'suspended', 'rejected' => 'rejected'] as $name => $status) {
        $role = $name === 'pending-org' ? 'organization' : ($name === 'pending-volunteer' ? 'volunteer' :
            (in_array($name, ['suspended', 'rejected'], true) ? 'donor' : $name));
        $email = $prefix . '-' . $name . '@example.test';
        $statement = $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)');
        $statement->execute(['Auth <test>', $email, password_hash($password, PASSWORD_DEFAULT), '0771234567', 'Test address', $role, $status]);
        $id = (int) $pdo->lastInsertId();
        $created[$id] = $email;
        $accounts[$name] = ['id' => $id, 'email' => $email];
        if ($role === 'organization') {
            $pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$id, 'Auth Test Organization']);
        }
    }
    $guest = client();
    foreach (['donor','organization','volunteer','admin'] as $role) {
        [$status, $headers] = request($guest, $role . '/index.php');
        check($status === 303 && str_contains($headers, '/login.php'), 'guest blocked from ' . $role);
    }
    [$status] = request($guest, 'logout.php');
    check($status === 405, 'GET logout rejected');
    [$status] = request($guest, 'login.php', ['email' => $accounts['donor']['email'], 'password' => $password]);
    check($status === 403, 'login without CSRF rejected');
    [, , $html] = request($guest, 'login.php');
    [$status] = request($guest, 'login.php', ['csrf_token' => token($html), 'email' => ['bad'], 'password' => ['bad']]);
    check($status === 422, 'malformed login fields rejected');
    [$status, , $html] = login($guest, $accounts['donor']['email'], 'Wrong-password');
    check($status === 401 && str_contains($html, 'email address or password is incorrect'), 'wrong password rejected');
    [$unknownStatus, , $unknownHtml] = login($guest, $prefix . '-unknown@example.test', 'Wrong-password');
    check($unknownStatus === 401 && str_contains($unknownHtml, 'email address or password is incorrect'), 'unknown email uses same error');
    foreach (['pending-org','pending-volunteer','suspended','rejected'] as $name) {
        $blocked = client();
        [$status, , $html] = login($blocked, $accounts[$name]['email'], $password);
        check($status === 403 && str_contains($html, str_starts_with($name, 'pending') ? 'pending administrator approval' : 'not active'), $name . ' cannot sign in');
        [$status] = request($blocked, 'donor/index.php');
        check($status === 303, $name . ' has no authenticated session');
        curl_close($blocked);
    }
    foreach (['donor','organization','volunteer','admin'] as $role) {
        $browser = client();
        [, , $html] = request($browser, 'login.php');
        $oldCookie = sessionCookie($browser);
        $oldToken = token($html);
        [$status, $headers] = request($browser, 'login.php', ['csrf_token' => $oldToken,
            'email' => strtoupper($accounts[$role]['email']), 'password' => $password, 'role' => 'admin']);
        check($status === 303 && str_contains($headers, '/' . $role . '/index.php'), $role . ' routed using stored role');
        check(sessionCookie($browser) !== $oldCookie, $role . ' session ID regenerated');
        $replay = client();
        curl_setopt($replay, CURLOPT_COOKIE, 'food_donation_session=' . $oldCookie);
        [$status] = request($replay, $role . '/index.php');
        check($status === 303, $role . ' old session ID cannot access dashboard');
        curl_close($replay);
        foreach (['donor','organization','volunteer','admin'] as $target) {
            [$status, , $html] = request($browser, $target . '/index.php');
            check($status === ($target === $role ? 200 : 403), $role . ' access to ' . $target);
            if ($target === $role) {
                check(str_contains($html, 'Auth &lt;test&gt;') && !str_contains($html, 'Auth <test>'), 'dashboard name escaped');
                $logoutToken = token($html);
            }
        }
        [$status] = request($browser, 'login.php');
        check($status === 303, 'signed-in ' . $role . ' skips login');
        [$status] = request($browser, 'register.php');
        check($status === 303, 'signed-in ' . $role . ' skips registration');
        [$status] = request($browser, 'logout.php', ['csrf_token' => $oldToken]);
        check($status === 403, 'old CSRF token cannot log out ' . $role);
        [$status] = request($browser, $role . '/index.php');
        check($status === 200, 'failed logout preserves ' . $role . ' session');
        $signedInCookie = sessionCookie($browser);
        [$status, $headers] = request($browser, 'logout.php', ['csrf_token' => $logoutToken]);
        check($status === 303 && str_contains($headers, 'logged_out=1'), $role . ' logout succeeds');
        [$status] = request($browser, $role . '/index.php');
        check($status === 303, $role . ' dashboard blocked after logout');
        $replay = client();
        curl_setopt($replay, CURLOPT_COOKIE, 'food_donation_session=' . $signedInCookie);
        [$status] = request($replay, $role . '/index.php');
        check($status === 303, $role . ' destroyed session cannot be replayed');
        curl_close($replay);
        curl_close($browser);
    }
    $browser = client();
    login($browser, $accounts['donor']['email'], $password);
    $pdo->prepare("UPDATE users SET role='volunteer' WHERE id=?")->execute([$accounts['donor']['id']]);
    [$status] = request($browser, 'donor/index.php');
    check($status === 403, 'role change immediately blocks previous role');
    [$status] = request($browser, 'volunteer/index.php');
    check($status === 200, 'current database role used');
    $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$accounts['donor']['id']]);
    [$status] = request($browser, 'volunteer/index.php');
    check($status === 303, 'suspension revokes existing session');
    [$status, , $html] = request($browser, 'login.php');
    check($status === 200 && token($html) !== '', 'new login form works after revocation');
    curl_close($browser);
    $limited = client();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        [$status] = login($limited, $accounts['admin']['email'], 'Wrong-password');
        check($status === 401, 'failed attempt ' . ($attempt + 1) . ' counted');
    }
    [$status, $headers] = login($limited, $accounts['admin']['email'], $password);
    check($status === 429 && str_contains($headers, 'Retry-After:'), 'session login cooldown enforced');
    curl_close($limited);
    curl_close($guest);
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    $pdo->beginTransaction();
    foreach ($created as $id => $email) {
        $pdo->prepare('DELETE o FROM organizations o JOIN users u ON u.id=o.user_id WHERE u.id=? AND u.email=?')->execute([$id, $email]);
        $pdo->prepare('DELETE FROM users WHERE id=? AND email=?')->execute([$id, $email]);
    }
    $pdo->commit();
    echo 'Temporary auth accounts cleaned up.' . PHP_EOL;
}
exit(isset($failed) ? 1 : 0);
