<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
$pdo=db();$base='http://localhost/Community%20Food%20Donation%20System/';$prefix='tracking-check-'.bin2hex(random_bytes(5));$users=[];$orgs=[];$foods=[];$requests=[];
function check(bool $passed,string $label):void {if (!$passed) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {global $base;curl_setopt($c,CURLOPT_URL,$base.$path);if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}$response=curl_exec($c);if ($response===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($response,0,$size),substr($response,$size)];}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);[$status]=request($c,'login.php',['csrf_token'=>$m[1],'email'=>$email,'password'=>'Tracking-check-2026!']);check($status===303,'fixture login');}
try {
    foreach (['donor'=>'donor','org-a'=>'organization','org-b'=>'organization','volunteer'=>'volunteer','admin'=>'admin'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',password_hash('Tracking-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name],$prefix.'-'.$name]);$orgs[$name]=(int)$pdo->lastInsertId();}
    }
    $kinds=['pending','rejected','cancelled','approved','assigned','collected','delivered','completed','expired','other-completed'];
    for ($i=0;$i<22;$i++) {$kinds[]='extra-'.$i;}
    $time=date('Y-m-d H:i:s',time()-600);
    foreach ($kinds as $name) {
        $state=match($name) {'assigned','collected','delivered','completed'=>$name,'other-completed'=>'completed','cancelled'=>'cancelled','expired'=>'assigned',default=>'requested'};
        $approved=in_array($name,['approved','assigned','collected','delivered','completed','expired','other-completed'],true);
        $requestStatus=$approved?'approved':(in_array($name,['rejected','cancelled'],true)?$name:'pending');
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$users['donor'],$prefix.'-'.$name,'produce','Test description',10,'kg','Private pickup '.$prefix,date('Y-m-d H:i:s',time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-300:time()+7200),$state,$state==='completed'?$time:null]);$foods[$name]=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,message,delivery_address,status,decided_at) VALUES (?,?,?,?,?,?)')->execute([$foods[$name],$orgs[$name==='other-completed'?'org-b':'org-a'],'Message <script>alert(1)</script>','Private delivery '.$prefix,$requestStatus,$requestStatus==='pending'?null:$time]);$requests[$name]=(int)$pdo->lastInsertId();
        if ($name==='other-completed') {$pdo->prepare("INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,decided_at) VALUES (?,?,?,'rejected',?)")->execute([$foods[$name],$orgs['org-a'],'Rejected recipient address',$time]);$requests['competing-rejected']=(int)$pdo->lastInsertId();}
        if (in_array($name,['assigned','collected','delivered','completed','expired','other-completed'],true)) {
            $taskStatus=in_array($name,['delivered','completed','other-completed'],true)?'delivered':($name==='collected'?'collected':'assigned');
            $pdo->prepare('INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$requests[$name],$users['volunteer'],$users['admin'],$taskStatus,$time,$taskStatus==='assigned'?null:$time,in_array($taskStatus,['collected','delivered'],true)?$time:null,$taskStatus==='delivered'?$time:null]);
        }
    }
    $guest=browser();[$status]=request($guest,'organization/requests.php');check($status===303,'guest history blocked');
    $donor=browser();login($donor,$prefix.'-donor@example.test');[$status]=request($donor,'organization/requests.php');check($status===403,'other role history blocked');
    $a=browser();$b=browser();login($a,$prefix.'-org-a@example.test');login($b,$prefix.'-org-b@example.test');
    [$status,,$html]=request($a,'organization/requests.php');preg_match_all('/organization\/request\.php\?id=[0-9]+/',$html,$links);check($status===200 && count($links[0])===20,'history paginated at twenty');
    [,, $html]=request($a,'organization/requests.php?page=2');check(str_contains($html,'Page 2 of 2'),'second page reachable');
    [$status]=request($a,'organization/requests.php?status=bad');check($status===400,'invalid request filter rejected');
    [$status]=request($a,'organization/requests.php?delivery=bad');check($status===400,'invalid delivery filter rejected');
    [,, $html]=request($a,'organization/requests.php?delivery=completed');check(str_contains($html,$prefix.'-completed') && !str_contains($html,$prefix.'-other-completed'),'completed history includes only donations received by this organization');
    check((bool)preg_match('/<a[^>]*delivery=completed[^>]*aria-current="page"[^>]*>Completed history<\/a>/',$html),'organization Completed history highlighted');
    check(!preg_match('/<a[^>]*organization\/requests\.php"[^>]*aria-current="page"[^>]*>My requests<\/a>/',$html),'My requests not highlighted on completed history');
    [,, $html]=request($a,'organization/requests.php?status=rejected');check(str_contains($html,$prefix.'-rejected') && !str_contains($html,$prefix.'-assigned'),'request-status filter works');
    [,, $html]=request($a,'organization/requests.php?delivery=expired');check(str_contains($html,$prefix.'-expired') && str_contains($html,'Expired'),'overdue task filter uses effective expired status');
    [,, $html]=request($a,'organization/requests.php?search='.rawurlencode($prefix.'-delivered'));check(str_contains($html,'1 matching requests'),'food-name search works');
    [$status]=request($a,'organization/request.php?id='.$requests['other-completed'].'&organization_id='.$orgs['org-b']);check($status===404,'forged organization parameter cannot access another request');
    [$status]=request($b,'organization/request.php?id='.$requests['assigned']);check($status===404,'other organization cannot view tracking');
    foreach (['pending','rejected','cancelled'] as $name) {[$status,,$html]=request($a,'organization/request.php?id='.$requests[$name]);check($status===200 && !str_contains($html,'Collection contacts') && !str_contains($html,$prefix.'-volunteer'),'non-approved '.$name.' request has no collection contacts');}
    [,, $html]=request($a,'organization/request.php?id='.$requests['approved']);check(str_contains($html,'Awaiting administrator assignment') && !str_contains($html,'class="is-recorded"><span class="timeline-dot" aria-hidden="true"></span><div><strong>Food delivered'),'approval alone does not imply assignment or delivery');
    foreach (['assigned'=>3,'collected'=>4,'delivered'=>5,'completed'=>6] as $name=>$recorded) {
        [$status,,$html]=request($a,'organization/request.php?id='.$requests[$name]);
        check($status===200 && substr_count($html,'class="is-recorded"')===$recorded,$name.' timeline uses recorded timestamps');
        check(str_contains($html,$prefix.'-volunteer') && str_contains($html,'Private delivery '.$prefix),'own volunteer and delivery details shown');
        check(str_contains($html,'Message &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'tracking text escaped');
    }
    [,, $html]=request($a,'organization/request.php?id='.$requests['competing-rejected']);check(!str_contains($html,'Collection contacts') && !str_contains($html,$prefix.'-volunteer'),'rejected competitor cannot see winning collection contact');
    [,, $html]=request($a,'organization/request.php?id='.$requests['expired']);check(str_contains($html,'last recorded progress'),'expired tracking explains closed progress');
    $q=$pdo->prepare('SELECT status FROM donations WHERE id=?');$q->execute([$foods['expired']]);check($q->fetchColumn()==='assigned','tracking GET does not mutate workflow status');
    [,, $html]=request($a,'organization/index.php');check(str_contains($html,'Track request') && str_contains($html,'organization/requests.php?delivery=completed'),'dashboard links to tracking and history');
    foreach ([$guest,$donor,$a,$b] as $client) {curl_close($client);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($foods as $id) {$pdo->prepare('DELETE t FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id WHERE r.donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}foreach ($orgs as $id) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only tracking test records removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
