<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('volunteer');
$roleLabel = 'Collection volunteer';
$introduction = 'Help connect donors and organizations through food collection and delivery.';
require __DIR__ . '/../includes/role_home.php';
