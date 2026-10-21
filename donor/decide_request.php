<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('donor');
require_once __DIR__.'/../includes/donor_requests.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the decision buttons on the request page.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload the request page and try again.');}
$id=donor_donation_id();$decision=post_text('decision');
try {
    decide_donation_request((int)$user['id'],$id,$decision);
    $_SESSION['request_success']=$decision==='approve'?'Request approved. Other pending requests were rejected. Awaiting volunteer assignment.':'Request rejected. The decision has been kept in history.';
    unset($_SESSION['csrf_token']);header('Location: '.app_url('donor/request.php?id='.$id),true,303);exit;
} catch (Throwable $exception) {
    if ($exception instanceof OutOfBoundsException) {http_response_code(404);exit('Request not found.');}
    if ($exception instanceof InvalidArgumentException) {http_response_code(422);exit($exception->getMessage());}
    if ($exception instanceof DomainException) {http_response_code(409);exit($exception->getMessage());}
    error_log('Request decision failed. Code: '.$exception->getCode());donation_load_error();
}
