<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('donor');
$roleLabel = 'Food donor';
$introduction = 'Share surplus food and help it reach your community.';
require __DIR__ . '/../includes/role_home.php';
