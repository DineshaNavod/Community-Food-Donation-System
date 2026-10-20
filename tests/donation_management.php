<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/donation_form.php';
$pdo=db();$base='http://localhost/Community%20Food%20Donation%20System/';
$prefix='manage-check-'.bin2hex(random_bytes(5));$users=[];$foods=[];$orgId=null;$files=[];
function check(bool $passed,string $label):void {if (!$passed) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path,?array $fields=null):array {
    global $base;curl_setopt($c,CURLOPT_URL,$base.$path);
    if ($fields===null) {curl_setopt($c,CURLOPT_HTTPGET,true);}else {curl_setopt($c,CURLOPT_POST,true);$multipart=(bool)array_filter($fields,static fn($v):bool=>$v instanceof CURLFile);curl_setopt($c,CURLOPT_POSTFIELDS,$multipart?$fields:http_build_query($fields));}
    $response=curl_exec($c);if ($response===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);
    return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($response,0,$size),substr($response,$size)];
}
function hidden(string $html,string $name):string {if (!preg_match('/name="'.preg_quote($name,'/').'" value="([a-f0-9]{64})"/',$html,$m)) {throw new RuntimeException('Missing '.$name);}return $m[1];}
function sign_in(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');[$s]=request($c,'login.php',['csrf_token'=>hidden($html,'csrf_token'),'email'=>$email,'password'=>'Management-check-2026!']);check($s===303,'fixture login');}
function row(int $id):array {global $pdo;$q=$pdo->prepare('SELECT * FROM donations WHERE id=?');$q->execute([$id]);return $q->fetch();}
function edit_fields(CurlHandle $c,int $id):array {
    global $prefix;[ $status,, $html]=request($c,'donor/edit_donation.php?id='.$id);check($status===200,'eligible edit form loads');
    return ['csrf_token'=>hidden($html,'csrf_token'),'version'=>hidden($html,'version'),'food_name'=>$prefix.'-updated','category'=>'produce','description'=>'Updated food description for collection.',
        'quantity'=>'7.25','quantity_unit'=>'kg','pickup_location'=>'Updated private pickup','available_at'=>date('Y-m-d\TH:i',time()-300),'deadline_at'=>date('Y-m-d\TH:i',time()+7200)];
}
try {
    foreach (['donor-a'=>'donor','donor-b'=>'donor','org'=>'organization'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix,$prefix.'-'.$name.'@example.test',password_hash('Management-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users['org'],'Management test']);$orgId=(int)$pdo->lastInsertId();
    $names=['editable'=>'available','pending'=>'requested','approved'=>'requested','assigned'=>'assigned','collected'=>'collected','delivered'=>'delivered','completed'=>'completed','cancelled'=>'cancelled','expired'=>'available','requested-history'=>'available','foreign'=>'available','special%_food'=>'available'];
    for ($i=0;$i<21;$i++) {$names['extra-'.$i]='available';}
    foreach ($names as $name=>$status) {
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $users[$name==='foreign'?'donor-b':'donor-a'],$prefix.'-'.$name,'produce','Description <script>alert(1)</script>',10,'kg','Private collection '.$prefix,
            date('Y-m-d H:i:s',time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-3600:time()+7200),$status]);$foods[$name]=(int)$pdo->lastInsertId();
    }
    foreach (['pending'=>'pending','approved'=>'approved','requested-history'=>'rejected'] as $name=>$status) {$pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status) VALUES (?,?,?,?)')->execute([$foods[$name],$orgId,'Test delivery',$status]);}
    $imagePath='uploads/donations/'.bin2hex(random_bytes(16)).'.jpg';$image=imagecreatetruecolor(12,12);imagejpeg($image,__DIR__.'/../'.$imagePath);imagedestroy($image);$files[]=$imagePath;
    $pdo->prepare('UPDATE donations SET image_path=? WHERE id=?')->execute([$imagePath,$foods['editable']]);
    $guest=browser();[$status]=request($guest,'donor/donations.php');check($status===303,'guest cannot list donations');
    $org=browser();sign_in($org,$prefix.'-org@example.test');[$status]=request($org,'donor/donations.php');check($status===403,'organization denied donor pages');
    $a=browser();sign_in($a,$prefix.'-donor-a@example.test');$b=browser();sign_in($b,$prefix.'-donor-b@example.test');
    [$status,,$html]=request($a,'donor/donations.php?donor_id='.$users['donor-b']);check($status===200 && !str_contains($html,$prefix.'-foreign'),'list ignores forged donor filter');
    preg_match_all('/donor\/donation\.php\?id=[0-9]+/',$html,$links);check(count($links[0])===20 && str_contains($html,'Next'),'list paginated at twenty');
    [,, $html]=request($a,'donor/donations.php?page=2');preg_match_all('/donor\/donation\.php\?id=[0-9]+/',$html,$links);check(count($links[0])===12 && str_contains($html,'Previous'),'second page includes remaining history');
    [,, $html]=request($a,'donor/donations.php?status=completed');check(str_contains($html,$prefix.'-completed') && !str_contains($html,$prefix.'-editable'),'completed history filtered');
    check((bool)preg_match('/<a[^>]*donor\/donations\.php\?status=completed[^>]*aria-current="page"[^>]*>Completed history<\/a>/',$html),'Completed history tab is selected');
    check(!preg_match('/<a[^>]*donor\/donations\.php"[^>]*aria-current="page"[^>]*>My donations<\/a>/',$html),'My donations tab is not selected for completed history');
    [,, $html]=request($a,'donor/donations.php?search='.rawurlencode('special%_food'));preg_match_all('/donor\/donation\.php\?id=[0-9]+/',$html,$links);check(count($links[0])===1,'search treats percent and underscore literally');
    [$status]=request($a,'donor/donations.php?status=invalid');check($status===400,'invalid filters rejected');
    [$status,,$html]=request($a,'donor/donation.php?id='.$foods['editable']);check($status===200 && str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>') && str_contains($html,$imagePath),'owned detail and photo escaped');
    foreach (['donation.php','edit_donation.php'] as $route) {[$status]=request($b,'donor/'.$route.'?id='.$foods['editable']);check($status===404,'other donor cannot access '.$route);}
    foreach (['0','-1','bad','999999999'] as $id) {[$status]=request($a,'donor/donation.php?id='.$id);check($status===404,'bad or missing donation rejected');}
    $fields=edit_fields($a,$foods['editable']);$oldVersion=$fields['version'];$fields['donor_id']=$users['donor-b'];$fields['status']='completed';
    [$status]=request($a,'donor/edit_donation.php?id='.$foods['editable'],array_replace($fields,['csrf_token'=>'bad']));check($status===403,'edit CSRF enforced');
    [$status]=request($a,'donor/edit_donation.php?id='.$foods['editable'],array_replace($fields,['quantity'=>'0']));check($status===422,'edit values validated');
    [$status]=request($a,'donor/edit_donation.php?id='.$foods['editable'],$fields);$saved=row($foods['editable']);check($status===303 && $saved['food_name']===$fields['food_name'] && $saved['quantity']==='7.25','eligible edit saved');
    check((int)$saved['donor_id']===$users['donor-a'] && $saved['status']==='available' && $saved['image_path']===$imagePath,'edit preserves owner/status/existing photo');
    $fields=edit_fields($a,$foods['editable']);$fields['version']=$oldVersion;$fields['food_name']='Stale overwrite';[$status]=request($a,'donor/edit_donation.php?id='.$foods['editable'],$fields);check($status===409 && row($foods['editable'])['food_name']!=='Stale overwrite','stale edit rejected');
    $temp=tempnam(sys_get_temp_dir(),'management-image-');$image=imagecreatetruecolor(20,20);imagepng($image,$temp);imagedestroy($image);
    $fields=edit_fields($a,$foods['editable']);$fields['food_image']=new CURLFile($temp,'image/png','replacement.png');[$status]=request($a,'donor/edit_donation.php?id='.$foods['editable'],$fields);$replacement=row($foods['editable'])['image_path'];$files[]=$replacement;
    check($status===303 && $replacement!==$imagePath && !is_file(__DIR__.'/../'.$imagePath) && is_file(__DIR__.'/../'.$replacement),'replacement saved and previous photo removed');
    $fields=edit_fields($a,$foods['editable']);$fields['remove_image']='1';[$status]=request($a,'donor/edit_donation.php?id='.$foods['editable'],$fields);
    // Apache changed the filesystem in another process; discard PHP's cached stat.
    clearstatcache();
    check($status===303 && row($foods['editable'])['image_path']===null && !is_file(__DIR__.'/../'.$replacement),'explicit photo removal saved');
    foreach (['pending','approved','assigned','collected','delivered','completed','cancelled','expired','requested-history'] as $name) {[$status]=request($a,'donor/edit_donation.php?id='.$foods[$name]);check($status===409,$name.' cannot be edited');}
    [$status]=request($a,'donor/cancel_donation.php?id='.$foods['editable']);check($status===405,'GET cannot cancel');
    [,, $html]=request($a,'donor/donation.php?id='.$foods['editable']);$cancel=['csrf_token'=>hidden($html,'csrf_token'),'confirm'=>'1'];
    [$status]=request($a,'donor/cancel_donation.php?id='.$foods['editable'],array_replace($cancel,['csrf_token'=>'bad']));check($status===403,'cancel CSRF enforced');
    [$status]=request($a,'donor/cancel_donation.php?id='.$foods['editable'],['csrf_token'=>$cancel['csrf_token']]);check($status===422,'cancellation confirmation required');
    [,, $html]=request($b,'donor/donation.php?id='.$foods['foreign']);[$status]=request($b,'donor/cancel_donation.php?id='.$foods['editable'],['csrf_token'=>hidden($html,'csrf_token'),'confirm'=>'1']);check($status===404,'other donor cannot cancel');
    [$status]=request($a,'donor/cancel_donation.php?id='.$foods['editable'],$cancel);check($status===303 && row($foods['editable'])['status']==='cancelled','available donation cancelled with history retained');
    [,, $html]=request($a,'donor/donation.php?id='.$foods['pending']);$cancel['csrf_token']=hidden($html,'csrf_token');[$status]=request($a,'donor/cancel_donation.php?id='.$foods['pending'],$cancel);
    $q=$pdo->prepare('SELECT status FROM donation_requests WHERE donation_id=?');$q->execute([$foods['pending']]);check($status===303 && row($foods['pending'])['status']==='cancelled' && $q->fetchColumn()==='cancelled','donation and pending request cancelled atomically');
    [,, $html]=request($a,'donor/donation.php?id='.$foods['approved']);$cancel['csrf_token']=hidden($html,'csrf_token');
    foreach (['approved','assigned','collected','delivered','completed','cancelled','expired'] as $name) {[$status]=request($a,'donor/cancel_donation.php?id='.$foods[$name],$cancel);check($status===409,$name.' cannot be cancelled');}
    foreach ([$guest,$org,$a,$b] as $client) {curl_close($client);}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {
    foreach ($files as $path) {remove_food_image($path);}
    if (isset($temp) && is_file($temp)) {unlink($temp);}
    foreach ($foods as $id) {$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}
    if ($orgId!==null) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$orgId]);}
    foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
    echo 'Management fixtures and test images cleaned up.'.PHP_EOL;
}
exit(isset($failed)?1:0);
