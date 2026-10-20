<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';require_once __DIR__.'/../includes/admin_accounts.php';
$pdo=db();$prefix='approval-check-'.bin2hex(random_bytes(5));$users=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];
}
function token(string $html):string {preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);if (!isset($m[1])) {throw new RuntimeException('Missing CSRF token');}return $m[1];}
function login(CurlHandle $c,string $email):int {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>token($html),'email'=>$email,'password'=>'Approval-check-2026!']);return $status;}
function row(int $id):array {$q=db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();}
try {
    foreach (['admin'=>['admin','active'],'org'=>['organization','pending'],'volunteer'=>['volunteer','pending'],'reject'=>['organization','pending'],'missing'=>['organization','pending'],'donor'=>['donor','active'],'pending-donor'=>['donor','pending'],'active-org'=>['organization','active']] as $name=>[$role,$status]) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.' <script>alert(1)</script> '.$name,$prefix.'-'.$name.'@example.test',password_hash('Approval-check-2026!',PASSWORD_DEFAULT),'0700000000','Test registration address',$role,$status]);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization' && $name!=='missing') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name,description) VALUES (?,?,?)')->execute([$users[$name],$prefix.'-'.$name,'Help <b>neighbors</b>']);}
    }
    $before=row($users['org']);$guest=browser();[$status]=request($guest,'admin/accounts.php');check($status===303,'guest cannot review registrations');
    $organization=browser();check(login($organization,$before['email'])===403,'pending organization cannot sign in');
    $donor=browser();check(login($donor,$prefix.'-donor@example.test')===303,'donor fixture login');[$status]=request($donor,'admin/accounts.php');check($status===403,'donor cannot review registrations');
    [$status]=request($donor,'admin/decide_account.php?id='.$users['org'],[]);check($status===403,'donor cannot approve');
    $admin=browser();check(login($admin,$prefix.'-admin@example.test')===303,'admin fixture login');
    [,, $html]=request($admin,'admin/index.php');check(str_contains($html,'admin/accounts.php'),'dashboard links to account approvals');
    [$status,,$html]=request($admin,'admin/accounts.php');$csrf=token($html);check($status===200 && str_contains($html,$prefix.'-org@example.test'),'pending organization listed');
    check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($html,'Help &lt;b&gt;neighbors&lt;/b&gt;') && !str_contains($html,'<script>alert(1)</script>'),'registration details escaped');
    check(!str_contains($html,$prefix.'-donor@example.test') && !str_contains($html,$prefix.'-active-org@example.test'),'only eligible pending roles listed');
    [,, $html]=request($admin,'admin/accounts.php?role=organization');check(!str_contains($html,$prefix.'-volunteer@example.test'),'organization filter works');
    [$status]=request($admin,'admin/accounts.php?role=admin');check($status===400,'invalid role filter rejected');
    $path='admin/decide_account.php?id='.$users['org'];$fields=['csrf_token'=>$csrf,'decision'=>'approve','reviewed'=>'yes'];
    [$status]=request($admin,$path);check($status===405,'GET cannot approve');
    [$status]=request($admin,$path,array_replace($fields,['csrf_token'=>'bad']));check($status===403,'CSRF enforced');
    [$status]=request($admin,$path,array_replace($fields,['reviewed'=>'']));check($status===400,'explicit review required');
    [$status]=request($admin,$path,array_replace($fields,['decision'=>'admin']));check($status===400,'invalid decision rejected');
    foreach (['missing','donor','pending-donor','active-org','admin'] as $name) {[$status]=request($admin,'admin/decide_account.php?id='.$users[$name],$fields);check($status===409,'invalid '.$name.' approval blocked');}
    [$status]=request($admin,$path,$fields+['role'=>'admin','password'=>'forged']);$after=row($users['org']);check($status===303 && $after['status']==='active','organization approved');
    unset($before['status'],$before['updated_at'],$after['status'],$after['updated_at']);check($before===$after,'approval preserves identity, role and password');
    check(login($organization,$prefix.'-org@example.test')===303,'approved organization can sign in');[$status]=request($organization,'organization/profile.php');check($status===200,'approved organization can use its module');
    [,, $html]=request($admin,'admin/accounts.php');$fields['csrf_token']=token($html);check(str_contains($html,'Account approved. This user can now sign in.'),'approval success displayed');
    [$status]=request($admin,$path,$fields);check($status===409,'repeat approval rejected');
    $fields['decision']='reject';[$status]=request($admin,'admin/decide_account.php?id='.$users['reject'],$fields);check($status===303 && row($users['reject'])['status']==='rejected','registration rejection saved');
    $rejected=browser();check(login($rejected,$prefix.'-reject@example.test')===403,'rejected organization cannot sign in');
    [,, $html]=request($admin,'admin/accounts.php');$fields=['csrf_token'=>token($html),'decision'=>'approve','reviewed'=>'yes'];
    [$status]=request($admin,'admin/decide_account.php?id='.$users['volunteer'],$fields);check($status===303 && row($users['volunteer'])['status']==='active','volunteer approved');
    $volunteer=browser();check(login($volunteer,$prefix.'-volunteer@example.test')===303,'approved volunteer can sign in');
    $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$users['admin']]);
    try {decide_pending_account($users['admin'],$users['missing'],'reject');throw new RuntimeException('Inactive admin accepted');}catch (DomainException $e) {check(row($users['missing'])['status']==='pending','service rejects inactive administrator');}
    foreach ([$guest,$organization,$donor,$admin,$rejected,$volunteer] as $c) {curl_close($c);}
} catch (Throwable $e) {fwrite(STDERR,'FAIL: '.$e->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($users as $id) {$pdo->prepare('DELETE FROM organizations WHERE user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only account approval fixtures removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
