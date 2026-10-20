<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_role($profileRole);
require_once __DIR__.'/../includes/profile.php';
$values=array_intersect_key($user,array_flip(['full_name','email','phone','address']));
$organization=null;
if ($profileRole==='organization') {
    $query=db()->prepare('SELECT id,organization_name,description FROM organizations WHERE user_id=?');
    $query->execute([$user['id']]);$organization=$query->fetch();
    if (!$organization) {http_response_code(409);exit('Organization profile is unavailable. Contact the administrator.');}
    $values['organization_name']=$organization['organization_name'];$values['description']=$organization['description']??'';
}
$version=editable_profile_version($user,$organization);$errors=[];
$message=$_SESSION[$profileRole.'_profile_success']??'';unset($_SESSION[$profileRole.'_profile_success']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $message='';
    foreach ($values as $field=>$value) {$values[$field]=post_text($field);}
    $values['email']=strtolower($values['email']);$version=post_text('version');
    if (!valid_csrf_token($_POST['csrf_token']??null)) {
        http_response_code(403);$errors[]='Your form session is invalid or has expired. Submit this form again.';
    } else {
        $errors=validate_profile($values);
        if ($organization!==null) {
            if (mb_strlen($values['organization_name'])<2 || mb_strlen($values['organization_name'])>150) {
                $errors[]='Enter an organization name between 2 and 150 characters.';
            }
            if (mb_strlen($values['description'])>1500) {$errors[]='Keep the organization description within 1500 characters.';}
        }
        if (!$errors) {
            $pdo=db();
            try {
                $pdo->beginTransaction();
                $query=$pdo->prepare('SELECT id,full_name,email,phone,address,password_hash,role,status FROM users WHERE id=? FOR UPDATE');
                $query->execute([$user['id']]);$locked=$query->fetch();
                if (!$locked || $locked['role']!==$profileRole || $locked['status']!=='active') {
                    throw new DomainException('Your account is no longer allowed to update this profile.');
                }
                $lockedOrganization=null;
                if ($organization!==null) {
                    $query=$pdo->prepare('SELECT id,organization_name,description FROM organizations WHERE user_id=? FOR UPDATE');
                    $query->execute([$user['id']]);$lockedOrganization=$query->fetch();
                    if (!$lockedOrganization) {throw new DomainException('Your organization profile is unavailable. Reload the page.');}
                }
                if (!hash_equals(editable_profile_version($locked,$lockedOrganization),$version)) {
                    throw new DomainException('Your profile changed after you opened this form. Reload the page before editing again.');
                }
                $emailChanged=$values['email']!==$locked['email'];
                if ($emailChanged) {
                    $password=is_string($_POST['current_password']??null)?$_POST['current_password']:'';
                    if ($password==='' || strlen($password)>72 || str_contains($password,"\0")
                        || !password_verify($password,$locked['password_hash'])) {
                        throw new InvalidArgumentException('Enter your correct current password to change the email address.');
                    }
                }
                // Contact fields are explicitly allowed; organization fields use
                // a separate scoped update. Never accept account permissions or passwords.
                $query=$pdo->prepare('UPDATE users SET full_name=?,email=?,phone=?,address=? WHERE id=?');
                $query->execute([$values['full_name'],$values['email'],$values['phone'],$values['address'],$user['id']]);
                if ($lockedOrganization!==null) {
                    $pdo->prepare('UPDATE organizations SET organization_name=?,description=? WHERE id=? AND user_id=?')
                        ->execute([$values['organization_name'],$values['description']===''?null:$values['description'],$lockedOrganization['id'],$user['id']]);
                }
                $pdo->commit();
                if ($emailChanged) {session_regenerate_id(true);}
                unset($_SESSION['csrf_token']);
                $_SESSION[$profileRole.'_profile_success']='Your profile has been updated.'.($emailChanged?' Use your new email the next time you sign in.':'');
                header('Location: '.app_url($profileRole.'/profile.php'),true,303);exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {$pdo->rollBack();}
                if ($exception instanceof PDOException && (int)($exception->errorInfo[1]??0)===1062) {
                    $errors[]='That email address is already registered. Please use another address.';
                } elseif ($exception instanceof DomainException) {
                    http_response_code(409);$errors[]=$exception->getMessage();
                } elseif ($exception instanceof InvalidArgumentException) {
                    $errors[]=$exception->getMessage();
                } else {
                    error_log('Profile update failed. Code: '.$exception->getCode());
                    http_response_code(503);$errors[]='Your profile could not be saved. Please try again later.';
                }
            }
        }
        if ($errors && !in_array(http_response_code(),[409,503],true)) {http_response_code(422);}
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My profile | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head><body>
<?php $navigationUser=$user;require __DIR__.'/../includes/header.php'; ?>
<main class="container py-5" id="main-content"><div class="row g-4">
<?php $donorPage=$organizationPage=$volunteerPage='profile';require __DIR__.'/'.$profileRole.'_sidebar.php'; ?>
<section class="col-lg-9" aria-labelledby="profile-heading">
<div class="dashboard-welcome mb-4"><span class="eyebrow text-green">YOUR COMMUNITY ACCOUNT</span><h1 class="h2 mt-3" id="profile-heading">My profile</h1><p class="text-secondary">Keep your contact details up to date so collection can be coordinated smoothly.</p></div>
<?php if ($message!==''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form action="<?= e(app_url($profileRole.'/profile.php')) ?>" method="post" class="account-card">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="version" value="<?= e($version) ?>">
<?php if ($organization!==null): ?><h2 class="h5 mb-4">Organization details</h2>
<div class="mb-3"><label for="organization_name" class="form-label">Organization name</label><input id="organization_name" name="organization_name" class="form-control" value="<?= e($values['organization_name']) ?>" minlength="2" maxlength="150" autocomplete="organization" required></div>
<div class="mb-4"><label for="description" class="form-label">Organization description <span class="optional">(optional)</span></label><textarea id="description" name="description" class="form-control" rows="3" maxlength="1500"><?= e($values['description']) ?></textarea></div><?php endif; ?>
<h2 class="h5 mb-4"><?= $organization!==null?'Contact person':'Personal details' ?></h2>
<div class="row g-3 mb-3"><div class="col-md-6"><label for="full_name" class="form-label">Full name</label><input id="full_name" name="full_name" class="form-control" value="<?= e($values['full_name']) ?>" minlength="2" maxlength="100" autocomplete="name" required></div>
<div class="col-md-6"><label for="phone" class="form-label">Phone number</label><input id="phone" name="phone" type="tel" class="form-control" value="<?= e($values['phone']) ?>" maxlength="25" autocomplete="tel" required></div></div>
<div class="mb-3"><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" class="form-control" value="<?= e($values['email']) ?>" maxlength="191" autocomplete="email" aria-describedby="email-help" required><div id="email-help" class="form-text">This is the email you use to sign in.</div></div>
<div class="mb-3"><label for="address" class="form-label">Address</label><textarea id="address" name="address" class="form-control" rows="3" minlength="5" maxlength="255" autocomplete="street-address" aria-describedby="address-help" required><?= e($values['address']) ?></textarea><div id="address-help" class="form-text"><?= $profileRole==='volunteer'?'Your contact address for coordinating volunteer activity. Task pickup and delivery addresses stay as recorded.':($organization!==null?'Used as the default delivery address for new requests. Existing request addresses stay as recorded.':'Used as the default pickup location for new donations. Existing donation locations stay as recorded.') ?></div></div>
<div class="mb-4"><label for="current_password" class="form-label">Current password <span class="optional">(required only when changing email)</span></label><input id="current_password" name="current_password" type="password" class="form-control" maxlength="72" autocomplete="current-password"><div class="form-text">Enter your current password to confirm an email change.</div></div>
<div class="donation-form-actions"><button class="btn btn-community" type="submit">Save profile &rarr;</button><a class="btn btn-quiet" href="<?= e(dashboard_url($profileRole)) ?>">Back to dashboard</a></div>
</form></section></div></main>
<?php require __DIR__.'/../includes/footer.php'; ?></body></html>
