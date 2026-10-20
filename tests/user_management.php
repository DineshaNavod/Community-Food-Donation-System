<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';require_once __DIR__.'/../includes/admin_accounts.php';
$pdo=db();$prefix='user-management-'.bin2hex(random_bytes(5));$users=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];
}
function token(string $html):string {preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);if (!isset($m[1])) {throw new RuntimeException('Missing CSRF token');}return $m[1];}
function login(CurlHandle $c,string $email):int {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>token($html),'email'=>$email,'password'=>'User-management-2026!']);return $status;}
function row(int $id):array {$q=db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();}
try {
    $fixtures=['admin'=>['admin','active'],'other-admin'=>['admin','active'],'donor'=>['donor','active'],'org'=>['organization','active'],'volunteer'=>['volunteer','active'],'pending'=>['organization','pending'],'rejected'=>['organization','rejected'],'missing'=>['organization','suspended']];
    for ($i=0;$i<21;$i++) {$fixtures['extra-'.$i]=['donor','active'];}
    $hash=password_hash('User-management-2026!',PASSWORD_DEFAULT);
    foreach ($fixtures as $name=>[$role,$status]) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.' <script>alert(1)</script> '.$name,$prefix.'-'.$name.'@example.test',$hash,'0700000000','Test address',$role,$status]);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization' && $name!=='missing') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name],$prefix.' organization '.$name]);}
    }
    $guest=browser();[$status]=request($guest,'admin/users.php');check($status===303,'guest list blocked');
    $donor=browser();check(login($donor,$prefix.'-donor@example.test')===303,'donor login');[$status]=request($donor,'admin/users.php');check($status===403,'other role list blocked');
    [$status]=request($donor,'admin/change_access.php?id='.$users['org'],[]);check($status===403,'other role access changes blocked');
    $admin=browser();check(login($admin,$prefix.'-admin@example.test')===303,'admin login');
    [,, $html]=request($admin,'admin/index.php');check(str_contains($html,'admin/users.php'),'dashboard has user management link');
    $query='search='.rawurlencode($prefix);[$status,,$html]=request($admin,'admin/users.php?'.$query);$csrf=token($html);
    check($status===200 && str_contains($html,'29 matching users') && substr_count($html,'<summary>Contact and registration details</summary>')===20,'filtered list paginated at twenty');
    check((bool)preg_match('/<a[^>]*admin\/users\.php[^>]*aria-current="page"/',$html),'Registered users tab highlighted');
    check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>') && !str_contains($html,$hash),'details escaped without password hashes');
    [,, $html]=request($admin,'admin/users.php?'.$query.'&page=2');check(str_contains($html,'Page 2 of 2') && substr_count($html,'<summary>Contact and registration details</summary>')===9,'second page shows remaining users');
    [,, $html]=request($admin,'admin/users.php?'.$query.'&role=organization&status=pending');check(str_contains($html,'1 matching users') && str_contains($html,$prefix.'-pending@example.test') && str_contains($html,'Review approval'),'combined filters and pending approval link');
    [,, $html]=request($admin,'admin/users.php?search='.rawurlencode($prefix.' organization org'));check(str_contains($html,'1 matching users') && str_contains($html,$prefix.'-org@example.test'),'organization name search works');
    [,, $html]=request($admin,'admin/users.php?search=%25'.$prefix);check(str_contains($html,'0 matching users'),'wildcards treated literally');
    foreach (['role=bad','status=bad','search[]=bad','page=-1'] as $filter) {[$status]=request($admin,'admin/users.php?'.$filter);check($status===400,'invalid list filter rejected');}
    $path='admin/change_access.php?id='.$users['donor'];$fields=['csrf_token'=>$csrf,'action'=>'suspend','reviewed'=>'yes'];$before=row($users['donor']);
    [$status]=request($admin,$path);check($status===405,'GET cannot change access');
    [$status]=request($admin,$path,array_replace($fields,['csrf_token'=>'bad']));check($status===403,'CSRF enforced');
    [$status]=request($admin,$path,array_replace($fields,['reviewed'=>'']));check($status===400,'explicit review required');
    foreach (['delete','approve',['bad']] as $action) {[$status]=request($admin,$path,array_replace($fields,['action'=>$action]));check($status===400,'invalid access action rejected');}
    foreach (['admin','other-admin','pending','rejected'] as $name) {[$status]=request($admin,'admin/change_access.php?id='.$users[$name],$fields);check($status===409,'protected '.$name.' state rejected');}
    [$status]=request($admin,'admin/change_access.php?id=2147483647',$fields);check($status===404,'missing account returns not found');
    [$status]=request($admin,'admin/change_access.php?id='.$users['missing'],array_replace($fields,['action'=>'reactivate']));check($status===409,'organization without profile cannot reactivate');
    [$status,$headers]=request($admin,$path,$fields+['role'=>'admin','password'=>'forged','status'=>'active']);$suspended=row($users['donor']);
    check($status===303 && str_contains($headers,'admin/users.php') && $suspended['status']==='suspended','donor suspension saved and redirects');
    $original=$before;$changed=$suspended;unset($original['status'],$original['updated_at'],$changed['status'],$changed['updated_at']);check($original===$changed,'suspension preserves identity, role and password');
    [$status]=request($donor,'donor/index.php');check($status===303,'existing suspended session loses protected access');
    $fresh=browser();check(login($fresh,$before['email'])===403,'suspended account cannot sign in');
    [,, $html]=request($admin,'admin/users.php?'.$query.'&status=suspended');check(str_contains($html,'Account suspended.') && str_contains($html,'Suspended') && str_contains($html,'Reactivate'),'suspension message and correct next action');$fields['csrf_token']=token($html);
    [$status]=request($admin,$path,$fields);check($status===409 && row($users['donor'])===$suspended,'duplicate suspension rejected without mutation');
    $fields['action']='reactivate';[$status]=request($admin,$path,$fields);check($status===303 && row($users['donor'])['status']==='active','suspended donor reactivated');
    [$status]=request($donor,'donor/index.php');check($status===303,'session cleared during suspension stays signed out');check(login($fresh,$before['email'])===303,'reactivated donor signs in with original password');
    [,, $html]=request($admin,'admin/users.php?'.$query);$fields['csrf_token']=token($html);[$status]=request($admin,$path,$fields);check($status===409,'duplicate reactivation rejected');
    foreach (['org','volunteer'] as $name) {change_account_access($users['admin'],$users[$name],'suspend');check(row($users[$name])['status']==='suspended',$name.' suspension supported');change_account_access($users['admin'],$users[$name],'reactivate');check(row($users[$name])['status']==='active',$name.' reactivation supported');}
    $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$users['admin']]);
    try {change_account_access($users['admin'],$users['donor'],'suspend');throw new RuntimeException('Inactive admin accepted');}catch (DomainException $exception) {check(row($users['donor'])['status']==='active','inactive administrator rejected by service');}
    foreach ([$guest,$donor,$admin,$fresh] as $c) {curl_close($c);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($users as $id) {$pdo->prepare('DELETE FROM organizations WHERE user_id=?')->execute([$id]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only user management fixtures removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
