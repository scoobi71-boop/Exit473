<?php
require_once 'auth.php';
require_role(['site_owner']);

define('BIZ_DATA_ROOT', realpath(__DIR__ . '/../bizsite/data') . DIRECTORY_SEPARATOR);

/** Creates a blank info.csv/menu.csv for a newly-invited business, if it doesn't already exist. */
function biz_provision_slug($slug) {
    $dir = BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR;
    if (is_dir($dir)) return;
    mkdir($dir, 0755, true);

    $infoFile = $dir . 'info.csv';
    $fh = fopen($infoFile, 'w');
    fprintf($fh, "\xEF\xBB\xBF");
    $defaults = [
        'name' => ucwords(str_replace('-', ' ', $slug)), 'tagline' => '', 'description' => '',
        'category' => 'other', 'phone' => '', 'email' => '', 'website' => '', 'address' => '',
        'hours' => '', 'accent_color' => '#2D6A4F', 'hero_image' => '', 'logo_image' => '',
        'facebook' => '', 'instagram' => '',
    ];
    foreach ($defaults as $k => $v) fputcsv($fh, [$k, $v]);
    fclose($fh);

    $fh2 = fopen($dir . 'menu.csv', 'w');
    fprintf($fh2, "\xEF\xBB\xBF");
    fputcsv($fh2, ['id', 'category', 'name', 'description', 'price', 'image']);
    fclose($fh2);
}

$tab = $_GET['tab'] ?? 'admins';
if (!in_array($tab, ['admins', 'portal', 'biz'], true)) $tab = 'admins';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $email      = trim($_POST['email'] ?? '');
        $role       = $_POST['role'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'error:Enter a valid email address.';
        } elseif ($first_name === '' || $last_name === '') {
            $msg = 'error:Enter a first and last name.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $msg = 'error:Enter a valid phone number.';
        } elseif (!in_array($role, ['site_owner', 'content_manager', 'finance'], true)) {
            $msg = 'error:Choose a role.';
        } else {
            $existingInProfiles = supabase_table_select('admin_profiles', 'email=eq.' . urlencode($email) . '&select=id', null, true);
            if ($existingInProfiles['status'] === 200 && !empty($existingInProfiles['data'])) {
                $msg = 'error:That email is already an admin.';
            } else {
                $created = supabase_auth_admin_create_user($email, [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'phone'      => $phone,
                ]);
                $uid = null;
                if (in_array($created['status'], [200, 201], true) && !empty($created['data']['id'])) {
                    $uid = $created['data']['id'];
                } elseif (($created['data']['error_code'] ?? '') === 'email_exists') {
                    // Already a Supabase Auth user in this project (e.g. an
                    // event organizer via portal/) — reuse that identity.
                    $existingUser = supabase_auth_admin_get_user_by_email($email);
                    if ($existingUser) $uid = $existingUser['id'];
                }

                if ($uid) {
                    supabase_table_insert('admin_profiles', [
                        'id'         => $uid,
                        'email'      => $email,
                        'role'       => $role,
                        'first_name' => $first_name,
                        'last_name'  => $last_name,
                        'phone'      => $phone,
                    ], null, true);
                    supabase_auth_recover($email);
                    $msg = 'ok:Invited ' . $email . ' — they\'ll get an email with a code to set their password.';
                } else {
                    $msg = 'error:Could not create that account. Please try again.';
                }
            }
        }
    } elseif ($action === 'update') {
        $uid        = $_POST['id'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $role       = $_POST['role'] ?? '';
        if (!$uid) {
            $msg = 'error:Missing user.';
        } elseif ($first_name === '' || $last_name === '') {
            $msg = 'error:Enter a first and last name.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $msg = 'error:Enter a valid phone number.';
        } elseif (!in_array($role, ['site_owner', 'content_manager', 'finance'], true)) {
            $msg = 'error:Choose a role.';
        } else {
            supabase_table_update('admin_profiles', 'id=eq.' . urlencode($uid), [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'phone'      => $phone,
                'role'       => $role,
            ], null, true);
            $msg = 'ok:Updated ' . $first_name . ' ' . $last_name . '.';
        }
    } elseif ($action === 'remove') {
        $uid = $_POST['id'] ?? '';
        if ($uid === $_SESSION['admin_user_id']) {
            $msg = 'error:You can\'t remove your own account.';
        } elseif ($uid) {
            supabase_auth_admin_delete_user($uid);
            $msg = 'ok:User removed.';
        }
    } elseif ($action === 'remove_portal_user') {
        $uid = $_POST['id'] ?? '';
        if ($uid) {
            // Cascades: deleting the auth user removes its profiles row
            // (profiles.id FK) and, in turn, its events (events.organizer_id
            // FK) — see portal/schema_01_profiles.sql / schema_02_events.sql.
            supabase_auth_admin_delete_user($uid);
            $msg = 'ok:Portal user removed.';
        }
    } elseif ($action === 'update_portal_user') {
        $uid          = $_POST['id'] ?? '';
        $first_name   = trim($_POST['first_name'] ?? '');
        $last_name    = trim($_POST['last_name'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $display_name = trim($_POST['display_name'] ?? '');
        if (!$uid) {
            $msg = 'error:Missing user.';
        } elseif ($first_name === '' || $last_name === '') {
            $msg = 'error:Enter a first and last name.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $msg = 'error:Enter a valid phone number.';
        } elseif ($address === '') {
            $msg = 'error:Enter an address.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username)) {
            $msg = 'error:Username must be 3–32 characters (letters, numbers, "_", "-", ".").';
        } else {
            $taken = supabase_table_select('profiles', 'username=eq.' . urlencode($username) . '&id=neq.' . urlencode($uid) . '&select=id', null, true);
            if ($taken['status'] === 200 && !empty($taken['data'])) {
                $msg = 'error:That username is already taken.';
            } else {
                supabase_table_update('profiles', 'id=eq.' . urlencode($uid), [
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'phone'        => $phone,
                    'address'      => $address,
                    'username'     => $username,
                    'display_name' => $display_name !== '' ? $display_name : null,
                ], null, true);
                $msg = 'ok:Updated ' . $first_name . ' ' . $last_name . '.';
            }
        }
    } elseif ($action === 'invite_biz_user') {
        $email      = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $slug       = strtolower(trim($_POST['slug'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'error:Enter a valid email address.';
        } elseif ($first_name === '' || $last_name === '') {
            $msg = 'error:Enter a first and last name.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $msg = 'error:Enter a valid phone number.';
        } elseif (!preg_match('/^[a-z0-9-]{2,50}$/', $slug)) {
            $msg = 'error:Business ID must be lowercase letters, numbers, and hyphens only.';
        } else {
            $existingSlug = supabase_table_select('biz_profiles', 'slug=eq.' . urlencode($slug) . '&select=id', null, true);
            if ($existingSlug['status'] === 200 && !empty($existingSlug['data'])) {
                $msg = 'error:That business ID is already taken.';
            } else {
                $existingInProfiles = supabase_table_select('biz_profiles', 'email=eq.' . urlencode($email) . '&select=id', null, true);
                if ($existingInProfiles['status'] === 200 && !empty($existingInProfiles['data'])) {
                    $msg = 'error:That email already manages a business.';
                } else {
                    $created = supabase_auth_admin_create_user($email, [
                        'first_name' => $first_name,
                        'last_name'  => $last_name,
                        'phone'      => $phone,
                    ]);
                    $uid = null;
                    if (in_array($created['status'], [200, 201], true) && !empty($created['data']['id'])) {
                        $uid = $created['data']['id'];
                    } elseif (($created['data']['error_code'] ?? '') === 'email_exists') {
                        // Already a Supabase Auth user in this project (e.g. an
                        // admin or event organizer) — reuse that identity.
                        $existingUser = supabase_auth_admin_get_user_by_email($email);
                        if ($existingUser) $uid = $existingUser['id'];
                    }

                    if ($uid) {
                        supabase_table_insert('biz_profiles', [
                            'id'         => $uid,
                            'email'      => $email,
                            'slug'       => $slug,
                            'first_name' => $first_name,
                            'last_name'  => $last_name,
                            'phone'      => $phone,
                        ], null, true);
                        biz_provision_slug($slug);
                        supabase_table_insert('finance_businesses', [
                            'slug'         => $slug,
                            'display_name' => ucwords(str_replace('-', ' ', $slug)),
                        ], null, true);
                        supabase_auth_recover($email);
                        $msg = 'ok:Invited ' . $email . ' to manage "' . $slug . '" — they\'ll get an email with a code to set their password.';
                    } else {
                        $msg = 'error:Could not create that account. Please try again.';
                    }
                }
            }
        }
    } elseif ($action === 'update_biz_user') {
        $uid        = $_POST['id'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        if (!$uid) {
            $msg = 'error:Missing user.';
        } elseif ($first_name === '' || $last_name === '') {
            $msg = 'error:Enter a first and last name.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $msg = 'error:Enter a valid phone number.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'error:Enter a valid email address.';
        } else {
            $current = supabase_table_select('biz_profiles', 'id=eq.' . urlencode($uid) . '&select=email', null, true);
            $emailChanged = ($current['status'] === 200 && !empty($current['data'][0]) && $current['data'][0]['email'] !== $email);

            $taken = supabase_table_select('biz_profiles', 'email=eq.' . urlencode($email) . '&id=neq.' . urlencode($uid) . '&select=id', null, true);
            if ($emailChanged && $taken['status'] === 200 && !empty($taken['data'])) {
                $msg = 'error:That email is already used by another business.';
            } else {
                if ($emailChanged) {
                    $updated = supabase_auth_admin_update_user($uid, ['email' => $email, 'email_confirm' => true]);
                    if (!in_array($updated['status'], [200, 201], true)) {
                        $msg = 'error:Could not update the login email — ' . ($updated['data']['msg'] ?? $updated['data']['error_description'] ?? 'it may already be in use elsewhere.');
                    }
                }
                if ($msg === '') {
                    supabase_table_update('biz_profiles', 'id=eq.' . urlencode($uid), [
                        'first_name' => $first_name,
                        'last_name'  => $last_name,
                        'phone'      => $phone,
                        'email'      => $email,
                    ], null, true);
                    $msg = 'ok:Updated ' . $first_name . ' ' . $last_name . '.';
                }
            }
        }
    } elseif ($action === 'remove_biz_user') {
        $uid = $_POST['id'] ?? '';
        if ($uid) {
            // Cascades: deleting the auth user removes its biz_profiles row
            // (biz_profiles.id FK) — see bizsite/admin/schema_01_biz_profiles.sql.
            // The bizsite/data/<slug> directory (business content) is left
            // in place in case the business is reassigned to a new account.
            supabase_auth_admin_delete_user($uid);
            $msg = 'ok:Business user removed.';
        }
    }
}

$resp  = supabase_table_select('admin_profiles', 'select=*&order=created_at.asc', null, true);
$users = ($resp['status'] === 200) ? $resp['data'] : [];

$portalUsers = [];
if ($tab === 'portal') {
    $presp = supabase_table_select('profiles', 'select=*&order=created_at.asc', null, true);
    $portalUsers = ($presp['status'] === 200) ? $presp['data'] : [];
    foreach ($portalUsers as &$pu) {
        $authUser = supabase_auth_admin_get_user_by_id($pu['id']);
        $pu['email'] = $authUser['email'] ?? null;
    }
    unset($pu);
}

$bizUsers = [];
if ($tab === 'biz') {
    $bresp = supabase_table_select('biz_profiles', 'select=*&order=created_at.asc', null, true);
    $bizUsers = ($bresp['status'] === 200) ? $bresp['data'] : [];
}

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.table-wrap{overflow-x:auto}
.users-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:.88rem}
.users-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.users-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:top;word-break:break-word;overflow-wrap:anywhere}
.users-table tr:hover td{background:#FFF9F7}
.users-table .edit-row{display:none}
.users-table .edit-row.open{display:table-row}
.users-table .edit-row td{background:#FBF7F5}
.users-table .actions-cell{text-align:right}
.users-table .actions-cell form{display:inline}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Users</h1>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="max-width:640px"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <div style="display:flex;gap:.5rem;margin-bottom:1.5rem">
    <a href="?tab=admins" class="btn <?= $tab === 'admins' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Admins</a>
    <a href="?tab=portal" class="btn <?= $tab === 'portal' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Portal Users</a>
    <a href="?tab=biz" class="btn <?= $tab === 'biz' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Businesses</a>
  </div>

  <?php if ($tab === 'admins'): ?>
  <div class="panel">
    <h2 class="panel-title">Admins</h2>
    <?php if ($users): ?>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Role</th>
          <th>Joined</th>
          <th>Last Login</th>
          <th>Logins</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $i => $u): ?>
        <?php
          $lastLoginText = 'Never';
          if (!empty($u['last_login_at'])) {
              $lastLoginDt = new DateTime($u['last_login_at']);
              $lastLoginDt->setTimezone(new DateTimeZone('America/New_York'));
              $lastLoginText = $lastLoginDt->format('M j, Y g:ia') . ' ET';
          }
          $joinedText = '—';
          if (!empty($u['created_at'])) {
              $joinedDt = new DateTime($u['created_at']);
              $joinedDt->setTimezone(new DateTimeZone('America/New_York'));
              $joinedText = $joinedDt->format('M j, Y');
          }
          $loginCount = (int)($u['login_count'] ?? 0);
          $fullName   = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
          $isSelf     = $u['id'] === $_SESSION['admin_user_id'];
          $rowId      = 'admin-edit-' . $i;
        ?>
        <tr>
          <td><strong><?= $fullName !== '' ? h($fullName) : h($u['email']) ?></strong><?= $isSelf ? ' <span style="font-size:.78rem;color:#7A6565">(you)</span>' : '' ?></td>
          <td><?= h($u['email']) ?></td>
          <td><?= !empty($u['phone']) ? h($u['phone']) : '—' ?></td>
          <td><span style="text-transform:capitalize"><?= h(str_replace('_', ' ', $u['role'])) ?></span></td>
          <td><?= h($joinedText) ?></td>
          <td><?= h($lastLoginText) ?></td>
          <td><?= $loginCount ?></td>
          <td class="actions-cell">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Edit</button>
            <?php if (!$isSelf): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove ' + <?= json_encode($u['email']) ?> + '? This cannot be undone.');">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="id" value="<?= h($u['id']) ?>">
                <button class="btn btn-danger btn-sm" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="8">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= h($u['id']) ?>">
              <div class="form-grid">
                <div class="form-row">
                  <label>First Name</label>
                  <input type="text" name="first_name" required value="<?= h($u['first_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Last Name</label>
                  <input type="text" name="last_name" required value="<?= h($u['last_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Phone</label>
                  <input type="tel" name="phone" required value="<?= h($u['phone'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Role</label>
                  <?php if ($isSelf): ?>
                    <input type="text" value="<?= h(str_replace('_', ' ', $u['role'])) ?>" disabled style="text-transform:capitalize;background:#F7F3F3">
                    <input type="hidden" name="role" value="<?= h($u['role']) ?>">
                  <?php else: ?>
                    <select name="role" required>
                      <option value="content_manager" <?= $u['role'] === 'content_manager' ? 'selected' : '' ?>>Content Manager</option>
                      <option value="finance" <?= $u['role'] === 'finance' ? 'selected' : '' ?>>Finance</option>
                      <option value="site_owner" <?= $u['role'] === 'site_owner' ? 'selected' : '' ?>>Site Owner</option>
                    </select>
                  <?php endif; ?>
                </div>
              </div>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Save Changes</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#7A6565">No admins found.</p><?php endif; ?>
  </div>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Invite an Admin</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="invite">
      <div class="form-row">
        <label>First Name</label>
        <input type="text" name="first_name" required autocomplete="given-name">
      </div>
      <div class="form-row">
        <label>Last Name</label>
        <input type="text" name="last_name" required autocomplete="family-name">
      </div>
      <div class="form-row">
        <label>Phone</label>
        <input type="tel" name="phone" required autocomplete="tel" placeholder="(473) 555-0100">
      </div>
      <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" required autocomplete="email" placeholder="colleague@example.com">
      </div>
      <div class="form-row">
        <label>Role</label>
        <select name="role" required>
          <option value="content_manager">Content Manager</option>
          <option value="finance">Finance</option>
          <option value="site_owner">Site Owner</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Send Invite</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'portal'): ?>
  <div class="panel">
    <h2 class="panel-title">Portal Users (Organizers)</h2>
    <?php if ($portalUsers): ?>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Address</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($portalUsers as $i => $pu): ?>
        <?php
          $fullName    = trim(($pu['first_name'] ?? '') . ' ' . ($pu['last_name'] ?? ''));
          $displayName = $fullName !== '' ? $fullName : ($pu['display_name'] ?: $pu['username']);
          $joinedText  = '—';
          if (!empty($pu['created_at'])) {
              $joinedDt = new DateTime($pu['created_at']);
              $joinedDt->setTimezone(new DateTimeZone('America/New_York'));
              $joinedText = $joinedDt->format('M j, Y');
          }
          $rowId = 'portal-edit-' . $i;
        ?>
        <tr>
          <td><strong><?= h($displayName) ?></strong></td>
          <td>@<?= h($pu['username']) ?></td>
          <td><?= !empty($pu['email']) ? h($pu['email']) : '—' ?></td>
          <td><?= !empty($pu['phone']) ? h($pu['phone']) : '—' ?></td>
          <td><?= !empty($pu['address']) ? h($pu['address']) : '—' ?></td>
          <td><?= h($joinedText) ?></td>
          <td class="actions-cell">
            <a href="/portal/admin/events.php?organizer=<?= urlencode($pu['id']) ?>" class="btn btn-outline btn-sm">Events →</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Edit</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Remove ' + <?= json_encode($pu['username']) ?> + '? This deletes their account and any events they created. This cannot be undone.');">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="remove_portal_user">
              <input type="hidden" name="id" value="<?= h($pu['id']) ?>">
              <button class="btn btn-danger btn-sm" type="submit">Remove</button>
            </form>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="7">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_portal_user">
              <input type="hidden" name="id" value="<?= h($pu['id']) ?>">
              <div class="form-grid">
                <div class="form-row">
                  <label>First Name</label>
                  <input type="text" name="first_name" required value="<?= h($pu['first_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Last Name</label>
                  <input type="text" name="last_name" required value="<?= h($pu['last_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Phone</label>
                  <input type="tel" name="phone" required value="<?= h($pu['phone'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Address</label>
                  <input type="text" name="address" required value="<?= h($pu['address'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Username</label>
                  <input type="text" name="username" required value="<?= h($pu['username']) ?>">
                </div>
                <div class="form-row">
                  <label>Display Name</label>
                  <input type="text" name="display_name" value="<?= h($pu['display_name'] ?? '') ?>" placeholder="Shown publicly on event pages">
                </div>
              </div>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Save Changes</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#7A6565">No portal users found.</p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'biz'): ?>
  <div class="panel">
    <h2 class="panel-title">Businesses (bizsite)</h2>
    <?php if ($bizUsers): ?>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Business ID</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Joined</th>
          <th>Last Login</th>
          <th>Logins</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bizUsers as $i => $bu): ?>
        <?php
          $lastLoginText = 'Never';
          if (!empty($bu['last_login_at'])) {
              $lastLoginDt = new DateTime($bu['last_login_at']);
              $lastLoginDt->setTimezone(new DateTimeZone('America/New_York'));
              $lastLoginText = $lastLoginDt->format('M j, Y g:ia') . ' ET';
          }
          $joinedText = '—';
          if (!empty($bu['created_at'])) {
              $joinedDt = new DateTime($bu['created_at']);
              $joinedDt->setTimezone(new DateTimeZone('America/New_York'));
              $joinedText = $joinedDt->format('M j, Y');
          }
          $loginCount = (int)($bu['login_count'] ?? 0);
          $fullName   = trim(($bu['first_name'] ?? '') . ' ' . ($bu['last_name'] ?? ''));
          $rowId      = 'biz-edit-' . $i;
        ?>
        <tr>
          <td><strong><?= $fullName !== '' ? h($fullName) : h($bu['email']) ?></strong></td>
          <td><?= h($bu['slug']) ?></td>
          <td><?= h($bu['email']) ?></td>
          <td><?= !empty($bu['phone']) ? h($bu['phone']) : '—' ?></td>
          <td><?= h($joinedText) ?></td>
          <td><?= h($lastLoginText) ?></td>
          <td><?= $loginCount ?></td>
          <td class="actions-cell">
            <a href="/bizsite/index.php?biz=<?= urlencode($bu['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">View Site ↗</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Edit</button>
            <form method="post" style="display:inline" onsubmit="return confirm('Remove ' + <?= json_encode($bu['email']) ?> + '? This cannot be undone. Their business data will be kept.');">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="remove_biz_user">
              <input type="hidden" name="id" value="<?= h($bu['id']) ?>">
              <button class="btn btn-danger btn-sm" type="submit">Remove</button>
            </form>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="8">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_biz_user">
              <input type="hidden" name="id" value="<?= h($bu['id']) ?>">
              <div class="form-grid">
                <div class="form-row">
                  <label>First Name</label>
                  <input type="text" name="first_name" required value="<?= h($bu['first_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Last Name</label>
                  <input type="text" name="last_name" required value="<?= h($bu['last_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Phone</label>
                  <input type="tel" name="phone" required value="<?= h($bu['phone'] ?? '') ?>">
                </div>
                <div class="form-row">
                  <label>Email</label>
                  <input type="email" name="email" required value="<?= h($bu['email'] ?? '') ?>">
                  <span class="hint" style="font-size:.72rem;color:#7A6565">Also updates their login. Use this once the business's real email comes in.</span>
                </div>
                <div class="form-row">
                  <label>Business ID</label>
                  <input type="text" value="<?= h($bu['slug']) ?>" disabled style="background:#F7F3F3">
                  <span class="hint" style="font-size:.72rem;color:#7A6565">Can't be changed — it's part of the business's data folder and public link.</span>
                </div>
              </div>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Save Changes</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#7A6565">No businesses found.</p><?php endif; ?>
  </div>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Invite a Business</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="invite_biz_user">
      <div class="form-row">
        <label>First Name</label>
        <input type="text" name="first_name" required autocomplete="given-name">
      </div>
      <div class="form-row">
        <label>Last Name</label>
        <input type="text" name="last_name" required autocomplete="family-name">
      </div>
      <div class="form-row">
        <label>Phone</label>
        <input type="tel" name="phone" required autocomplete="tel" placeholder="(473) 555-0100">
      </div>
      <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" required autocomplete="email" placeholder="owner@example.com">
      </div>
      <div class="form-row">
        <label>Business ID</label>
        <input type="text" name="slug" required pattern="[a-z0-9-]{2,50}" placeholder="e.g. spice-garden">
        <span class="hint">Lowercase letters, numbers, and hyphens only. Becomes their data folder and public link (bizsite/index.php?biz=...).</span>
      </div>
      <button class="btn btn-primary" type="submit">Send Invite</button>
    </form>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
