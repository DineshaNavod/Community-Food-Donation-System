<?php
declare(strict_types=1);
require_once __DIR__ . '/donation_form.php';
require_once __DIR__ . '/dashboard.php';

function donor_donation_id(): int
{
    $value = $_GET['id'] ?? null;
    $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
    if ($id === false || $id === null) { http_response_code(404); exit('Donation not found.'); }
    return $id;
}

function owned_donation(int $id, int $donorId, bool $lock = false): ?array
{
    $query = db()->prepare('SELECT * FROM donations WHERE id=? AND donor_id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id, $donorId]);
    $donation = $query->fetch();
    if (!$donation) { return null; }
    $query = db()->prepare('SELECT status FROM donation_requests WHERE donation_id=?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$id]);
    $requests = $query->fetchAll();
    $donation['request_count'] = count($requests);
    $donation['approved_count'] = count(array_filter($requests, static fn(array $row): bool => $row['status'] === 'approved'));
    return $donation;
}

function donation_display_status(array $donation): string
{
    return in_array($donation['status'], ['available','requested','assigned'], true)
        && strtotime($donation['deadline_at']) <= time() ? 'expired' : $donation['status'];
}

function can_edit_donation(array $donation): bool
{
    return donation_display_status($donation) === 'available' && (int) $donation['request_count'] === 0;
}

function can_cancel_donation(array $donation): bool
{
    return in_array(donation_display_status($donation), ['available','requested'], true)
        && (int) $donation['approved_count'] === 0;
}

function donation_version(array $donation): string
{
    // Include content as well as timestamps: two edits may happen in one second.
    return hash('sha256', json_encode($donation, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function lock_active_donor(int $id): void
{
    $query = db()->prepare('SELECT role,status FROM users WHERE id=? FOR UPDATE');
    $query->execute([$id]);
    $account = $query->fetch();
    if (!$account || $account['role'] !== 'donor' || $account['status'] !== 'active') {
        throw new DomainException('Your account is no longer allowed to change donations.');
    }
}

function donation_load_error(): never
{
    http_response_code(503);
    exit('Donation information is temporarily unavailable. Please try again later.');
}
