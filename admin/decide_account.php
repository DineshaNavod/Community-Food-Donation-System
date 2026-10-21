<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/admin_accounts.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the account approval form.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload account approvals and try again.');}
$id=is_string($_GET['id']??null)?filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
if (!$id) {http_response_code(404);exit('Account not found.');}
if (post_text('reviewed')!=='yes') {http_response_code(400);exit('Review the registration before deciding.');}
try {
    $decision=post_text('decision');decide_pending_account((int)$user['id'],$id,$decision);
    unset($_SESSION['csrf_token']);$_SESSION['account_approval_success']=$decision==='approve'?'Account approved. This user can now sign in.':'Registration rejected. This user cannot sign in.';
    header('Location: '.app_url('admin/accounts.php'),true,303);exit;
} catch (OutOfBoundsException $exception) {http_response_code(404);exit('Account not found.');}
catch (InvalidArgumentException $exception) {http_response_code(400);exit($exception->getMessage());}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Account decision failed. Code: '.$exception->getCode());http_response_code(503);exit('This registration could not be updated. Please try again later.');}
