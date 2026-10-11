<?php
require_once 'auth.php';
require_auth();

$events     = read_csv(DATA_PATH . 'events.csv');
$businesses = read_csv(DATA_PATH . 'businesses.csv');
$spotlight  = read_csv(DATA_PATH . 'spotlight.csv');
$artists_raw = DATA_PATH . 'calypso-artists.json';
$artists     = file_exists($artists_raw) ? (json_decode(file_get_contents($artists_raw), true) ?: []) : [];
$tents_raw   = DATA_PATH . 'calypso-tents.json';
$tents       = file_exists($tents_raw) ? (json_decode(file_get_contents($tents_raw), true) ?: []) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Dashboard</h1>

  <div class="stat-grid">
    <a href="events.php" class="stat-card">
      <div class="stat-icon">📅</div>
      <div class="stat-num"><?= count($events) ?></div>
      <div class="stat-label">Events</div>
    </a>
    <a href="businesses.php" class="stat-card">
      <div class="stat-icon">🏪</div>
      <div class="stat-num"><?= count($businesses) ?></div>
      <div class="stat-label">Businesses</div>
    </a>
    <a href="spotlight.php" class="stat-card">
      <div class="stat-icon">✨</div>
      <div class="stat-num"><?= count($spotlight) ?></div>
      <div class="stat-label">Spotlight Items</div>
    </a>
    <a href="calypso-tents.php" class="stat-card">
      <div class="stat-icon">🎪</div>
      <div class="stat-num"><?= count($tents) ?></div>
      <div class="stat-label">Calypso Tents</div>
    </a>
    <a href="calypso-artists.php" class="stat-card">
      <div class="stat-icon">🎤</div>
      <div class="stat-num"><?= count($artists) ?></div>
      <div class="stat-label">Artists</div>
    </a>
    <a href="../index.html" target="_blank" class="stat-card" style="margin-left:auto">
      <div class="stat-icon">🌐</div>
      <div class="stat-num">↗</div>
      <div class="stat-label">View Site</div>
    </a>
  </div>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Account</h2>
    <p style="color:#7A6565;font-size:.9rem">Manage your password from <a href="account.php">My Account</a>.</p>
  </div>
</main>
</body>
</html>
