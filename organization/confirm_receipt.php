<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('organization');
require_once __DIR__.'/../includes/organization_requests.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the receipt confirmation form on your request page.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload the request page and try again.');}
$id=donor_donation_id();
if (post_text('confirm_received')!=='yes') {http_response_code(400);exit('Confirm that your organization received this food.');}
try {
    confirm_organization_receipt((int)$user['id'],$id);
    unset($_SESSION['csrf_token']);$_SESSION['receipt_success']=$id;
    header('Location: '.app_url('organization/request.php?id='.$id),true,303);exit;
} catch (OutOfBoundsException $exception) {http_response_code(404);exit('Request not found.');}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Receipt confirmation failed. Code: '.$exception->getCode());donation_load_error();}
