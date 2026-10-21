<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/admin_accounts.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);header('Allow: POST');exit('Use the access controls on Registered users.');}
if (!valid_csrf_token($_POST['csrf_token']??null)) {http_response_code(403);exit('Invalid form session. Reload registered users and try again.');}
$id=is_string($_GET['id']??null)?filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):false;
if (!$id) {http_response_code(404);exit('Account not found.');}
if (post_text('reviewed')!=='yes') {http_response_code(400);exit('Confirm that you reviewed this account before changing access.');}
try {
    $action=post_text('action');change_account_access((int)$user['id'],$id,$action);
    unset($_SESSION['csrf_token']);$_SESSION['access_success']=$action==='suspend'?'Account suspended. Sign-in and protected actions are now blocked.':'Account reactivated. This user can sign in again.';
    header('Location: '.app_url('admin/users.php'),true,303);exit;
} catch (OutOfBoundsException $exception) {http_response_code(404);exit('Account not found.');}
catch (InvalidArgumentException $exception) {http_response_code(400);exit($exception->getMessage());}
catch (DomainException $exception) {http_response_code(409);exit($exception->getMessage());}
catch (Throwable $exception) {error_log('Account access update failed. Code: '.$exception->getCode());http_response_code(503);exit('Account access could not be updated. Please try again later.');}
