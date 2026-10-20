<?php
declare(strict_types=1);

// Load environment variables from the project root.
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Keep technical errors in the server log.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Colombo');

define('APP_NAME', 'Community Food Donation Coordination System');

define(
    'APP_ENV',
    $_ENV['APP_ENV'] ?? 'production'
);

// Keep the existing application path.
define(
    'APP_BASE_PATH',
    '/' . rawurlencode(basename(dirname(__DIR__)))
);

// Preserve the constant names used by the existing application.
define('DB_HOST', $_ENV['FOOD_DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['FOOD_DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['FOOD_DB_NAME'] ?? '');
define('DB_USER', $_ENV['FOOD_DB_USER'] ?? '');
define('DB_PASS', $_ENV['FOOD_DB_PASS'] ?? '');

