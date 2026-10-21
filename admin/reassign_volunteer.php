<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/admin_workflow.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the reassignment form on donation details.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload donation details and try again.');}
$id=donor_donation_id();$ids=[];
foreach (['volunteer_id','current_volunteer_id'] as $field) {$value=is_string($_POST[$field]??null)?filter_var($_POST[$field],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;if (!$value) {http_response_code(400);exit('Choose a valid volunteer and reload this assignment.');}$ids[$field]=$value;}
if (post_text('confirm_reassign')!=='yes') {http_response_code(400);exit('Confirm reassignment before submitting.');}
try {
    $donationId=admin_reassign_task((int)$user['id'],$id,$ids['volunteer_id'],$ids['current_volunteer_id']);
    unset($_SESSION['csrf_token']);$_SESSION['admin_workflow_success']=['id'=>$donationId,'message'=>'Volunteer reassigned. The task now belongs to the selected volunteer and awaits acceptance.'];
    header('Location: '.app_url('admin/donation.php?id='.$donationId),true,303);exit;
}catch (OutOfBoundsException $exception) {http_response_code(404);exit('Collection task not found.');}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Admin reassignment failed. Code: '.$exception->getCode());donation_load_error();}
