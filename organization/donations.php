<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('organization');
require_once __DIR__.'/../includes/organization_donations.php';
$search=$_GET['search']??'';$category=$_GET['category']??'';$location=$_GET['location']??'';
foreach ([$search,$category,$location] as $value) {if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>150) {http_response_code(400);exit('Invalid food filters.');}}
if ($category!=='' && !isset(FOOD_CATEGORIES[$category])) {http_response_code(400);exit('Invalid category.');}
$search=trim($search);$location=trim($location);
$page=filter_var($_GET['page']??'1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
try {
    $profile=organization_profile((int)$user['id']);
    $where="d.status IN ('available','requested') AND d.available_at<=NOW() AND d.deadline_at>NOW()
        AND u.status='active' AND u.role='donor'
        AND NOT EXISTS (SELECT 1 FROM donation_requests a WHERE a.donation_id=d.id AND a.status='approved')";$params=[];
    foreach (['food_name'=>$search,'pickup_location'=>$location] as $column=>$value) {if ($value!=='') {$where.=" AND d.$column LIKE ? ESCAPE '!'";$params[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$value).'%';}}
    if ($category!=='') {$where.=' AND d.category=?';$params[]=$category;}
    $query=db()->prepare("SELECT COUNT(*) FROM donations d JOIN users u ON u.id=d.donor_id WHERE $where");$query->execute($params);$total=(int)$query->fetchColumn();$pages=max(1,(int)ceil($total/12));$page=min($page,$pages);
    $query=db()->prepare("SELECT d.id,d.food_name,d.category,d.quantity,d.quantity_unit,d.pickup_location,d.deadline_at,d.image_path,
        r.status AS own_request_status FROM donations d JOIN users u ON u.id=d.donor_id
        LEFT JOIN donation_requests r ON r.donation_id=d.id AND r.organization_id=?
        WHERE $where ORDER BY d.deadline_at,d.id DESC LIMIT 12 OFFSET ?");
    $query->bindValue(1,(int)$profile['id'],PDO::PARAM_INT);
    foreach ($params as $index=>$value) {$query->bindValue($index+2,$value,PDO::PARAM_STR);}
    $query->bindValue(count($params)+2,($page-1)*12,PDO::PARAM_INT);$query->execute();$donations=$query->fetchAll();
} catch (PDOException $exception) {error_log('Organization browse failed. Code: '.$exception->getCode());donation_load_error();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Browse food | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?><main class="container py-5" id="main-content"><div class="row g-4"><?php $organizationPage='browse';require __DIR__.'/../includes/organization_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="browse-heading"><div class="dashboard-welcome mb-4"><span class="eyebrow text-green">GOOD FOOD, READY TO SHARE</span><h1 class="h2 mt-3" id="browse-heading">Find food for your community</h1><p class="text-secondary">Browse current donations and request food suitable for <?= e($profile['organization_name']) ?>.</p></div>
<form method="get" action="<?= e(app_url('organization/donations.php')) ?>" class="account-card donation-filters mb-4"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="search">Food name</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" maxlength="150"></div><div class="col-md-6"><label class="form-label" for="location">Pickup location</label><input class="form-control" id="location" name="location" value="<?= e($location) ?>" maxlength="150"></div><div class="col-md-8"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category"><option value="">All categories</option><?php foreach (FOOD_CATEGORIES as $value=>$label): ?><option value="<?= e($value) ?>" <?= $category===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-4 d-flex align-items-end"><button class="btn btn-community w-100" type="submit">Find food</button></div></div></form>
<p class="small text-secondary"><?= e((string)$total) ?> matching donations &middot; Sri Lanka time</p>
<?php if (!$donations): ?><div class="food-empty"><h2 class="h5">No matching donations right now.</h2><p>Try another category or location, or check again when donors share more food.</p><a class="home-link" href="<?= e(app_url('organization/donations.php')) ?>">Clear filters</a></div><?php else: ?><div class="row g-3">
<?php foreach ($donations as $donation): ?><div class="col-md-6"><article class="community-role-card food-preview">
<?php if (is_string($donation['image_path']) && preg_match('/\Auploads\/donations\/[a-f0-9]{32}\.jpg\z/',$donation['image_path'])): ?><img class="food-preview-image mb-3" src="<?= e(app_url($donation['image_path'])) ?>" alt="<?= e($donation['food_name']) ?>" loading="lazy"><?php endif; ?>
<?= $donation['own_request_status']!==null?status_badge($donation['own_request_status']):status_badge('available') ?><p class="role-label mt-3"><?= e(FOOD_CATEGORIES[$donation['category']]) ?></p><h2 class="h5"><?= e($donation['food_name']) ?></h2><p><?= e(rtrim(rtrim($donation['quantity'],'0'),'.').' '.$donation['quantity_unit']) ?><br><?= e($donation['pickup_location']) ?><br>Collect by <?= e(dashboard_date($donation['deadline_at'])) ?></p><a href="<?= e(app_url('organization/donation.php?id='.$donation['id'])) ?>">View food &rarr;</a></article></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($pages>1): ?><nav class="donation-pagination mt-4" aria-label="Food pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="<?= e(app_url('organization/donations.php?'.http_build_query(['search'=>$search,'category'=>$category,'location'=>$location,'page'=>$page-1]))) ?>">&larr; Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="<?= e(app_url('organization/donations.php?'.http_build_query(['search'=>$search,'category'=>$category,'location'=>$location,'page'=>$page+1]))) ?>">Next &rarr;</a><?php endif; ?></nav><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
