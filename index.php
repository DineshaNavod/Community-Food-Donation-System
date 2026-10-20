<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$navigationUser = current_user();
$activePage = 'home';
$availableDonations = [];
$donationsUnavailable = false;
try {
    $query = db()->prepare("SELECT d.food_name, d.category, d.quantity, d.quantity_unit, d.deadline_at, d.image_path
        FROM donations d JOIN users u ON u.id = d.donor_id
        WHERE d.status = 'available' AND d.available_at <= NOW() AND d.deadline_at > NOW()
        AND u.status = 'active' AND u.role = 'donor'
        AND NOT EXISTS (SELECT 1 FROM donation_requests r WHERE r.donation_id = d.id AND r.status = 'approved')
        ORDER BY d.created_at DESC, d.id DESC LIMIT 3");
    $query->execute();
    $availableDonations = $query->fetchAll();
} catch (PDOException $exception) {
    error_log('Homepage donations unavailable. Code: ' . $exception->getCode());
    $donationsUnavailable = true;
}
$joinUrl = $navigationUser === null ? app_url('register.php') : dashboard_url($navigationUser['role']);
$categoryLabels = ['cooked_meals' => 'Cooked meals', 'bakery' => 'Bakery', 'produce' => 'Fruit & vegetables',
    'dry_goods' => 'Dry goods', 'dairy' => 'Dairy', 'other' => 'Other food'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connect surplus food with local communities. Join as a food donor, community organization, or collection volunteer.">
    <title>Good food, shared | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main id="main-content">
        <section class="home-hero container" aria-labelledby="hero-heading">
            <div class="hero-copy">
                <span class="hero-kicker"><span aria-hidden="true"></span> SMALL ACTIONS. SHARED IMPACT.</span>
                <h1 id="hero-heading">Good food deserves<br><span>a second chance.</span></h1>
                <p>Turn surplus into support. Connect with local organizations and volunteers to share food, reduce waste, and care for your community.</p>
                <div class="hero-actions">
                    <a class="btn btn-community" href="<?= e($joinUrl) ?>"><?= $navigationUser === null ? 'Start sharing food' : 'Go to my dashboard' ?> <span aria-hidden="true">&rarr;</span></a>
                    <a class="btn btn-quiet" href="#how-it-works">See how it works <span aria-hidden="true">&darr;</span></a>
                </div>
                <p class="hero-footnote"><span aria-hidden="true">&#10003;</span> A place for donors, organizations, and everyday helpers.</p>
            </div>
            <div class="hero-art">
                <span class="art-label">A LITTLE SURPLUS. A LOT OF POSSIBILITY.</span>
                <img src="assets/images/community-basket.svg" width="600" height="490" alt="An illustrated basket of fresh vegetables, fruit, and bread, ready to share.">
                <div class="sharing-note"><span class="sharing-heart" aria-hidden="true">&#9825;</span><div><strong>Made to be shared</strong><span>Good food. A stronger community.</span></div></div>
            </div>
        </section>

        <div class="purpose-strip"><div class="container"><span>Less food wasted.</span><span>More connections made.</span><span>More care shared.</span></div></div>

        <section class="container home-section" id="our-community" aria-labelledby="community-heading">
            <div class="section-heading"><div><span class="eyebrow text-green">THERE IS A PLACE FOR YOU</span><h2 id="community-heading">One community. Many ways to help.</h2></div><p>Whether you have food to share or time to give, your contribution matters.</p></div>
            <div class="row g-4">
                <?php foreach ([['01', 'Food donors', 'Give surplus a purpose.', 'Restaurants, shops, households, and event organizers can share food that would otherwise go to waste.'],
                    ['02', 'Community organizations', 'Bring good food closer.', 'Find donations that can support the people in your care and coordinate receipt with your community.'],
                    ['03', 'Collection volunteers', 'Be the connection.', 'Help collect food from donors and deliver it to the organizations that need it.']] as $role): ?>
                    <div class="col-md-4"><article class="community-role-card"><span class="role-number"><?= e($role[0]) ?></span><p class="role-label"><?= e($role[1]) ?></p><h3><?= e($role[2]) ?></h3><p><?= e($role[3]) ?></p><a href="<?= e($joinUrl) ?>"><?= $navigationUser === null ? 'Join our community' : 'Open my dashboard' ?> <span aria-hidden="true">&rarr;</span></a></article></div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="how-section" id="how-it-works" aria-labelledby="how-heading"><div class="container home-section">
            <div class="section-heading"><div><span class="eyebrow text-green">FROM SURPLUS TO SUPPORT</span><h2 id="how-heading">A simple journey. A meaningful difference.</h2></div></div>
            <ol class="how-steps">
                <li><span>01</span><h3>Share available food</h3><p>A donor adds food details, quantity, a pickup location, and a collection deadline.</p></li>
                <li><span>02</span><h3>Connect with an organization</h3><p>An organization requests the food and the donor approves a suitable request.</p></li>
                <li><span>03</span><h3>Collect and deliver</h3><p>An assigned volunteer collects the food and brings it to the organization.</p></li>
                <li><span>04</span><h3>Confirm it arrived</h3><p>The receiving organization confirms receipt to complete the donation.</p></li>
            </ol>
        </div></section>

        <section class="container home-section" id="available-food" aria-labelledby="food-heading">
            <div class="section-heading"><div><span class="eyebrow text-green">GOOD FOOD, READY TO SHARE</span><h2 id="food-heading">Fresh opportunities to make a difference.</h2></div><p>A look at the latest food available from our community.</p></div>
            <?php if ($donationsUnavailable): ?>
                <div class="food-empty" role="status"><h3>We could not load available food.</h3><p>Please try again in a moment.</p></div>
            <?php elseif (!$availableDonations): ?>
                <div class="food-empty"><span class="empty-symbol" aria-hidden="true">&#9825;</span><h3>The next good thing could start with you.</h3><p>No food donations are available right now. Have surplus food to share? Join our community and help it go further.</p><a class="home-link" href="<?= e($joinUrl) ?>"><?= $navigationUser === null ? 'Become part of the community' : 'Go to my dashboard' ?> &rarr;</a></div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($availableDonations as $donation): ?>
                        <?php $previewImage = is_string($donation['image_path']) && preg_match('/\Auploads\/donations\/[a-f0-9]{32}\.jpg\z/', $donation['image_path']) ? $donation['image_path'] : null; ?>
                        <div class="col-md-4"><article class="community-role-card food-preview">
                            <?php if ($previewImage !== null): ?><img class="food-preview-image mb-3" src="<?= e(app_url($previewImage)) ?>" alt="<?= e($donation['food_name']) ?>" loading="lazy" width="600" height="360"><?php endif; ?>
                            <span class="badge text-bg-success">Available</span><p class="role-label mt-4"><?= e($categoryLabels[$donation['category']] ?? 'Food donation') ?></p><h3><?= e($donation['food_name']) ?></h3><p><?= e(rtrim(rtrim($donation['quantity'], '0'), '.')) ?> <?= e($donation['quantity_unit']) ?></p><p class="food-deadline">Collect by <?= e(date('j M, g:i a', strtotime($donation['deadline_at']))) ?> (Sri Lanka time)</p><a href="<?= e($navigationUser === null ? app_url('login.php') : dashboard_url($navigationUser['role'])) ?>"><?= $navigationUser === null ? 'Sign in to get involved' : 'Open my dashboard' ?> &rarr;</a>
                        </article></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="container mb-5"><div class="community-invitation"><div><span class="eyebrow">MAKE ROOM FOR SOMETHING GOOD</span><h2>What you share can change someone&rsquo;s day.</h2><p>Be part of a community that puts good food to good use.</p></div><a class="btn btn-light" href="<?= e($joinUrl) ?>"><?= $navigationUser === null ? 'Join the community' : 'My dashboard' ?> &rarr;</a></div></section>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
