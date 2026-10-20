<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/organization_requests.php';
$pdo=db();$prefix='receipt-check-'.bin2hex(random_bytes(5));$users=[];$orgs=[];$foods=[];$requests=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $response=curl_exec($c);if ($response===false) {throw new RuntimeException(curl_error($c));}
    $size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($response,0,$size),substr($response,$size)];
}
function token(string $html):string {preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);return $m[1]??'';}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>token($html),'email'=>$email,'password'=>'Receipt-check-2026!']);check($status===303,'fixture login');}
function food_state(int $id):array {$q=db()->prepare('SELECT status,completed_at FROM donations WHERE id=?');$q->execute([$id]);return $q->fetch();}
try {
    foreach (['donor'=>'donor','a'=>'organization','b'=>'organization','volunteer'=>'volunteer','admin'=>'admin'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',password_hash('Receipt-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name],$prefix.'-'.$name]);$orgs[$name]=(int)$pdo->lastInsertId();}
    }
    $past=date('Y-m-d H:i:s',time()-600);
    foreach (['delivered','assigned','collected','cancelled','expired','pending','rejected','missing-task','missing-time','cancelled-task'] as $name) {
        $state=in_array($name,['assigned','collected','cancelled','expired'],true)?$name:'delivered';
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$users['donor'],$prefix.'-'.$name,'produce','Test receipt',2,'kg','Test pickup',date('Y-m-d H:i:s',time()-7200),date('Y-m-d H:i:s',time()-300),$state]);$foods[$name]=(int)$pdo->lastInsertId();
        $rStatus=in_array($name,['pending','rejected'],true)?$name:'approved';
        $pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,decided_at) VALUES (?,?,?,?,?)')->execute([$foods[$name],$orgs['a'],'Test delivery',$rStatus,$rStatus==='pending'?null:$past]);$requests[$name]=(int)$pdo->lastInsertId();
        if ($name!=='missing-task') {
            $tStatus=match($name) {'assigned'=>'assigned','collected'=>'collected','cancelled-task'=>'cancelled',default=>'delivered'};
            $pdo->prepare('INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$requests[$name],$users['volunteer'],$users['admin'],$tStatus,$past,$past,$past,$name==='missing-time'?null:$past]);
        }
    }
    $path='organization/confirm_receipt.php?id='.$requests['delivered'];$guest=browser();[$status]=request($guest,$path,[]);check($status===303,'guest cannot confirm');
    $donor=browser();login($donor,$prefix.'-donor@example.test');[$status]=request($donor,$path,[]);check($status===403,'other role cannot confirm');
    $a=browser();$b=browser();login($a,$prefix.'-a@example.test');login($b,$prefix.'-b@example.test');
    [$status]=request($a,$path);check($status===405,'GET cannot confirm');
    [,, $html]=request($a,'organization/request.php?id='.$requests['delivered']);$csrf=token($html);check($csrf!=='' && str_contains($html,'Confirm food receipt'),'delivered request offers confirmation');
    [$status]=request($a,$path,['confirm_received'=>'yes']);check($status===403,'missing CSRF rejected');
    [$status]=request($a,$path,['csrf_token'=>str_repeat('0',64),'confirm_received'=>'yes']);check($status===403,'forged CSRF rejected');
    [$status]=request($a,$path,['csrf_token'=>$csrf]);check($status===400,'explicit receipt acknowledgement required');
    [,, $html]=request($b,'organization/donations.php');$bToken=token($html);
    // Browse has no form on an empty list; login page provides this session's token.
    if ($bToken==='') {[,, $html]=request($b,'login.php');$bToken=token($html);}
    [$status]=request($b,$path,['csrf_token'=>$bToken,'confirm_received'=>'yes','organization_id'=>$orgs['a']]);check($status===404,'foreign organization cannot confirm through forged identity');
    check(food_state($foods['delivered'])===['status'=>'delivered','completed_at'=>null],'rejected actions leave donation unchanged');
    foreach ($requests as $name=>$id) {
        if ($name==='delivered') {continue;}
        $before=food_state($foods[$name]);[,, $html]=request($a,'organization/request.php?id='.$id);
        check(!str_contains($html,'Confirm food receipt'),'no receipt form for '.$name);
        [$status]=request($a,'organization/confirm_receipt.php?id='.$id,['csrf_token'=>$csrf,'confirm_received'=>'yes','status'=>'completed']);
        check($status===409 && food_state($foods[$name])===$before,'invalid '.$name.' transition rejected without mutation');
    }
    $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$users['a']]);
    try {confirm_organization_receipt($users['a'],$requests['delivered']);throw new RuntimeException('Suspended account accepted');}catch (DomainException $e) {check(food_state($foods['delivered'])['status']==='delivered','service rejects suspended organization');}
    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$users['a']]);
    [$status,$headers]=request($a,$path,['csrf_token'=>$csrf,'confirm_received'=>'yes','organization_id'=>$orgs['b'],'completed_at'=>'2000-01-01 00:00:00']);
    $completed=food_state($foods['delivered']);check($status===303 && str_contains($headers,'organization/request.php?id='.$requests['delivered']),'valid confirmation redirects to owned request');
    check($completed['status']==='completed' && strtotime($completed['completed_at'])>=time()-15,'completion timestamp recorded by server even after pickup deadline');
    [,, $html]=request($a,'organization/request.php?id='.$requests['delivered']);$newToken=token($html);
    check(str_contains($html,'Receipt confirmed. This donation is now completed') && substr_count($html,'class="is-recorded"')===6 && !str_contains($html,'Confirm food receipt'),'completed detail shows success and all recorded steps');
    [,, $html]=request($a,'organization/requests.php?delivery=completed');check(str_contains($html,$prefix.'-delivered'),'completed donation enters recipient history');
    [$status]=request($a,$path,['csrf_token'=>$csrf,'confirm_received'=>'yes']);check($status===403,'old confirmation token rotated');
    // Completed pages have no receipt form; retrieve the current token from login.
    if ($newToken==='') {[,, $html]=request($a,'login.php');$newToken=token($html);}
    [$status]=request($a,$path,['csrf_token'=>$newToken,'confirm_received'=>'yes']);check($status===409 && food_state($foods['delivered'])===$completed,'repeated confirmation cannot overwrite completion');
    $q=$pdo->prepare('SELECT status,delivered_at FROM collection_tasks WHERE request_id=?');$q->execute([$requests['delivered']]);check($q->fetch()===['status'=>'delivered','delivered_at'=>$past],'volunteer delivery record preserved');
    foreach ([$guest,$donor,$a,$b] as $client) {curl_close($client);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {
    foreach ($foods as $id) {$pdo->prepare('DELETE t FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id WHERE r.donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}
    foreach ($orgs as $id) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
    echo 'Only receipt test fixtures removed.'.PHP_EOL;
}
exit(isset($failed)?1:0);
