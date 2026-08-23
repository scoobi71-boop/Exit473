<?php
require_once __DIR__ . '/auth.php';

/**
 * Supabase's email-confirmation link redirects here with the new session's
 * tokens in the URL *fragment* (#access_token=...&refresh_token=...), which
 * browsers never send to the server. The inline script below reads the
 * fragment client-side and POSTs it back to this same script to store in
 * the PHP session.
 *
 * No CSRF token is required on that POST: the payload is a real,
 * Supabase-signed token pair that only exists because it was in this page's
 * own URL — there's no ambient credential for a cross-site request to ride
 * on, and portal_store_session() below still re-validates the token against
 * Supabase's /auth/v1/user before trusting it.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    $accessToken  = $_POST['access_token']  ?? '';
    $refreshToken = $_POST['refresh_token'] ?? '';
    if (!$accessToken || !$refreshToken) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing tokens.']);
        exit;
    }

    $userResp = supabase_request('GET', '/auth/v1/user', ['access_token' => $accessToken]);
    if ($userResp['status'] === 200 && !empty($userResp['data']['id'])) {
        portal_store_session([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => $userResp['data'],
        ]);
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Could not verify session.']);
    }
    exit;
}

if (portal_access_token()) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirming Your Account — Exit 473 Event Portal</title>
<meta name="robots" content="noindex">
<?php include 'head_styles.php'; ?>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal</span></div>
    <div class="portal-nav-links"></div>
    <div class="portal-nav-right"><a href="../index.html">Exit 473 ↗</a></div>
  </nav>
  <div class="portal-wrap">
    <div class="portal-card" style="text-align:center">
      <h1>Confirming Your Account…</h1>
      <p class="portal-sub" id="verify-status">One moment.</p>
      <p class="portal-links"><a href="login.php">Go to login →</a></p>
    </div>
  </div>
  <script>
  (function () {
    var hash = window.location.hash.indexOf('#') === 0 ? window.location.hash.slice(1) : '';
    var params = new URLSearchParams(hash);
    var accessToken = params.get('access_token');
    var refreshToken = params.get('refresh_token');
    var errorDescription = params.get('error_description');
    var statusEl = document.getElementById('verify-status');

    if (errorDescription) {
      statusEl.textContent = decodeURIComponent(errorDescription.replace(/\+/g, ' '));
      return;
    }
    if (!accessToken || !refreshToken) {
      statusEl.textContent = 'This confirmation link is invalid or has expired. Try registering again, or log in if you already verified.';
      return;
    }

    var body = new URLSearchParams();
    body.set('ajax', '1');
    body.set('access_token', accessToken);
    body.set('refresh_token', refreshToken);

    fetch('verify.php', { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          window.location.replace('dashboard.php');
        } else {
          statusEl.textContent = 'Could not confirm your account. Please try logging in.';
        }
      })
      .catch(function () {
        statusEl.textContent = 'Something went wrong confirming your account.';
      });
  })();
  </script>
</body>
</html>
