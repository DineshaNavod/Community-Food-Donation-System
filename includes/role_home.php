<?php
declare(strict_types=1);
// Included only after the role entry page has called require_role().
require_once __DIR__ . '/dashboard.php';
$dashboard = dashboard_data($user);
$donationSuccess = $_SESSION['donation_success'] ?? '';
unset($_SESSION['donation_success']);
$recordTitles = ['donor'=>'Recent donations','organization'=>'Recent requests',
    'volunteer'=>'Recent collection tasks','admin'=>'Recent donations'];
$emptyMessages = ['donor'=>'Your donations will appear here once you start sharing food.',
    'organization'=>'Your donation requests will appear here.',
    'volunteer'=>'Your collection tasks will appear here when an administrator assigns them.',
    'admin'=>'Food donations will appear here when donors publish them.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($roleLabel) ?> dashboard | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
    <?php $navigationUser = $user; require __DIR__ . '/header.php'; ?>
    <main class="container py-5" id="main-content">
        <div class="row g-4">
            <?php if ($user['role']==='admin'): ?>
            <?php $adminPage='dashboard';require __DIR__.'/admin_sidebar.php'; ?>
            <?php else: ?><aside class="col-lg-3">
                <nav class="account-sidebar" aria-label="Account navigation">
                    <span class="eyebrow">YOUR COMMUNITY SPACE</span>
                    <p class="mt-3 mb-4"><?= e($roleLabel) ?></p>
                    <a href="<?= e(dashboard_url($user['role'])) ?>" aria-current="page">Dashboard overview</a>
                    <?php if ($user['role']==='organization'): ?><a href="<?= e(app_url('organization/donations.php')) ?>">Browse food</a><?php endif; ?>
                    <?php if ($user['role']==='organization'): ?><a href="<?= e(app_url('organization/requests.php')) ?>">My requests</a><a href="<?= e(app_url('organization/requests.php?delivery=completed')) ?>">Completed history</a><?php endif; ?>
                    <?php if ($user['role']==='organization'): ?><a href="<?= e(app_url('organization/profile.php')) ?>">My profile</a><?php endif; ?>
                    <?php if ($user['role'] === 'donor'): ?><a href="<?= e(app_url('donor/add_donation.php')) ?>">Share food</a><?php endif; ?>
                    <?php if ($user['role'] === 'donor'): ?><a href="<?= e(app_url('donor/donations.php')) ?>">My donations</a><a href="<?= e(app_url('donor/donations.php?status=completed')) ?>">Completed history</a><?php endif; ?>
                    <?php if ($user['role']==='donor'): ?><a href="<?= e(app_url('donor/requests.php')) ?>">Incoming requests</a><?php endif; ?>
                    <a href="<?= $user['role']==='volunteer'?e(app_url('volunteer/recent.php')):'#recent-records' ?>"><?= e($recordTitles[$user['role']]) ?></a>
                    <?php if ($user['role'] === 'admin'): ?><a href="#pending-accounts">Pending accounts</a><?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('admin/accounts.php')) ?>">Account approvals</a><?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('admin/users.php')) ?>">Registered users</a><?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('admin/donations.php')) ?>">All donations</a><a href="<?= e(app_url('admin/requests.php')) ?>">Donation requests</a><?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('admin/assignments.php')) ?>">Assign collections</a><?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('admin/reports.php')) ?>">Reports and statistics</a><?php endif; ?>
                    <?php if ($user['role'] === 'volunteer'): ?><a href="<?= e(app_url('volunteer/tasks.php')) ?>">My collection tasks</a><?php endif; ?>
                    <?php if ($user['role'] === 'volunteer'): ?><a href="<?= e(app_url('volunteer/history.php')) ?>">Completed task history</a><a href="<?= e(app_url('volunteer/profile.php')) ?>">My profile</a><?php endif; ?>
                    <a href="#account-details">My account</a>
                    <?php if ($user['role']==='donor'): ?><a href="<?= e(app_url('donor/profile.php')) ?>">Edit profile</a><?php endif; ?>
                    <a href="<?= e(app_url('index.php')) ?>">Community home</a>
                </nav>
            </aside><?php endif; ?>
            <section class="col-lg-9" aria-labelledby="welcome-heading">
                <div class="dashboard-welcome">
                    <span class="eyebrow text-green"><?= e($roleLabel) ?> DASHBOARD</span>
                    <h1 class="h2 mt-3" id="welcome-heading">Welcome, <?= e($user['full_name']) ?>.</h1>
                    <p class="text-secondary"><?= e($introduction) ?></p>
                    <div class="dashboard-meta"><span class="badge text-bg-success">Active account</span><span><?= e(date('l, j F Y')) ?> &middot; Sri Lanka time</span></div>
                </div>
                <?php if ($user['role'] === 'donor'): ?><div class="my-4"><a class="btn btn-community" href="<?= e(app_url('donor/add_donation.php')) ?>">Share a food donation &rarr;</a></div><?php endif; ?>
                <?php if ($user['role']==='organization'): ?><div class="my-4"><a class="btn btn-community" href="<?= e(app_url('organization/donations.php')) ?>">Browse available food &rarr;</a></div><?php endif; ?>
                <?php if ($donationSuccess !== ''): ?><div class="alert alert-success mt-3" role="status"><?= e($donationSuccess) ?></div><?php endif; ?>
                <?php if ($dashboard['error']): ?>
                    <div class="alert alert-warning mt-4" role="alert">We could not load your dashboard information. Please try again shortly.</div>
                <?php else: ?>
                    <div class="row g-3 mt-2 mb-4" aria-label="Dashboard statistics">
                        <?php foreach ($dashboard['statistics'] as $statistic): ?>
                            <div class="col-sm-6 col-xl-3"><article class="dashboard-stat"><h2><?= e($statistic[0]) ?></h2><strong><?= e(number_format((int) $statistic[1])) ?></strong><p><?= e($statistic[2]) ?></p></article></div>
                        <?php endforeach; ?>
                    </div>
                    <section class="dashboard-panel mb-4" id="recent-records" aria-labelledby="records-heading">
                        <div class="dashboard-panel-heading"><h2 id="records-heading"><?= e($recordTitles[$user['role']]) ?></h2><span>Latest 5 &middot; Sri Lanka time</span></div>
                        <?php if (!$dashboard['records']): ?>
                            <div class="dashboard-empty"><span aria-hidden="true">&#9825;</span><h3>A fresh start.</h3><p><?= e($emptyMessages[$user['role']]) ?></p></div>
                        <?php else: ?>
                            <div class="table-responsive"><table class="table dashboard-table align-middle mb-0">
                                <caption class="visually-hidden"><?= e($recordTitles[$user['role']]) ?>, latest five records. Dates are in Sri Lanka time.</caption>
                                <thead><tr><th scope="col">Food</th>
                                    <?php if ($user['role'] === 'donor'): ?><th scope="col">Quantity</th><th scope="col">Collect by</th>
                                    <?php elseif ($user['role'] === 'organization'): ?><th scope="col">Request</th>
                                    <?php elseif ($user['role'] === 'volunteer'): ?><th scope="col">Organization</th><th scope="col">Task</th>
                                    <?php else: ?><th scope="col">Donor</th><?php endif; ?>
                                    <th scope="col">Donation</th><th scope="col">Updated</th>
                                </tr></thead>
                                <tbody><?php foreach ($dashboard['records'] as $record): ?><tr>
                                    <td class="food-cell"><?php if (in_array($user['role'],['donor','organization'],true)): ?><a class="home-link" href="<?= e(app_url($user['role'].'/donation.php?id='.$record['id'])) ?>"><?= e($record['food_name']) ?></a><?php elseif ($user['role']==='volunteer'): ?><a class="home-link" href="<?= e(app_url('volunteer/task.php?id='.$record['task_id'])) ?>"><?= e($record['food_name']) ?></a><?php else: ?><?= e($record['food_name']) ?><?php endif; ?><?php if ($user['role']==='organization'): ?><a class="home-link small d-block mt-2" href="<?= e(app_url('organization/request.php?id='.$record['request_id'])) ?>">Track request &rarr;</a><?php endif; ?></td>
                                    <?php if ($user['role'] === 'donor'): ?><td><?= e(rtrim(rtrim($record['quantity'], '0'), '.') . ' ' . $record['quantity_unit']) ?></td><td><?= e(dashboard_date($record['deadline_at'])) ?></td>
                                    <?php elseif ($user['role'] === 'organization'): ?><td><?= status_badge($record['request_status']) ?></td>
                                    <?php elseif ($user['role'] === 'volunteer'): ?><td class="food-cell"><?= e($record['organization_name']) ?></td><td><?= status_badge($record['task_status']) ?></td>
                                    <?php else: ?><td class="food-cell"><?= e($record['donor_name']) ?></td><?php endif; ?>
                                    <td><?= status_badge($record['donation_status']) ?></td><td><?= e(dashboard_date($record['updated_at'])) ?></td>
                                </tr><?php endforeach; ?></tbody>
                            </table></div>
                        <?php endif; ?>
                    </section>
                    <?php if ($user['role'] === 'admin'): ?>
                        <section class="dashboard-panel mb-4" id="pending-accounts" aria-labelledby="pending-heading">
                            <div class="dashboard-panel-heading"><h2 id="pending-heading">Pending accounts</h2><span><?= e((string) $dashboard['pending_count']) ?> awaiting approval</span></div>
                            <p class="dashboard-panel-message"><a class="home-link" href="<?= e(app_url('admin/accounts.php')) ?>">Review and approve accounts &rarr;</a></p>
                            <?php if (!$dashboard['pending_accounts']): ?>
                                <p class="dashboard-panel-message">No organization or volunteer accounts are waiting for approval.</p>
                            <?php else: ?>
                                <div class="table-responsive"><table class="table dashboard-table align-middle mb-0"><caption class="visually-hidden">Latest five pending organization and volunteer accounts.</caption>
                                    <thead><tr><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Registered</th></tr></thead><tbody>
                                    <?php foreach ($dashboard['pending_accounts'] as $account): ?><tr><td class="food-cell"><?= e($account['full_name']) ?></td><td><?= $account['role'] === 'organization' ? 'Organization' : 'Volunteer' ?></td><td><?= status_badge('pending') ?></td><td><?= e(dashboard_date($account['created_at'])) ?></td></tr><?php endforeach; ?>
                                    </tbody></table></div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="account-card" id="account-details">
                    <h2 class="h5">Your account details</h2>
                    <?php if ($user['role']==='donor'): ?><a class="home-link small" href="<?= e(app_url('donor/profile.php')) ?>">Edit my profile &rarr;</a><?php endif; ?>
                    <?php if ($user['role']==='organization'): ?><a class="home-link small" href="<?= e(app_url('organization/profile.php')) ?>">Edit my profile &rarr;</a><?php endif; ?>
                    <?php if ($user['role']==='volunteer'): ?><a class="home-link small" href="<?= e(app_url('volunteer/profile.php')) ?>">Edit my profile &rarr;</a><?php endif; ?>
                    <dl class="row mt-3 mb-0 account-details">
                        <?php if ($dashboard['organization_name'] !== null): ?><dt class="col-sm-3">Organization</dt><dd class="col-sm-9"><?= e($dashboard['organization_name']) ?></dd><?php endif; ?>
                        <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= e($user['email']) ?></dd>
                        <dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= e($user['phone']) ?></dd>
                        <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($user['address']) ?></dd>
                    </dl>
                </div>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
