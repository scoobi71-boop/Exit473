<?php
require_once 'auth.php';
require_auth();

$CSV     = DATA_PATH . 'businesses.csv';
$JS_FILE = realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR . 'businesses.js';
$HEADS   = ['id','name','category','short_desc','description','phone','email','website','hours','address','featured','accent_color','tags','icon','sort_order','active'];
$CATS    = ['bar','barbershop','cafe','cultural','entertainment','local-craft','other','restaurant','retail','service','shop','snack-bar','tea-shop','wellness'];

/* Fill in sort_order + active for rows that pre-date those columns. Returns true if any change was made. */
function migrate_rows(array &$rows): bool {
    $changed = false;
    $counter = 10;
    foreach ($rows as &$r) {
        if (!isset($r['sort_order']) || $r['sort_order'] === '') {
            $r['sort_order'] = (string)$counter; $changed = true;
        }
        $counter = max($counter, (int)$r['sort_order']) + 10;
        if (!isset($r['active']) || $r['active'] === '') {
            $r['active'] = 'true'; $changed = true;
        }
    }
    unset($r);
    return $changed;
}

$msg = '';

/* ---- Handle POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $rows   = read_csv($CSV);
    migrate_rows($rows);

    if ($action === 'delete') {
        $del_id = (string)($_POST['del_id'] ?? '');
        $rows   = array_values(array_filter($rows, fn($r) => (string)$r['id'] !== $del_id));
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_CSV', $CSV);
        $msg = 'ok:Business deleted.';

    } elseif ($action === 'save') {
        $id     = trim($_POST['id'] ?? '');
        $is_new = ($id === '');
        $new_id = $is_new ? (string)next_id($rows) : $id;
        $tags   = implode('|', array_filter(array_map('trim', explode('|', trim($_POST['tags'] ?? '')))));
        $so     = trim($_POST['sort_order'] ?? '');
        if ($is_new || $so === '') {
            $max = array_reduce($rows, fn($c, $r) => max($c, (int)($r['sort_order'] ?? 0)), 0);
            $so  = (string)($max + 10);
        }
        $entry = [
            'id'          => $new_id,
            'name'        => trim($_POST['name']        ?? ''),
            'category'    => trim($_POST['category']    ?? 'other'),
            'short_desc'  => trim($_POST['short_desc']  ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'phone'       => trim($_POST['phone']       ?? ''),
            'email'       => trim($_POST['email']       ?? ''),
            'website'     => trim($_POST['website']     ?? '#') ?: '#',
            'hours'       => trim($_POST['hours']       ?? ''),
            'address'     => trim($_POST['address']     ?? ''),
            'featured'    => isset($_POST['featured'])       ? 'true' : 'false',
            'accent_color'=> trim($_POST['accent_color'] ?? '#CC0000'),
            'tags'        => $tags,
            'icon'        => trim($_POST['icon']        ?? ''),
            'sort_order'  => $so,
            'active'      => isset($_POST['active_status']) ? 'true' : 'false',
        ];
        if ($is_new) {
            $rows[] = $entry;
        } else {
            foreach ($rows as &$r) { if ((string)$r['id'] === $id) { $r = $entry; break; } }
            unset($r);
        }
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_CSV', $CSV);
        $msg = 'ok:Business ' . ($is_new ? 'added' : 'updated') . ' successfully.';

    } elseif ($action === 'toggle_active') {
        $tog_id = (string)($_POST['tog_id'] ?? '');
        foreach ($rows as &$r) {
            if ((string)$r['id'] === $tog_id) {
                $r['active'] = ($r['active'] === 'true') ? 'false' : 'true';
                break;
            }
        }
        unset($r);
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_CSV', $CSV);
        $msg = 'ok:Business status updated.';

    } elseif ($action === 'reorder') {
        $mov_id = (string)($_POST['mov_id'] ?? '');
        $dir    = $_POST['dir'] ?? '';
        usort($rows, fn($a, $b) => (int)($a['sort_order'] ?? 0) - (int)($b['sort_order'] ?? 0));
        $idx = null;
        foreach ($rows as $i => $r) { if ((string)$r['id'] === $mov_id) { $idx = $i; break; } }
        if ($idx !== null) {
            $si = $dir === 'up' ? $idx - 1 : $idx + 1;
            if ($si >= 0 && $si < count($rows)) {
                $tmp = $rows[$idx]['sort_order'];
                $rows[$idx]['sort_order'] = $rows[$si]['sort_order'];
                $rows[$si]['sort_order']  = $tmp;
            }
        }
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_CSV', $CSV);
        $msg = 'ok:Order updated.';
    }
}

/* ---- Load & display ---- */
$rows = read_csv($CSV);
if (migrate_rows($rows)) {
    write_csv($CSV, $rows, $HEADS);
    write_js_datafile($JS_FILE, 'EXIT473_CSV', $CSV);
}
usort($rows, fn($a, $b) => (int)($a['sort_order'] ?? 0) - (int)($b['sort_order'] ?? 0));

$edit_id = $_GET['edit'] ?? '';
$editing = null;
foreach ($rows as $r) { if ((string)$r['id'] === $edit_id) { $editing = $r; break; } }

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Businesses — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.biz-table{width:100%;border-collapse:collapse;font-size:.88rem}
.biz-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.biz-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:middle}
.biz-table tr:hover td{background:#FFF9F7}
.cat-badge{display:inline-block;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:#ECEFF1;color:#37474F}
.feat-yes{color:#1B5E20;font-weight:700}
.feat-no{color:#9E9E9E}
.color-swatch{display:inline-block;width:18px;height:18px;border-radius:4px;border:1px solid rgba(0,0,0,.15);vertical-align:middle;margin-right:.35rem}
.table-wrap{overflow-x:auto}
.tags-list{font-size:.75rem;color:#7A6565;line-height:1.6}
.status-active{display:inline-block;padding:.2rem .6rem;border-radius:99px;font-size:.72rem;font-weight:700;background:#E8F5E9;color:#1B5E20;border:1px solid #A5D6A7}
.status-inactive{display:inline-block;padding:.2rem .6rem;border-radius:99px;font-size:.72rem;font-weight:700;background:#FFEBEE;color:#B71C1C;border:1px solid #FFCDD2}
.order-btns{display:flex;flex-direction:column;gap:2px;align-items:center}
.btn-order{background:none;border:1px solid #D0C8C8;border-radius:4px;width:24px;height:22px;cursor:pointer;font-size:.75rem;line-height:1;color:#5D4037;padding:0;transition:background .15s}
.btn-order:hover{background:#F5C840;border-color:#F5C840;color:#1A0505}
.btn-order:disabled{opacity:.25;cursor:default}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">Businesses</h1>
    <a href="?new=1" class="btn btn-primary">+ Add New Business</a>
  </div>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:1.5rem"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <?php if ($editing || isset($_GET['new'])): ?>
  <!-- EDIT / ADD FORM -->
  <div class="panel" id="biz-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Business' : 'Add New Business' ?></h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">
      <input type="hidden" name="sort_order" value="<?= h($editing['sort_order'] ?? '') ?>">

      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Business Name *</label>
          <input type="text" name="name" required value="<?= h($editing['name'] ?? '') ?>" placeholder="e.g. Spice Garden Restaurant">
        </div>
        <div class="form-row">
          <label>Category *</label>
          <select name="category" required>
            <?php foreach ($CATS as $c): ?>
              <option value="<?= $c ?>" <?= ($editing['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucwords(str_replace('-', ' ', $c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Icon (emoji)</label>
          <input type="text" name="icon" value="<?= h($editing['icon'] ?? '') ?>" placeholder="e.g. 🌿">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Short Description *</label>
          <input type="text" name="short_desc" required value="<?= h($editing['short_desc'] ?? '') ?>" placeholder="One-line summary shown in cards">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Full Description *</label>
          <textarea name="description" required rows="5" placeholder="Full description shown on business detail page..."><?= h($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= h($editing['phone'] ?? '') ?>" placeholder="+1-473-403-7377">
        </div>
        <div class="form-row">
          <label>Email</label>
          <input type="email" name="email" value="<?= h($editing['email'] ?? '') ?>" placeholder="info@example.com">
        </div>
        <div class="form-row">
          <label>Website</label>
          <input type="text" name="website" value="<?= h($editing['website'] ?? '#') ?>" placeholder="https://... or leave blank">
        </div>
        <div class="form-row">
          <label>Hours</label>
          <input type="text" name="hours" value="<?= h($editing['hours'] ?? '') ?>" placeholder="e.g. Mon–Fri 9am–6pm">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Address</label>
          <input type="text" name="address" value="<?= h($editing['address'] ?? '') ?>" placeholder="e.g. Unit 1, Exit 473, St. George's">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Tags <small style="font-weight:400;text-transform:none;color:#7A6565">(separate with | pipe character)</small></label>
          <input type="text" name="tags" value="<?= h($editing['tags'] ?? '') ?>" placeholder="e.g. Caribbean|Local Cuisine|Dine-in">
        </div>
        <div class="form-row">
          <label>Accent Colour</label>
          <div style="display:flex;align-items:center;gap:.6rem">
            <input type="color" name="accent_color" value="<?= h($editing['accent_color'] ?? '#CC0000') ?>" style="width:48px;height:36px;padding:2px;border:1.5px solid #EAE2E2;border-radius:6px;cursor:pointer">
            <input type="text" id="accent_hex" value="<?= h($editing['accent_color'] ?? '#CC0000') ?>" placeholder="#CC0000" style="width:110px" oninput="document.querySelector('[name=accent_color]').value=this.value">
          </div>
        </div>
        <div class="form-row" style="display:flex;gap:2rem;align-items:center;margin-top:1.4rem">
          <label class="checkbox-label" style="margin:0">
            <input type="checkbox" name="featured" value="1" <?= ($editing['featured'] ?? '') === 'true' ? 'checked' : '' ?>>
            Featured (shown on home page)
          </label>
          <label class="checkbox-label" style="margin:0">
            <input type="checkbox" name="active_status" value="1" <?= ($editing === null || ($editing['active'] ?? 'true') === 'true') ? 'checked' : '' ?>>
            Active (visible on site)
          </label>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">💾 Save Business</button>
        <a href="businesses.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
  <script>
  document.querySelector('[name=accent_color]').addEventListener('input', function() {
    document.getElementById('accent_hex').value = this.value;
  });
  </script>
  <?php endif; ?>

  <!-- BUSINESSES TABLE -->
  <div class="panel">
    <h2 class="panel-title"><?= count($rows) ?> Business<?= count($rows) !== 1 ? 'es' : '' ?>
      <small style="font-weight:400;font-size:.78rem;color:#7A6565;margin-left:.75rem">Drag rows using ↑↓ to change the display order on the site</small>
    </h2>
    <?php if (empty($rows)): ?>
      <p style="color:#7A6565;text-align:center;padding:2rem">No businesses yet. Add your first business above.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="biz-table">
      <thead>
        <tr>
          <th>Business</th>
          <th>Category</th>
          <th>Contact</th>
          <th>Featured</th>
          <th>Active</th>
          <th style="text-align:center">Order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $i => $r):
        $is_first = $i === 0;
        $is_last  = $i === count($rows) - 1;
        $is_active = ($r['active'] ?? 'true') === 'true';
      ?>
        <tr<?= $is_active ? '' : ' style="opacity:.6"' ?>>
          <td>
            <strong><?= $r['icon'] ? h($r['icon']) . ' ' : '' ?><?= h($r['name']) ?></strong><br>
            <small style="color:#7A6565"><?= h($r['short_desc']) ?></small>
            <?php if ($r['tags']): ?>
              <div class="tags-list"><?= h(str_replace('|', ' · ', $r['tags'])) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="cat-badge"><?= h($r['category']) ?></span></td>
          <td style="font-size:.82rem">
            <?php if ($r['phone']): ?><div><?= h($r['phone']) ?></div><?php endif; ?>
            <?php if ($r['email']): ?><div><?= h($r['email']) ?></div><?php endif; ?>
            <?php if ($r['hours']): ?><div style="color:#7A6565"><?= h($r['hours']) ?></div><?php endif; ?>
          </td>
          <td>
            <span class="<?= $r['featured'] === 'true' ? 'feat-yes' : 'feat-no' ?>">
              <?= $r['featured'] === 'true' ? '★ Yes' : 'No' ?>
            </span>
          </td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="tog_id" value="<?= h($r['id']) ?>">
              <button type="submit" class="<?= $is_active ? 'status-active' : 'status-inactive' ?>" style="cursor:pointer;font-family:inherit;border:none;background:none;padding:0">
                <?= $is_active ? '● Active' : '○ Inactive' ?>
              </button>
            </form>
          </td>
          <td style="text-align:center;white-space:nowrap">
            <div class="order-btns">
              <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="mov_id" value="<?= h($r['id']) ?>">
                <input type="hidden" name="dir" value="up">
                <button class="btn-order" type="submit" title="Move up" <?= $is_first ? 'disabled' : '' ?>>▲</button>
              </form>
              <span style="font-size:.7rem;color:#9E9E9E"><?= (int)($r['sort_order'] ?? 0) ?></span>
              <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="reorder">
                <input type="hidden" name="mov_id" value="<?= h($r['id']) ?>">
                <input type="hidden" name="dir" value="down">
                <button class="btn-order" type="submit" title="Move down" <?= $is_last ? 'disabled' : '' ?>>▼</button>
              </form>
            </div>
          </td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= h($r['id']) ?>#biz-form" class="btn btn-sm btn-outline">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= h(addslashes($r['name'])) ?>?')">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="del_id" value="<?= h($r['id']) ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
