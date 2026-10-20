<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
$pdo=db();$prefix='org-profile-'.bin2hex(random_bytes(5));$users=[];$orgs=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];
}
function hidden(string $html,string $name):string {preg_match('/name="'.preg_quote($name,'/').'" value="([a-f0-9]{64})"/',$html,$m);if (!isset($m[1])) {throw new RuntimeException('Missing '.$name);}return $m[1];}
function login(CurlHandle $c,string $email):int {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>hidden($html,'csrf_token'),'email'=>$email,'password'=>'Org-profile-2026!']);return $status;}
function rows(int $id):array {$q=db()->prepare('SELECT u.*,o.id AS organization_id,o.organization_name,o.description FROM users u LEFT JOIN organizations o ON o.user_id=u.id WHERE u.id=?');$q->execute([$id]);return $q->fetch();}
function form(CurlHandle $c):array {global $prefix;[$status,,$html]=request($c,'organization/profile.php');check($status===200,'organization form loads');return ['csrf_token'=>hidden($html,'csrf_token'),'version'=>hidden($html,'version'),'full_name'=>'Updated contact','email'=>$prefix.'-a@example.test','phone'=>'+94 77 123 4567','address'=>'Updated delivery address','organization_name'=>'Community <script>alert(1)</script>','description'=>'Helping <b>neighbors</b>'];}
try {
    foreach (['a'=>'organization','b'=>'organization','donor'=>'donor','missing'=>'organization'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix,$prefix.'-'.$name.'@example.test',password_hash('Org-profile-2026!',PASSWORD_DEFAULT),'0700000000','Original address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization' && $name!=='missing') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name,description) VALUES (?,?,?)')->execute([$users[$name],$prefix.'-'.$name,'Original description']);$orgs[$name]=(int)$pdo->lastInsertId();}
    }
    $before=rows($users['a']);$other=rows($users['b']);
    $pdo->prepare("INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status) VALUES (?,?,'produce','Test',1,'kg','Test pickup',?,?,'requested')")->execute([$users['donor'],$prefix,date('Y-m-d H:i:s',time()-300),date('Y-m-d H:i:s',time()+3600)]);$food=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status) VALUES (?,?,?,'pending')")->execute([$food,$orgs['a'],'Original request address']);
    $guest=browser();[$status]=request($guest,'organization/profile.php');check($status===303,'guest blocked');
    $donor=browser();check(login($donor,$prefix.'-donor@example.test')===303,'donor login');[$status]=request($donor,'organization/profile.php');check($status===403,'donor blocked from organization form');
    $missing=browser();check(login($missing,$prefix.'-missing@example.test')===303,'missing-profile login');[$status]=request($missing,'organization/profile.php');check($status===409,'missing linked profile handled');
    $a=browser();check(login($a,$before['email'])===303,'organization login');$fields=form($a);$oldVersion=$fields['version'];
    [$status]=request($a,'organization/profile.php',array_replace($fields,['csrf_token'=>'bad']));check($status===403,'CSRF enforced');
    foreach (['organization_name'=>'x','description'=>str_repeat('x',1501),'phone'=>'abc','address'=>'x','email'=>'bad'] as $field=>$value) {[$status]=request($a,'organization/profile.php',array_replace($fields,[$field=>$value]));check($status===422,'invalid '.$field.' rejected');}
    foreach ([['organization_name'=>['bad']],['description'=>"bad\0text"]] as $changes) {[$status]=request($a,'organization/profile.php',array_replace($fields,$changes));check($status===422,'malformed organization text rejected');}
    $fields+=['id'=>$users['b'],'user_id'=>$users['b'],'organization_id'=>$orgs['b'],'role'=>'admin','status'=>'active','password'=>'forged'];
    [$status]=request($a,'organization/profile.php',$fields);$saved=rows($users['a']);check($status===303 && $saved['organization_name']===$fields['organization_name'] && $saved['description']===$fields['description'] && $saved['address']===$fields['address'],'contact and organization saved together');
    check(rows($users['b'])===$other && $saved['role']==='organization' && $saved['status']==='active' && $saved['password_hash']===$before['password_hash'],'forged identity/role/status/password ignored');
    [,, $html]=request($a,'organization/profile.php');check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($html,'Helping &lt;b&gt;neighbors&lt;/b&gt;') && str_contains($html,'profile has been updated'),'saved fields escaped with success message');
    check((bool)preg_match('/<a[^>]*organization\/profile\.php[^>]*aria-current="page"/',$html),'profile tab highlighted');
    [,, $html]=request($a,'organization/index.php');check(str_contains($html,'Community &lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($html,'organization/profile.php'),'dashboard reflects organization name and profile link');
    $q=$pdo->prepare('SELECT delivery_address FROM donation_requests WHERE donation_id=?');$q->execute([$food]);check($q->fetchColumn()==='Original request address','recorded request address preserved');
    $fields=form($a);$fields['version']=$oldVersion;[$status]=request($a,'organization/profile.php',$fields);check($status===409,'stale contact/organization snapshot rejected');
    $fields=form($a);$pdo->prepare('UPDATE organizations SET description=? WHERE id=?')->execute(['Changed elsewhere',$orgs['a']]);[$status]=request($a,'organization/profile.php',$fields);check($status===409 && rows($users['a'])['description']==='Changed elsewhere','organization-only concurrent edit protected');
    $fields=form($a);$fields['email']=$prefix.'-new@example.test';$fields['organization_name']='Must not save yet';
    [$status]=request($a,'organization/profile.php',$fields);check($status===422 && rows($users['a'])['organization_name']===$saved['organization_name'],'email change requires password before either table changes');
    $fields['current_password']='wrong';[$status]=request($a,'organization/profile.php',$fields);check($status===422,'wrong current password rejected');
    $fields['current_password']='Org-profile-2026!';$fields['email']=$other['email'];$beforeDuplicate=rows($users['a']);[$status]=request($a,'organization/profile.php',$fields);check($status===422 && rows($users['a'])===$beforeDuplicate,'duplicate email rolls back all profile fields');
    $fields['email']=strtoupper($prefix.'-new@example.test');$fields['description']='';[$status]=request($a,'organization/profile.php',$fields);$saved=rows($users['a']);check($status===303 && $saved['email']===strtolower($fields['email']) && $saved['description']===null,'verified email normalized and optional description cleared');
    $fresh=browser();check(login($fresh,$before['email'])===401 && login($fresh,$saved['email'])===303,'new email signs in with existing password');
    foreach ([$guest,$donor,$missing,$a,$fresh] as $c) {curl_close($c);}
} catch (Throwable $e) {fwrite(STDERR,'FAIL: '.$e->getMessage().PHP_EOL);$failed=true;}
finally {
    if (isset($food)) {$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$food]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$food]);}
    foreach ($orgs as $id) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only organization profile fixtures removed.'.PHP_EOL;
}
exit(isset($failed)?1:0);
