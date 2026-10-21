<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';$user=require_role('donor');
require_once __DIR__.'/../includes/donor_requests.php';$id=donor_donation_id();
try {$request=owned_request($id,(int)$user['id']);}
catch (PDOException $exception) {error_log('Request detail failed. Code: '.$exception->getCode());donation_load_error();}
if (!$request) {http_response_code(404);exit('Request not found.');}
try {$donation=owned_donation((int)$request['donation_id'],(int)$user['id']);}
catch (PDOException $exception) {error_log('Request donation check failed. Code: '.$exception->getCode());donation_load_error();}
$canDecide=$donation!==null && $request['request_status']==='pending'
    && in_array(donation_display_status($donation),['available','requested'],true) && $donation['approved_count']===0;
$canApprove=$canDecide && $request['organization_status']==='active' && $request['organization_role']==='organization';
$message=$_SESSION['request_success']??'';unset($_SESSION['request_success']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Review request | <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"><script src="<?= e(app_url('assets/js/donations.js')) ?>" defer></script></head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?>
<main class="container py-5" id="main-content"><div class="row g-4"><?php $donorPage='requests';require __DIR__.'/../includes/donor_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="review-heading"><div class="dashboard-welcome mb-4"><a class="home-link" href="<?= e(app_url('donor/requests.php')) ?>">&larr; Incoming requests</a><h1 class="h2 mt-3" id="review-heading"><?= e($request['organization_name']) ?></h1><p class="text-secondary">Requesting <?= e($request['food_name']) ?></p><?= status_badge($request['request_status']) ?></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<article class="account-card"><h2 class="h5 mb-4">Request details</h2><dl class="row account-details">
<dt class="col-sm-4">Donation</dt><dd class="col-sm-8"><a class="home-link" href="<?= e(app_url('donor/donation.php?id='.$request['donation_id'])) ?>"><?= e($request['food_name']) ?></a></dd>
<dt class="col-sm-4">Contact person</dt><dd class="col-sm-8"><?= e($request['full_name']) ?></dd>
<dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($request['email']) ?></dd>
<dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e($request['phone']) ?></dd>
<dt class="col-sm-4">Delivery address</dt><dd class="col-sm-8 donation-description"><?= e($request['delivery_address']) ?></dd>
<dt class="col-sm-4">Requested</dt><dd class="col-sm-8"><?= e(dashboard_date($request['created_at'])) ?></dd>
<dt class="col-sm-4">Collection deadline</dt><dd class="col-sm-8"><?= e(dashboard_date($request['deadline_at'])) ?></dd>
<?php if ($request['decided_at']!==null): ?><dt class="col-sm-4">Decision recorded</dt><dd class="col-sm-8"><?= e(dashboard_date($request['decided_at'])) ?></dd><?php endif; ?>
</dl><h3 class="h6">Message from the organization</h3><p class="donation-description"><?= e($request['message']??'No message provided.') ?></p><p class="form-text mb-0">All times use Sri Lanka time.</p></article>
<?php if ($canDecide): ?><section class="account-card mt-4" aria-labelledby="decision-heading"><h2 class="h5" id="decision-heading">Your decision</h2><p class="small text-secondary">Approving reserves this donation for this organization and rejects other pending requests. An administrator will then assign a volunteer.</p>
<?php if (!$canApprove): ?><div class="alert alert-warning">This organization is not active. Approval is unavailable; you may reject its request.</div><?php endif; ?>
<div class="donation-form-actions">
<?php if ($canApprove): ?><form method="post" action="<?= e(app_url('donor/decide_request.php?id='.$id)) ?>" data-confirm="Approve this organization? Other pending requests for this donation will be rejected."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="decision" value="approve"><button type="submit" class="btn btn-community">Approve request</button></form><?php endif; ?>
<form method="post" action="<?= e(app_url('donor/decide_request.php?id='.$id)) ?>" data-confirm="Reject this request? This decision will be kept in history."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="decision" value="reject"><button type="submit" class="btn btn-outline-danger">Reject request</button></form>
</div></section><?php else: ?><div class="alert alert-info mt-4">This request is no longer open for a decision.</div><?php endif; ?>
</section></div></main><?php require __DIR__.'/../includes/footer.php'; ?></body></html>
