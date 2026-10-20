<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';require_once __DIR__.'/../includes/demo_setup.php';
try {foreach (load_sample_data(db()) as $message) {echo $message.PHP_EOL;}}
catch (Throwable $exception) {fwrite(STDERR,'Demo setup failed: '.$exception->getMessage().PHP_EOL);exit(1);}
