<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php'; $user=require_role('donor');
require_once __DIR__.'/../includes/donor_donations.php'; $id=donor_donation_id();
try { $donation=owned_donation($id,(int)$user['id']); }
catch (PDOException $exception) { error_log('Donor detail failed. Code: '.$exception->getCode()); donation_load_error(); }
if ($donation===null) { http_response_code(404); exit('Donation not found.'); }
$message=$_SESSION['donation_success']??''; unset($_SESSION['donation_success']);
$image=is_string($donation['image_path']) && preg_match('/\Auploads\/donations\/[a-f0-9]{32}\.jpg\z/',$donation['image_path']) ? $donation['image_path']:null;
$completed=$donation['status']==='completed';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e($donation['food_name']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"><script src="<?= e(app_url('assets/js/donations.js')) ?>" defer></script></head><body>
<?php $navigationUser=$user; require __DIR__.'/../includes/header.php'; ?>
<main class="container py-5" id="main-content"><div class="row g-4"><?php $donorPage=$completed?'history':'donations'; require __DIR__.'/../includes/donor_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="detail-heading"><div class="dashboard-welcome mb-4"><a class="home-link" href="<?= e(app_url($completed?'donor/donations.php?status=completed':'donor/donations.php')) ?>">&larr; <?= $completed?'Completed history':'My donations' ?></a><h1 class="h2 mt-3" id="detail-heading"><?= e($donation['food_name']) ?></h1><?= status_badge(donation_display_status($donation)) ?></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<article class="account-card donation-detail">
<?php if ($image!==null): ?><img class="donation-detail-image mb-4" src="<?= e(app_url($image)) ?>" alt="<?= e($donation['food_name']) ?>"><?php endif; ?>
<h2 class="h5">Food details</h2><p class="donation-description"><?= e($donation['description']) ?></p><dl class="row account-details mt-4">
<dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= e(FOOD_CATEGORIES[$donation['category']]??'Other food') ?></dd>
<dt class="col-sm-4">Quantity</dt><dd class="col-sm-8"><?= e(rtrim(rtrim($donation['quantity'],'0'),'.').' '.$donation['quantity_unit']) ?></dd>
<dt class="col-sm-4">Pickup location</dt><dd class="col-sm-8 donation-description"><?= e($donation['pickup_location']) ?></dd>
<dt class="col-sm-4">Available from</dt><dd class="col-sm-8"><?= e(dashboard_date($donation['available_at'])) ?></dd>
<dt class="col-sm-4">Collection deadline</dt><dd class="col-sm-8"><?= e(dashboard_date($donation['deadline_at'])) ?></dd>
<dt class="col-sm-4">Requests received</dt><dd class="col-sm-8"><?= e((string)$donation['request_count']) ?></dd>
<dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?= e(dashboard_date($donation['created_at'])) ?></dd>
<?php if ($donation['completed_at']!==null): ?><dt class="col-sm-4">Completed</dt><dd class="col-sm-8"><?= e(dashboard_date($donation['completed_at'])) ?></dd><?php endif; ?>
</dl><p class="form-text">All times use Sri Lanka time.</p>
<p><a class="home-link" href="<?= e(app_url('donor/requests.php?donation_id='.$id)) ?>">View requests for this donation &rarr;</a></p>
<?php if (can_edit_donation($donation)): ?><a class="btn btn-community" href="<?= e(app_url('donor/edit_donation.php?id='.$id)) ?>">Edit donation</a><?php else: ?><p class="form-text">Editing is available only before any requests are received and before the deadline.</p><?php endif; ?>
</article>
<?php if (can_cancel_donation($donation)): ?><section class="account-card mt-4" aria-labelledby="cancel-heading"><h2 class="h5" id="cancel-heading">Cancel this donation</h2><p class="text-secondary small">Cancellation closes this donation and cancels pending requests. Its history will be kept.</p><form action="<?= e(app_url('donor/cancel_donation.php?id='.$id)) ?>" method="post" data-confirm="Cancel this donation and its pending requests? This cannot be undone."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-outline-danger" type="submit" name="confirm" value="1">Confirm cancellation</button></form></section><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
