<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php'; $user=require_role('donor');
require_once __DIR__.'/../includes/donor_donations.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the cancellation form on your donation page.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload your donation page and try again.');}
if (post_text('confirm')!=='1') {http_response_code(422);exit('Please confirm cancellation on the donation page.');}
$id=donor_donation_id();$pdo=db();
try {
    $pdo->beginTransaction();lock_active_donor((int)$user['id']);
    $donation=owned_donation($id,(int)$user['id'],true);
    if ($donation===null) {$pdo->rollBack();http_response_code(404);exit('Donation not found.');}
    if (!can_cancel_donation($donation)) {throw new DomainException('This donation can no longer be cancelled.');}
    $pdo->prepare("UPDATE donation_requests SET status='cancelled',decided_at=NOW() WHERE donation_id=? AND status='pending'")->execute([$id]);
    $pdo->prepare("UPDATE donations SET status='cancelled' WHERE id=? AND donor_id=?")->execute([$id,$user['id']]);
    $pdo->commit();$_SESSION['donation_success']='Your donation was cancelled. Its history has been kept.';
    unset($_SESSION['csrf_token']);header('Location: '.app_url('donor/donation.php?id='.$id),true,303);exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {$pdo->rollBack();}
    if ($exception instanceof DomainException) {http_response_code(409);exit($exception->getMessage());}
    error_log('Cancellation failed. Code: '.$exception->getCode());donation_load_error();
}
