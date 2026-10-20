<?php $donorPage = $donorPage ?? ''; ?>
<aside class="col-lg-3"><nav class="account-sidebar" aria-label="Donor navigation">
    <span class="eyebrow">YOUR COMMUNITY SPACE</span><p class="mt-3 mb-4">Food donor</p>
    <a href="<?= e(dashboard_url('donor')) ?>" <?= $donorPage === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard overview</a>
    <a href="<?= e(app_url('donor/add_donation.php')) ?>" <?= $donorPage === 'add' ? 'aria-current="page"' : '' ?>>Share food</a>
    <a href="<?= e(app_url('donor/donations.php')) ?>" <?= $donorPage === 'donations' ? 'aria-current="page"' : '' ?>>My donations</a>
    <a href="<?= e(app_url('donor/donations.php?status=completed')) ?>" <?= $donorPage === 'history' ? 'aria-current="page"' : '' ?>>Completed history</a>
    <a href="<?= e(app_url('donor/requests.php')) ?>" <?= $donorPage === 'requests' ? 'aria-current="page"' : '' ?>>Incoming requests</a>
    <a href="<?= e(app_url('donor/profile.php')) ?>" <?= $donorPage === 'profile' ? 'aria-current="page"' : '' ?>>My profile</a>
    <a href="<?= e(app_url('index.php')) ?>">Community home</a>
</nav></aside>
