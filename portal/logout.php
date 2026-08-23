<?php
require_once __DIR__ . '/auth.php';
// Only clear the portal's own session keys — this PHP session is shared with
// /admin/ (same cookie), so a full session_destroy() here would also log out
// anyone who happens to be signed into the core site admin at the same time.
portal_logout();
header('Location: login.php');
exit;
