<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard.php';
$pdo = db();
$prefix = 'dashboard-check-' . bin2hex(random_bytes(5));
function check(bool $passed, string $label): void
{
    if (!$passed) { throw new RuntimeException($label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function numbers(array $dashboard): array
{
    return array_map(static fn(array $stat): int => (int) $stat[1], $dashboard['statistics']);
}
try {
    $baseline = dashboard_data(['id'=>0, 'role'=>'admin']);
    $pdo->beginTransaction();
    $users = [];
    foreach (['donor-a'=>'donor','donor-b'=>'donor','org-a'=>'organization','org-b'=>'organization',
        'vol-a'=>'volunteer','vol-b'=>'volunteer','admin'=>'admin','pending'=>'volunteer'] as $name=>$role) {
        $pdo->prepare('INSERT INTO users (full_name,email,password_hash,phone,address,role,status) VALUES (?,?,?,?,?,?,?)')->execute([
            $prefix . '-' . $name, $prefix . '-' . $name . '@example.test', password_hash('Dashboard-check-2026!', PASSWORD_DEFAULT),
            '0700000000','Test address',$role,$name==='pending'?'pending':'active']);
        $users[$name] = ['id'=>(int)$pdo->lastInsertId(),'role'=>$role];
    }
    $orgs = [];
    foreach (['org-a','org-b'] as $name) {
        $pdo->prepare('INSERT INTO organizations (user_id,organization_name) VALUES (?,?)')->execute([$users[$name]['id'],$prefix.'-'.$name]);
        $orgs[$name] = (int)$pdo->lastInsertId();
    }
    $foods = [];
    foreach (['available','completed','expired','assigned','collected'] as $name) {
        $pdo->prepare('INSERT INTO donations (donor_id,food_name,category,description,quantity,quantity_unit,pickup_location,available_at,deadline_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $users[$name==='collected'?'donor-b':'donor-a']['id'], $prefix.'-'.$name,'produce','Test',10,'kg','Test pickup',
            date('Y-m-d H:i:s',time()-7200),date('Y-m-d H:i:s',$name==='expired'?time()-3600:time()+7200),$name==='expired'?'available':$name]);
        $foods[$name] = (int)$pdo->lastInsertId();
    }
    foreach (['available','completed','assigned','collected'] as $name) {
        $pdo->prepare('INSERT INTO donation_requests (donation_id,organization_id,delivery_address,status) VALUES (?,?,?,?)')->execute([
            $foods[$name],$orgs[$name==='collected'?'org-b':'org-a'],'Test delivery',$name==='available'?'pending':'approved']);
        $requestId = (int)$pdo->lastInsertId();
        if (in_array($name,['assigned','collected'],true)) {
            $pdo->prepare('INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status) VALUES (?,?,?,?)')->execute([
                $requestId,$users[$name==='assigned'?'vol-a':'vol-b']['id'],$users['admin']['id'],$name]);
        }
    }
    foreach (['donor-a'=>[4,1,1,1],'donor-b'=>[1,0,0,0],'org-a'=>[3,1,1,1],
        'org-b'=>[1,0,1,0],'vol-a'=>[1,1,0,0],'vol-b'=>[1,0,1,0]] as $name=>$expected) {
        $data = dashboard_data($users[$name]);
        check(!$data['error'] && numbers($data)===$expected,$name.' scoped statistics');
        $expectedRows = ['donor-a'=>4,'donor-b'=>1,'org-a'=>3,'org-b'=>1,'vol-a'=>1,'vol-b'=>1][$name];
        check(count($data['records'])===$expectedRows,$name.' scoped record count');
        if (in_array($name,['donor-a','org-a','vol-a'],true)) {
            check(!str_contains(json_encode($data['records']),$prefix.'-collected'),$name.' cannot see unrelated collection');
        }
        if ($name==='donor-a') {
            $expired = array_values(array_filter($data['records'],static fn(array $row):bool=>$row['food_name']===$prefix.'-expired'));
            check($expired[0]['donation_status']==='expired','overdue food displayed as expired');
            $query=$pdo->prepare('SELECT status FROM donations WHERE id=?');$query->execute([$foods['expired']]);
            check($query->fetchColumn()==='available','dashboard read does not alter workflow records');
        }
    }
    $admin = dashboard_data($users['admin']);
    $expected = numbers($baseline); $expected[0]+=8;$expected[1]+=5;$expected[2]+=4;$expected[3]+=1;
    check(numbers($admin)===$expected,'admin system totals');
    check($admin['pending_count']===$baseline['pending_count']+1,'admin pending approval count');
    check(count($admin['records'])<=5 && count($admin['pending_accounts'])<=5,'recent lists bounded to five records');
    check(!str_contains(status_badge('<script>'),'script') && str_contains(status_badge('<script>'),'Unknown'),'unknown status safely labelled');
} catch (Throwable $exception) {
    fwrite(STDERR,'FAIL: '.$exception->getMessage().PHP_EOL);$failed=true;
} finally {
    if ($pdo->inTransaction()) {$pdo->rollBack();}
    echo 'All dashboard fixtures rolled back.' . PHP_EOL;
}
exit(isset($failed)?1:0);
