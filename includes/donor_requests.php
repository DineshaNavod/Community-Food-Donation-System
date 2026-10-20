<?php
declare(strict_types=1);
require_once __DIR__.'/donor_donations.php';

function owned_request(int $requestId, int $donorId): ?array
{
    $query=db()->prepare('SELECT r.id,r.donation_id,r.organization_id,r.message,r.delivery_address,
        r.status AS request_status,r.created_at,r.decided_at,d.food_name,d.status AS donation_status,
        d.deadline_at,o.organization_name,u.full_name,u.email,u.phone,u.status AS organization_status,
        u.role AS organization_role FROM donation_requests r JOIN donations d ON d.id=r.donation_id
        JOIN organizations o ON o.id=r.organization_id JOIN users u ON u.id=o.user_id
        WHERE r.id=? AND d.donor_id=?');
    $query->execute([$requestId,$donorId]);
    return $query->fetch() ?: null;
}

function decide_donation_request(int $donorId, int $requestId, string $decision): int
{
    if (!in_array($decision,['approve','reject'],true)) {
        throw new InvalidArgumentException('Choose approve or reject.');
    }
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT r.donation_id,o.user_id FROM donation_requests r
            JOIN donations d ON d.id=r.donation_id JOIN organizations o ON o.id=r.organization_id
            WHERE r.id=? AND d.donor_id=?');
        $query->execute([$requestId,$donorId]);$lookup=$query->fetch();
        if (!$lookup) {throw new OutOfBoundsException('Request not found.');}
        // Lock participating accounts before the donation, in a consistent order.
        $query=$pdo->prepare('SELECT id,role,status FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $query->execute([$donorId,$lookup['user_id']]);$accounts=[];
        foreach ($query->fetchAll() as $account) {$accounts[(int)$account['id']]=$account;}
        $donor=$accounts[$donorId]??null;
        if (!$donor || $donor['role']!=='donor' || $donor['status']!=='active') {
            throw new DomainException('Your account cannot decide donation requests.');
        }
        $donation=owned_donation((int)$lookup['donation_id'],$donorId,true);
        if (!$donation) {throw new OutOfBoundsException('Request not found.');}
        $query=$pdo->prepare('SELECT status FROM donation_requests WHERE id=? AND donation_id=? FOR UPDATE');
        $query->execute([$requestId,$donation['id']]);$status=$query->fetchColumn();
        if ($status!=='pending') {throw new DomainException('This request has already been decided or cancelled.');}
        if (!in_array(donation_display_status($donation),['available','requested'],true) || $donation['approved_count']>0) {
            throw new DomainException('This donation is no longer open for request decisions.');
        }
        if ($decision==='approve') {
            $organization=$accounts[(int)$lookup['user_id']]??null;
            if (!$organization || $organization['role']!=='organization' || $organization['status']!=='active') {
                throw new DomainException('Only an active, approved organization can receive a donation.');
            }
            $pdo->prepare("UPDATE donation_requests SET status='approved',decided_at=NOW() WHERE id=?")->execute([$requestId]);
            $pdo->prepare("UPDATE donation_requests SET status='rejected',decided_at=NOW()
                WHERE donation_id=? AND id<>? AND status='pending'")->execute([$donation['id'],$requestId]);
            // Requested remains the donation state until an admin assigns a volunteer.
            $pdo->prepare("UPDATE donations SET status='requested',updated_at=NOW() WHERE id=?")->execute([$donation['id']]);
        } else {
            $pdo->prepare("UPDATE donation_requests SET status='rejected',decided_at=NOW() WHERE id=?")->execute([$requestId]);
            $query=$pdo->prepare("SELECT id FROM donation_requests WHERE donation_id=? AND status='pending' FOR UPDATE");
            $query->execute([$donation['id']]);
            $nextStatus=count($query->fetchAll())>0?'requested':'available';
            $pdo->prepare('UPDATE donations SET status=?,updated_at=NOW() WHERE id=?')->execute([$nextStatus,$donation['id']]);
        }
        $pdo->commit();return (int)$donation['id'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {$pdo->rollBack();}
        throw $exception;
    }
}
