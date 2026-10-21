<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('organization');
require_once __DIR__.'/../includes/organization_donations.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the request form on a food donation page.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload the food page and try again.');}
$id=donor_donation_id();$message=post_text('message');$address=post_text('delivery_address');
try {
    submit_organization_request((int)$user['id'],$id,$message,$address);
    unset($_SESSION['csrf_token']);$_SESSION['organization_request_success']='Your request was sent. The donor will review it.';
    header('Location: '.app_url('organization/donation.php?id='.$id),true,303);exit;
} catch (Throwable $exception) {
    if ($exception instanceof InvalidArgumentException) {
        $_SESSION['organization_request_errors']=[$exception->getMessage()];
        $_SESSION['organization_request_old']=['donation_id'=>$id,'message'=>mb_substr($message,0,1500),'address'=>mb_substr($address,0,255)];
        header('Location: '.app_url('organization/donation.php?id='.$id),true,303);exit;
    }
    if ($exception instanceof OutOfBoundsException) {http_response_code(404);exit('Donation not found.');}
    if ($exception instanceof DomainException) {http_response_code(409);exit($exception->getMessage());}
    error_log('Organization request failed. Code: '.$exception->getCode());donation_load_error();
}
