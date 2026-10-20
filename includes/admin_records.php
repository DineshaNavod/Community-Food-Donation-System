<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';$user=require_role('admin');
require_once __DIR__.'/donor_donations.php';
$isRequests=$recordType==='requests';$recordRecent=$recordRecent??false;
$title=$recordRecent?'Recent donations':($isRequests?'Donation requests':'All donations');
$donationStatuses=['all','available','requested','assigned','collected','delivered','completed','cancelled','expired'];
$statuses=$isRequests?['all','pending','approved','rejected','cancelled']:$donationStatuses;
$status=$_GET['status']??'all';$delivery=$_GET['delivery']??'all';$search=$_GET['search']??'';
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
if (!is_string($status) || !in_array($status,$statuses,true) || !is_string($delivery) || !in_array($delivery,$donationStatuses,true)
    || !is_string($search) || !mb_check_encoding($search,'UTF-8') || mb_strlen($search)>150 || str_contains($search,"\0") || $page===false) {http_response_code(400);exit('Invalid overview filters.');}
$search=trim($search);$where='1=1';$params=[];
$effective="CASE WHEN d.status IN ('available','requested','assigned') AND d.deadline_at<=NOW() THEN 'expired' ELSE d.status END";
if ($status!=='all') {$where.=$isRequests?' AND r.status=?':" AND ($effective)=?";$params[]=$status;}
if ($isRequests && $delivery!=='all') {$where.=" AND ($effective)=?";$params[]=$delivery;}
if ($isRequests) {
    $from=' FROM donation_requests r JOIN donations d ON d.id=r.donation_id JOIN users donor ON donor.id=d.donor_id
        JOIN organizations o ON o.id=r.organization_id LEFT JOIN collection_tasks t ON t.request_id=r.id
        LEFT JOIN users v ON v.id=t.volunteer_id';
    $select="SELECT r.id,r.donation_id,r.status AS record_status,d.food_name,$effective AS donation_status,
        o.organization_name,t.status AS task_status,v.full_name AS volunteer_name,
        GREATEST(r.updated_at,d.updated_at,COALESCE(t.updated_at,r.updated_at)) AS latest_update";
} else {
    $from=' FROM donations d JOIN users donor ON donor.id=d.donor_id';
    $select="SELECT d.id,d.id AS donation_id,d.food_name,$effective AS donation_status,donor.full_name AS donor_name,
        d.quantity,d.quantity_unit,d.deadline_at,d.updated_at AS latest_update,
        (SELECT COUNT(*) FROM donation_requests r WHERE r.donation_id=d.id) AS request_count";
}
if ($search!=='') {
    $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
    $where.=" AND (d.food_name LIKE ? ESCAPE '!' OR donor.full_name LIKE ? ESCAPE '!'";array_push($params,$term,$term);
    if ($isRequests) {$where.=" OR o.organization_name LIKE ? ESCAPE '!'";$params[]=$term;}$where.=')';
}
try {
    $query=db()->prepare('SELECT COUNT(*)'.$from.' WHERE '.$where);$query->execute($params);$total=(int)$query->fetchColumn();
    $limit=$recordRecent?5:20;$pages=$recordRecent?1:max(1,(int)ceil($total/$limit));$page=min($page,$pages);$offset=($page-1)*$limit;
    $order=$isRequests?'r.id':'d.id';$query=db()->prepare($select.$from.' WHERE '.$where." ORDER BY latest_update DESC,$order DESC LIMIT $limit OFFSET $offset");$query->execute($params);$records=$query->fetchAll();
} catch (PDOException $exception) {error_log('Admin overview failed. Code: '.$exception->getCode());donation_load_error();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e($title) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/header.php'; ?><main class="container py-5" id="main-content"><div class="row g-4"><?php $adminPage=$recordRecent?'recent':$recordType;require __DIR__.'/admin_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="overview-heading"><div class="dashboard-welcome mb-4"><h1 class="h2" id="overview-heading"><?= e($title) ?></h1><p class="text-secondary">Review food donations, request decisions and recorded collection progress across the community.</p></div>
<form method="get" class="account-card mb-4"><div class="row g-3"><div class="col-12"><label class="form-label" for="search"><?= $isRequests?'Food, donor or organization name':'Food or donor name' ?></label><input class="form-control" name="search" id="search" value="<?= e($search) ?>" maxlength="150"></div><div class="col-md-5"><label class="form-label" for="status"><?= $isRequests?'Request status':'Donation status' ?></label><select class="form-select" name="status" id="status"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $status===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div><?php if ($isRequests): ?><div class="col-md-4"><label class="form-label" for="delivery">Donation status</label><select class="form-select" name="delivery" id="delivery"><?php foreach ($donationStatuses as $option): ?><option value="<?= e($option) ?>" <?= $delivery===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="col-md-3 d-flex align-items-end"><button class="btn btn-community">Apply filters</button></div></div></form>
<div class="dashboard-panel"><div class="dashboard-panel-heading"><h2><?= e($title) ?></h2><span><?= $recordRecent?'Latest 5 of ':'' ?><?= e((string)$total) ?> matching <?= e($recordType) ?></span></div><?php if (!$records): ?><div class="dashboard-empty"><h3>No matching records.</h3><p>Try another name or status.</p></div><?php else: ?><div class="table-responsive"><table class="table dashboard-table align-middle mb-0"><caption class="visually-hidden">Administrator <?= e($recordType) ?> overview, times in Sri Lanka time.</caption><thead><tr><th scope="col">Food</th><?php if ($isRequests): ?><th scope="col">Organization</th><th scope="col">Request</th><th scope="col">Collection</th><?php else: ?><th scope="col">Donor</th><th scope="col">Quantity</th><th scope="col">Requests</th><?php endif; ?><th scope="col">Donation</th><th scope="col">Updated</th><th scope="col">Details</th></tr></thead><tbody>
<?php foreach ($records as $record): ?><tr><td><?= e($record['food_name']) ?></td><?php if ($isRequests): ?><td><?= e($record['organization_name']) ?></td><td><?= status_badge($record['record_status']) ?></td><td><?= $record['task_status']===null?'Not assigned':status_badge($record['task_status']) ?><?php if ($record['volunteer_name']!==null): ?><div class="small mt-2"><?= e($record['volunteer_name']) ?></div><?php endif; ?></td><?php else: ?><td><?= e($record['donor_name']) ?></td><td><?= e(rtrim(rtrim($record['quantity'],'0'),'.').' '.$record['quantity_unit']) ?></td><td><?= e((string)$record['request_count']) ?></td><?php endif; ?><td><?= status_badge($record['donation_status']) ?></td><td><?= e(dashboard_date($record['latest_update'])) ?></td><td><a class="home-link" href="<?= e(app_url('admin/donation.php?id='.$record['donation_id'].($isRequests?'#request-'.$record['id']:''))) ?>">View &rarr;</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
<?php if ($pages>1): ?><nav class="donation-pagination mt-4" aria-label="Overview pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="?<?= e(http_build_query(['status'=>$status,'delivery'=>$delivery,'search'=>$search,'page'=>$page-1])) ?>">Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="?<?= e(http_build_query(['status'=>$status,'delivery'=>$delivery,'search'=>$search,'page'=>$page+1])) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</section></div></main><?php require __DIR__.'/footer.php'; ?></body></html>
