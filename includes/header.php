<?php
declare(strict_types=1);
$navigationUser = $navigationUser ?? current_user();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$activePage = $activePage ?? '';
?>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header shared-header">
    <div class="container navigation-layout">
        <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="Community Food home"><span class="brand-mark" aria-hidden="true">C</span> Community Food</a>
        <nav class="primary-navigation" aria-label="Main navigation">
            <a href="<?= e(app_url('index.php')) ?>" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
            <a href="<?= e(app_url('index.php#how-it-works')) ?>">How it works</a>
            <a href="<?= e(app_url('index.php#available-food')) ?>">Available food</a>
        </nav>
        <div class="navigation-actions">
            <?php if ($navigationUser !== null): ?>
                <a class="home-link" href="<?= e(dashboard_url($navigationUser['role'])) ?>">My dashboard</a>
                <form action="<?= e(app_url('logout.php')) ?>" method="post" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-outline-success btn-sm" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="home-link" href="<?= e(app_url('login.php')) ?>" <?= $activePage === 'login' ? 'aria-current="page"' : '' ?>>Sign in</a>
                <a class="btn btn-community nav-join" href="<?= e(app_url('register.php')) ?>" <?= $activePage === 'register' ? 'aria-current="page"' : '' ?>>Join the community <span aria-hidden="true">&rarr;</span></a>
            <?php endif; ?>
        </div>
    </div>
</header>
