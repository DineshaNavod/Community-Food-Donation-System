<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/admin_workflow.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the cancellation form on donation details.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload donation details and try again.');}
$id=donor_donation_id();
if (post_text('confirm_cancel')!=='yes') {http_response_code(400);exit('Confirm cancellation before submitting.');}
try {
    admin_cancel_food((int)$user['id'],$id);unset($_SESSION['csrf_token']);$_SESSION['admin_workflow_success']=['id'=>$id,'message'=>'Donation cancelled. Open requests and collection tasks were closed; their records were retained.'];
    header('Location: '.app_url('admin/donation.php?id='.$id),true,303);exit;
}catch (OutOfBoundsException $exception) {http_response_code(404);exit('Donation not found.');}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Admin cancellation failed. Code: '.$exception->getCode());donation_load_error();}
