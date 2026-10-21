<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$roleLabel = 'Administrator';
$introduction = 'Support the people and coordination behind community food donations.';
require __DIR__ . '/../includes/role_home.php';
