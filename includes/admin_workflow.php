<?php
declare(strict_types=1);
require_once __DIR__.'/volunteer_tasks.php';

function admin_can_cancel_food(array $donation,array $tasks): bool
{
    if (!in_array($donation['status'],['available','requested','assigned','expired'],true) || $donation['completed_at']!==null) {return false;}
    foreach ($tasks as $task) {
        if ($task['collected_at']!==null || $task['delivered_at']!==null || in_array($task['task_status'],['collected','delivered'],true)) {return false;}
    }
    return true;
}

function admin_cancel_food(int $adminId,int $donationId): void
{
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT role,status FROM users WHERE id=? FOR UPDATE');$query->execute([$adminId]);$admin=$query->fetch();
        if (!$admin || $admin['role']!=='admin' || $admin['status']!=='active') {throw new DomainException('Your account cannot cancel donations.');}
        $query=$pdo->prepare('SELECT status,completed_at FROM donations WHERE id=? FOR UPDATE');$query->execute([$donationId]);$donation=$query->fetch();
        if (!$donation) {throw new OutOfBoundsException('Donation not found.');}
        $query=$pdo->prepare('SELECT id FROM donation_requests WHERE donation_id=? ORDER BY id FOR UPDATE');$query->execute([$donationId]);$requestIds=$query->fetchAll(PDO::FETCH_COLUMN);
        $tasks=[];
        foreach ($requestIds as $id) {$query=$pdo->prepare('SELECT id,status AS task_status,collected_at,delivered_at FROM collection_tasks WHERE request_id=? FOR UPDATE');$query->execute([$id]);$task=$query->fetch();if ($task) {$tasks[]=$task;}}
        if (!admin_can_cancel_food($donation,$tasks)) {throw new DomainException('This donation cannot be cancelled after collection or closure.');}
        $pdo->prepare("UPDATE donation_requests SET status='cancelled',decided_at=COALESCE(decided_at,NOW()) WHERE donation_id=? AND status IN ('pending','approved')")->execute([$donationId]);
        foreach ($tasks as $task) {$pdo->prepare("UPDATE collection_tasks SET status='cancelled',updated_at=NOW() WHERE id=? AND status IN ('assigned','accepted')")->execute([$task['id']]);}
        $pdo->prepare("UPDATE donations SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([$donationId]);
        $pdo->commit();
    } catch (Throwable $exception) {if ($pdo->inTransaction()) {$pdo->rollBack();}throw $exception;}
}

function admin_reassign_task(int $adminId,int $taskId,int $volunteerId,int $expectedVolunteerId): int
{
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT t.request_id,r.donation_id,d.donor_id,o.user_id AS recipient_id
            FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id JOIN donations d ON d.id=r.donation_id
            JOIN organizations o ON o.id=r.organization_id WHERE t.id=?');$query->execute([$taskId]);$lookup=$query->fetch();
        if (!$lookup) {throw new OutOfBoundsException('Collection task not found.');}
        $query=$pdo->prepare('SELECT id,role,status FROM users WHERE id IN (?,?,?,?) ORDER BY id FOR UPDATE');$query->execute([$adminId,$volunteerId,$lookup['donor_id'],$lookup['recipient_id']]);$accounts=[];
        foreach ($query->fetchAll() as $account) {$accounts[(int)$account['id']]=$account;}
        foreach ([[$adminId,'admin'],[$volunteerId,'volunteer'],[(int)$lookup['donor_id'],'donor'],[(int)$lookup['recipient_id'],'organization']] as [$id,$role]) {
            if (!isset($accounts[$id]) || $accounts[$id]['role']!==$role || $accounts[$id]['status']!=='active') {throw new DomainException('Reassignment requires an active administrator, volunteer, donor and organization.');}
        }
        $query=$pdo->prepare('SELECT status,available_at,deadline_at,completed_at FROM donations WHERE id=? FOR UPDATE');$query->execute([$lookup['donation_id']]);$donation=$query->fetch();
        $query=$pdo->prepare('SELECT status FROM donation_requests WHERE id=? FOR UPDATE');$query->execute([$lookup['request_id']]);$requestStatus=$query->fetchColumn();
        $query=$pdo->prepare('SELECT volunteer_id,status AS task_status,assigned_at,accepted_at,collected_at,delivered_at FROM collection_tasks WHERE id=? AND request_id=? FOR UPDATE');$query->execute([$taskId,$lookup['request_id']]);$task=$query->fetch();
        if (!$task || !$donation || (int)$task['volunteer_id']!==$expectedVolunteerId || $volunteerId===$expectedVolunteerId
            || volunteer_next_action($task+$donation+['request_status'=>$requestStatus])!=='accept') {
            throw new DomainException('Reassignment is available only before acceptance, within the pickup window. Reload the donation and choose a different volunteer.');
        }
        // Retain the task ID and request; replace only the still-unaccepted assignment.
        $pdo->prepare('UPDATE collection_tasks SET volunteer_id=?,assigned_by=?,assigned_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$volunteerId,$adminId,$taskId]);
        $pdo->prepare('UPDATE donations SET updated_at=NOW() WHERE id=?')->execute([$lookup['donation_id']]);
        $pdo->commit();return (int)$lookup['donation_id'];
    } catch (Throwable $exception) {if ($pdo->inTransaction()) {$pdo->rollBack();}throw $exception;}
}
