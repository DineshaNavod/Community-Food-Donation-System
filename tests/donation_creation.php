<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/donation_form.php';
$pdo = db();
$base = 'http://localhost/Community%20Food%20Donation%20System/';
$prefix = 'donation-check-' . bin2hex(random_bytes(6));
$ids = []; $tempFiles = [];
function check(bool $passed, string $label): void
{
    if (!$passed) { throw new RuntimeException($label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function browser(): CurlHandle
{
    $client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);return $client;
}
function request(CurlHandle $client,string $path,?array $fields=null): array
{
    global $base;
    curl_setopt($client,CURLOPT_URL,$base.$path);
    if ($fields===null) {curl_setopt($client,CURLOPT_HTTPGET,true);}
    else {
        curl_setopt($client,CURLOPT_POST,true);
        $multipart=(bool)array_filter($fields,static fn($value):bool=>$value instanceof CURLFile);
        curl_setopt($client,CURLOPT_POSTFIELDS,$multipart?$fields:http_build_query($fields));
    }
    $response=curl_exec($client);
    if ($response===false) {throw new RuntimeException(curl_error($client));}
    $length=curl_getinfo($client,CURLINFO_HEADER_SIZE);
    return [(int)curl_getinfo($client,CURLINFO_HTTP_CODE),substr($response,0,$length),substr($response,$length)];
}
function token(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$match)) {throw new RuntimeException('CSRF token missing');}
    return $match[1];
}
function sign_in(CurlHandle $client,string $email): void
{
    [,, $form]=request($client,'login.php');
    [$status]=request($client,'login.php',['csrf_token'=>token($form),'email'=>$email,'password'=>'Donation-check-2026!']);
    check($status===303,'fixture account signed in');
}
try {
    foreach (['donor','organization'] as $role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([
            $prefix,$prefix.'-'.$role.'@example.test',password_hash('Donation-check-2026!',PASSWORD_DEFAULT),'0700000000','Test address',$role,'active']);
        $ids[$role]=(int)$pdo->lastInsertId();
    }
    $guest=browser();[$status]=request($guest,'donor/add_donation.php');check($status===303,'guests redirected from form');
    $organization=browser();sign_in($organization,$prefix.'-organization@example.test');
    [$status]=request($organization,'donor/add_donation.php');check($status===403,'other roles denied creation');
    $client=browser();sign_in($client,$prefix.'-donor@example.test');
    [$status,,$html]=request($client,'donor/add_donation.php');check($status===200,'donor form loads');
    $fields=['csrf_token'=>token($html),'food_name'=>$prefix.'-plain','category'=>'cooked_meals',
        'description'=>'Freshly prepared test meal, packaged for collection.','quantity'=>'12.50','quantity_unit'=>'portions',
        'pickup_location'=>'Private pickup '.$prefix,'available_at'=>date('Y-m-d\TH:i',time()-300),
        'deadline_at'=>date('Y-m-d\TH:i',time()+7200)];
    [$status]=request($client,'donor/add_donation.php',array_replace($fields,['csrf_token'=>'bad']));check($status===403,'invalid CSRF rejected');
    foreach (['zero quantity'=>['quantity'=>'0'],'negative quantity'=>['quantity'=>'-1'],
        'excess precision'=>['quantity'=>'1.001'],'quantity overflow'=>['quantity'=>'100000000'],
        'unknown category'=>['category'=>'invalid'],'unknown unit'=>['quantity_unit'=>'invalid'],
        'missing name'=>['food_name'=>''],'array field'=>['food_name'=>['bad']],
        'short description'=>['description'=>'short'],'past deadline'=>['deadline_at'=>date('Y-m-d\TH:i',time()-600)],
        'invalid calendar date'=>['available_at'=>'2030-02-30T12:00'],
        'deadline before start'=>['available_at'=>date('Y-m-d\TH:i',time()+10800)]] as $label=>$changes) {
        [$status]=request($client,'donor/add_donation.php',array_replace($fields,$changes));check($status===422,$label.' rejected');
    }
    $bad=tempnam(sys_get_temp_dir(),'food-check-');$tempFiles[]=$bad;file_put_contents($bad,'<?php echo "BAD UPLOAD"; ?>');
    $png=tempnam(sys_get_temp_dir(),'food-check-');$tempFiles[]=$png;
    $image=imagecreatetruecolor(16,16);imagefill($image,0,0,imagecolorallocate($image,120,180,80));imagepng($image,$png);imagedestroy($image);
    file_put_contents($png,'<?php echo "APPENDED_CODE"; ?>',FILE_APPEND);
    foreach (['PHP file'=>new CURLFile($bad,'application/x-php','food.php'),
        'fake JPEG'=>new CURLFile($bad,'image/jpeg','food.jpg'),
        'executable filename'=>new CURLFile($png,'image/png','food.php')] as $label=>$file) {
        [$status]=request($client,'donor/add_donation.php',array_replace($fields,['food_image'=>$file]));check($status===422,$label.' rejected');
    }
    $large=tempnam(sys_get_temp_dir(),'food-check-');$tempFiles[]=$large;file_put_contents($large,str_repeat('x',2*1024*1024+1));
    [$status]=request($client,'donor/add_donation.php',array_replace($fields,['food_image'=>new CURLFile($large,'image/jpeg','large.jpg')]));check($status===422,'oversized upload rejected');
    $wide=tempnam(sys_get_temp_dir(),'food-check-');$tempFiles[]=$wide;$image=imagecreatetruecolor(4097,1);imagepng($image,$wide);imagedestroy($image);
    [$status]=request($client,'donor/add_donation.php',array_replace($fields,['food_image'=>new CURLFile($wide,'image/png','wide.png')]));check($status===422,'oversized dimensions rejected');
    $query=$pdo->prepare('SELECT COUNT(*) FROM donations WHERE donor_id=?');$query->execute([$ids['donor']]);check((int)$query->fetchColumn()===0,'rejected forms create no donations');
    $fields['donor_id']=$ids['organization'];$fields['status']='completed';
    [$status,$headers]=request($client,'donor/add_donation.php',$fields);check($status===303 && str_contains($headers,'/donor/index.php'),'plain donation saved and redirected');
    $query=$pdo->prepare('SELECT * FROM donations WHERE donor_id=? ORDER BY id');$query->execute([$ids['donor']]);$plain=$query->fetch();
    check($plain && $plain['status']==='available' && $plain['image_path']===null,'server controls donor and initial status; image optional');
    [,, $html]=request($client,'donor/index.php');check(str_contains($html,'published successfully') && str_contains($html,$fields['food_name']),'success and recent donation shown');
    [$status]=request($client,'donor/add_donation.php',$fields);check($status===403,'successful form token cannot create duplicate');
    [,, $html]=request($client,'donor/add_donation.php');$fields['csrf_token']=token($html);$fields['food_name']=$prefix.'-photo<script>alert(1)</script>';
    $fields['food_image']=new CURLFile($png,'image/png','food.png');
    [$status]=request($client,'donor/add_donation.php',$fields);check($status===303,'real PNG upload accepted');
    $query=$pdo->prepare('SELECT image_path FROM donations WHERE donor_id=? AND image_path IS NOT NULL');$query->execute([$ids['donor']]);$path=$query->fetchColumn();
    check(is_string($path) && preg_match('/\Auploads\/donations\/[a-f0-9]{32}\.jpg\z/',$path)===1,'random JPEG filename stored');
    $bytes=file_get_contents(__DIR__.'/../'.$path);check(!str_contains($bytes,'APPENDED_CODE') && getimagesize(__DIR__.'/../'.$path)['mime']==='image/jpeg','image re-encoded and appended content stripped');
    [$status,$headers]=request($guest,$path);check($status===200 && stripos($headers,'Content-Type: image/jpeg')!==false,'safe uploaded JPEG served correctly');
    [$status]=request($guest,'uploads/.htaccess');check($status===403,'upload configuration protected');
    [,, $html]=request($guest,'index.php');check(str_contains($html,$path) && str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'homepage shows image and escaped food name');
    check(!str_contains($html,'Private pickup '.$prefix),'public preview omits pickup address');
    foreach ([$client,$guest,$organization] as $handle) {curl_close($handle);}
} catch (Throwable $exception) {
    fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;
} finally {
    if (isset($ids['donor'])) {
        $query=$pdo->prepare('SELECT image_path FROM donations WHERE donor_id=?');$query->execute([$ids['donor']]);
        foreach ($query->fetchAll() as $row) {remove_food_image($row['image_path']);}
        $pdo->prepare('DELETE FROM donations WHERE donor_id=?')->execute([$ids['donor']]);
    }
    foreach ($ids as $id) {$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
    foreach ($tempFiles as $file) {if (is_file($file)) {unlink($file);}}
    echo 'Only test accounts, donations, and test images cleaned up.' . PHP_EOL;
}
exit(isset($failed)?1:0);
