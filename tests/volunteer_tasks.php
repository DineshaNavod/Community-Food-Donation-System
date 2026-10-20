<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';require_once __DIR__.'/../includes/volunteer_tasks.php';
$pdo=db();$prefix='volunteer-check-'.bin2hex(random_bytes(5));$users=[];$foods=[];$requests=[];$tasks=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];
}
function token(string $html):string {preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);if (!isset($m[1])) {throw new RuntimeException('Missing CSRF token');}return $m[1];}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>token($html),'email'=>$email,'password'=>'Volunteer-check-2026!']);check($status===303,'fixture login');}
function donation_state(int $id):string {$q=db()->prepare('SELECT status FROM donations WHERE id=?');$q->execute([$id]);return $q->fetchColumn();}
try {
    foreach (['admin'=>'admin','donor'=>'donor','org'=>'organization','a'=>'volunteer','b'=>'volunteer','blocked'=>'volunteer'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',password_hash('Volunteer-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,$name==='blocked'?'pending':'active']);$users[$name]=(int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users['org'],$prefix.' organization']);$org=(int)$pdo->lastInsertId();
    $kinds=['ready','pending','expired','future','cancelled','assigned','collected','delivered','completed','cancelled-task','foreign'];
    for ($i=0;$i<21;$i++) {$kinds[]='extra-'.$i;}
    $past=date('Y-m-d H:i:s',time()-600);
    foreach ($kinds as $name) {
        $state=match($name) {'assigned','collected','delivered','completed','cancelled'=>$name,'expired','cancelled-task','foreign'=>'assigned',default=>str_starts_with($name,'extra-')?'assigned':'requested'};
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$users['donor'],$prefix.'-'.$name,'produce','Food <script>alert(1)</script>',3,'kg','Private pickup '.$prefix,date('Y-m-d H:i:s',$name==='future'?time()+300:time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-300:time()+7200),$state,$state==='completed'?$past:null]);$foods[$name]=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,message,status,decided_at) VALUES (?,?,?,?,?,?)')->execute([$foods[$name],$org,'Private delivery '.$prefix,'Message <b>test</b>',$name==='pending'?'pending':'approved',$name==='pending'?null:$past]);$requests[$name]=(int)$pdo->lastInsertId();
        if (in_array($state,['assigned','collected','delivered','completed'],true)) {
            $tStatus=$name==='cancelled-task'?'cancelled':(in_array($state,['delivered','completed'],true)?'delivered':($state==='collected'?'collected':'assigned'));
            $pdo->prepare('INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$requests[$name],$users[$name==='foreign'?'b':'a'],$users['admin'],$tStatus,$past,$tStatus==='assigned'?null:$past,in_array($tStatus,['collected','delivered'],true)?$past:null,$tStatus==='delivered'?$past:null]);$tasks[$name]=(int)$pdo->lastInsertId();
        }
    }
    $guest=browser();[$status]=request($guest,'volunteer/tasks.php');check($status===303,'guest task list blocked');
    $donor=browser();login($donor,$prefix.'-donor@example.test');[$status]=request($donor,'volunteer/tasks.php');check($status===403,'donor cannot view volunteer tasks');
    $a=browser();$b=browser();$admin=browser();login($a,$prefix.'-a@example.test');login($b,$prefix.'-b@example.test');login($admin,$prefix.'-admin@example.test');
    [$status,,$html]=request($a,'volunteer/tasks.php');preg_match_all('/volunteer\/task\.php\?id=[0-9]+/',$html,$links);check($status===200 && count($links[0])===20,'task list paginated at twenty');
    [,, $html]=request($a,'volunteer/tasks.php?page=2');check(str_contains($html,'Page 2 of 2'),'second task page reachable');
    [,, $html]=request($a,'volunteer/tasks.php?status=delivered');check(str_contains($html,$prefix.'-completed') && !str_contains($html,$prefix.'-assigned'),'task-status filter works');
    [,, $html]=request($a,'volunteer/tasks.php?search='.rawurlencode($prefix.'-foreign').'&volunteer_id='.$users['b']);check(str_contains($html,'0 matching tasks'),'forged volunteer identity cannot expose foreign tasks');
    [,, $html]=request($a,'volunteer/tasks.php?search=%25');check(str_contains($html,'0 matching tasks'),'search treats SQL wildcard literally');
    foreach (['status=bad','status[]=assigned','page=-1','search[]=bad'] as $filter) {[$status]=request($a,'volunteer/tasks.php?'.$filter);check($status===400,'invalid task filter rejected');}
    [$status]=request($b,'volunteer/task.php?id='.$tasks['assigned'].'&volunteer_id='.$users['a']);check($status===404,'foreign task detail protected');
    [,, $html]=request($a,'volunteer/task.php?id='.$tasks['assigned']);check(str_contains($html,'Private pickup '.$prefix) && str_contains($html,'Private delivery '.$prefix) && str_contains($html,$prefix.' organization'),'assigned volunteer sees pickup and recipient information');
    check(str_contains($html,'Food &lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($html,'Message &lt;b&gt;test&lt;/b&gt;') && !str_contains($html,'<script>alert(1)</script>'),'task content escaped');
    foreach (['assigned'=>1,'collected'=>3,'delivered'=>4,'completed'=>5] as $name=>$count) {[,, $html]=request($a,'volunteer/task.php?id='.$tasks[$name]);check(substr_count($html,'class="is-recorded"')===$count,$name.' progress uses recorded timestamps');}
    foreach (['expired','cancelled-task'] as $name) {[,, $html]=request($a,'volunteer/task.php?id='.$tasks[$name]);check(str_contains($html,'Contact the administrator before travelling') && !str_contains($html,'Private delivery '.$prefix),'closed task warning and contact privacy');}
    check(donation_state($foods['expired'])==='assigned','GET does not mutate expired donation');
    [,, $html]=request($a,'volunteer/index.php');check(str_contains($html,'volunteer/tasks.php') && str_contains($html,'volunteer/task.php?id='),'volunteer dashboard links to tasks');
    check((bool)preg_match('/href="[^"]*volunteer\/recent\.php"[^>]*>Recent collection tasks<\/a>/',$html),'dashboard recent tasks opens a page instead of a local anchor');
    [$status,,$html]=request($a,'volunteer/recent.php');preg_match_all('/volunteer\/task\.php\?id=[0-9]+/',$html,$recentLinks);
    check($status===200 && count($recentLinks[0])===5 && str_contains($html,'Latest 5 of'),'recent page displays latest five tasks');
    check((bool)preg_match('/<a[^>]*volunteer\/recent\.php[^>]*aria-current="page"/',$html) && !preg_match('/<a[^>]*volunteer\/tasks\.php[^>]*aria-current="page"/',$html),'Recent collection tasks tab visibly selected alone');
    [,, $html]=request($b,'volunteer/recent.php');check(!str_contains($html,$prefix.'-assigned') && str_contains($html,$prefix.'-foreign'),'recent page shows only signed-in volunteer tasks');
    [$status]=request($guest,'volunteer/recent.php');check($status===303,'guest recent tasks blocked');
    [$status]=request($guest,'volunteer/history.php');check($status===303,'guest history blocked');
    [$status]=request($donor,'volunteer/history.php');check($status===403,'wrong role history blocked');
    [,, $html]=request($a,'volunteer/history.php');check(str_contains($html,'2 matching tasks') && str_contains($html,$prefix.'-delivered') && str_contains($html,$prefix.'-completed') && !str_contains($html,$prefix.'-assigned'),'history contains only delivered collections');
    check((bool)preg_match('/<a[^>]*volunteer\/history\.php[^>]*aria-current="page"/',$html) && !preg_match('/<a[^>]*volunteer\/tasks\.php[^>]*aria-current="page"/',$html),'history tab highlighted without tasks tab');
    [,, $html]=request($a,'volunteer/history.php?receipt=confirmed');check(str_contains($html,'1 matching tasks') && str_contains($html,$prefix.'-completed') && !str_contains($html,$prefix.'-delivered'),'confirmed receipt history filter');
    [,, $html]=request($a,'volunteer/history.php?receipt=awaiting');check(str_contains($html,'1 matching tasks') && str_contains($html,$prefix.'-delivered') && !str_contains($html,$prefix.'-completed'),'awaiting receipt history filter');
    [,, $html]=request($b,'volunteer/history.php?volunteer_id='.$users['a']);check(str_contains($html,'0 matching tasks'),'history cannot be switched to another volunteer');
    [$status]=request($a,'volunteer/history.php?receipt=bad');check($status===400,'invalid receipt filter rejected');
    [,, $html]=request($a,'volunteer/history.php?search='.rawurlencode($prefix.'-completed'));check(str_contains($html,'1 matching tasks'),'history food search');
    [,, $html]=request($a,'volunteer/task.php?id='.$tasks['completed']);check((bool)preg_match('/<a[^>]*volunteer\/history\.php[^>]*aria-current="page"/',$html),'completed detail selects history tab');
    [$status]=request($a,'admin/assignments.php');check($status===403,'volunteer cannot assign collections');
    [,, $html]=request($admin,'admin/assignments.php');$csrf=token($html);check(str_contains($html,$prefix.'-ready') && !str_contains($html,$prefix.'-pending') && !str_contains($html,$prefix.'-future') && !str_contains($html,$prefix.'-expired'),'admin list shows eligible approved donations only');
    check(!str_contains($html,$prefix.'-blocked@example.test'),'pending volunteers excluded from choices');
    $path='admin/assign_volunteer.php?id='.$requests['ready'];$fields=['csrf_token'=>$csrf,'volunteer_id'=>$users['a']];
    [$status]=request($admin,$path);check($status===405,'GET cannot assign');
    [$status]=request($admin,$path,array_replace($fields,['csrf_token'=>'bad']));check($status===403,'assignment CSRF enforced');
    [$status]=request($admin,$path,array_replace($fields,['volunteer_id'=>['bad']]));check($status===400,'malformed volunteer rejected');
    foreach (['blocked','donor','admin'] as $name) {[$status]=request($admin,$path,array_replace($fields,['volunteer_id'=>$users[$name]]));check($status===409,'invalid '.$name.' assignee rejected');}
    foreach (['pending','expired','future','cancelled','assigned'] as $name) {[$status]=request($admin,'admin/assign_volunteer.php?id='.$requests[$name],$fields);check($status===409,'ineligible '.$name.' assignment rejected');}
    $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$users['org']]);[$status]=request($admin,$path,$fields);check($status===409 && donation_state($foods['ready'])==='requested','inactive recipient blocks assignment without mutation');$pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$users['org']]);
    [$status]=request($admin,$path,$fields+['assigned_by'=>$users['b'],'status'=>'delivered']);check($status===303 && donation_state($foods['ready'])==='assigned','assignment creates task and changes donation atomically');
    $q=$pdo->prepare('SELECT * FROM collection_tasks WHERE request_id=?');$q->execute([$requests['ready']]);$newTask=$q->fetch();check((int)$newTask['volunteer_id']===$users['a'] && (int)$newTask['assigned_by']===$users['admin'] && $newTask['status']==='assigned','assignment ownership/status server-controlled');
    [,, $html]=request($a,'volunteer/task.php?id='.$newTask['id']);check(str_contains($html,$prefix.'-ready'),'new collection visible to selected volunteer');
    [$status]=request($b,'volunteer/task.php?id='.$newTask['id']);check($status===404,'new collection hidden from other volunteer');
    [,, $html]=request($admin,'admin/assignments.php');$fields['csrf_token']=token($html);[$status]=request($admin,$path,$fields);check($status===409,'repeat assignment rejected');
    [,, $html]=request($a,'organization/request.php?id='.$requests['ready']);check(!str_contains($html,'Private delivery '.$prefix),'wrong role cannot view organization tracking');
    foreach ([$guest,$donor,$a,$b,$admin] as $c) {curl_close($c);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {
    foreach ($foods as $id) {$pdo->prepare('DELETE t FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id WHERE r.donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}
    if (isset($org)) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$org]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only volunteer test fixtures removed.'.PHP_EOL;
}
exit(isset($failed)?1:0);
