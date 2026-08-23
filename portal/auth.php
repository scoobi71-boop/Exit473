<?php
/**
 * portal/auth.php
 *
 * Session + auth helpers for the public event portal. Deliberately
 * independent from admin/auth.php — separate session keys, no shared state.
 * This is a distinct login system from the core site admin; the site owner
 * simply holds credentials for both.
 */

require_once __DIR__ . '/supabase.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function portal_csrf_token() {
    if (empty($_SESSION['portal_csrf'])) $_SESSION['portal_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['portal_csrf'];
}
function portal_check_csrf() {
    if (empty($_POST['_csrf']) || !hash_equals($_SESSION['portal_csrf'] ?? '', $_POST['_csrf'])) {
        http_response_code(403); die('Invalid request.');
    }
}

/** Store a Supabase auth session (access/refresh tokens + user) after login/signup/verify. */
function portal_store_session($authData) {
    $_SESSION['portal_access_token']  = $authData['access_token']  ?? null;
    $_SESSION['portal_refresh_token'] = $authData['refresh_token'] ?? null;
    $_SESSION['portal_user_id']       = $authData['user']['id']    ?? null;
    $_SESSION['portal_user_email']    = $authData['user']['email'] ?? null;
}

function portal_logout() {
    unset(
        $_SESSION['portal_access_token'],
        $_SESSION['portal_refresh_token'],
        $_SESSION['portal_user_id'],
        $_SESSION['portal_user_email']
    );
}

function portal_base64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Reads the `exp` claim out of a JWT without needing a signing-key/library. */
function portal_jwt_expiry($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return 0;
    $payload = json_decode(portal_base64url_decode($parts[1]), true);
    return (int)($payload['exp'] ?? 0);
}

/**
 * Returns a valid access token for the current session, transparently
 * refreshing it first if it's expired (or about to). Returns null if the
 * visitor isn't logged in or the refresh token is no longer valid.
 */
function portal_access_token() {
    if (empty($_SESSION['portal_access_token'])) return null;

    if (portal_jwt_expiry($_SESSION['portal_access_token']) > time() + 30) {
        return $_SESSION['portal_access_token'];
    }

    if (!empty($_SESSION['portal_refresh_token'])) {
        $refreshed = supabase_auth_refresh($_SESSION['portal_refresh_token']);
        if ($refreshed['status'] === 200 && !empty($refreshed['data']['access_token'])) {
            portal_store_session($refreshed['data']);
            return $_SESSION['portal_access_token'];
        }
    }

    portal_logout();
    return null;
}

/** The logged-in user's own profiles row, or null. */
function portal_current_profile() {
    $token = portal_access_token();
    if (!$token || empty($_SESSION['portal_user_id'])) return null;
    $resp = supabase_table_select(
        'profiles',
        'id=eq.' . urlencode($_SESSION['portal_user_id']) . '&select=*',
        $token
    );
    if ($resp['status'] === 200 && !empty($resp['data'][0])) return $resp['data'][0];
    return null;
}

function portal_require_auth() {
    if (!portal_access_token()) {
        // Root-relative on purpose: portal/dashboard.php isn't the only
        // caller — a relative "login.php" redirect would resolve wrong for
        // scripts at other depths.
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '/portal/dashboard.php');
        header('Location: /portal/login.php?next=' . $next);
        exit;
    }
}

/*
 * Portal-admin access (approving events, viewing orders, etc.) is
 * deliberately gated by the *same login as the core site admin*
 * (admin/.adminpass / $_SESSION['admin_ok']), not a separate Supabase
 * account — see portal/admin/index.php. This system's own auth (above) is
 * only for organizers registering to create/sell tickets for events.
 */
