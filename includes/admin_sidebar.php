<?php $adminPage=$adminPage??''; ?>
<aside class="col-lg-3"><nav class="account-sidebar" aria-label="Administrator navigation"><span class="eyebrow">YOUR COMMUNITY SPACE</span><p class="mt-3 mb-4">Administrator</p>
<a href="<?= e(dashboard_url('admin')) ?>" <?= $adminPage==='dashboard'?'aria-current="page"':'' ?>>Dashboard overview</a>
<a href="<?= e(app_url('admin/recent.php')) ?>" <?= $adminPage==='recent'?'aria-current="page"':'' ?>>Recent donations</a>
<a href="<?= e(app_url('admin/accounts.php')) ?>" <?= $adminPage==='accounts'?'aria-current="page"':'' ?>>Pending accounts</a>
<a href="<?= e(app_url('admin/users.php')) ?>" <?= $adminPage==='users'?'aria-current="page"':'' ?>>Registered users</a>
<a href="<?= e(app_url('admin/donations.php')) ?>" <?= $adminPage==='donations'?'aria-current="page"':'' ?>>All donations</a>
<a href="<?= e(app_url('admin/requests.php')) ?>" <?= $adminPage==='requests'?'aria-current="page"':'' ?>>Donation requests</a>
<a href="<?= e(app_url('admin/assignments.php')) ?>" <?= $adminPage==='assignments'?'aria-current="page"':'' ?>>Assign collections</a>
<a href="<?= e(app_url('admin/reports.php')) ?>" <?= $adminPage==='reports'?'aria-current="page"':'' ?>>Reports and statistics</a>
<a href="<?= e(app_url('index.php')) ?>">Community home</a></nav></aside>
