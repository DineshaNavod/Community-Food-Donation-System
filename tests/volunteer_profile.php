<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
$pdo=db();$prefix='vol-profile-'.bin2hex(random_bytes(5));$users=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
    $r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];
}
function hidden(string $html,string $name):string {preg_match('/name="'.preg_quote($name,'/').'" value="([a-f0-9]{64})"/',$html,$m);if (!isset($m[1])) {throw new RuntimeException('Missing '.$name);}return $m[1];}
function login(CurlHandle $c,string $email):int {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>hidden($html,'csrf_token'),'email'=>$email,'password'=>'Volunteer-profile-2026!']);return $status;}
function row(int $id):array {$q=db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();}
function form(CurlHandle $c):array {global $prefix;[$status,,$html]=request($c,'volunteer/profile.php');check($status===200,'volunteer profile loads');return ['csrf_token'=>hidden($html,'csrf_token'),'version'=>hidden($html,'version'),'full_name'=>'Volunteer <script>alert(1)</script>','email'=>$prefix.'-a@example.test','phone'=>'+94 77 123 4567','address'=>'Updated volunteer address'];}
try {
    foreach (['a'=>'volunteer','b'=>'volunteer','donor'=>'donor'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',password_hash('Volunteer-profile-2026!',PASSWORD_DEFAULT),'0700000000','Original address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
    }
    $before=row($users['a']);$other=row($users['b']);$guest=browser();[$status]=request($guest,'volunteer/profile.php');check($status===303,'guest blocked');
    $donor=browser();check(login($donor,$prefix.'-donor@example.test')===303,'donor login');[$status]=request($donor,'volunteer/profile.php');check($status===403,'donor cannot edit volunteer profile');
    $a=browser();check(login($a,$before['email'])===303,'volunteer login');$fields=form($a);$old=$fields['version'];
    [$status]=request($a,'volunteer/profile.php',array_replace($fields,['csrf_token'=>'bad']));check($status===403,'CSRF enforced');
    foreach (['full_name'=>['bad'],'phone'=>'abc','address'=>'x','email'=>'bad'] as $field=>$value) {[$status]=request($a,'volunteer/profile.php',array_replace($fields,[$field=>$value]));check($status===422,'invalid '.$field.' rejected');}
    $fields+=['id'=>$users['b'],'user_id'=>$users['b'],'role'=>'admin','status'=>'suspended','password'=>'forged'];[$status]=request($a,'volunteer/profile.php',$fields);$saved=row($users['a']);
    check($status===303 && $saved['full_name']===$fields['full_name'] && $saved['address']===$fields['address'],'contact fields saved');
    check(row($users['b'])===$other && $saved['role']==='volunteer' && $saved['status']==='active' && $saved['password_hash']===$before['password_hash'],'identity, permissions and password protected');
    [,, $html]=request($a,'volunteer/profile.php');check(str_contains($html,'profile has been updated') && str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'default pickup location'),'success, escaping and volunteer address guidance');
    check((bool)preg_match('/<a[^>]*volunteer\/profile\.php[^>]*aria-current="page"/',$html),'profile tab highlighted');
    [,, $html]=request($a,'volunteer/index.php');check(str_contains($html,'volunteer/profile.php') && str_contains($html,'volunteer/history.php') && str_contains($html,'Volunteer &lt;script&gt;alert(1)&lt;/script&gt;'),'dashboard reflects profile and history links');
    $fields=form($a);$fields['version']=$old;$fields['full_name']='Stale edit';[$status]=request($a,'volunteer/profile.php',$fields);check($status===409 && row($users['a'])['full_name']===$saved['full_name'],'stale edits rejected');
    $fields=form($a);$fields['email']=$prefix.'-new@example.test';[$status]=request($a,'volunteer/profile.php',$fields);check($status===422,'email change requires password');
    $fields['current_password']='wrong';[$status]=request($a,'volunteer/profile.php',$fields);check($status===422,'wrong password blocks email change');
    $fields['current_password']='Volunteer-profile-2026!';$fields['email']=$other['email'];[$status]=request($a,'volunteer/profile.php',$fields);check($status===422 && row($users['a'])['email']===$before['email'],'duplicate email rejected');
    $fields['email']=strtoupper($prefix.'-new@example.test');[$status]=request($a,'volunteer/profile.php',$fields);check($status===303 && row($users['a'])['email']===strtolower($fields['email']),'verified email normalized and saved');
    $fresh=browser();check(login($fresh,$before['email'])===401 && login($fresh,strtolower($fields['email']))===303,'new email signs in with unchanged password');
    foreach ([$guest,$donor,$a,$fresh] as $c) {curl_close($c);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only volunteer profile fixtures removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
