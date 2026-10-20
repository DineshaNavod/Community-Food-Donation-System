<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('volunteer');
require_once __DIR__.'/../includes/volunteer_tasks.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the update form on your collection task.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload the task and try again.');}
$id=donor_donation_id();
if (post_text('confirm_update')!=='yes') {http_response_code(400);exit('Confirm the collection update before submitting.');}
try {
    $action=post_text('action');update_volunteer_task((int)$user['id'],$id,$action);
    $messages=['accept'=>'Task accepted. You can now collect the food from the donor.',
        'collect'=>'Collection recorded. Deliver the food to the receiving organization.',
        'deliver'=>'Delivery recorded. The organization can now confirm receipt.'];
    unset($_SESSION['csrf_token']);$_SESSION['task_update_success']=['id'=>$id,'message'=>$messages[$action]];
    header('Location: '.app_url('volunteer/task.php?id='.$id),true,303);exit;
}catch (OutOfBoundsException $exception) {http_response_code(404);exit('Collection task not found.');}
catch (InvalidArgumentException $exception) {http_response_code(400);exit($exception->getMessage());}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Volunteer update failed. Code: '.$exception->getCode());donation_load_error();}
