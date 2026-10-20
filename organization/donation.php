<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('organization');
require_once __DIR__.'/../includes/organization_donations.php';$id=donor_donation_id();
try {$profile=organization_profile((int)$user['id']);$donation=organization_donation($id,(int)$profile['id']);}
catch (PDOException $exception) {error_log('Organization detail failed. Code: '.$exception->getCode());donation_load_error();}
if (!$donation || (!organization_food_available($donation) && $donation['own_request_id']===null)) {http_response_code(404);exit('Donation not found or no longer available.');}
$canRequest=organization_food_available($donation) && $donation['own_request_id']===null;
$message=$_SESSION['organization_request_success']??'';unset($_SESSION['organization_request_success']);
$errors=$_SESSION['organization_request_errors']??[];unset($_SESSION['organization_request_errors']);
$old=$_SESSION['organization_request_old']??[];unset($_SESSION['organization_request_old']);
// Failed form values are tied to a donation so they do not appear on another one.
$address=($old['donation_id']??null)===$id?($old['address']??$user['address']):$user['address'];
$note=($old['donation_id']??null)===$id?($old['message']??''):'';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e($donation['food_name']) ?> | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?><main class="container py-5" id="main-content"><div class="row g-4"><?php $organizationPage='browse';require __DIR__.'/../includes/organization_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="food-heading"><div class="dashboard-welcome mb-4"><a class="home-link" href="<?= e(app_url('organization/donations.php')) ?>">&larr; Browse food</a><h1 class="h2 mt-3" id="food-heading"><?= e($donation['food_name']) ?></h1><?= status_badge(donation_display_status($donation)) ?></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<article class="account-card">
<?php if (is_string($donation['image_path']) && preg_match('/\Auploads\/donations\/[a-f0-9]{32}\.jpg\z/',$donation['image_path'])): ?><img class="donation-detail-image mb-4" src="<?= e(app_url($donation['image_path'])) ?>" alt="<?= e($donation['food_name']) ?>"><?php endif; ?>
<h2 class="h5">About this food</h2><p class="donation-description"><?= e($donation['description']) ?></p><dl class="row account-details">
<dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= e(FOOD_CATEGORIES[$donation['category']]) ?></dd><dt class="col-sm-4">Quantity</dt><dd class="col-sm-8"><?= e(rtrim(rtrim($donation['quantity'],'0'),'.').' '.$donation['quantity_unit']) ?></dd>
<dt class="col-sm-4">Pickup location</dt><dd class="col-sm-8 donation-description"><?= e($donation['pickup_location']) ?></dd><dt class="col-sm-4">Available from</dt><dd class="col-sm-8"><?= e(dashboard_date($donation['available_at'])) ?></dd><dt class="col-sm-4">Collect by</dt><dd class="col-sm-8"><?= e(dashboard_date($donation['deadline_at'])) ?></dd><dt class="col-sm-4">Donor</dt><dd class="col-sm-8"><?= e($donation['donor_name']) ?></dd></dl><p class="form-text mb-0">All dates and times use Sri Lanka time.</p></article>
<?php if ($donation['own_request_id']!==null): ?><section class="account-card mt-4"><h2 class="h5">Your organization's request</h2><?= status_badge($donation['own_request_status']) ?><p class="donation-description mt-3"><?= e($donation['own_delivery_address']) ?></p><p class="donation-description"><?= e($donation['own_message']??'No message provided.') ?></p><p class="form-text">Your request is recorded. Each organization can request a donation once.</p><?php if ($donation['own_request_status']==='approved'): ?><p class="small mb-0">Donor contact: <?= e($donation['donor_phone']) ?> &middot; <?= e($donation['donor_email']) ?></p><?php endif; ?></section>
<?php elseif ($canRequest): ?><section class="account-card mt-4"><h2 class="h5">Request this donation</h2><p class="small text-secondary">The donor will review your request before a volunteer is assigned.</p><form action="<?= e(app_url('organization/request_donation.php?id='.$id)) ?>" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label for="delivery_address" class="form-label">Delivery address</label><textarea id="delivery_address" name="delivery_address" class="form-control" rows="2" minlength="5" maxlength="255" required><?= e($address) ?></textarea></div><div class="mb-3"><label for="message" class="form-label">Message to the donor <span class="optional">(optional)</span></label><textarea id="message" name="message" class="form-control" rows="3" maxlength="1500"><?= e($note) ?></textarea></div><button class="btn btn-community" type="submit">Send donation request &rarr;</button></form></section><?php endif; ?>
<?php if ($donation['own_request_id']!==null): ?><p class="mt-4"><a class="btn btn-community" href="<?= e(app_url('organization/request.php?id='.$donation['own_request_id'])) ?>">Track my request &rarr;</a></p><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
