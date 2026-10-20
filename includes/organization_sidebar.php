<?php $organizationPage=$organizationPage??''; ?>
<aside class="col-lg-3"><nav class="account-sidebar" aria-label="Organization navigation"><span class="eyebrow">YOUR COMMUNITY SPACE</span><p class="mt-3 mb-4">Community organization</p>
<a href="<?= e(dashboard_url('organization')) ?>" <?= $organizationPage==='dashboard'?'aria-current="page"':'' ?>>Dashboard overview</a>
<a href="<?= e(app_url('organization/donations.php')) ?>" <?= $organizationPage==='browse'?'aria-current="page"':'' ?>>Browse food</a>
<a href="<?= e(app_url('organization/requests.php')) ?>" <?= $organizationPage==='requests'?'aria-current="page"':'' ?>>My requests</a>
<a href="<?= e(app_url('organization/requests.php?delivery=completed')) ?>" <?= $organizationPage==='history'?'aria-current="page"':'' ?>>Completed history</a>
<a href="<?= e(app_url('organization/index.php#recent-records')) ?>">Recent requests</a>
<a href="<?= e(app_url('organization/profile.php')) ?>" <?= $organizationPage==='profile'?'aria-current="page"':'' ?>>My profile</a>
<a href="<?= e(app_url('index.php')) ?>">Community home</a></nav></aside>
