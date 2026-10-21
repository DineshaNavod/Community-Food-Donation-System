<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('donor');
require_once __DIR__.'/../includes/donor_requests.php';
$status=$_GET['status']??'all';$donationFilter=$_GET['donation_id']??'';
$statuses=['all','pending','approved','rejected','cancelled'];
if (!is_string($status) || !in_array($status,$statuses,true) || !is_string($donationFilter)
    || ($donationFilter!=='' && !filter_var($donationFilter,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]))) {
    http_response_code(400);exit('Invalid request filters.');
}
$where='d.donor_id=?';$params=[$user['id']];
if ($status!=='all') {$where.=' AND r.status=?';$params[]=$status;}
if ($donationFilter!=='') {$where.=' AND d.id=?';$params[]=(int)$donationFilter;}
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
try {
    $query=db()->prepare("SELECT COUNT(*) FROM donation_requests r JOIN donations d ON d.id=r.donation_id WHERE $where");
    $query->execute($params);$total=(int)$query->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);
    $query=db()->prepare("SELECT r.id,r.status AS request_status,r.created_at,d.food_name,d.status,d.deadline_at,o.organization_name
        FROM donation_requests r JOIN donations d ON d.id=r.donation_id JOIN organizations o ON o.id=r.organization_id
        WHERE $where ORDER BY r.created_at DESC,r.id DESC LIMIT 20 OFFSET ?");
    foreach ($params as $index=>$value) {$query->bindValue($index+1,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);}
    $query->bindValue(count($params)+1,($page-1)*20,PDO::PARAM_INT);$query->execute();$requests=$query->fetchAll();
} catch (PDOException $exception) {error_log('Request list failed. Code: '.$exception->getCode());donation_load_error();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Incoming requests | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?>
<main class="container py-5" id="main-content"><div class="row g-4"><?php $donorPage='requests';require __DIR__.'/../includes/donor_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="requests-heading"><div class="dashboard-welcome mb-4"><span class="eyebrow text-green">CONNECT FOOD WITH YOUR COMMUNITY</span><h1 class="h2 mt-3" id="requests-heading">Incoming requests</h1><p class="text-secondary">Review organizations requesting your food and choose who will receive each donation.</p></div>
<form action="<?= e(app_url('donor/requests.php')) ?>" method="get" class="account-card mb-4"><div class="row g-3 align-items-end"><div class="col-md-8"><label class="form-label" for="status">Request status</label><select id="status" name="status" class="form-select"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $status===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><button class="btn btn-community w-100" type="submit">Apply filter</button></div></div><?php if ($donationFilter!==''): ?><input type="hidden" name="donation_id" value="<?= e($donationFilter) ?>"><a class="home-link small d-block mt-3" href="<?= e(app_url('donor/requests.php')) ?>">Show all my donations</a><?php endif; ?></form>
<div class="dashboard-panel"><div class="dashboard-panel-heading"><h2>Organization requests</h2><span><?= e((string)$total) ?> matching requests</span></div>
<?php if (!$requests): ?><div class="dashboard-empty"><h3>No matching requests.</h3><p>Requests will appear when organizations ask for your available food.</p></div><?php else: ?><div class="table-responsive"><table class="table dashboard-table align-middle mb-0"><caption class="visually-hidden">Requests for your donations. Dates use Sri Lanka time.</caption><thead><tr><th scope="col">Food</th><th scope="col">Organization</th><th scope="col">Request</th><th scope="col">Donation</th><th scope="col">Received</th><th scope="col">Review</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?><tr><td class="food-cell"><?= e($request['food_name']) ?></td><td class="food-cell"><?= e($request['organization_name']) ?></td><td><?= status_badge($request['request_status']) ?></td><td><?= status_badge(donation_display_status($request)) ?></td><td><?= e(dashboard_date($request['created_at'])) ?></td><td><a class="home-link" href="<?= e(app_url('donor/request.php?id='.$request['id'])) ?>" aria-label="<?= e('Review '.$request['organization_name'].' request for '.$request['food_name']) ?>">Review &rarr;</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
<?php if ($pages>1): ?><nav class="donation-pagination mt-4" aria-label="Request pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="<?= e(app_url('donor/requests.php?'.http_build_query(['status'=>$status,'donation_id'=>$donationFilter,'page'=>$page-1]))) ?>">&larr; Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="<?= e(app_url('donor/requests.php?'.http_build_query(['status'=>$status,'donation_id'=>$donationFilter,'page'=>$page+1]))) ?>">Next &rarr;</a><?php endif; ?></nav><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
