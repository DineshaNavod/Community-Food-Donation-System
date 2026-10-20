<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/volunteer_tasks.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the collection assignment form.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload assignments and try again.');}
$id=donor_donation_id();$volunteerId=is_string($_POST['volunteer_id']??null)?filter_var($_POST['volunteer_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
if (!$volunteerId) {http_response_code(400);exit('Choose an active volunteer.');}
try {
    assign_collection_volunteer((int)$user['id'],$id,$volunteerId);
    unset($_SESSION['csrf_token']);$_SESSION['assignment_success']='Volunteer assigned. The collection now appears in their My collection tasks.';
    header('Location: '.app_url('admin/assignments.php'),true,303);exit;
}catch (OutOfBoundsException $exception) {http_response_code(404);exit('Request not found.');}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Volunteer assignment failed. Code: '.$exception->getCode());donation_load_error();}
