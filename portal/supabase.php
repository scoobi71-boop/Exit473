<?php
/**
 * portal/supabase.php
 *
 * Thin REST helpers for Supabase (Auth + PostgREST), shared by every portal/*
 * script. Plain cURL — no Composer/SDK, matching the rest of this project.
 */

function portal_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = __DIR__ . '/config.php';
    if (!file_exists($file)) {
        http_response_code(500);
        die('Event portal is not configured. Copy portal/config.php.example to portal/config.php and fill in your keys.');
    }
    $cfg = include $file;
    return $cfg;
}

/**
 * Low-level request against the Supabase project (Auth or PostgREST).
 * $path is relative, e.g. '/auth/v1/signup' or '/rest/v1/events'.
 *
 * opts:
 *   body          - array, JSON-encoded as the request body
 *   access_token  - a user's Supabase access token (RLS-scoped request)
 *   service_role  - true to use the service_role key instead of anon (bypasses RLS)
 *   prefer        - value for the Prefer header (e.g. 'return=representation')
 */
function supabase_request($method, $path, $opts = []) {
    $cfg = portal_config();
    $url = rtrim($cfg['supabase_url'], '/') . $path;

    $useServiceRole = !empty($opts['service_role']);
    $apiKey = $useServiceRole ? $cfg['supabase_service_role_key'] : $cfg['supabase_anon_key'];

    $headers = [
        'apikey: ' . $apiKey,
        'Content-Type: application/json',
    ];
    if (!empty($opts['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $opts['access_token'];
    } else {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
    if (!empty($opts['prefer'])) {
        $headers[] = 'Prefer: ' . $opts['prefer'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if (array_key_exists('body', $opts)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['body']));
    }
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'data' => null, 'error' => $err];
    }
    $data = json_decode($raw, true);
    return ['status' => $status, 'data' => $data, 'raw' => $raw];
}

/* ---- Auth API (GoTrue) ---- */

function supabase_auth_signup($email, $password, $metadata = [], $redirectTo = null) {
    $body = ['email' => $email, 'password' => $password];
    if ($metadata) $body['data'] = $metadata;
    $path = '/auth/v1/signup';
    if ($redirectTo) $path .= '?redirect_to=' . urlencode($redirectTo);
    return supabase_request('POST', $path, ['body' => $body]);
}

/**
 * Absolute URL to a path within portal/, e.g. portal_url('verify.php').
 * The base path is hardcoded rather than derived from SCRIPT_NAME: every
 * caller lives directly under /portal/, so there's nothing to detect, and
 * deriving it dynamically is exactly what produced a broken redirect URL
 * (dirname() of PHP CLI's fake SCRIPT_NAME) during testing.
 */
function portal_url($path) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'exit473.com';
    return $scheme . '://' . $host . '/portal/' . ltrim($path, '/');
}

function supabase_auth_login($email, $password) {
    return supabase_request('POST', '/auth/v1/token?grant_type=password', [
        'body' => ['email' => $email, 'password' => $password],
    ]);
}

/**
 * Verifies the 6-digit OTP code Supabase includes in the confirmation email
 * (the {{ .Token }} template variable) and returns a session directly in the
 * response body — no link-click, no URL fragment parsing. Used instead of
 * the magic-link flow because Gmail (and other mail scanners) can prefetch
 * and burn a single-use confirmation link before the user ever clicks it.
 */
function supabase_auth_verify_otp($email, $token, $type = 'signup') {
    return supabase_request('POST', '/auth/v1/verify', [
        'body' => ['type' => $type, 'email' => $email, 'token' => $token],
    ]);
}

function supabase_auth_refresh($refreshToken) {
    return supabase_request('POST', '/auth/v1/token?grant_type=refresh_token', [
        'body' => ['refresh_token' => $refreshToken],
    ]);
}

function supabase_auth_resend($email) {
    return supabase_request('POST', '/auth/v1/resend', [
        'body' => ['type' => 'signup', 'email' => $email],
    ]);
}

/** Sends a "reset your password" email; the link lands the visitor on $redirectTo. */
function supabase_auth_recover($email, $redirectTo = null) {
    $path = '/auth/v1/recover';
    if ($redirectTo) $path .= '?redirect_to=' . urlencode($redirectTo);
    return supabase_request('POST', $path, ['body' => ['email' => $email]]);
}

/**
 * Admin API (service_role only) — creates a user directly, bypassing the
 * public signup flow. Used to invite admin/portal-admin accounts.
 */
function supabase_auth_admin_create_user($email, $metadata = [], $emailConfirm = true) {
    $body = ['email' => $email, 'email_confirm' => $emailConfirm, 'password' => bin2hex(random_bytes(16))];
    if ($metadata) $body['user_metadata'] = $metadata;
    return supabase_request('POST', '/auth/v1/admin/users', ['body' => $body, 'service_role' => true]);
}

function supabase_auth_admin_delete_user($id) {
    return supabase_request('DELETE', '/auth/v1/admin/users/' . urlencode($id), ['service_role' => true]);
}

/** Looks up an existing auth user by email (service_role only), or null. */
function supabase_auth_admin_get_user_by_email($email) {
    $resp = supabase_request('GET', '/auth/v1/admin/users?email=' . urlencode($email), ['service_role' => true]);
    if ($resp['status'] !== 200) return null;
    $users = $resp['data']['users'] ?? $resp['data'] ?? [];
    foreach ($users as $u) {
        if (isset($u['email']) && strcasecmp($u['email'], $email) === 0) return $u;
    }
    return null;
}

/** Looks up an existing auth user by id (service_role only), or null. */
function supabase_auth_admin_get_user_by_id($id) {
    $resp = supabase_request('GET', '/auth/v1/admin/users/' . urlencode($id), ['service_role' => true]);
    return $resp['status'] === 200 ? $resp['data'] : null;
}

/** Calls a Postgres function exposed via PostgREST (POST /rest/v1/rpc/<fn>). */
function supabase_rpc($fn, $args = [], $accessToken = null, $serviceRole = false) {
    return supabase_request('POST', '/rest/v1/rpc/' . $fn, [
        'body'         => $args,
        'access_token' => $accessToken,
        'service_role' => $serviceRole,
    ]);
}

/* ---- PostgREST (table) API ---- */

function supabase_table_select($table, $query = '', $accessToken = null, $serviceRole = false) {
    $path = '/rest/v1/' . $table . ($query ? ('?' . $query) : '');
    return supabase_request('GET', $path, [
        'access_token' => $accessToken,
        'service_role' => $serviceRole,
    ]);
}

function supabase_table_insert($table, $row, $accessToken = null, $serviceRole = false, $returnRepresentation = true) {
    return supabase_request('POST', '/rest/v1/' . $table, [
        'body'         => $row,
        'access_token' => $accessToken,
        'service_role' => $serviceRole,
        'prefer'       => $returnRepresentation ? 'return=representation' : 'return=minimal',
    ]);
}

function supabase_table_update($table, $query, $patch, $accessToken = null, $serviceRole = false) {
    return supabase_request('PATCH', '/rest/v1/' . $table . '?' . $query, [
        'body'         => $patch,
        'access_token' => $accessToken,
        'service_role' => $serviceRole,
        'prefer'       => 'return=representation',
    ]);
}

function supabase_table_delete($table, $query, $accessToken = null, $serviceRole = false) {
    return supabase_request('DELETE', '/rest/v1/' . $table . '?' . $query, [
        'access_token' => $accessToken,
        'service_role' => $serviceRole,
        'prefer'       => 'return=minimal',
    ]);
}

/** URL-safe slug from a title, e.g. "Spicemas Fete!" -> "spicemas-fete". */
function portal_slugify($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'event';
}
