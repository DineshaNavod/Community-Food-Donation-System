<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('admin');
require_once __DIR__.'/../includes/volunteer_tasks.php';
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
if ($page===false) {http_response_code(400);exit('Invalid page.');}
try {
    $from=" FROM donation_requests r JOIN donations d ON d.id=r.donation_id
        JOIN users donor ON donor.id=d.donor_id JOIN organizations o ON o.id=r.organization_id
        JOIN users recipient ON recipient.id=o.user_id LEFT JOIN collection_tasks t ON t.request_id=r.id
        WHERE r.status='approved' AND d.status='requested' AND d.available_at<=NOW() AND d.deadline_at>NOW()
        AND donor.role='donor' AND donor.status='active' AND recipient.role='organization' AND recipient.status='active' AND t.id IS NULL";
    $total=(int)db()->query('SELECT COUNT(*)'.$from)->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);$offset=($page-1)*20;
    $requests=db()->query('SELECT r.id,d.food_name,d.pickup_location,d.deadline_at,o.organization_name,donor.full_name AS donor_name'.$from." ORDER BY d.deadline_at,r.id LIMIT 20 OFFSET $offset")->fetchAll();
    $volunteers=db()->query("SELECT id,full_name,email FROM users WHERE role='volunteer' AND status='active' ORDER BY full_name,id")->fetchAll();
}catch (PDOException $exception) {error_log('Assignment list failed. Code: '.$exception->getCode());donation_load_error();}
$message=$_SESSION['assignment_success']??'';unset($_SESSION['assignment_success']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Assign collections | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?><main class="container py-5" id="main-content"><div class="row g-4"><?php $adminPage='assignments';require __DIR__.'/../includes/admin_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="assignments-heading"><div class="dashboard-welcome mb-4"><h1 class="h2" id="assignments-heading">Assign collections</h1><p class="text-secondary">Assign an active volunteer after a donor approves an organization's request. Donations within their pickup window are listed earliest deadline first.</p></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if (!$volunteers): ?><div class="alert alert-info">No active volunteers are available. <a href="<?= e(app_url('admin/accounts.php?role=volunteer')) ?>">Review volunteer registrations</a> first.</div><?php endif; ?>
<p><?= e((string)$total) ?> donations awaiting assignment</p>
<?php if (!$requests): ?><div class="account-card">No eligible donations are awaiting assignment. A donor must approve an organization's request before it can be assigned.</div><?php endif; ?>
<?php foreach ($requests as $request): ?><article class="account-card mb-4"><h2 class="h5"><?= e($request['food_name']) ?></h2><dl class="row account-details mt-3"><dt class="col-sm-4">Donor</dt><dd class="col-sm-8"><?= e($request['donor_name']) ?></dd><dt class="col-sm-4">Pickup location</dt><dd class="col-sm-8 donation-description"><?= e($request['pickup_location']) ?></dd><dt class="col-sm-4">Receiving organization</dt><dd class="col-sm-8"><?= e($request['organization_name']) ?></dd><dt class="col-sm-4">Collect by</dt><dd class="col-sm-8"><?= e(dashboard_date($request['deadline_at'])) ?></dd></dl>
<?php if ($volunteers): ?><form method="post" action="<?= e(app_url('admin/assign_volunteer.php?id='.$request['id'])) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label" for="volunteer-<?= e((string)$request['id']) ?>">Collection volunteer</label><select class="form-select mb-3" name="volunteer_id" id="volunteer-<?= e((string)$request['id']) ?>" required><option value="">Choose a volunteer</option><?php foreach ($volunteers as $volunteer): ?><option value="<?= e((string)$volunteer['id']) ?>"><?= e($volunteer['full_name'].' — '.$volunteer['email']) ?></option><?php endforeach; ?></select><button class="btn btn-community">Assign volunteer</button></form><?php endif; ?></article><?php endforeach; ?>
<?php if ($pages>1): ?><nav class="donation-pagination" aria-label="Assignment pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="?page=<?= e((string)($page-1)) ?>">Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="?page=<?= e((string)($page+1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
