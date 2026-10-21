<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('donor');
require_once __DIR__ . '/../includes/donor_donations.php';
$editing = isset($editing) && $editing === true;
$original = null;
if ($editing) {
    $id = donor_donation_id();
    try { $original = owned_donation($id,(int)$user['id']); }
    catch (PDOException $exception) { error_log('Edit form failed. Code: '.$exception->getCode()); donation_load_error(); }
    if ($original === null) { http_response_code(404); exit('Donation not found.'); }
    if (!can_edit_donation($original)) { http_response_code(409); exit('This donation is no longer eligible for editing. Return to My donations to view its status.'); }
}
$values = ['food_name'=>'','category'=>'','description'=>'','quantity'=>'','quantity_unit'=>'portions',
    'pickup_location'=>$user['address'],'available_at'=>date('Y-m-d\TH:i'),'deadline_at'=>''];
if ($editing) {
    foreach ($values as $field=>$default) { $values[$field] = $original[$field]; }
    foreach (['available_at','deadline_at'] as $field) { $values[$field] = date('Y-m-d\TH:i',strtotime($original[$field])); }
}
$formVersion = $editing ? donation_version($original) : '';
$formAction = app_url($editing ? 'donor/edit_donation.php?id='.$id : 'donor/add_donation.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field=>$default) { $values[$field] = post_text($field); }
    if ($editing) { $formVersion = post_text('version'); }
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your form session is invalid or has expired. Please submit this form again.';
    } else {
        $errors = validate_donation($values);
        if (!$errors) {
            $imagePath = null;
            $saved = false;
            $pdo = db();
            try {
                if (isset($_FILES['food_image']) && !is_array($_FILES['food_image'])) {
                    throw new InvalidArgumentException('Choose a valid image file.');
                }
                $imagePath = store_food_image($_FILES['food_image'] ?? null);
                $pdo->beginTransaction();
                lock_active_donor((int)$user['id']);
                $locked = null;
                if ($editing) {
                    $locked = owned_donation($id,(int)$user['id'],true);
                    if ($locked === null || !can_edit_donation($locked)) {
                        throw new DomainException('This donation is no longer eligible for editing. Reload My donations.');
                    }
                    if (!hash_equals(donation_version($locked),$formVersion)) {
                        throw new DomainException('This donation changed after you opened the form. Reload this page before editing again.');
                    }
                }
                if (donation_datetime($values['deadline_at']) <= new DateTimeImmutable()) {
                    throw new InvalidArgumentException('The collection deadline has passed. Choose a future deadline.');
                }
                $finalImage = $editing && $imagePath === null && post_text('remove_image') !== '1' ? $locked['image_path'] : $imagePath;
                $parameters = [$values['food_name'],$values['category'],$values['description'],
                    $values['quantity'],$values['quantity_unit'],$values['pickup_location'],
                    donation_datetime($values['available_at'])->format('Y-m-d H:i:s'),
                    donation_datetime($values['deadline_at'])->format('Y-m-d H:i:s'),$finalImage];
                if ($editing) {
                    $query = $pdo->prepare('UPDATE donations SET food_name=?,category=?,description=?,quantity=?,
                        quantity_unit=?,pickup_location=?,available_at=?,deadline_at=?,image_path=? WHERE id=? AND donor_id=?');
                    $query->execute([...$parameters,$id,$user['id']]);
                } else {
                    $query = $pdo->prepare("INSERT INTO donations (food_name,category,description,quantity,
                        quantity_unit,pickup_location,available_at,deadline_at,image_path,donor_id,status)
                        VALUES (?,?,?,?,?,?,?,?,?,?,'available')");
                    $query->execute([...$parameters,$user['id']]);
                }
                $pdo->commit();
                $saved = true;
                if ($editing && $locked['image_path'] !== $finalImage) { remove_food_image($locked['image_path']); }
                $_SESSION['donation_success'] = $editing ? 'Your donation has been updated.' : 'Your food donation has been published successfully.';
                unset($_SESSION['csrf_token']);
                header('Location: ' . ($editing ? app_url('donor/donation.php?id='.$id) : dashboard_url('donor')), true, 303);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                if (!$saved) { remove_food_image($imagePath); }
                if ($exception instanceof InvalidArgumentException) {
                    $errors[] = $exception->getMessage();
                } elseif ($exception instanceof DomainException) {
                    http_response_code(409);
                    $errors[] = $exception->getMessage();
                } else {
                    error_log('Donation creation failed. Code: ' . $exception->getCode());
                    http_response_code(503);
                    $errors[] = 'Your donation could not be saved. Please try again later.';
                }
            }
        }
        if ($errors && !in_array(http_response_code(),[409,503],true)) { http_response_code(422); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editing ? 'Edit donation' : 'Share food' ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
<?php $navigationUser=$user; require __DIR__ . '/../includes/header.php'; ?>
<main class="container py-5" id="main-content">
    <div class="row g-4">
        <?php $donorPage=$editing?'donations':'add'; require __DIR__.'/../includes/donor_sidebar.php'; ?>
        <section class="col-lg-9" aria-labelledby="donation-heading">
            <div class="dashboard-welcome mb-4"><span class="eyebrow text-green">GIVE SURPLUS A PURPOSE</span><h1 class="h2 mt-3" id="donation-heading"><?= $editing ? 'Edit your donation' : 'Share a food donation' ?></h1><p class="text-secondary">Tell your community what you have available and when it can be collected.</p></div>
            <?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form action="<?= e($formAction) ?>" method="post" enctype="multipart/form-data" class="account-card donation-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php if ($editing): ?><input type="hidden" name="version" value="<?= e($formVersion) ?>"><?php endif; ?>
                <h2 class="h5 mb-4">About the food</h2>
                <div class="row g-3 mb-3">
                    <div class="col-md-8"><label for="food_name" class="form-label">Food name</label><input id="food_name" name="food_name" class="form-control" minlength="2" maxlength="150" value="<?= e($values['food_name']) ?>" placeholder="For example, freshly cooked rice and curry" required></div>
                    <div class="col-md-4"><label for="category" class="form-label">Category</label><select id="category" name="category" class="form-select" required><option value="">Choose a category</option><?php foreach (FOOD_CATEGORIES as $value=>$label): ?><option value="<?= e($value) ?>" <?= $values['category']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="mb-3"><label for="description" class="form-label">Description</label><textarea id="description" name="description" class="form-control" rows="4" minlength="10" maxlength="3000" aria-describedby="description-help" required><?= e($values['description']) ?></textarea><div id="description-help" class="form-text">Describe the food, packaging, ingredients or known allergens, and storage instructions.</div></div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label for="quantity" class="form-label">Quantity</label><input id="quantity" name="quantity" type="number" class="form-control" min="0.01" max="99999999.99" step="0.01" value="<?= e($values['quantity']) ?>" required></div>
                    <div class="col-md-6"><label for="quantity_unit" class="form-label">Unit</label><select id="quantity_unit" name="quantity_unit" class="form-select" required><?php foreach (FOOD_UNITS as $value=>$label): ?><option value="<?= e($value) ?>" <?= $values['quantity_unit']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="mb-4"><label for="food_image" class="form-label">Food photo <span class="optional">(optional)</span></label><input id="food_image" name="food_image" type="file" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="image-help"><div id="image-help" class="form-text">JPEG, PNG, or WebP, up to 2 MB, 4096 pixels per side and 8 megapixels. Photos may be displayed publicly. Images are saved as JPEG.</div>
                <?php if ($editing && $original['image_path'] !== null): ?><p class="form-text mt-2">Your existing photo is kept unless you upload a replacement or select removal below.</p><div class="form-check"><input id="remove_image" name="remove_image" type="checkbox" value="1" class="form-check-input" <?= post_text('remove_image')==='1'?'checked':'' ?>><label for="remove_image" class="form-check-label small">Remove the existing photo (a new upload takes priority)</label></div><?php endif; ?></div>
                <hr class="my-4"><h2 class="h5 mb-4">Collection details</h2>
                <div class="mb-3"><label for="pickup_location" class="form-label">Pickup location</label><textarea id="pickup_location" name="pickup_location" class="form-control" rows="2" minlength="5" maxlength="255" required><?= e($values['pickup_location']) ?></textarea></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label for="available_at" class="form-label">Available from</label><input id="available_at" name="available_at" type="datetime-local" class="form-control" value="<?= e($values['available_at']) ?>" required></div>
                    <div class="col-md-6"><label for="deadline_at" class="form-label">Collection deadline</label><input id="deadline_at" name="deadline_at" type="datetime-local" class="form-control" value="<?= e($values['deadline_at']) ?>" required></div>
                </div>
                <p class="form-text">All dates and times use Sri Lanka time. Set a collection deadline suitable for the food.</p>
                <div class="donation-form-actions mt-4"><button type="submit" class="btn btn-community"><?= $editing?'Save changes':'Publish donation' ?> &rarr;</button><a class="btn btn-quiet" href="<?= e($editing ? app_url('donor/donation.php?id='.$id) : dashboard_url('donor')) ?>"><?= $editing?'Back to donation':'Back to dashboard' ?></a></div>
                <p class="form-text mt-3 mb-0">After a validation error, please select your photo again before submitting.</p>
            </form>
        </section>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
