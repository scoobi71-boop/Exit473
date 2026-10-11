<?php
/**
 * scripts/bootstrap-admin.php
 *
 * One-off CLI to create the very first admin/ account (role: site_owner),
 * since admin/users.php requires being logged in as a site_owner already.
 * Run once, from the repo root:
 *
 *   php scripts/bootstrap-admin.php you@example.com "First" "Last" "+1 473 555 0100"
 *
 * Uses the service_role key already in portal/config.php (same Supabase
 * project admin/ and portal/ both talk to). Creates the auth user, an
 * admin_profiles row with role=site_owner, then emails a reset code via the
 * same flow admin/forgot-password.php + admin/reset-password.php use.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script is CLI-only.\n");
}

require_once __DIR__ . '/../portal/supabase.php';

$usage = "Usage: php scripts/bootstrap-admin.php you@example.com \"First\" \"Last\" \"Phone\"\n";

$email      = trim($argv[1] ?? '');
$first_name = trim($argv[2] ?? '');
$last_name  = trim($argv[3] ?? '');
$phone      = trim($argv[4] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, $usage);
    exit(1);
}
if ($first_name === '' || $last_name === '') {
    fwrite(STDERR, "First and last name are required.\n" . $usage);
    exit(1);
}
if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    fwrite(STDERR, "A valid phone number is required.\n" . $usage);
    exit(1);
}

$existing = supabase_table_select('admin_profiles', 'email=eq.' . urlencode($email) . '&select=id', null, true);
if ($existing['status'] === 200 && !empty($existing['data'])) {
    fwrite(STDERR, "An admin_profiles row for {$email} already exists.\n");
    exit(1);
}

$created = supabase_auth_admin_create_user($email, [
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'phone'      => $phone,
]);
if (in_array($created['status'], [200, 201], true) && !empty($created['data']['id'])) {
    $uid = $created['data']['id'];
} elseif (($created['data']['error_code'] ?? '') === 'email_exists') {
    // Already a Supabase Auth user in this project (e.g. also an event
    // organizer via portal/) — reuse that identity, just add the profile row.
    $existingUser = supabase_auth_admin_get_user_by_email($email);
    if (!$existingUser) {
        fwrite(STDERR, "That email is already registered, but the admin API couldn't look up its user id.\n");
        exit(1);
    }
    $uid = $existingUser['id'];
    echo "Note: {$email} already had a Supabase account (e.g. from the event portal) — reusing it.\n";
} else {
    fwrite(STDERR, "Could not create the auth user: " . ($created['raw'] ?? 'unknown error') . "\n");
    exit(1);
}

$inserted = supabase_table_insert('admin_profiles', [
    'id'         => $uid,
    'email'      => $email,
    'role'       => 'site_owner',
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'phone'      => $phone,
], null, true);
if ($inserted['status'] < 200 || $inserted['status'] >= 300) {
    fwrite(STDERR, "Created the auth user but failed to insert admin_profiles: " . ($inserted['raw'] ?? 'unknown error') . "\n");
    exit(1);
}

supabase_auth_recover($email);

echo "Created site_owner account for {$email} and sent a reset code.\n";
echo "Go to https://exit473.com/admin/reset-password.php?email=" . urlencode($email) . " to set the password.\n";
