<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';$pdo=db();$prefix='overview-check-'.bin2hex(random_bytes(5));$users=[];$orgs=[];$foods=[];$requests=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function browser():CurlHandle {$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $c;}
function request(CurlHandle $c,string $path):array {curl_setopt($c,CURLOPT_URL,'http://localhost/Community%20Food%20Donation%20System/'.$path);curl_setopt($c,CURLOPT_HTTPGET,true);$r=curl_exec($c);if ($r===false) {throw new RuntimeException(curl_error($c));}$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);return [(int)curl_getinfo($c,CURLINFO_HTTP_CODE),substr($r,0,$size),substr($r,$size)];}
function login(CurlHandle $c,string $email):void {[,, $html]=request($c,'login.php');preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$m);curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query(['csrf_token'=>$m[1],'email'=>$email,'password'=>'Overview-check-2026!']));curl_exec($c);check(curl_getinfo($c,CURLINFO_HTTP_CODE)===303,'fixture login');}
try {
    $hash=password_hash('Overview-check-2026!',PASSWORD_DEFAULT);
    foreach (['admin'=>'admin','donor'=>'donor','other-donor'=>'donor','org'=>'organization','other-org'=>'organization','volunteer'=>'volunteer'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([$prefix.'-'.$name,$prefix.'-'.$name.'@example.test',$hash,'0700000000','Test address',$role,'active']);$users[$name]=(int)$pdo->lastInsertId();
        if ($role==='organization') {$pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name],$prefix.'-'.$name]);$orgs[$name]=(int)$pdo->lastInsertId();}
    }
    $kinds=['available','requested','assigned','collected','delivered','completed','expired','cancelled','rejected','future'];for ($i=0;$i<15;$i++) {$kinds[]='extra-'.$i;}
    $past=date('Y-m-d H:i:s',time()-600);
    foreach ($kinds as $name) {
        $state=match($name) {'available','requested','assigned','collected','delivered','completed','cancelled'=>$name,'expired'=>'assigned','future'=>'available',default=>'requested'};
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$users[$name==='available'?'other-donor':'donor'],$prefix.'-'.$name,'produce','Description <script>alert(1)</script>',5,'kg','Pickup '.$prefix,date('Y-m-d H:i:s',$name==='future'?time()+600:time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-300:time()+7200),$state,$state==='completed'?$past:null]);$foods[$name]=(int)$pdo->lastInsertId();
        if (in_array($name,['available','future'],true)) {continue;}
        $approved=in_array($name,['assigned','collected','delivered','completed','expired'],true);$rState=$approved?'approved':($name==='rejected'?'rejected':($name==='cancelled'?'cancelled':'pending'));
        $pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,message,status,decided_at) VALUES (?,?,?,?,?,?)')->execute([$foods[$name],$orgs['org'],'Delivery '.$prefix,'Message <b>help</b>',$rState,$rState==='pending'?null:$past]);$requests[$name]=(int)$pdo->lastInsertId();
        if ($approved) {
            $tState=in_array($name,['delivered','completed'],true)?'delivered':($name==='collected'?'collected':'assigned');
            $pdo->prepare('INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status,assigned_at,accepted_at,collected_at,delivered_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$requests[$name],$users['volunteer'],$users['admin'],$tState,$past,$tState==='assigned'?null:$past,in_array($tState,['collected','delivered'],true)?$past:null,$tState==='delivered'?$past:null]);
        }
    }
    $pdo->prepare("INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status,decided_at) VALUES (?,?,?,'rejected',?)")->execute([$foods['completed'],$orgs['other-org'],'Competing address',$past]);$competitor=(int)$pdo->lastInsertId();
    $guest=browser();foreach (['admin/donations.php','admin/requests.php','admin/donation.php?id='.$foods['completed']] as $path) {[$status]=request($guest,$path);check($status===303,'guest overview blocked');}
    foreach (['donor','org','volunteer'] as $name) {$c=browser();login($c,$prefix.'-'.$name.'@example.test');foreach (['admin/donations.php','admin/requests.php','admin/donation.php?id='.$foods['completed']] as $path) {[$status]=request($c,$path);check($status===403,$name.' cannot access administrator overview');}curl_close($c);}
    $admin=browser();login($admin,$prefix.'-admin@example.test');$search='search='.rawurlencode($prefix);
    [$status,,$html]=request($admin,'admin/donations.php?'.$search);check($status===200 && str_contains($html,'25 matching donations') && substr_count($html,'>View &rarr;</a>')===20,'donation overview paginated without duplicated request rows');
    [,, $html]=request($admin,'admin/donations.php?'.$search.'&page=2');check(str_contains($html,'Page 2 of 2') && substr_count($html,'>View &rarr;</a>')===5,'second donation page reachable');
    [,, $html]=request($admin,'admin/requests.php?'.$search);check(str_contains($html,'24 matching requests') && substr_count($html,'>View &rarr;</a>')===20,'request overview paginated');
    [,, $html]=request($admin,'admin/requests.php?'.$search.'&page=2');check(str_contains($html,'Page 2 of 2'),'second request page reachable');
    [,, $html]=request($admin,'admin/donations.php?'.$search.'&status=expired');check(str_contains($html,'1 matching donations') && str_contains($html,$prefix.'-expired'),'effective expiry filter');
    [,, $html]=request($admin,'admin/requests.php?'.$search.'&status=approved&delivery=completed');check(str_contains($html,'1 matching requests') && str_contains($html,'#request-'.$requests['completed']),'combined request/donation filters and detail anchor');
    [,, $html]=request($admin,'admin/requests.php?search='.rawurlencode($prefix.'-other-org'));check(str_contains($html,'1 matching requests'),'organization search includes competing request');
    [,, $html]=request($admin,'admin/donations.php?search='.rawurlencode($prefix.'-other-donor'));check(str_contains($html,'1 matching donations'),'administrator sees other donor records');
    [,, $html]=request($admin,'admin/donations.php?search=%25'.$prefix);check(str_contains($html,'0 matching donations'),'literal wildcard search');
    foreach (['status=bad','search[]=bad','page=0','delivery[]=bad'] as $filter) {[$status]=request($admin,'admin/requests.php?'.$filter);check($status===400,'invalid overview filter rejected');}
    [$status]=request($admin,'admin/donation.php?id=2147483647');check($status===404,'missing donation returns not found');
    [$status,,$html]=request($admin,'admin/donation.php?id='.$foods['completed']);check($status===200 && str_contains($html,'id="request-'.$competitor.'"') && str_contains($html,$prefix.'-other-org'),'detail includes all competing requests');
    check(str_contains($html,'Description &lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($html,'Message &lt;b&gt;help&lt;/b&gt;') && !str_contains($html,'<script>alert(1)</script>') && !str_contains($html,$hash),'detail escaped and excludes password hashes');
    check(str_contains($html,'Pickup '.$prefix) && str_contains($html,'Delivery '.$prefix) && str_contains($html,$prefix.'-volunteer'),'admin sees donor, delivery and volunteer information');
    check(substr_count($html,'class="is-recorded"')===6,'completed tracking has six recorded steps without competitor receipt');
    [,, $html]=request($admin,'admin/donation.php?id='.$foods['assigned']);check(substr_count($html,'class="is-recorded"')===3,'assigned tracking never infers collection/delivery');
    [,, $html]=request($admin,'admin/donation.php?id='.$foods['requested']);check(str_contains($html,'Awaiting donor review.'),'pending request explains decision state');
    [,, $html]=request($admin,'admin/donation.php?id='.$foods['available']);check(str_contains($html,'No organization has requested this donation.'),'unrequested donation empty state');
    $q=$pdo->prepare('SELECT status FROM donations WHERE id=?');$q->execute([$foods['expired']]);check($q->fetchColumn()==='assigned','overview reads do not mutate expired state');
    [,, $html]=request($admin,'admin/index.php');check(str_contains($html,'admin/donations.php') && str_contains($html,'admin/requests.php'),'dashboard links to overview pages');
    check((bool)preg_match('/href="[^"]*admin\/recent\.php"[^>]*>Recent donations<\/a>/',$html),'admin Recent donations opens a page');
    check((bool)preg_match('/href="[^"]*admin\/accounts\.php"[^>]*>Pending accounts<\/a>/',$html),'admin Pending accounts opens approvals');
    [$status,,$html]=request($admin,'admin/recent.php?'.$search);check($status===200 && substr_count($html,'>View &rarr;</a>')===5 && str_contains($html,'Latest 5 of'),'recent donations shows latest five matches');
    check((bool)preg_match('/<a[^>]*admin\/recent\.php[^>]*aria-current="page"/',$html) && !preg_match('/<a[^>]*admin\/donations\.php[^>]*aria-current="page"/',$html),'Recent donations selected without All donations');
    [$status,,$html]=request($admin,'admin/accounts.php');check($status===200 && (bool)preg_match('/<a[^>]*admin\/accounts\.php[^>]*aria-current="page"[^>]*>Pending accounts<\/a>/',$html),'Pending accounts page loads and highlights matching menu');
    [$status]=request($guest,'admin/recent.php');check($status===303,'guest recent donations blocked');
    curl_close($guest);curl_close($admin);
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach ($foods as $id) {$pdo->prepare('DELETE t FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id WHERE r.donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donation_requests WHERE donation_id=?')->execute([$id]);$pdo->prepare('DELETE FROM donations WHERE id=?')->execute([$id]);}foreach ($orgs as $id) {$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$id]);}foreach ($users as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}echo 'Only overview test fixtures removed.'.PHP_EOL;}
exit(isset($failed)?1:0);
