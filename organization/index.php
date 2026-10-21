<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('organization');
$roleLabel = 'Community organization';
$introduction = 'Connect available food with the people your organization supports.';
require __DIR__ . '/../includes/role_home.php';
