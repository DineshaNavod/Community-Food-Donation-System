<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('organization');
require_once __DIR__.'/../includes/organization_requests.php';
$status=$_GET['status']??'all';$delivery=$_GET['delivery']??'all';$search=$_GET['search']??'';
$requestStatuses=['all','pending','approved','rejected','cancelled'];
$deliveryStatuses=['all','requested','assigned','collected','delivered','completed','cancelled','expired'];
if (!is_string($status) || !in_array($status,$requestStatuses,true) || !is_string($delivery)
    || !in_array($delivery,$deliveryStatuses,true) || !is_string($search) || !mb_check_encoding($search,'UTF-8')
    || mb_strlen($search)>150) {http_response_code(400);exit('Invalid request filters.');}
$search=trim($search);$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
$effectiveStatus="CASE WHEN d.status IN ('available','requested','assigned') AND d.deadline_at<=NOW() THEN 'expired' ELSE d.status END";
try {
    $profile=organization_profile((int)$user['id']);$where='r.organization_id=?';$params=[(int)$profile['id']];
    if ($status!=='all') {$where.=' AND r.status=?';$params[]=$status;}
    if ($delivery!=='all') {$where.=" AND ($effectiveStatus)=?";$params[]=$delivery;
        if ($delivery==='completed') {$where.=" AND r.status='approved'";}}
    if ($search!=='') {$where.=" AND d.food_name LIKE ? ESCAPE '!'";$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
    $query=db()->prepare("SELECT COUNT(*) FROM donation_requests r JOIN donations d ON d.id=r.donation_id WHERE $where");
    $query->execute($params);$total=(int)$query->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);
    $query=db()->prepare("SELECT r.id,r.status AS request_status,d.food_name,$effectiveStatus AS donation_status,
        t.status AS task_status,GREATEST(r.updated_at,d.updated_at,COALESCE(t.updated_at,r.updated_at)) AS latest_update
        FROM donation_requests r JOIN donations d ON d.id=r.donation_id
        LEFT JOIN collection_tasks t ON t.request_id=r.id WHERE $where ORDER BY latest_update DESC,r.id DESC LIMIT 20 OFFSET ?");
    foreach ($params as $index=>$value) {$query->bindValue($index+1,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);}
    $query->bindValue(count($params)+1,($page-1)*20,PDO::PARAM_INT);$query->execute();$requests=$query->fetchAll();
} catch (PDOException $exception) {error_log('Organization history failed. Code: '.$exception->getCode());donation_load_error();}
$history=$delivery==='completed';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= $history?'Completed history':'My requests' ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?>
<main class="container py-5" id="main-content"><div class="row g-4"><?php $organizationPage=$history?'history':'requests';require __DIR__.'/../includes/organization_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="history-heading"><div class="dashboard-welcome mb-4"><span class="eyebrow text-green">FOLLOW YOUR COMMUNITY CONNECTIONS</span><h1 class="h2 mt-3" id="history-heading"><?= $history?'Completed history':'My requests' ?></h1><p class="text-secondary">Review your organization's requests and follow collection and delivery progress.</p></div>
<form method="get" action="<?= e(app_url('organization/requests.php')) ?>" class="account-card donation-filters mb-4"><div class="row g-3"><div class="col-md-12"><label class="form-label" for="search">Food name</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" maxlength="150"></div><div class="col-md-5"><label class="form-label" for="status">Request status</label><select class="form-select" id="status" name="status"><?php foreach ($requestStatuses as $option): ?><option value="<?= e($option) ?>" <?= $status===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label" for="delivery">Donation status</label><select class="form-select" id="delivery" name="delivery"><?php foreach ($deliveryStatuses as $option): ?><option value="<?= e($option) ?>" <?= $delivery===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-community w-100" type="submit">Apply filters</button></div></div></form>
<div class="dashboard-panel"><div class="dashboard-panel-heading"><h2>Request history</h2><span><?= e((string)$total) ?> matching requests &middot; Sri Lanka time</span></div>
<?php if (!$requests): ?><div class="dashboard-empty"><h3>No matching requests.</h3><p>Try another filter or browse available food to make a request.</p><a class="home-link" href="<?= e(app_url('organization/donations.php')) ?>">Browse food &rarr;</a></div><?php else: ?><div class="table-responsive"><table class="table dashboard-table align-middle mb-0"><caption class="visually-hidden">Your organization's requests and latest recorded delivery progress.</caption><thead><tr><th scope="col">Food</th><th scope="col">Request</th><th scope="col">Donation</th><th scope="col">Collection task</th><th scope="col">Updated</th><th scope="col">Track</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?><tr><td class="food-cell"><?= e($request['food_name']) ?></td><td><?= status_badge($request['request_status']) ?></td><td><?= status_badge($request['donation_status']) ?></td><td><?= $request['task_status']===null?'No assignment':status_badge($request['task_status']) ?></td><td><?= e(dashboard_date($request['latest_update'])) ?></td><td><a class="home-link" href="<?= e(app_url('organization/request.php?id='.$request['id'])) ?>" aria-label="<?= e('Track '.$request['food_name']) ?>">View &rarr;</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
<?php if ($pages>1): ?><nav class="donation-pagination mt-4" aria-label="Request pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="<?= e(app_url('organization/requests.php?'.http_build_query(['status'=>$status,'delivery'=>$delivery,'search'=>$search,'page'=>$page-1]))) ?>">&larr; Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="<?= e(app_url('organization/requests.php?'.http_build_query(['status'=>$status,'delivery'=>$delivery,'search'=>$search,'page'=>$page+1]))) ?>">Next &rarr;</a><?php endif; ?></nav><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
