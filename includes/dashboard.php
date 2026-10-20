<?php
declare(strict_types=1);

// Each query is scoped to the authenticated database user, never a URL parameter.
function dashboard_data(array $user): array
{
    $pdo = db();
    $data = ['statistics' => [], 'records' => [], 'pending_accounts' => [],
        'organization_name' => null, 'error' => false];
    // Show uncollected overdue food honestly without changing workflow records.
    $foodStatus = "CASE WHEN d.status IN ('available','requested','assigned') AND d.deadline_at <= NOW()
        THEN 'expired' ELSE d.status END";
    try {
        switch ($user['role']) {
            case 'donor':
                $query = $pdo->prepare("SELECT COUNT(*) AS total,
                    COALESCE(SUM(status='available' AND available_at<=NOW() AND deadline_at>NOW()),0) AS available,
                    COALESCE(SUM(status='completed'),0) AS completed FROM donations WHERE donor_id=?");
                $query->execute([$user['id']]);
                $counts = $query->fetch();
                $query = $pdo->prepare("SELECT COUNT(*) FROM donation_requests r JOIN donations d ON d.id=r.donation_id
                    WHERE d.donor_id=? AND r.status='pending' AND d.status IN ('available','requested') AND d.deadline_at>NOW()");
                $query->execute([$user['id']]);
                $data['statistics'] = [['Total donations', $counts['total'], 'All your food donations'],
                    ['Available now', $counts['available'], 'Within the pickup window'],
                    ['Pending requests', $query->fetchColumn(), 'Awaiting your decision'],
                    ['Completed', $counts['completed'], 'Receipt confirmed']];
                $query = $pdo->prepare("SELECT d.id, d.food_name, d.quantity, d.quantity_unit, $foodStatus AS donation_status,
                    d.deadline_at, d.updated_at FROM donations d WHERE d.donor_id=? ORDER BY d.updated_at DESC,d.id DESC LIMIT 5");
                $query->execute([$user['id']]);
                $data['records'] = $query->fetchAll();
                break;
            case 'organization':
                $query = $pdo->prepare('SELECT id,organization_name FROM organizations WHERE user_id=?');
                $query->execute([$user['id']]);
                $organization = $query->fetch();
                $data['organization_name'] = $organization ? $organization['organization_name'] : null;
                $organizationId = $organization ? (int) $organization['id'] : 0;
                $query = $pdo->prepare("SELECT COUNT(*) AS total,
                    COALESCE(SUM(r.status='pending' AND d.status IN ('available','requested') AND d.deadline_at>NOW()),0) AS pending,
                    COALESCE(SUM(r.status='approved' AND (d.status IN ('collected','delivered')
                        OR (d.status='assigned' AND d.deadline_at>NOW()))),0) AS in_progress,
                    COALESCE(SUM(r.status='approved' AND d.status='completed'),0) AS completed
                    FROM donation_requests r JOIN donations d ON d.id=r.donation_id WHERE r.organization_id=?");
                $query->execute([$organizationId]);
                $counts = $query->fetch();
                $data['statistics'] = [['Total requests', $counts['total'], 'All your donation requests'],
                    ['Pending requests', $counts['pending'], 'Awaiting a donor decision'],
                    ['In progress', $counts['in_progress'], 'Assigned, collected or delivered'],
                    ['Received', $counts['completed'], 'Receipt confirmed']];
                $query = $pdo->prepare("SELECT d.id,r.id AS request_id,d.food_name,r.status AS request_status,$foodStatus AS donation_status,
                    GREATEST(r.updated_at,d.updated_at,COALESCE(t.updated_at,r.updated_at)) AS updated_at
                    FROM donation_requests r JOIN donations d ON d.id=r.donation_id
                    LEFT JOIN collection_tasks t ON t.request_id=r.id
                    WHERE r.organization_id=? ORDER BY updated_at DESC,r.id DESC LIMIT 5");
                $query->execute([$organizationId]);
                $data['records'] = $query->fetchAll();
                break;
            case 'volunteer':
                $query = $pdo->prepare("SELECT COUNT(*) AS total,
                    COALESCE(SUM(t.status='assigned' AND d.status='assigned' AND d.deadline_at>NOW()),0) AS awaiting,
                    COALESCE(SUM((t.status='accepted' AND d.status='assigned' AND d.deadline_at>NOW())
                        OR (t.status='collected' AND d.status='collected')),0) AS in_progress,
                    COALESCE(SUM(t.status='delivered'),0) AS delivered
                    FROM collection_tasks t JOIN donation_requests r ON r.id=t.request_id
                    JOIN donations d ON d.id=r.donation_id WHERE t.volunteer_id=?");
                $query->execute([$user['id']]);
                $counts = $query->fetch();
                $data['statistics'] = [['Total assignments', $counts['total'], 'All your collection tasks'],
                    ['To accept', $counts['awaiting'], 'Assigned and within deadline'],
                    ['In progress', $counts['in_progress'], 'Accepted or collected'],
                    ['Delivered', $counts['delivered'], 'Food brought to its destination']];
                $query = $pdo->prepare("SELECT t.id AS task_id,d.food_name,o.organization_name,t.status AS task_status,
                    $foodStatus AS donation_status,GREATEST(t.updated_at,d.updated_at) AS updated_at FROM collection_tasks t
                    JOIN donation_requests r ON r.id=t.request_id JOIN donations d ON d.id=r.donation_id
                    JOIN organizations o ON o.id=r.organization_id WHERE t.volunteer_id=?
                    ORDER BY updated_at DESC,t.id DESC LIMIT 5");
                $query->execute([$user['id']]);
                $data['records'] = $query->fetchAll();
                break;
            case 'admin':
                $counts = $pdo->query("SELECT (SELECT COUNT(*) FROM users) AS users,
                    (SELECT COUNT(*) FROM donations) AS donations,
                    (SELECT COUNT(*) FROM donation_requests) AS requests,
                    (SELECT COUNT(*) FROM donations WHERE status='completed') AS completed")->fetch();
                $data['statistics'] = [['Registered users', $counts['users'], 'Across all four roles'],
                    ['Total donations', $counts['donations'], 'All recorded food donations'],
                    ['Total requests', $counts['requests'], 'From community organizations'],
                    ['Completed donations', $counts['completed'], 'Receipt confirmed']];
                $data['pending_count'] = (int) $pdo->query("SELECT COUNT(*) FROM users
                    WHERE role IN ('organization','volunteer') AND status='pending'")->fetchColumn();
                $data['pending_accounts'] = $pdo->query("SELECT full_name,role,created_at FROM users
                    WHERE role IN ('organization','volunteer') AND status='pending'
                    ORDER BY created_at DESC,id DESC LIMIT 5")->fetchAll();
                $data['records'] = $pdo->query("SELECT d.food_name,u.full_name AS donor_name,
                    $foodStatus AS donation_status,d.updated_at FROM donations d JOIN users u ON u.id=d.donor_id
                    ORDER BY d.updated_at DESC,d.id DESC LIMIT 5")->fetchAll();
                break;
        }
    } catch (PDOException $exception) {
        error_log('Dashboard unavailable. Code: ' . $exception->getCode());
        // Do not present failed queries as zero statistics or an empty history.
        $data = ['statistics' => [], 'records' => [], 'pending_accounts' => [],
            'organization_name' => null, 'error' => true];
        http_response_code(503);
    }
    return $data;
}

function status_badge(string $status): string
{
    $labels = ['available'=>'Available','requested'=>'Requested','assigned'=>'Assigned',
        'accepted'=>'Accepted','collected'=>'Collected','delivered'=>'Delivered',
        'completed'=>'Completed','cancelled'=>'Cancelled','expired'=>'Expired',
        'pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','active'=>'Active','suspended'=>'Suspended'];
    $colors = ['available'=>'green','completed'=>'green','delivered'=>'green','approved'=>'green',
        'pending'=>'amber','requested'=>'amber','assigned'=>'blue','accepted'=>'blue',
        'collected'=>'blue','cancelled'=>'gray','expired'=>'gray','rejected'=>'red','active'=>'green','suspended'=>'red'];
    return '<span class="status-pill status-' . ($colors[$status] ?? 'gray') . '">'
        . e($labels[$status] ?? 'Unknown') . '</span>';
}

function dashboard_date(string $date): string
{
    return date('j M Y, g:i a', strtotime($date));
}
