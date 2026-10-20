<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
$pdo=db();$base='http://localhost/Community%20Food%20Donation%20System/';$prefix='org-check-'.bin2hex(random_bytes(5));$users=[];$orgs=[];$foods=[];
function check(bool $passed,string $label):void {if (!$passed) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {global $base;curl_setopt($c,CURLOPT_URL,$base.$path);if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}$response=curl_exec($c);if ($response===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($response,0,$size),substr($response,$size)];}
function csrf(string $html):string {if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m)) {throw new RuntimeException('Missing CSRF');}return $m[1];}
function login(CurlHandle $c,string $email):int {[,, $html]=request($c,'login.php');[$status]=request($c,'login.php',['csrf_token'=>csrf($html),'email'=>$email,'password'=>'Organization-check-2026!']);return $status;}
function form(CurlHandle $c,int $id):array {[ $status,, $html]=request($c,'organization/donation.php?id='.$id);check($status===200,'eligible donation detail loads');return ['csrf_token'=>csrf($html),'delivery_address'=>'Organization delivery address','message'=>'Request <script>alert(1)</script>'];}
try {
    foreach (['donor'=>'donor','inactive'=>'donor','org-a'=>'organization','org-b'=>'organization','org-c'=>'organization','pending'=>'organization'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',password_hash('Organization-check-2026!',PASSWORD_DEFAULT),'0700000000','Fixture address',$role,$name==='inactive'?'suspended':($name==='pending'?'pending':'active')]);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name],$prefix.' '.$name]);$orgs[$name]=(int)$pdo->lastInsertId();}
    }
    $kinds=['available','requested','expired','future','cancelled','assigned','collected','delivered','completed','allocated','inactive'];
    for ($i=0;$i<13;$i++) {$kinds[]='extra-'.$i;}
    foreach ($kinds as $name) {
        $status=in_array($name,['requested','cancelled','assigned','collected','delivered','completed'],true)?$name:($name==='allocated'?'requested':'available');
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $users[$name==='inactive'?'inactive':'donor'],$prefix.'-'.$name.($name==='available'?'<script>alert(1)</script>':''),$name==='available'?'bakery':'produce','Description <script>alert(1)</script>',10,'packs',$name==='available'?'North District':'South District',date('Y-m-d H:i:s',$name==='future'?time()+3600:time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-3600:time()+7200),$status]);$foods[$name]=(int)$pdo->lastInsertId();
    }
    foreach (['allocated'=>'approved','requested'=>'pending'] as $name=>$status) {$pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status) VALUES (?,?,?,?)')->execute([$foods[$name],$orgs['org-c'],'Private other organization address',$status]);}
    $guest=browser();[$status]=request($guest,'organization/donations.php');check($status===303,'guest browse blocked');
    $donor=browser();check(login($donor,$prefix.'-donor@example.test')===303,'donor fixture login');[$status]=request($donor,'organization/donations.php');check($status===403,'donor cannot use organization pages');
    $pending=browser();check(login($pending,$prefix.'-pending@example.test')===403,'pending organization cannot sign in');
    $a=browser();$b=browser();$c=browser();foreach (['org-a'=>$a,'org-b'=>$b,'org-c'=>$c] as $name=>$client) {check(login($client,$prefix.'-'.$name.'@example.test')===303,$name.' fixture login');}
    [$status,,$html]=request($a,'organization/donations.php?search='.$prefix);check($status===200 && str_contains($html,'15 matching donations'),'browse includes available and unallocated requested food');
    foreach (['expired','future','cancelled','assigned','collected','delivered','completed','allocated','inactive'] as $kind) {check(!str_contains($html,$prefix.'-'.$kind),$kind.' excluded from browse');}
    [,, $html]=request($a,'organization/donations.php?search='.$prefix.'&page=2');check(str_contains($html,'Page 2 of 2'),'food results paginate');
    [,, $html]=request($a,'organization/donations.php?search='.$prefix.'&category=bakery&location=North');check(str_contains($html,'1 matching donations') && str_contains($html,$prefix.'-available'),'category and pickup filters combined');
    check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'browse food names escaped');
    [$status]=request($a,'organization/donations.php?category=bad');check($status===400,'invalid browse filter rejected');
    [$status]=request($a,'organization/donation.php?id='.$foods['allocated']);check($status===404,'unrelated organization cannot view allocated food');
    [$status,,$html]=request($c,'organization/donation.php?id='.$foods['allocated']);check($status===200 && str_contains($html,'Your organization'),'recipient can still view allocated food');
    $fields=form($a,$foods['available']);[$status]=request($a,'organization/request_donation.php?id='.$foods['available']);check($status===405,'GET cannot submit request');
    [$status]=request($a,'organization/request_donation.php?id='.$foods['available'],array_replace($fields,['csrf_token'=>'bad']));check($status===403,'request CSRF enforced');
    foreach (['short address'=>['delivery_address'=>'x'],'array address'=>['delivery_address'=>['bad']],'overlong message'=>['message'=>str_repeat('x',1501)]] as $label=>$changes) {
        [$status]=request($a,'organization/request_donation.php?id='.$foods['available'],array_replace($fields,$changes));[,, $html]=request($a,'organization/donation.php?id='.$foods['available']);check($status===303 && str_contains($html,'Use a delivery address'),'invalid '.$label.' rejected with form error');
    }
    $fields['organization_id']=$orgs['org-b'];$fields['user_id']=$users['org-b'];$fields['status']='approved';
    [$status]=request($a,'organization/request_donation.php?id='.$foods['available'],$fields);check($status===303,'organization request submitted');
    $q=$pdo->prepare('SELECT * FROM donation_requests WHERE donation_id=?');$q->execute([$foods['available']]);$saved=$q->fetch();
    check((int)$saved['organization_id']===$orgs['org-a'] && $saved['status']==='pending','server controls requesting organization and status');
    $q=$pdo->prepare('SELECT status FROM donations WHERE id=?');$q->execute([$foods['available']]);check($q->fetchColumn()==='requested','donation and request state updated together');
    [$status]=request($a,'organization/request_donation.php?id='.$foods['available'],$fields);check($status===403,'old successful token cannot replay');
    [,, $html]=request($a,'organization/donation.php?id='.$foods['available']);check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'own message and food description escaped');
    $fields['csrf_token']=csrf($html);[$status]=request($a,'organization/request_donation.php?id='.$foods['available'],$fields);check($status===409,'duplicate organization request blocked');
    $fieldsB=form($b,$foods['available']);[$status]=request($b,'organization/request_donation.php?id='.$foods['available'],$fieldsB);check($status===303,'another organization may request unallocated food');
    [,, $html]=request($donor,'donor/requests.php?donation_id='.$foods['available']);check(str_contains($html,$prefix.' org-a') && str_contains($html,$prefix.' org-b'),'donor sees organization requests end to end');
    [,, $html]=request($donor,'donor/request.php?id='.$saved['id']);[$status]=request($donor,'donor/decide_request.php?id='.$saved['id'],['csrf_token'=>csrf($html),'decision'=>'approve']);check($status===303,'donor approves actual submitted request');
    [,, $html]=request($a,'organization/donation.php?id='.$foods['available']);check(str_contains($html,'Approved') && str_contains($html,'Donor contact:'),'approved organization sees decision and contact');
    [,, $html]=request($b,'organization/donation.php?id='.$foods['available']);check(str_contains($html,'Rejected') && !str_contains($html,'Donor contact:'),'competing organization sees rejection without recipient contact');
    [$status]=request($c,'organization/donation.php?id='.$foods['available']);check($status===404,'unrelated organization cannot view new allocation');
    [,, $html]=request($c,'organization/donation.php?id='.$foods['requested']);$blocked=['csrf_token'=>csrf($html),'delivery_address'=>'Test delivery','message'=>''];
    foreach (['expired','future','cancelled','assigned','collected','delivered','completed','allocated','inactive'] as $kind) {[$status]=request($c,'organization/request_donation.php?id='.$foods[$kind],$blocked);check($status===409,$kind.' blocks direct submission');}
    [,, $html]=request($a,'organization/index.php');check(str_contains($html,'organization/donation.php?id='.$foods['available']),'dashboard links to own requested donation');
    foreach ([$guest,$donor,$pending,$a,$b,$c] as $client) {curl_close($client);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($foods as $id) {$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}foreach ($orgs as $id) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only organization test fixtures removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
