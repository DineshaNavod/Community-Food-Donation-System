<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/volunteer_tasks.php';
require_once __DIR__.'/../includes/organization_requests.php';
require_once __DIR__.'/../includes/donor_requests.php';
$pdo=db();$prefix='workflow-check-'.bin2hex(random_bytes(5));$users=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];
}
function token(string $html):string {preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);if (!isset($m[1])) {throw new RuntimeException('Missing CSRF token');}return $m[1];}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>token($html),'email'=>$email,'password'=>'Workflow-check-2026!']);check($status===303,'fixture login');}
function snapshot(int $id):array {$q=db()->prepare('SELECT t.*,d.status AS donation_status,d.completed_at FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id JOIN donations d ON d.id=r.donation_id WHERE t.id=?');$q->execute([$id]);return $q->fetch();}
function cross_role_progress(CurlHandle $donor,CurlHandle $organization,CurlHandle $volunteer,CurlHandle $admin,int $food,int $requestId,int $taskId,string $state,string $taskState):void
{
    global $users,$prefix;
    $before=snapshot($taskId);
    foreach ([[$donor,'donor/donation.php?id='.$food],[$organization,'organization/request.php?id='.$requestId],
        [$volunteer,'volunteer/task.php?id='.$taskId],[$admin,'admin/donation.php?id='.$food]] as [$client,$path]) {
        [$status,,$html]=request($client,$path);check($status===200 && str_contains($html,$prefix) && str_contains($html,status_badge($state)),$path.' reflects '.$state);
    }
    $donorData=dashboard_data(['id'=>$users['donor'],'role'=>'donor']);
    $orgData=dashboard_data(['id'=>$users['org'],'role'=>'organization']);
    $volData=dashboard_data(['id'=>$users['a'],'role'=>'volunteer']);
    check(!$donorData['error'] && !$orgData['error'] && !$volData['error'],'all participant dashboards load during '.$state);
    check((int)$donorData['statistics'][3][1]===($state==='completed'?1:0)
        && (int)$orgData['statistics'][3][1]===($state==='completed'?1:0),'donor and recipient receipt totals agree during '.$state);
    check((int)$orgData['statistics'][2][1]===($state==='completed'?0:1),'organization in-progress count follows '.$state);
    check((int)$volData['statistics'][1][1]===($taskState==='assigned'?1:0)
        && (int)$volData['statistics'][2][1]===(in_array($taskState,['accepted','collected'],true)?1:0)
        && (int)$volData['statistics'][3][1]===($taskState==='delivered'?1:0),'volunteer counters follow '.$taskState);
    check(snapshot($taskId)===$before,'cross-role reads preserve workflow at '.$state);
}
try {
    foreach (['admin'=>'admin','donor'=>'donor','org'=>'organization','a'=>'volunteer','b'=>'volunteer'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',password_hash('Workflow-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users['org'],$prefix]);$org=(int)$pdo->lastInsertId();
    $available=date('Y-m-d H:i:s',time()-7200);$deadline=date('Y-m-d H:i:s',time()+7200);
    $pdo->prepare("INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at) VALUES (?,?,'produce','Workflow test',2,'kg','Test pickup',?,?)")->execute([$users['donor'],$prefix,$available,$deadline]);$food=(int)$pdo->lastInsertId();
    $requestId=submit_organization_request($users['org'],$food,'Test request','Test delivery address');
    decide_donation_request($users['donor'],$requestId,'approve');assign_collection_volunteer($users['admin'],$requestId,$users['a']);
    $q=$pdo->prepare('SELECT id FROM collection_tasks WHERE request_id=?');$q->execute([$requestId]);$taskId=(int)$q->fetchColumn();check($taskId>0,'request, donor approval and administrator assignment integrated');
    $path='volunteer/update_task.php?id='.$taskId;$detail='volunteer/task.php?id='.$taskId;
    $guest=browser();[$status]=request($guest,$path,[]);check($status===303,'guest updates blocked');
    $donor=browser();login($donor,$prefix.'-donor@example.test');[$status]=request($donor,$path,[]);check($status===403,'wrong role updates blocked');
    $a=browser();$b=browser();$organization=browser();$admin=browser();login($a,$prefix.'-a@example.test');login($b,$prefix.'-b@example.test');login($organization,$prefix.'-org@example.test');login($admin,$prefix.'-admin@example.test');
    cross_role_progress($donor,$organization,$a,$admin,$food,$requestId,$taskId,'assigned','assigned');
    [,, $html]=request($organization,'organization/donations.php?search='.rawurlencode($prefix));check(!str_contains($html,'organization/donation.php?id='.$food.'"'),'allocated food leaves organization browsing');
    [,, $html]=request($guest,'index.php');check(!str_contains($html,$prefix),'allocated food leaves public food preview');
    [,, $html]=request($a,$detail);$csrf=token($html);check(str_contains($html,'value="accept"') && !str_contains($html,'value="collect"'),'assigned page offers acceptance only');
    $fields=['csrf_token'=>$csrf,'action'=>'accept','confirm_update'=>'yes'];$initial=snapshot($taskId);
    [$status]=request($a,$path);check($status===405,'GET cannot update tasks');
    [$status]=request($a,$path,array_replace($fields,['csrf_token'=>'bad']));check($status===403,'CSRF enforced');
    [$status]=request($a,$path,array_replace($fields,['confirm_update'=>'']));check($status===400,'explicit update acknowledgement required');
    foreach (['complete',['bad']] as $action) {[$status]=request($a,$path,array_replace($fields,['action'=>$action]));check($status===400,'invalid action rejected');}
    foreach (['collect','deliver'] as $action) {[$status]=request($a,$path,array_replace($fields,['action'=>$action]));check($status===409 && snapshot($taskId)===$initial,'cannot skip to '.$action);}
    [,, $html]=request($b,'volunteer/tasks.php');[$status]=request($b,$path,['csrf_token'=>token($html),'action'=>'accept','confirm_update'=>'yes','volunteer_id'=>$users['a']]);check($status===404 && snapshot($taskId)===$initial,'another volunteer cannot update through forged identity');
    foreach (['expired','future','cancelled','rejected','cancelled-task'] as $case) {
        if ($case==='expired') {$pdo->prepare('UPDATE donations SET deadline_at=? WHERE id=?')->execute([date('Y-m-d H:i:s',time()-300),$food]);}
        if ($case==='future') {$pdo->prepare('UPDATE donations SET available_at=? WHERE id=?')->execute([date('Y-m-d H:i:s',time()+300),$food]);}
        if ($case==='cancelled') {$pdo->prepare("UPDATE donations SET status='cancelled' WHERE id=?")->execute([$food]);}
        if ($case==='rejected') {$pdo->prepare("UPDATE donation_requests SET status='rejected' WHERE id=?")->execute([$requestId]);}
        if ($case==='cancelled-task') {$pdo->prepare("UPDATE collection_tasks SET status='cancelled' WHERE id=?")->execute([$taskId]);}
        $before=snapshot($taskId);[$status]=request($a,$path,$fields);check($status===409 && snapshot($taskId)===$before,$case.' acceptance blocked without mutation');
        $pdo->prepare("UPDATE donations SET status='assigned',available_at=?,deadline_at=? WHERE id=?")->execute([$available,$deadline,$food]);
        $pdo->prepare("UPDATE donation_requests SET status='approved' WHERE id=?")->execute([$requestId]);$pdo->prepare("UPDATE collection_tasks SET status='assigned' WHERE id=?")->execute([$taskId]);
    }
    $beforeInactive=snapshot($taskId);$pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$users['a']]);
    try {update_volunteer_task($users['a'],$taskId,'accept');throw new RuntimeException('Suspended volunteer accepted');}catch (DomainException $e) {check(snapshot($taskId)===$beforeInactive,'inactive volunteer rejected by service');}
    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$users['a']]);
    [$status,$headers]=request($a,$path,$fields+['status'=>'delivered','accepted_at'=>'2000-01-01 00:00:00','volunteer_id'=>$users['b']]);$accepted=snapshot($taskId);
    check($status===303 && str_contains($headers,'volunteer/task.php?id='.$taskId) && $accepted['status']==='accepted' && $accepted['donation_status']==='assigned','acceptance records task only and redirects');
    check(strtotime($accepted['accepted_at'])>=time()-15 && (int)$accepted['volunteer_id']===$users['a'],'timestamp and ownership server-controlled');
    [$status]=request($a,$path,$fields);check($status===403,'successful update rotates old CSRF token');
    [,, $html]=request($a,$detail);$fields['csrf_token']=token($html);check(str_contains($html,'Task accepted.') && str_contains($html,'value="collect"'),'accepted page offers collection and success');
    cross_role_progress($donor,$organization,$a,$admin,$food,$requestId,$taskId,'assigned','accepted');
    [$status]=request($a,$path,$fields);check($status===409 && snapshot($taskId)===$accepted,'repeated acceptance preserves first timestamp');
    $fields['action']='collect';$pdo->prepare('UPDATE donations SET deadline_at=? WHERE id=?')->execute([date('Y-m-d H:i:s',time()-300),$food]);
    [$status]=request($a,$path,$fields);check($status===409 && snapshot($taskId)['status']==='accepted','collection after pickup deadline blocked');$pdo->prepare('UPDATE donations SET deadline_at=? WHERE id=?')->execute([$deadline,$food]);
    [$status]=request($a,$path,$fields);$collected=snapshot($taskId);check($status===303 && $collected['status']==='collected' && $collected['donation_status']==='collected' && $collected['collected_at']!==null && $collected['accepted_at']===$accepted['accepted_at'],'collection updates task and donation together');
    cross_role_progress($donor,$organization,$a,$admin,$food,$requestId,$taskId,'collected','collected');
    [,, $html]=request($a,$detail);$fields['csrf_token']=token($html);check(str_contains($html,'value="deliver"'),'collected page offers delivery');
    [$status]=request($a,$path,$fields);check($status===409 && snapshot($taskId)===$collected,'repeated collection preserves timestamps');
    $pdo->prepare('UPDATE donations SET deadline_at=? WHERE id=?')->execute([date('Y-m-d H:i:s',time()-300),$food]);$fields['action']='deliver';
    [$status]=request($a,$path,$fields);$delivered=snapshot($taskId);check($status===303 && $delivered['status']==='delivered' && $delivered['donation_status']==='delivered' && $delivered['delivered_at']!==null && $delivered['completed_at']===null,'delivery after collection works beyond pickup deadline without completing receipt');
    cross_role_progress($donor,$organization,$a,$admin,$food,$requestId,$taskId,'delivered','delivered');
    [,, $html]=request($a,$detail);$fields['csrf_token']=token($html);check(str_contains($html,'Awaiting the organization') && !str_contains($html,'name="action"'),'delivered page awaits organization receipt');
    [$status]=request($a,$path,$fields);check($status===409 && snapshot($taskId)===$delivered,'repeated delivery preserves timestamps');
    [,, $html]=request($organization,'organization/request.php?id='.$requestId);check(str_contains($html,'Confirm food receipt'),'organization sees delivered donation ready for confirmation');
    [$status]=request($organization,'organization/confirm_receipt.php?id='.$requestId,['csrf_token'=>token($html),'confirm_received'=>'yes']);$completed=snapshot($taskId);
    check($status===303 && $completed['donation_status']==='completed' && $completed['completed_at']!==null && $completed['status']==='delivered','organization receipt completes integrated workflow');
    [$status]=request($a,$path,$fields);check($status===409 && snapshot($taskId)===$completed,'completed donation cannot be reopened by volunteer');
    [,, $html]=request($a,$detail);check(substr_count($html,'class="is-recorded"')===5,'completed volunteer timeline includes receipt');
    cross_role_progress($donor,$organization,$a,$admin,$food,$requestId,$taskId,'completed','delivered');
    [,, $html]=request($donor,'donor/donation.php?id='.$food);check((bool)preg_match('/<a[^>]*status=completed[^>]*aria-current="page"/',$html),'completed donor detail keeps Completed history selected');
    foreach ([[$donor,'donor/donations.php?status=completed'],[$organization,'organization/requests.php?delivery=completed'],[$a,'volunteer/history.php']] as [$client,$page]) {[,, $html]=request($client,$page);check(str_contains($html,$prefix),'completed food appears in '.$page);}
    // Distinct timestamps prove receipt updates propagate even though the task stays delivered.
    $taskTime=date('Y-m-d H:i:s',time()-120);$receiptTime=date('Y-m-d H:i:s',time()-60);
    $pdo->prepare('UPDATE collection_tasks SET updated_at=? WHERE id=?')->execute([$taskTime,$taskId]);
    $pdo->prepare('UPDATE donations SET updated_at=? WHERE id=?')->execute([$receiptTime,$food]);
    $dashboard=dashboard_data(['id'=>$users['a'],'role'=>'volunteer']);check($dashboard['records'][0]['updated_at']===$receiptTime,'volunteer dashboard uses latest receipt update rather than old task time');
    foreach ([$guest,$donor,$a,$b,$organization,$admin] as $c) {curl_close($c);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {
    if (isset($food)) {$pdo->prepare('DELETE t FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id WHERE r.donation_id=?')->execute([$food]);$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$food]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$food]);}
    if (isset($org)) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$org]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only volunteer workflow fixtures removed.'.PHP_EOL;
}
exit(isset($failed)?1:0);
