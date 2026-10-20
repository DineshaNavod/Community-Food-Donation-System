<?php
declare(strict_types=1);
require_once __DIR__.'/donor_donations.php';

function volunteer_task(int $taskId,int $volunteerId): ?array
{
    $query=db()->prepare('SELECT t.id,t.status AS task_status,t.assigned_at,t.accepted_at,t.collected_at,t.delivered_at,
        d.food_name,d.description,d.quantity,d.quantity_unit,d.pickup_location,d.available_at,d.deadline_at,
        d.status,d.completed_at,r.status AS request_status,r.delivery_address,r.message,
        donor.full_name AS donor_name,donor.phone AS donor_phone,
        o.organization_name,recipient.full_name AS recipient_name,recipient.phone AS recipient_phone
        FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id
        JOIN donations d ON d.id=r.donation_id JOIN users donor ON donor.id=d.donor_id
        JOIN organizations o ON o.id=r.organization_id JOIN users recipient ON recipient.id=o.user_id
        WHERE t.id=? AND t.volunteer_id=?');
    $query->execute([$taskId,$volunteerId]);return $query->fetch()?:null;
}

function assign_collection_volunteer(int $adminId,int $requestId,int $volunteerId): void
{
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT r.donation_id,d.donor_id,o.user_id AS recipient_id FROM donation_requests r
            JOIN donations d ON d.id=r.donation_id JOIN organizations o ON o.id=r.organization_id WHERE r.id=?');
        $query->execute([$requestId]);$lookup=$query->fetch();
        if (!$lookup) {throw new OutOfBoundsException('Request not found.');}
        $query=$pdo->prepare('SELECT id,role,status FROM users WHERE id IN (?,?,?,?) ORDER BY id FOR UPDATE');
        $query->execute([$adminId,$volunteerId,$lookup['donor_id'],$lookup['recipient_id']]);$accounts=[];
        foreach ($query->fetchAll() as $account) {$accounts[(int)$account['id']]=$account;}
        foreach ([[$adminId,'admin'],[$volunteerId,'volunteer'],[(int)$lookup['donor_id'],'donor'],[(int)$lookup['recipient_id'],'organization']] as [$id,$role]) {
            if (!isset($accounts[$id]) || $accounts[$id]['role']!==$role || $accounts[$id]['status']!=='active') {
                throw new DomainException('Assignment requires an active administrator, volunteer, donor and organization.');
            }
        }
        $query=$pdo->prepare('SELECT status,available_at,deadline_at FROM donations WHERE id=? FOR UPDATE');
        $query->execute([$lookup['donation_id']]);$donation=$query->fetch();
        $query=$pdo->prepare('SELECT status FROM donation_requests WHERE id=? FOR UPDATE');$query->execute([$requestId]);
        if ($query->fetchColumn()!=='approved' || !$donation || $donation['status']!=='requested'
            || strtotime($donation['available_at'])>time() || strtotime($donation['deadline_at'])<=time()) {
            throw new DomainException('Only an approved donation within its pickup window can be assigned.');
        }
        $query=$pdo->prepare('SELECT id FROM collection_tasks WHERE request_id=? FOR UPDATE');$query->execute([$requestId]);
        if ($query->fetchColumn()) {throw new DomainException('This request already has a collection task.');}
        $pdo->prepare("INSERT INTO collection_tasks (request_id,volunteer_id,assigned_by,status) VALUES (?,?,?,'assigned')")
            ->execute([$requestId,$volunteerId,$adminId]);
        $pdo->prepare("UPDATE donations SET status='assigned',updated_at=NOW() WHERE id=?")->execute([$lookup['donation_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}

function volunteer_task_finished(array $task): bool
{
    return $task['request_status']==='approved' && $task['task_status']==='delivered'
        && $task['delivered_at']!==null && in_array($task['status'],['delivered','completed'],true);
}

function volunteer_next_action(array $task): ?string
{
    if ($task['request_status']!=='approved' || $task['completed_at']!==null
        || $task['assigned_at']===null || $task['delivered_at']!==null) {return null;}
    if ($task['task_status']==='collected' && $task['status']==='collected'
        && $task['accepted_at']!==null && $task['collected_at']!==null) {return 'deliver';}
    if ($task['status']!=='assigned' || strtotime($task['available_at'])>time()
        || strtotime($task['deadline_at'])<=time() || $task['collected_at']!==null) {return null;}
    if ($task['task_status']==='assigned' && $task['accepted_at']===null) {return 'accept';}
    if ($task['task_status']==='accepted' && $task['accepted_at']!==null) {return 'collect';}
    return null;
}

function update_volunteer_task(int $volunteerId,int $taskId,string $action): void
{
    if (!in_array($action,['accept','collect','deliver'],true)) {throw new InvalidArgumentException('Choose a valid collection update.');}
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT role,status FROM users WHERE id=? FOR UPDATE');$query->execute([$volunteerId]);$account=$query->fetch();
        if (!$account || $account['role']!=='volunteer' || $account['status']!=='active') {
            throw new DomainException('Your account cannot update collection tasks.');
        }
        $query=$pdo->prepare('SELECT r.id AS request_id,r.donation_id FROM collection_tasks t
            JOIN donation_requests r ON r.id=t.request_id WHERE t.id=? AND t.volunteer_id=?');
        $query->execute([$taskId,$volunteerId]);$lookup=$query->fetch();
        if (!$lookup) {throw new OutOfBoundsException('Collection task not found.');}
        // Match assignment and receipt confirmation: donation, request, then task.
        $query=$pdo->prepare('SELECT status,available_at,deadline_at,completed_at FROM donations WHERE id=? FOR UPDATE');
        $query->execute([$lookup['donation_id']]);$donation=$query->fetch();
        $query=$pdo->prepare('SELECT status FROM donation_requests WHERE id=? FOR UPDATE');$query->execute([$lookup['request_id']]);$requestStatus=$query->fetchColumn();
        $query=$pdo->prepare('SELECT status AS task_status,assigned_at,accepted_at,collected_at,delivered_at
            FROM collection_tasks WHERE id=? AND volunteer_id=? AND request_id=? FOR UPDATE');
        $query->execute([$taskId,$volunteerId,$lookup['request_id']]);$task=$query->fetch();
        if (!$donation || !$task || volunteer_next_action($task+$donation+['request_status'=>$requestStatus])!==$action) {
            throw new DomainException('This update is no longer available. Reload the task and check its progress and pickup deadline.');
        }
        // Column names are fixed here, never taken from form data.
        $sql=match($action) {
            'accept'=>"UPDATE collection_tasks SET status='accepted',accepted_at=NOW(),updated_at=NOW() WHERE id=?",
            'collect'=>"UPDATE collection_tasks SET status='collected',collected_at=NOW(),updated_at=NOW() WHERE id=?",
            'deliver'=>"UPDATE collection_tasks SET status='delivered',delivered_at=NOW(),updated_at=NOW() WHERE id=?"
        };
        $pdo->prepare($sql)->execute([$taskId]);
        if ($action!=='accept') {
            $pdo->prepare('UPDATE donations SET status=?,updated_at=NOW() WHERE id=?')
                ->execute([$action==='collect'?'collected':'delivered',$lookup['donation_id']]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}
