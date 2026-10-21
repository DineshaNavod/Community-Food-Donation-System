<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/dashboard.php';
$role=is_string($_GET['role']??null)?$_GET['role']:'all';
if (!in_array($role,['all','organization','volunteer'],true)) {http_response_code(400);exit('Invalid role filter.');}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
if ($page===false) {http_response_code(400);exit('Invalid page.');}
$where="u.status='pending' AND u.role IN ('organization','volunteer')";$parameters=[];
if ($role!=='all') {$where.=' AND u.role=?';$parameters[]=$role;}
try {
    $query=db()->prepare("SELECT COUNT(*) FROM users u WHERE $where");$query->execute($parameters);$total=(int)$query->fetchColumn();
    $pages=max(1,(int)ceil($total/20));$page=min($page,$pages);$offset=($page-1)*20;
    $query=db()->prepare("SELECT u.id,u.full_name,u.email,u.phone,u.address,u.role,u.created_at,
        o.organization_name,o.description FROM users u LEFT JOIN organizations o ON o.user_id=u.id
        WHERE $where ORDER BY u.created_at,u.id LIMIT 20 OFFSET $offset");$query->execute($parameters);$accounts=$query->fetchAll();
} catch (PDOException $exception) {error_log('Approval list failed. Code: '.$exception->getCode());http_response_code(503);exit('Account approvals are temporarily unavailable. Please try again later.');}
$message=$_SESSION['account_approval_success']??'';unset($_SESSION['account_approval_success']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Account approvals | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?><main class="container py-5" id="main-content"><div class="row g-4"><?php $adminPage='accounts';require __DIR__.'/../includes/admin_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="approvals-heading"><div class="dashboard-welcome mb-4"><h1 class="h2" id="approvals-heading">Account approvals</h1><p class="text-secondary">Review organization and volunteer registrations. Approving an account allows it to sign in.</p></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<form method="get" class="account-card mb-4"><label for="role" class="form-label">Registration type</label><div class="d-flex flex-wrap gap-2"><select id="role" name="role" class="form-select w-auto"><?php foreach (['all'=>'All pending accounts','organization'=>'Organizations','volunteer'=>'Volunteers'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $role===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-community">Filter</button></div><p class="form-text mb-0"><?= e((string)$total) ?> pending accounts</p></form>
<?php if (!$accounts): ?><div class="account-card">No pending accounts match this filter.</div><?php endif; ?>
<?php foreach ($accounts as $account): ?><article class="account-card mb-4"><h2 class="h5"><?= e($account['full_name']) ?></h2><p class="small text-secondary"><?= $account['role']==='organization'?'Organization':'Volunteer' ?> &middot; Registered <?= e(dashboard_date($account['created_at'])) ?></p><dl class="row account-details">
<?php if ($account['role']==='organization'): ?><dt class="col-sm-3">Organization</dt><dd class="col-sm-9"><?= e($account['organization_name']??'Missing organization profile') ?></dd><dt class="col-sm-3">Description</dt><dd class="col-sm-9 donation-description"><?= e($account['description']??'Not provided') ?></dd><?php endif; ?>
<dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= e($account['email']) ?></dd><dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= e($account['phone']) ?></dd><dt class="col-sm-3">Address</dt><dd class="col-sm-9 donation-description"><?= e($account['address']) ?></dd></dl>
<form method="post" action="<?= e(app_url('admin/decide_account.php?id='.$account['id'])) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="form-check mb-3"><input class="form-check-input" id="reviewed-<?= e((string)$account['id']) ?>" name="reviewed" value="yes" type="checkbox" required><label class="form-check-label" for="reviewed-<?= e((string)$account['id']) ?>">I have reviewed this registration.</label></div><div class="d-flex gap-2 flex-wrap"><button class="btn btn-community" name="decision" value="approve" <?= $account['role']==='organization' && $account['organization_name']===null?'disabled':'' ?>>Approve account</button><button class="btn btn-outline-danger" name="decision" value="reject">Reject account</button></div></form></article><?php endforeach; ?>
<nav class="d-flex flex-wrap gap-3 align-items-center" aria-label="Account approval pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="?<?= e(http_build_query(['role'=>$role,'page'=>$page-1])) ?>">Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="?<?= e(http_build_query(['role'=>$role,'page'=>$page+1])) ?>">Next</a><?php endif; ?></nav>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
