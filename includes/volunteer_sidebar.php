<?php $volunteerPage=$volunteerPage??''; ?>
<aside class="col-lg-3"><nav class="account-sidebar" aria-label="Volunteer navigation"><span class="eyebrow">YOUR COMMUNITY SPACE</span><p class="mt-3 mb-4">Collection volunteer</p>
<a href="<?= e(dashboard_url('volunteer')) ?>" <?= $volunteerPage==='dashboard'?'aria-current="page"':'' ?>>Dashboard overview</a>
<a href="<?= e(app_url('volunteer/recent.php')) ?>" <?= $volunteerPage==='recent'?'aria-current="page"':'' ?>>Recent collection tasks</a>
<a href="<?= e(app_url('volunteer/tasks.php')) ?>" <?= $volunteerPage==='tasks'?'aria-current="page"':'' ?>>My collection tasks</a>
<a href="<?= e(app_url('volunteer/history.php')) ?>" <?= $volunteerPage==='history'?'aria-current="page"':'' ?>>Completed task history</a>
<a href="<?= e(app_url('volunteer/profile.php')) ?>" <?= $volunteerPage==='profile'?'aria-current="page"':'' ?>>My profile</a>
<a href="<?= e(app_url('index.php')) ?>">Community home</a></nav></aside>
