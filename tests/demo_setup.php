<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../includes/db.php';require_once __DIR__.'/../includes/demo_setup.php';
$pdo=db();$tables=['users','organizations','donations','donation_requests','collection_tasks'];$temporary=[];$before=[];
function check(bool $ok,string $label):void {if (!$ok) {throw new RuntimeException($label);}echo 'PASS: '.$label.PHP_EOL;}
function counts():array {global $pdo,$tables;$result=[];foreach ($tables as $table) {$result[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}return $result;}
try {
    $before=counts();
    foreach ($tables as $table) {
        $definition=$pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
        $definition=preg_replace('/\ACREATE TABLE/','CREATE TEMPORARY TABLE',$definition);
        // Temporary tables shadow the real names on this connection; MySQL omits FKs on them.
        $definition=implode("\n",array_filter(explode("\n",$definition),static fn(string $line):bool=>!str_contains($line,'FOREIGN KEY')));
        $definition=preg_replace('/,\s*\n\)/',"\n)",$definition);
        $pdo->exec($definition);$temporary[]=$table;
    }
    $pdo->prepare("INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES ('Existing Admin','admin@gmail.com',?,'0700000000','Original admin address','admin','active')")->execute([password_hash('Original-admin-2026!',PASSWORD_DEFAULT)]);
    $admin=$pdo->query("SELECT * FROM users WHERE role='admin'")->fetch();
    $messages=load_sample_data($pdo);check(str_contains(implode(' ',$messages),'Created 3 demo accounts'),'demo import succeeds');
    check(counts()===['users'=>4,'organizations'=>1,'donations'=>7,'donation_requests'=>5,'collection_tasks'=>3],'expected complete fixture counts');
    check($pdo->query("SELECT * FROM users WHERE role='admin'")->fetch()===$admin,'existing administrator unchanged');
    $accounts=$pdo->query("SELECT * FROM users WHERE role<>'admin'")->fetchAll();foreach ($accounts as $account) {check($account['status']==='active' && password_verify('Demo@1234',$account['password_hash']),'demo '.$account['role'].' active with valid password hash');}
    check((int)$pdo->query("SELECT COUNT(*) FROM donations WHERE food_name LIKE '[Demo]%' AND available_at<NOW() AND deadline_at>NOW() AND status='available'")->fetchColumn()===1,'live walkthrough food is currently available');
    check((int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status='available' AND deadline_at<NOW()")->fetchColumn()===1,'expired demonstration is past pickup window');
    check((int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status='completed' AND completed_at IS NOT NULL")->fetchColumn()===1,'receipt-confirmed example provided');
    check((int)$pdo->query("SELECT COUNT(*) FROM collection_tasks WHERE status='delivered' AND accepted_at<=collected_at AND collected_at<=delivered_at")->fetchColumn()===2,'delivery examples have ordered recorded milestones');
    $pdo->exec("UPDATE donations SET description='Live demonstration edit' WHERE status='completed'");$snapshot=$pdo->query('SELECT * FROM donations ORDER BY id')->fetchAll();
    $messages=load_sample_data($pdo);check(str_contains(implode(' ',$messages),'already exists') && $pdo->query('SELECT * FROM donations ORDER BY id')->fetchAll()===$snapshot,'repeat import preserves workflow changes and creates nothing');
    $pdo->exec("UPDATE users SET full_name='Conflicting real user' WHERE email='donor.demo@food.test'");$beforeConflict=counts();
    try {load_sample_data($pdo);throw new RuntimeException('Collision accepted');}catch (PDOException $exception) {check(str_contains($exception->getMessage(),'collision') && counts()===$beforeConflict,'conflicting account aborts without adding records');}
    check($pdo->query("SELECT full_name FROM users WHERE email='donor.demo@food.test'")->fetchColumn()==='Conflicting real user','collision does not replace existing identity');
    foreach (array_reverse($tables) as $table) {$pdo->exec('DELETE FROM '.$table);}
    try {load_sample_data($pdo);throw new RuntimeException('Missing administrator accepted');}catch (PDOException $exception) {check(str_contains($exception->getMessage(),'active administrator') && array_sum(counts())===0,'missing administrator aborts without partial data');}
} catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
finally {foreach (array_reverse($temporary) as $table) {$pdo->exec('DROP TEMPORARY TABLE '.$table);}if ($before) {check(counts()===$before,'real database row counts untouched by isolated seed tests');}echo 'Temporary demo-test tables removed.'.PHP_EOL;}
if (!isset($failed) && in_array('--verify-live',$argv,true)) {
    try {
        foreach (['donor'=>'donor.demo@food.test','organization'=>'organization.demo@food.test','volunteer'=>'volunteer.demo@food.test'] as $role=>$email) {
            $client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>15]);
            $base='http://localhost/Community%20Food%20Donation%20System/';curl_setopt($client,CURLOPT_URL,$base.'login.php');$html=curl_exec($client);
            if (!is_string($html) || !preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$token)) {throw new RuntimeException('Login form unavailable');}
            curl_setopt($client,CURLOPT_POST,true);curl_setopt($client,CURLOPT_POSTFIELDS,http_build_query(['csrf_token'=>$token[1],'email'=>$email,'password'=>'Demo@1234']));curl_exec($client);
            check(curl_getinfo($client,CURLINFO_HTTP_CODE)===303,'live demo '.$role.' signs in');
            curl_setopt($client,CURLOPT_HTTPGET,true);curl_setopt($client,CURLOPT_URL,$base.$role.'/index.php');$html=curl_exec($client);
            check(curl_getinfo($client,CURLINFO_HTTP_CODE)===200 && is_string($html) && str_contains($html,'[Demo]'),'live demo '.$role.' dashboard loads');curl_close($client);
        }
    } catch (Throwable $exception) {fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;}
}
exit(isset($failed)?1:0);
