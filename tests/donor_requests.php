<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
$pdo=db();$base='http://localhost/Community%20Food%20Donation%20System/';$prefix='request-check-'.bin2hex(random_bytes(5));$users=[];$orgs=[];$foods=[];$requests=[];
function check(bool $passed,string $label):void {if (!$passed) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {global $base;curl_setopt($c,CURLOPT_URL,$base.$path);if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}$result=curl_exec($c);if ($result===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($result,0,$size),substr($result,$size)];}
function csrf(string $html):string {if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m)) {throw new RuntimeException('Missing CSRF');}return $m[1];}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>csrf($html),'email'=>$email,'password'=>'Request-check-2026!']);check($status===303,'fixture login');}
function decide(CurlHandle $c,int $id,string $decision):array {[,, $html]=request($c,'donor/request.php?id='.$id);return request($c,'donor/decide_request.php?id='.$id,['csrf_token'=>csrf($html),'decision'=>$decision]);}
function request_status(int $id):string {global $pdo;$q=$pdo->prepare('SELECT status FROM donation_requests WHERE id=?');$q->execute([$id]);return $q->fetchColumn();}
function food_status(int $id):string {global $pdo;$q=$pdo->prepare('SELECT status FROM donations WHERE id=?');$q->execute([$id]);return $q->fetchColumn();}
try {
    foreach (['donor-a'=>'donor','donor-b'=>'donor','org-a'=>'organization','org-b'=>'organization','inactive'=>'organization'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.' '.$name,$prefix.'-'.$name.'@example.test',password_hash('Request-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,$name==='inactive'?'suspended':'active']);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name],$prefix.' '.$name.' <script>alert(1)</script>']);$orgs[$name]=(int)$pdo->lastInsertId();}
    }
    foreach (['approve','reject','expired','cancelled','assigned','collected','delivered','completed','inactive','foreign','race'] as $name) {
        $status=in_array($name,['cancelled','assigned','collected','delivered','completed'],true)?$name:'requested';
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$users[$name==='foreign'?'donor-b':'donor-a'],$prefix.'-'.$name,'produce','Test food',10,'kg','Test pickup',date('Y-m-d H:i:s',time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-3600:time()+7200),$status]);$foods[$name]=(int)$pdo->lastInsertId();
        foreach (['org-a','org-b'] as $org) {
            $pdo->prepare("INSERT INTO donation_requests (donation_id,organization_id,message,delivery_address,status) VALUES (?,?,?,?,'pending')")->execute([$foods[$name],$orgs[$name==='inactive'?'inactive':$org],'Message <script>alert(1)</script>','Private delivery '.$prefix]);$requests[$name][]=(int)$pdo->lastInsertId();
            if ($name==='inactive') {break;}
        }
    }
    $guest=browser();[$status]=request($guest,'donor/requests.php');check($status===303,'guest list blocked');
    $orgClient=browser();login($orgClient,$prefix.'-org-a@example.test');[$status]=request($orgClient,'donor/requests.php');check($status===403,'organization cannot use donor requests');
    $a=browser();login($a,$prefix.'-donor-a@example.test');$b=browser();login($b,$prefix.'-donor-b@example.test');
    [$status,,$html]=request($a,'donor/requests.php');check($status===200 && !str_contains($html,$prefix.'-foreign'),'request list scoped to donation owner');
    check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'organization names escaped');
    [$status]=request($a,'donor/requests.php?status=bad');check($status===400,'invalid status filter rejected');
    [,, $html]=request($a,'donor/requests.php?donation_id='.$foods['approve']);check(str_contains($html,$prefix.'-approve') && !str_contains($html,$prefix.'-reject'),'donation filter scopes list');
    [$status,,$html]=request($a,'donor/request.php?id='.$requests['approve'][0]);check($status===200 && str_contains($html,'Message &lt;script&gt;') && str_contains($html,'Private delivery '.$prefix),'owned request details escaped');
    [$status]=request($b,'donor/request.php?id='.$requests['approve'][0]);check($status===404,'other donor cannot view request');
    [$status]=request($a,'donor/decide_request.php?id='.$requests['approve'][0]);check($status===405,'GET cannot decide');
    [$status]=request($a,'donor/decide_request.php?id='.$requests['approve'][0],['csrf_token'=>'bad','decision'=>'approve']);check($status===403,'decision CSRF enforced');
    [$status]=decide($a,$requests['approve'][0],'invalid');check($status===422,'unknown decision rejected');
    [,, $html]=request($b,'donor/request.php?id='.$requests['foreign'][0]);[$status]=request($b,'donor/decide_request.php?id='.$requests['approve'][0],['csrf_token'=>csrf($html),'decision'=>'approve']);check($status===404,'other donor cannot decide');
    [$status]=decide($a,$requests['approve'][0],'approve');check($status===303 && request_status($requests['approve'][0])==='approved' && request_status($requests['approve'][1])==='rejected','approval and rejection of competitors committed together');
    check(food_status($foods['approve'])==='requested','approved donation awaits assignment');
    $q=$pdo->prepare('SELECT decided_at FROM donation_requests WHERE id=?');$q->execute([$requests['approve'][0]]);check($q->fetchColumn()!==null,'decision timestamp stored');
    [$status]=decide($a,$requests['approve'][1],'approve');check($status===409 && request_status($requests['approve'][0])==='approved','second allocation blocked');
    [$status]=decide($a,$requests['approve'][0],'reject');check($status===409,'approved decision cannot be reversed');
    [$status]=decide($a,$requests['reject'][0],'reject');check($status===303 && food_status($foods['reject'])==='requested' && request_status($requests['reject'][1])==='pending','one rejection preserves other pending request');
    [$status]=decide($a,$requests['reject'][1],'reject');check($status===303 && food_status($foods['reject'])==='available','last rejection returns donation to available');
    [$status]=decide($a,$requests['inactive'][0],'approve');check($status===409 && request_status($requests['inactive'][0])==='pending','inactive organization cannot be approved');
    [$status]=decide($a,$requests['inactive'][0],'reject');check($status===303,'inactive organization request may be rejected');
    foreach (['expired','cancelled','assigned','collected','delivered','completed'] as $name) {
        foreach (['approve','reject'] as $decision) {[$status]=decide($a,$requests[$name][0],$decision);check($status===409,$name.' blocks '.$decision);}
        check(request_status($requests[$name][0])==='pending','blocked decision leaves '.$name.' request unchanged');
    }
    // Hold the donation lock while two independent workers start, then release.
    $worker='require $argv[1]."/includes/db.php";require $argv[1]."/includes/donor_requests.php";try{decide_donation_request((int)$argv[2],(int)$argv[3],"approve");echo "APPROVED";}catch(DomainException $e){echo "BLOCKED";}catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}';
    $pdo->beginTransaction();$pdo->prepare('SELECT id FROM donations WHERE id=? FOR UPDATE')->execute([$foods['race']]);$workers=[];
    foreach ($requests['race'] as $requestId) {$pipes=[];$process=proc_open([PHP_BINARY,'-r',$worker,'--',dirname(__DIR__),(string)$users['donor-a'],(string)$requestId],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if (!is_resource($process)) {throw new RuntimeException('Cannot start concurrency worker');}fclose($pipes[0]);$workers[]=[$process,$pipes];}
    usleep(200000);$pdo->commit();$results=[];
    foreach ($workers as [$process,$pipes]) {$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0,'concurrent worker completed: '.$error);}
    sort($results);check($results===['APPROVED','BLOCKED'],'competing approvals produce exactly one winner');
    $q=$pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE donation_id=? AND status='approved'");$q->execute([$foods['race']]);check((int)$q->fetchColumn()===1,'exactly one approved request after concurrency test');
    foreach ([$guest,$orgClient,$a,$b] as $c) {curl_close($c);}
} catch (Throwable $exception) {if ($pdo->inTransaction()) {$pdo->rollBack();}fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($foods as $id) {$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}foreach ($orgs as $id) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Request fixtures removed; existing accounts/history preserved.'.PHP_EOL;}
exit(isset($failed)?1:0);
