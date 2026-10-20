<?php
declare(strict_types=1);

// A second protection in addition to database/.htaccess.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Administrator setup is available from the command line only.');
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_setup.php';

$options = getopt('', ['name:', 'email:', 'phone:', 'address:', 'help']);
if (isset($options['help']) || !$options) {
    echo "Usage: php database/create_admin.php --name=NAME --email=EMAIL --phone=PHONE --address=ADDRESS\n";
    echo "Supply the password through standard input, never as a command-line argument.\n";
    exit(isset($options['help']) ? 0 : 1);
}
$details = ['full_name' => $options['name'] ?? null, 'email' => $options['email'] ?? null,
    'phone' => $options['phone'] ?? null, 'address' => $options['address'] ?? null];
fwrite(STDERR, "Reading administrator password from standard input.\n");
$passwordLine = fgets(STDIN, 1024);
if ($passwordLine === false) {
    fwrite(STDERR, "No password received. No account was created.\n");
    exit(1);
}
$password = rtrim($passwordLine, "\r\n");
try {
    create_initial_admin(db(), $details, $password);
    echo "Initial administrator created successfully. Sign in using the supplied email and password.\n";
} catch (PDOException $exception) {
    error_log('Administrator setup database failure. Code: ' . $exception->getCode());
    fwrite(STDERR, "Setup could not be completed. Check your database settings and try again.\n");
    exit(1);
} catch (InvalidArgumentException | RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    unset($password, $passwordLine);
}
