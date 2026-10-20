<?php
declare(strict_types=1);
require_once __DIR__.'/organization_donations.php';

function organization_request(int $requestId,int $organizationId): ?array
{
    $query=db()->prepare('SELECT r.id,r.donation_id,r.message,r.delivery_address,r.status AS request_status,
        r.created_at,r.decided_at,d.food_name,d.quantity,d.quantity_unit,d.pickup_location,d.deadline_at,
        d.status,d.completed_at,u.full_name AS donor_name,u.phone AS donor_phone,u.email AS donor_email,
        t.id AS task_id,t.status AS task_status,t.assigned_at,t.accepted_at,t.collected_at,t.delivered_at,
        v.full_name AS volunteer_name,v.phone AS volunteer_phone
        FROM donation_requests r JOIN donations d ON d.id=r.donation_id JOIN users u ON u.id=d.donor_id
        LEFT JOIN collection_tasks t ON t.request_id=r.id LEFT JOIN users v ON v.id=t.volunteer_id
        WHERE r.id=? AND r.organization_id=?');
    $query->execute([$requestId,$organizationId]);return $query->fetch()?:null;
}

function organization_tracking_steps(array $request): array
{
    // Only real recorded timestamps are displayed; never infer delivery from a request decision.
    $approved=$request['request_status']==='approved';
    return [['Request submitted',$request['created_at']],
        ['Donor approval',$approved?$request['decided_at']:null],
        ['Volunteer assigned',$approved?$request['assigned_at']:null],
        ['Food collected',$approved?$request['collected_at']:null],
        ['Food delivered',$approved?$request['delivered_at']:null],
        ['Receipt confirmed',$approved && $request['status']==='completed'?$request['completed_at']:null]];
}

function can_confirm_receipt(array $request): bool
{
    return $request['request_status']==='approved' && $request['status']==='delivered'
        && $request['task_status']==='delivered' && $request['delivered_at']!==null
        && $request['completed_at']===null;
}

function confirm_organization_receipt(int $userId,int $requestId): void
{
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT role,status FROM users WHERE id=? FOR UPDATE');
        $query->execute([$userId]);$account=$query->fetch();
        if (!$account || $account['role']!=='organization' || $account['status']!=='active') {
            throw new DomainException('Your account cannot confirm receipt.');
        }
        $query=$pdo->prepare('SELECT r.donation_id,r.organization_id FROM donation_requests r
            JOIN organizations o ON o.id=r.organization_id WHERE r.id=? AND o.user_id=?');
        $query->execute([$requestId,$userId]);$lookup=$query->fetch();
        if (!$lookup) {throw new OutOfBoundsException('Request not found.');}
        // Use the same donation-before-request lock order as other workflow actions.
        $query=$pdo->prepare('SELECT status,completed_at FROM donations WHERE id=? FOR UPDATE');
        $query->execute([$lookup['donation_id']]);$donation=$query->fetch();
        $query=$pdo->prepare('SELECT status FROM donation_requests WHERE id=? AND organization_id=? FOR UPDATE');
        $query->execute([$requestId,$lookup['organization_id']]);$requestStatus=$query->fetchColumn();
        $query=$pdo->prepare('SELECT status,delivered_at FROM collection_tasks WHERE request_id=? FOR UPDATE');
        $query->execute([$requestId]);$task=$query->fetch();
        if (!$donation || !$task || !can_confirm_receipt([
            'request_status'=>$requestStatus,'status'=>$donation['status'],
            'completed_at'=>$donation['completed_at'],'task_status'=>$task['status'],
            'delivered_at'=>$task['delivered_at']])) {
            throw new DomainException('Receipt can only be confirmed once, after your approved donation is delivered.');
        }
        $pdo->prepare("UPDATE donations SET status='completed',completed_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$lookup['donation_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}
