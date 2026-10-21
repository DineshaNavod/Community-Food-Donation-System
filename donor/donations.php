<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('donor');
require_once __DIR__ . '/../includes/donor_donations.php';
$statuses = ['all','available','requested','assigned','collected','delivered','completed','cancelled','expired'];
$status = $_GET['status'] ?? 'all'; $search = $_GET['search'] ?? '';
if (!is_string($status) || !in_array($status,$statuses,true) || !is_string($search) || mb_strlen($search)>150
    || !mb_check_encoding($search,'UTF-8')) { http_response_code(400); exit('Invalid donation filters.'); }
$search = trim($search);
$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 1;
$effectiveStatus = "CASE WHEN status IN ('available','requested','assigned') AND deadline_at<=NOW() THEN 'expired' ELSE status END";
$where = 'donor_id=?'; $params = [$user['id']];
if ($status !== 'all') { $where .= " AND ($effectiveStatus)=?"; $params[] = $status; }
if ($search !== '') { $where .= " AND food_name LIKE ? ESCAPE '!'"; $params[] = '%' . str_replace(['!','%','_'],['!!','!%','!_'],$search) . '%'; }
try {
    $query = db()->prepare("SELECT COUNT(*) FROM donations WHERE $where"); $query->execute($params);
    $total = (int) $query->fetchColumn(); $pages = max(1,(int)ceil($total/20)); $page = min($page,$pages);
    $query = db()->prepare("SELECT id,food_name,quantity,quantity_unit,deadline_at,$effectiveStatus AS donation_status
        FROM donations WHERE $where ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET ?");
    foreach ($params as $index=>$value) { $query->bindValue($index+1,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR); }
    $query->bindValue(count($params)+1,($page-1)*20,PDO::PARAM_INT); $query->execute(); $donations = $query->fetchAll();
} catch (PDOException $exception) { error_log('Donor list failed. Code: '.$exception->getCode()); donation_load_error(); }
$message = $_SESSION['donation_success'] ?? ''; unset($_SESSION['donation_success']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My donations | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user; require __DIR__.'/../includes/header.php'; ?>
<main class="container py-5" id="main-content"><div class="row g-4">
<?php $donorPage=$status==='completed'?'history':'donations'; require __DIR__.'/../includes/donor_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="list-heading"><div class="dashboard-welcome"><span class="eyebrow text-green">YOUR FOOD, YOUR IMPACT</span><h1 class="h2 mt-3" id="list-heading">My donations</h1><p class="text-secondary">View your food donations and their collection progress.</p><a class="btn btn-community mb-4" href="<?= e(app_url('donor/add_donation.php')) ?>">Share food &rarr;</a></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<form class="account-card donation-filters mb-4" action="<?= e(app_url('donor/donations.php')) ?>" method="get"><div class="row g-3 align-items-end"><div class="col-md-6"><label for="search" class="form-label">Find food</label><input id="search" name="search" class="form-control" value="<?= e($search) ?>" maxlength="150" placeholder="Search by food name"></div><div class="col-md-3"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $status===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-community w-100" type="submit">Apply filters</button></div></div></form>
<div class="dashboard-panel"><div class="dashboard-panel-heading"><h2>Donation history</h2><span><?= e((string)$total) ?> matching donations &middot; Sri Lanka time</span></div>
<?php if (!$donations): ?><div class="dashboard-empty"><h3>No matching donations.</h3><p>Try another filter or share your first donation.</p></div><?php else: ?>
<div class="table-responsive"><table class="table dashboard-table align-middle mb-0"><caption class="visually-hidden">Your food donations and collection deadlines in Sri Lanka time.</caption><thead><tr><th scope="col">Food</th><th scope="col">Quantity</th><th scope="col">Collect by</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead><tbody>
<?php foreach ($donations as $donation): ?><tr><td class="food-cell"><?= e($donation['food_name']) ?></td><td><?= e(rtrim(rtrim($donation['quantity'],'0'),'.').' '.$donation['quantity_unit']) ?></td><td><?= e(dashboard_date($donation['deadline_at'])) ?></td><td><?= status_badge($donation['donation_status']) ?></td><td><a class="home-link" href="<?= e(app_url('donor/donation.php?id='.$donation['id'])) ?>" aria-label="<?= e('View '.$donation['food_name']) ?>">View &rarr;</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
<?php if ($pages>1): ?><nav class="donation-pagination mt-4" aria-label="Donation pages"><?php if ($page>1): ?><a class="btn btn-quiet" href="<?= e(app_url('donor/donations.php?'.http_build_query(['status'=>$status,'search'=>$search,'page'=>$page-1]))) ?>">&larr; Previous</a><?php endif; ?><span>Page <?= e((string)$page) ?> of <?= e((string)$pages) ?></span><?php if ($page<$pages): ?><a class="btn btn-quiet" href="<?= e(app_url('donor/donations.php?'.http_build_query(['status'=>$status,'search'=>$search,'page'=>$page+1]))) ?>">Next &rarr;</a><?php endif; ?></nav><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
