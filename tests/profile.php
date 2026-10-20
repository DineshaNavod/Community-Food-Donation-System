<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
$pdo=db();$base='http://localhost/Community%20Food%20Donation%20System/';$prefix='profile-check-'.bin2hex(random_bytes(5));$ids=[];
function check(bool $passed,string $label):void {if (!$passed) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {global $base;curl_setopt($c,CURLOPT_URL,$base.$path);if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}$response=curl_exec($c);if ($response===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($response,0,$size),substr($response,$size)];}
function hidden(string $html,string $name):string {if (!preg_match('/name="'.preg_quote($name,'/').'" value="([a-f0-9]{64})"/',$html,$m)) {throw new RuntimeException('Missing '.$name);}return $m[1];}
function login(CurlHandle $c,string $email):int {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>hidden($html,'csrf_token'),'email'=>$email,'password'=>'Profile-check-2026!']);return $status;}
function user_row(int $id):array {global $pdo;$q=$pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();}
function fields(CurlHandle $c):array {global $prefix;[ $status,, $html]=request($c,'donor/profile.php');check($status===200,'profile form loads');return ['csrf_token'=>hidden($html,'csrf_token'),'version'=>hidden($html,'version'),'full_name'=>'Updated <script>alert(1)</script>', 'email'=>$prefix.'-donor@example.test','phone'=>'+94 77 123 4567','address'=>'Updated default pickup address'];}
try {
    foreach (['donor','other','organization'] as $name) {$pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix,$prefix.'-'.$name.'@example.test',password_hash('Profile-check-2026!',PASSWORD_DEFAULT),'0700000000','Original address',$name==='organization'?'organization':'donor','active']);$ids[$name]=(int)$pdo->lastInsertId();}
    $before=user_row($ids['donor']);$otherBefore=user_row($ids['other']);
    $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$ids['donor'],$prefix,'produce','Profile test donation',10,'kg','Original recorded pickup',date('Y-m-d H:i:s',time()-300),date('Y-m-d H:i:s',time()+3600)]);
    $donationId=(int)$pdo->lastInsertId();
    $guest=browser();[$status]=request($guest,'donor/profile.php');check($status===303,'guest redirected');
    $org=browser();check(login($org,$prefix.'-organization@example.test')===303,'organization fixture login');[$status]=request($org,'donor/profile.php');check($status===403,'other role denied');
    $client=browser();check(login($client,$prefix.'-donor@example.test')===303,'donor fixture login');
    $form=fields($client);$oldVersion=$form['version'];
    [$status]=request($client,'donor/profile.php',array_replace($form,['csrf_token'=>'bad']));check($status===403,'CSRF enforced');
    foreach (['name'=>['full_name'=>''],'array'=>['full_name'=>['bad']],'email'=>['email'=>'invalid'],'phone'=>['phone'=>'invalid'],'address'=>['address'=>'x']] as $label=>$changes) {[$status]=request($client,'donor/profile.php',array_replace($form,$changes));check($status===422,'invalid '.$label.' rejected');}
    $form['user_id']=$ids['other'];$form['id']=$ids['other'];$form['role']='admin';$form['status']='suspended';$form['password']='Forged-new-password';
    [$status]=request($client,'donor/profile.php',$form);$saved=user_row($ids['donor']);
    check($status===303 && $saved['full_name']===$form['full_name'] && $saved['address']===$form['address'],'contact details saved');
    check($saved['role']==='donor' && $saved['status']==='active' && $saved['password_hash']===$before['password_hash'],'role/status/password cannot be changed by form');
    check(user_row($ids['other'])===$otherBefore,'another account cannot be edited');
    [,, $html]=request($client,'donor/profile.php');check(str_contains($html,'profile has been updated') && str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'success and escaped profile values displayed');
    [,, $html]=request($client,'donor/index.php');check(str_contains($html,'Updated &lt;script&gt;alert(1)&lt;/script&gt;'),'dashboard uses updated name');
    [,, $html]=request($client,'donor/add_donation.php');check(str_contains($html,$form['address']),'new donation uses updated default address');
    $q=$pdo->prepare('SELECT pickup_location FROM donations WHERE id=?');$q->execute([$donationId]);check($q->fetchColumn()==='Original recorded pickup','existing donation pickup unchanged');
    $form=fields($client);$form['version']=$oldVersion;$form['full_name']='Stale overwrite';[$status]=request($client,'donor/profile.php',$form);check($status===409 && user_row($ids['donor'])['full_name']!=='Stale overwrite','stale profile changes rejected');
    $form=fields($client);$form['email']=$prefix.'-new@example.test';
    [$status]=request($client,'donor/profile.php',$form);check($status===422,'email change requires password');
    $form['current_password']='Wrong-password';[$status]=request($client,'donor/profile.php',$form);check($status===422,'incorrect password blocks email change');
    $form['current_password']=['bad'];[$status]=request($client,'donor/profile.php',$form);check($status===422,'malformed password rejected');
    $form['current_password']='Profile-check-2026!';$form['email']=$prefix.'-other@example.test';
    [$status,,$html]=request($client,'donor/profile.php',$form);check($status===422 && str_contains($html,'already registered'),'duplicate email rejected');
    check(user_row($ids['donor'])['email']===$before['email'],'rejected email changes leave account intact');
    $form['email']=strtoupper($prefix.'-new@example.test');[$status]=request($client,'donor/profile.php',$form);check($status===303 && user_row($ids['donor'])['email']===strtolower($form['email']),'verified email change normalized and saved');
    [,, $html]=request($client,'donor/profile.php');check(str_contains($html,'Use your new email') && !str_contains($html,'value="Profile-check-2026!"'),'new-email confirmation without password echo');
    $query=$pdo->prepare('SELECT COUNT(*) FROM users WHERE id=?');$query->execute([$ids['donor']]);check((int)$query->fetchColumn()===1,'profile update did not create another account');
    $newClient=browser();check(login($newClient,$before['email'])===401,'old email no longer signs in');check(login($newClient,strtolower($form['email']))===303,'new email signs in with original password');
    foreach ([$guest,$org,$client,$newClient] as $c) {curl_close($c);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {if (isset($donationId)) {$pdo->prepare('DELETE FROM donations WHERE id=? AND donor_id=?')->execute([$donationId,$ids['donor']]);}foreach ($ids as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Temporary profile accounts and donation removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
