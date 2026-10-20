<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/admin_reports.php';$pdo=db();$prefix='report-check-'.bin2hex(random_bytes(5));$users=[];$foods=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function grouped(array $rows,string $field='total'):array {$result=[];foreach ($rows as $row) {$result[$row['label']]=(float)$row[$field];}return $result;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path):array {curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);curl_setopt($c,CURLOPT_HTTPGET,true);$r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query(['csrf_token'=>$m[1],'email'=>$email,'password'=>'Report-check-2026!']));curl_exec($c);check(curl_getinfo($c,CURLINFO_HTTP_CODE)===303,'fixture login');}
try {
    foreach ([['2026-02-30','2026-03-01'],['2026-03-02','2026-03-01'],['2026-01-01',''],[['bad'],'2026-01-01'],['9999-01-01','9999-02-01']] as [$start,$end]) {
        try {report_date_range($start,$end);throw new RuntimeException('Invalid range accepted');}catch (InvalidArgumentException $exception) {check(true,'invalid date range rejected');}
    }
    $range=report_date_range('2100-02-28','2100-03-01');check($range['parameters']===['2100-02-28 00:00:00','2100-03-02 00:00:00'],'inclusive end date translated to exclusive next midnight');
    $baseline=admin_report_data($range);$allBefore=admin_report_data(report_date_range('',''));
    $start='2100-02-28 00:00:00';$end='2100-03-01 23:59:59';$before='2100-02-27 23:59:59';$after='2100-03-02 00:00:00';
    $hash=password_hash('Report-check-2026!',PASSWORD_DEFAULT);
    foreach (['admin'=>'admin','donor'=>'donor','org'=>'organization','volunteer'=>'volunteer'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status,created_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',$hash,'0700000000','Test address',$role,'active',match($name) {'donor'=>$start,'org'=>$end,default=>$before}]);$users[$name]=(int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users['org'],$prefix]);$org=(int)$pdo->lastInsertId();
    foreach (['kg','portions','expired','pending','before','after'] as $name) {
        $state=match($name) {'kg','portions','after'=>'completed','before'=>'delivered','expired'=>'assigned',default=>'requested'};
        $created=match($name) {'portions'=>$end,'before'=>$before,'after'=>$after,default=>$start};
        $completed=match($name) {'kg'=>$end,'portions'=>$start,'after'=>$after,default=>null};
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,created_at,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$users['donor'],$prefix.'-'.$name,'produce','Report fixture',$name==='kg'?2.5:10,$name==='portions'?'portions':'kg','Test pickup','2020-01-01 00:00:00',$name==='expired'?'2020-01-02 00:00:00':'2100-04-01 00:00:00',$state,$created,$completed]);$foods[$name]=(int)$pdo->lastInsertId();
        $rState=match($name) {'pending'=>'pending','expired'=>'rejected',default=>'approved'};
        $pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,created_at,decided_at) VALUES (?,?,?,?,?,?)')->execute([$foods[$name],$org,'Test delivery',$rState,$created,$rState==='pending'?null:$created]);$requestId=(int)$pdo->lastInsertId();
        if (in_array($name,['kg','portions','before','after'],true)) {
            $assigned=match($name) {'kg'=>$start,'after'=>$after,default=>$before};$delivered=match($name) {'kg'=>$end,'portions'=>$start,default=>$after};
            $pdo->prepare("INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES (?,?,?,'delivered',?,?,?,?)")->execute([$requestId,$users['volunteer'],$users['admin'],$assigned,$assigned,$assigned,$delivered]);
        }
    }
    $report=admin_report_data($range);
    foreach (['users'=>2,'donations'=>4,'requests'=>4,'assignments'=>1,'deliveries'=>2,'receipts'=>2] as $key=>$increment) {check($report['totals'][$key]-$baseline['totals'][$key]===$increment,$key.' counted by its own event timestamp');}
    foreach (['donations'=>['completed'=>2,'expired'=>1,'requested'=>1],'requests'=>['approved'=>2,'rejected'=>1,'pending'=>1],'tasks'=>['delivered'=>1],'users'=>['donor'=>1,'organization'=>1]] as $key=>$expected) {
        $actual=grouped($report[$key]);$previous=grouped($baseline[$key]);foreach ($expected as $label=>$increment) {check(($actual[$label]??0)-($previous[$label]??0)===$increment+0.0,$key.' breakdown '.$label);}
        $totalKey=$key==='tasks'?'assignments':$key;check((int)array_sum($actual)===$report['totals'][$totalKey],$key.' breakdown reconciles with total');
    }
    $quantities=grouped($report['received_quantities'],'quantity');$previous=grouped($baseline['received_quantities'],'quantity');check(($quantities['kg']??0)-($previous['kg']??0)===2.5 && ($quantities['portions']??0)-($previous['portions']??0)===10.0,'receipt quantities kept separate by unit and completion date');
    $all=admin_report_data(report_date_range('',''));foreach (['users'=>4,'donations'=>6,'requests'=>6,'assignments'=>4,'deliveries'=>4,'receipts'=>3] as $key=>$increment) {check($all['totals'][$key]-$allBefore['totals'][$key]===$increment,'all-time '.$key.' includes fixtures outside date range');}
    $guest=browser();[$status]=request($guest,'admin/reports.php');check($status===303,'guest reports blocked');
    foreach (['donor','org','volunteer'] as $name) {$c=browser();login($c,$prefix.'-'.$name.'@example.test');[$status]=request($c,'admin/reports.php');check($status===403,$name.' reports blocked');curl_close($c);}
    $admin=browser();login($admin,$prefix.'-admin@example.test');[$status,,$html]=request($admin,'admin/reports.php?start=2100-02-28&end=2100-03-01');check($status===200 && str_contains($html,'Reports and statistics') && str_contains($html,'Food received by unit') && str_contains($html,'2100-02-28 to 2100-03-01'),'report page renders filtered summaries');
    check((bool)preg_match('/<a[^>]*admin\/reports\.php[^>]*aria-current="page"/',$html),'reports navigation selected');
    check(str_contains($html,'By delivery timestamp') && str_contains($html,'not historical snapshots') && !str_contains($html,$hash),'report meanings visible without credentials');
    foreach (['start=2026-02-30&end=2026-03-01','start[]=bad&end=2026-01-01','start=2026-03-02&end=2026-03-01','start=2026-01-01'] as $query) {[$status]=request($admin,'admin/reports.php?'.$query);check($status===400,'invalid HTTP date filter rejected');}
    [,, $html]=request($admin,'admin/reports.php?start=2200-01-01&end=2200-01-02');check(str_contains($html,'No records in this period.') && str_contains($html,'No confirmed food receipts'),'empty reports explained');
    [,, $html]=request($admin,'admin/index.php');check(str_contains($html,'admin/reports.php'),'dashboard links to reports');
    $q=$pdo->prepare('SELECT status FROM donations WHERE id=?');$q->execute([$foods['expired']]);check($q->fetchColumn()==='assigned','reports never mutate overdue donations');
    curl_close($guest);curl_close($admin);
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($foods as $id) {$pdo->prepare('DELETE t FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id WHERE r.donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}if (isset($org)) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$org]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only report test fixtures removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
