<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}

$root=dirname(__DIR__);$log=__DIR__.'/latest-results.log';
$suites=['registration','admin_setup','auth','dashboards','donation_creation','donation_management',
    'profile','donor_requests','organization_requests','organization_tracking','receipt_confirmation',
    'organization_profile','account_approvals','volunteer_tasks','volunteer_workflow','volunteer_profile',
    'user_management','admin_overview','admin_reports','admin_workflow','demo_setup'];
file_put_contents($log,'Full verification started '.date(DATE_ATOM).PHP_EOL);
function run_check(array $command,string $label):int
{
    global $root,$log;
    file_put_contents($log,PHP_EOL.'=== '.$label.' ==='.PHP_EOL,FILE_APPEND);
    $process=proc_open($command,[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root);
    if (!is_resource($process)) {throw new RuntimeException('Could not start '.$label);}
    fclose($pipes[0]);return proc_close($process);
}
try {
    $files=[];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && strtolower($file->getExtension())==='php') {$files[]=$file->getPathname();}
    }
    sort($files);$lintFailures=0;
    foreach ($files as $file) {if (run_check([PHP_BINARY,'-l',$file],'Syntax: '.substr($file,strlen($root)+1))!==0) {$lintFailures++;}}
    echo ($lintFailures?'FAIL':'PASS').': PHP syntax ('.count($files).' files, '.$lintFailures.' failures)'.PHP_EOL;
    $failures=$lintFailures;
    foreach ($suites as $suite) {
        clearstatcache(true,$log);$offset=filesize($log);
        $code=run_check([PHP_BINARY,__DIR__.'/'.$suite.'.php'],$suite);
        $handle=fopen($log,'rb');fseek($handle,$offset);$output=stream_get_contents($handle);fclose($handle);
        // A connection helper may exit early with code zero: require test evidence too.
        if (!str_contains($output,'PASS:') || str_contains($output,'FAIL:') || str_contains($output,'Fatal error:')) {$code=1;}
        echo ($code===0?'PASS':'FAIL').': '.$suite.($code===0?'':' (exit '.$code.')').PHP_EOL;
        if ($code!==0) {$failures++;}
    }
    $summary=count($suites).' suites finished; '.$failures.' failed checks.';
    echo $summary.PHP_EOL.'Detailed log: '.$log.PHP_EOL;
    file_put_contents($log,PHP_EOL.$summary.PHP_EOL,FILE_APPEND);
    exit($failures?1:0);
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);exit(1);}
