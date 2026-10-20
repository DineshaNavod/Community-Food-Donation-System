<?php
declare(strict_types=1);
// Reuse donation formatting/validation constants; entry pages enforce the role.
require_once __DIR__.'/donor_donations.php';

function organization_profile(int $userId): array
{
    $query=db()->prepare('SELECT id,organization_name FROM organizations WHERE user_id=?');
    $query->execute([$userId]);$profile=$query->fetch();
    if (!$profile) {http_response_code(409);exit('Your organization profile is missing. Please contact the administrator.');}
    return $profile;
}

function organization_donation(int $id,int $organizationId): ?array
{
    $query=db()->prepare('SELECT d.*,u.full_name AS donor_name,u.phone AS donor_phone,u.email AS donor_email,
        u.status AS donor_status,u.role AS donor_role,r.id AS own_request_id,r.status AS own_request_status,
        r.message AS own_message,r.delivery_address AS own_delivery_address,
        (SELECT COUNT(*) FROM donation_requests a WHERE a.donation_id=d.id AND a.status=\'approved\') AS approved_count
        FROM donations d JOIN users u ON u.id=d.donor_id
        LEFT JOIN donation_requests r ON r.donation_id=d.id AND r.organization_id=? WHERE d.id=?');
    $query->execute([$organizationId,$id]);return $query->fetch()?:null;
}

function organization_food_available(array $donation): bool
{
    return in_array($donation['status'],['available','requested'],true)
        && strtotime($donation['available_at'])<=time() && strtotime($donation['deadline_at'])>time()
        && $donation['donor_status']==='active' && $donation['donor_role']==='donor'
        && (int)$donation['approved_count']===0;
}

function submit_organization_request(int $userId,int $donationId,string $message,string $address): int
{
    if (!mb_check_encoding($message,'UTF-8') || !mb_check_encoding($address,'UTF-8')
        || str_contains($message,"\0") || str_contains($address,"\0") || mb_strlen($message)>1500
        || mb_strlen($address)<5 || mb_strlen($address)>255) {
        throw new InvalidArgumentException('Use a delivery address of 5 to 255 characters and a message up to 1500 characters.');
    }
    $pdo=db();
    try {
        $pdo->beginTransaction();
        $query=$pdo->prepare('SELECT donor_id FROM donations WHERE id=?');$query->execute([$donationId]);$donorId=$query->fetchColumn();
        if ($donorId===false) {throw new OutOfBoundsException('Donation not found.');}
        $query=$pdo->prepare('SELECT id,role,status FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');
        $query->execute([$userId,$donorId]);$accounts=[];
        foreach ($query->fetchAll() as $account) {$accounts[(int)$account['id']]=$account;}
        $orgUser=$accounts[$userId]??null;$donor=$accounts[(int)$donorId]??null;
        if (!$orgUser || $orgUser['role']!=='organization' || $orgUser['status']!=='active'
            || !$donor || $donor['role']!=='donor' || $donor['status']!=='active') {
            throw new DomainException('Only active organizations can request food from active donors.');
        }
        $query=$pdo->prepare('SELECT id FROM organizations WHERE user_id=? FOR UPDATE');$query->execute([$userId]);$organizationId=$query->fetchColumn();
        if ($organizationId===false) {throw new DomainException('Your organization profile is missing. Contact the administrator.');}
        $query=$pdo->prepare('SELECT * FROM donations WHERE id=? FOR UPDATE');$query->execute([$donationId]);$donation=$query->fetch();
        if (!$donation) {throw new OutOfBoundsException('Donation not found.');}
        if (!in_array($donation['status'],['available','requested'],true) || strtotime($donation['available_at'])>time()
            || strtotime($donation['deadline_at'])<=time()) {throw new DomainException('This donation is not currently available for requests.');}
        $query=$pdo->prepare('SELECT organization_id,status FROM donation_requests WHERE donation_id=? FOR UPDATE');$query->execute([$donationId]);
        foreach ($query->fetchAll() as $request) {
            if ((int)$request['organization_id']===(int)$organizationId) {throw new DomainException('Your organization has already requested this donation.');}
            if ($request['status']==='approved') {throw new DomainException('This donation has already been allocated.');}
        }
        $query=$pdo->prepare("INSERT INTO donation_requests (donation_id,organization_id,message,delivery_address,status) VALUES (?,?,?,?,'pending')");
        $query->execute([$donationId,$organizationId,$message===''?null:$message,$address]);$requestId=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE donations SET status='requested',updated_at=NOW() WHERE id=?")->execute([$donationId]);
        $pdo->commit();return $requestId;
    } catch (Throwable $exception) {if ($pdo->inTransaction()) {$pdo->rollBack();}throw $exception;}
}
