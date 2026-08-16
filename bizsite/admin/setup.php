<?php
/*
 * Business Setup — creates a new business account.
 * Visit once to create: setup.php?biz=my-business&password=secret
 * This page is disabled if the business already has a password set.
 */
require_once 'auth.php';

$slug = clean_slug($_GET['biz'] ?? '');
$pw   = $_GET['password'] ?? '';
$msg  = '';

if (!$slug) {
    $msg = 'Usage: setup.php?biz=your-slug&password=yourpassword';
} elseif (file_exists(get_hash_file($slug))) {
    $msg = 'Business "' . htmlspecialchars($slug) . '" already exists. Use the admin to change the password.';
} elseif (strlen($pw) < 6) {
    $msg = 'Password must be at least 6 characters.';
} else {
    $dir = BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Create password file
    change_pw($slug, $pw);

    // Create blank info.csv if it doesn't exist
    if (!file_exists($dir . 'info.csv')) {
        write_info($slug, [
            'name'        => ucwords(str_replace('-', ' ', $slug)),
            'tagline'     => '',
            'description' => '',
            'category'    => 'other',
            'phone'       => '',
            'email'       => '',
            'website'     => '',
            'address'     => '',
            'hours'       => '',
            'accent_color'=> '#2D6A4F',
            'hero_image'  => '',
            'logo_image'  => '',
            'facebook'    => '',
            'instagram'   => '',
        ]);
    }

    // Create blank menu.csv if it doesn't exist
    $menu = $dir . 'menu.csv';
    if (!file_exists($menu)) {
        write_csv($menu, [], ['id','category','name','description','price','image']);
    }

    $msg = 'Business "' . htmlspecialchars($slug) . '" created. <a href="index.php">Log in now</a>.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Business Setup</title>
<style>
body{font-family:'Segoe UI',sans-serif;background:#1E2A35;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}
.card{background:#fff;color:#1E2A35;border-radius:12px;padding:2rem;max-width:480px;width:100%}
h1{font-size:1.3rem;margin-bottom:1rem}
p{line-height:1.7;font-size:.95rem}
a{color:#2D6A4F}
code{background:#EEF2F5;padding:.2rem .4rem;border-radius:4px;font-size:.88rem}
</style>
</head>
<body>
<div class="card">
  <h1>Business Setup</h1>
  <p><?= $msg ?></p>
  <?php if (!$msg || str_contains($msg, 'Usage')): ?>
  <p style="margin-top:1rem;color:#718096">
    Example: <code>setup.php?biz=spice-garden&password=mypassword</code><br><br>
    The business ID (slug) must be lowercase letters, numbers, and hyphens only.
  </p>
  <?php endif; ?>
</div>
</body>
</html>
