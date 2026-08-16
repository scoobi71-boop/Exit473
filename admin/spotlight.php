<?php
require_once 'auth.php';
require_auth();

$CSV       = DATA_PATH . 'spotlight.csv';
$JS_FILE   = realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR . 'spotlight.js';
$SITE_ROOT = realpath(__DIR__ . '/..');
$HEADS     = ['id','type','title','subtitle','description','image','link_url','link_label','business_id','event_id','badge_label','start_date','end_date','active','sort_order'];
$TYPES     = ['sponsor' => 'Paid Sponsor', 'business' => 'Business Spotlight', 'event' => 'Upcoming Event'];

$businesses = read_csv(DATA_PATH . 'businesses.csv');
$events     = read_csv(DATA_PATH . 'events.csv');

function spot_img_dir($site_root) {
    return $site_root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'spotlight' . DIRECTORY_SEPARATOR;
}

function save_spotlight_image() {
    $file = $_FILES['image_upload'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed) || $file['size'] > 10 * 1024 * 1024) return null;
    global $SITE_ROOT;
    $dir = spot_img_dir($SITE_ROOT);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = 'spot_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return null;
    return 'images/spotlight/' . $name;
}

$msg = '';

/* ---- Handle POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $rows   = read_csv($CSV);

    if ($action === 'delete') {
        $del_id = (string)($_POST['del_id'] ?? '');
        $rows   = array_values(array_filter($rows, fn($r) => (string)$r['id'] !== $del_id));
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_SPOTLIGHT_CSV', $CSV);
        $msg = 'ok:Spotlight item deleted.';

    } elseif ($action === 'save') {
        $id     = trim($_POST['id'] ?? '');
        $is_new = ($id === '');
        $new_id = $is_new ? (string)next_id($rows) : $id;

        $existing_image = '';
        if (!$is_new) {
            foreach ($rows as $r) { if ((string)$r['id'] === $id) { $existing_image = $r['image'] ?? ''; break; } }
        }
        $uploaded   = save_spotlight_image();
        $manual_img = trim($_POST['image_url'] ?? '');
        $image      = $uploaded ?: ($manual_img !== '' ? $manual_img : $existing_image);

        $so = trim($_POST['sort_order'] ?? '');
        if ($is_new || $so === '') {
            $max = array_reduce($rows, fn($c, $r) => max($c, (int)($r['sort_order'] ?? 0)), 0);
            $so  = (string)($max + 10);
        }

        $entry = [
            'id'          => $new_id,
            'type'        => trim($_POST['type'] ?? 'sponsor'),
            'title'       => trim($_POST['title'] ?? ''),
            'subtitle'    => trim($_POST['subtitle'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'image'       => $image,
            'link_url'    => trim($_POST['link_url'] ?? '#') ?: '#',
            'link_label'  => trim($_POST['link_label'] ?? ''),
            'business_id' => trim($_POST['business_id'] ?? ''),
            'event_id'    => trim($_POST['event_id'] ?? ''),
            'badge_label' => trim($_POST['badge_label'] ?? ''),
            'start_date'  => trim($_POST['start_date'] ?? ''),
            'end_date'    => trim($_POST['end_date'] ?? ''),
            'active'      => isset($_POST['active_status']) ? 'true' : 'false',
            'sort_order'  => $so,
        ];
        if ($is_new) {
            $rows[] = $entry;
        } else {
            foreach ($rows as &$r) { if ((string)$r['id'] === $id) { $r = $entry; break; } }
            unset($r);
        }
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_SPOTLIGHT_CSV', $CSV);
        $msg = 'ok:Spotlight item ' . ($is_new ? 'added' : 'updated') . ' successfully.';

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
        write_js_datafile($JS_FILE, 'EXIT473_SPOTLIGHT_CSV', $CSV);
        $msg = 'ok:Status updated.';

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
        write_js_datafile($JS_FILE, 'EXIT473_SPOTLIGHT_CSV', $CSV);
        $msg = 'ok:Order updated.';
    }
}

/* ---- Load & display ---- */
$rows = read_csv($CSV);
usort($rows, fn($a, $b) => (int)($a['sort_order'] ?? 0) - (int)($b['sort_order'] ?? 0));

$edit_id = $_GET['edit'] ?? '';
$editing = null;
foreach ($rows as $r) { if ((string)$r['id'] === $edit_id) { $editing = $r; break; } }

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

// Data for the "link to existing record" autofill dropdowns
$biz_options = array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name'], 'short_desc' => $b['short_desc'] ?? ''], $businesses);
$event_options = array_map(fn($e) => ['id' => $e['id'], 'title' => $e['title'], 'description' => $e['description'] ?? '', 'date' => $e['date'] ?? ''], $events);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Spotlight — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.spot-table{width:100%;border-collapse:collapse;font-size:.88rem}
.spot-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.spot-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:middle}
.spot-table tr:hover td{background:#FFF9F7}
.type-badge{display:inline-block;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.type-sponsor{background:#FDF6D8;color:#B8860B}
.type-business{background:#FFF0F0;color:#990000}
.type-event{background:#E0EEFF;color:#1565C0}
.table-wrap{overflow-x:auto}
.status-active{display:inline-block;padding:.2rem .6rem;border-radius:99px;font-size:.72rem;font-weight:700;background:#E8F5E9;color:#1B5E20;border:1px solid #A5D6A7}
.status-inactive{display:inline-block;padding:.2rem .6rem;border-radius:99px;font-size:.72rem;font-weight:700;background:#FFEBEE;color:#B71C1C;border:1px solid #FFCDD2}
.order-btns{display:flex;flex-direction:column;gap:2px;align-items:center}
.btn-order{background:none;border:1px solid #D0C8C8;border-radius:4px;width:24px;height:22px;cursor:pointer;font-size:.75rem;line-height:1;color:#5D4037;padding:0;transition:background .15s}
.btn-order:hover{background:#F5C840;border-color:#F5C840;color:#1A0505}
.btn-order:disabled{opacity:.25;cursor:default}
.link-row{display:none}
.link-row.show{display:flex}
.img-preview{max-width:160px;border-radius:8px;margin-top:.5rem;display:block}
.hint{font-size:.78rem;color:#7A6565;margin-top:.3rem;display:block}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">Spotlight</h1>
    <a href="?new=1" class="btn btn-primary">+ Add Spotlight Item</a>
  </div>
  <p style="color:#7A6565;margin-top:-1rem;margin-bottom:1.5rem;max-width:640px">
    Items shown here rotate as a carousel on the homepage and directory page. Use it for paid sponsor placements,
    a rotating spotlight on one of your businesses, or a promo tied to an upcoming event.
  </p>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:1.5rem"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <?php if ($editing || isset($_GET['new'])): ?>
  <!-- EDIT / ADD FORM -->
  <div class="panel" id="spot-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Spotlight Item' : 'Add Spotlight Item' ?></h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">
      <input type="hidden" name="sort_order" value="<?= h($editing['sort_order'] ?? '') ?>">

      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Type *</label>
          <select name="type" id="type-select" required>
            <?php foreach ($TYPES as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($editing['type'] ?? 'sponsor') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row link-row" data-for="business" style="grid-column:1/-1">
          <label>Link to Existing Business <small style="font-weight:400;text-transform:none;color:#7A6565">(optional — autofills the fields below)</small></label>
          <select id="business-picker">
            <option value="">— Select a business —</option>
            <?php foreach ($biz_options as $b): ?>
              <option value="<?= h($b['id']) ?>" <?= ($editing['business_id'] ?? '') === $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row link-row" data-for="event" style="grid-column:1/-1">
          <label>Link to Existing Event <small style="font-weight:400;text-transform:none;color:#7A6565">(optional — autofills the fields below)</small></label>
          <select id="event-picker">
            <option value="">— Select an event —</option>
            <?php foreach ($event_options as $e): ?>
              <option value="<?= h($e['id']) ?>" <?= ($editing['event_id'] ?? '') === $e['id'] ? 'selected' : '' ?>><?= h($e['title']) ?> (<?= h($e['date']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="business_id" id="business_id" value="<?= h($editing['business_id'] ?? '') ?>">
        <input type="hidden" name="event_id" id="event_id" value="<?= h($editing['event_id'] ?? '') ?>">

        <div class="form-row" style="grid-column:1/-1">
          <label>Title *</label>
          <input type="text" name="title" id="title" required value="<?= h($editing['title'] ?? '') ?>" placeholder="e.g. La Galerie or Island Nights">
        </div>
        <div class="form-row">
          <label>Subtitle</label>
          <input type="text" name="subtitle" id="subtitle" value="<?= h($editing['subtitle'] ?? '') ?>" placeholder="e.g. Business Spotlight">
        </div>
        <div class="form-row">
          <label>Badge Label</label>
          <input type="text" name="badge_label" id="badge_label" value="<?= h($editing['badge_label'] ?? '') ?>" placeholder="e.g. Sponsored / Spotlight / Upcoming Event">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Description *</label>
          <textarea name="description" id="description" required rows="3" placeholder="Short blurb shown on the carousel slide..."><?= h($editing['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
          <label>Link URL</label>
          <input type="text" name="link_url" id="link_url" value="<?= h($editing['link_url'] ?? '#') ?>" placeholder="business.html?id=16 or https://...">
        </div>
        <div class="form-row">
          <label>Link Button Text</label>
          <input type="text" name="link_label" id="link_label" value="<?= h($editing['link_label'] ?? '') ?>" placeholder="e.g. Visit Website →">
        </div>

        <div class="form-row" style="grid-column:1/-1">
          <label>Image</label>
          <input type="file" name="image_upload" accept="image/jpeg,image/png,image/gif,image/webp">
          <span class="hint">Upload a photo/banner, or leave blank and paste a path/URL below. JPEG, PNG, GIF, WEBP — max 10 MB.</span>
          <input type="text" name="image_url" value="<?= h($editing['image'] ?? '') ?>" placeholder="images/spotlight/example.jpg (leave blank to keep current image)" style="margin-top:.5rem">
          <?php if (!empty($editing['image'])): ?>
            <img src="../<?= h($editing['image']) ?>" class="img-preview" alt="Current image">
          <?php endif; ?>
        </div>

        <div class="form-row">
          <label>Start Date <small style="font-weight:400;text-transform:none;color:#7A6565">(optional)</small></label>
          <input type="date" name="start_date" value="<?= h($editing['start_date'] ?? '') ?>">
          <span class="hint">Hidden from the site before this date.</span>
        </div>
        <div class="form-row">
          <label>End Date <small style="font-weight:400;text-transform:none;color:#7A6565">(optional)</small></label>
          <input type="date" name="end_date" id="end_date" value="<?= h($editing['end_date'] ?? '') ?>">
          <span class="hint">Automatically hidden after this date — handy for sponsor slots or past events.</span>
        </div>

        <div class="form-row" style="margin-top:.4rem">
          <label class="checkbox-label" style="margin:0">
            <input type="checkbox" name="active_status" value="1" <?= ($editing === null || ($editing['active'] ?? 'true') === 'true') ? 'checked' : '' ?>>
            Active (eligible to appear in the carousel)
          </label>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">💾 Save Spotlight Item</button>
        <a href="spotlight.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
  <script>
  (function() {
    const businesses = <?= json_encode($biz_options) ?>;
    const events      = <?= json_encode($event_options) ?>;
    const typeSelect  = document.getElementById('type-select');
    const linkRows    = document.querySelectorAll('.link-row');
    const bizPicker   = document.getElementById('business-picker');
    const evPicker    = document.getElementById('event-picker');

    const DEFAULT_BADGE = { sponsor: 'Sponsored', business: 'Spotlight', event: 'Upcoming Event' };
    const DEFAULT_SUB   = { sponsor: 'Sponsored', business: 'Business Spotlight', event: 'Upcoming Event' };

    function syncTypeUI() {
      const t = typeSelect.value;
      linkRows.forEach(row => row.classList.toggle('show', row.dataset.for === t));
    }
    typeSelect.addEventListener('change', () => {
      syncTypeUI();
      const t = typeSelect.value;
      if (!document.getElementById('badge_label').value) document.getElementById('badge_label').value = DEFAULT_BADGE[t] || '';
      if (!document.getElementById('subtitle').value) document.getElementById('subtitle').value = DEFAULT_SUB[t] || '';
    });
    syncTypeUI();

    bizPicker.addEventListener('change', () => {
      const biz = businesses.find(b => String(b.id) === bizPicker.value);
      document.getElementById('business_id').value = bizPicker.value;
      if (!biz) return;
      document.getElementById('title').value = biz.name;
      document.getElementById('description').value = biz.short_desc || '';
      document.getElementById('link_url').value = 'business.html?id=' + biz.id;
      document.getElementById('link_label').value = 'View ' + biz.name + ' →';
      document.getElementById('subtitle').value = DEFAULT_SUB.business;
      document.getElementById('badge_label').value = DEFAULT_BADGE.business;
    });

    evPicker.addEventListener('change', () => {
      const ev = events.find(e => String(e.id) === evPicker.value);
      document.getElementById('event_id').value = evPicker.value;
      if (!ev) return;
      document.getElementById('title').value = ev.title;
      document.getElementById('description').value = ev.description || '';
      document.getElementById('link_url').value = 'events.html';
      document.getElementById('link_label').value = 'See Event Details →';
      document.getElementById('subtitle').value = DEFAULT_SUB.event;
      document.getElementById('badge_label').value = DEFAULT_BADGE.event;
      if (ev.date) document.getElementById('end_date').value = ev.date;
    });
  })();
  </script>
  <?php endif; ?>

  <!-- SPOTLIGHT TABLE -->
  <div class="panel">
    <h2 class="panel-title"><?= count($rows) ?> Spotlight Item<?= count($rows) !== 1 ? 's' : '' ?>
      <small style="font-weight:400;font-size:.78rem;color:#7A6565;margin-left:.75rem">Use ↑↓ to change rotation order on the site</small>
    </h2>
    <?php if (empty($rows)): ?>
      <p style="color:#7A6565;text-align:center;padding:2rem">No spotlight items yet. Add your first one above.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="spot-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Type</th>
          <th>Schedule</th>
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
            <strong><?= h($r['title']) ?></strong><br>
            <small style="color:#7A6565"><?= h($r['subtitle'] ?: $r['description']) ?></small>
          </td>
          <td><span class="type-badge type-<?= h($r['type']) ?>"><?= h($TYPES[$r['type']] ?? $r['type']) ?></span></td>
          <td style="font-size:.8rem;color:#7A6565">
            <?php if ($r['start_date'] || $r['end_date']): ?>
              <?= h($r['start_date'] ?: '…') ?> → <?= h($r['end_date'] ?: '…') ?>
            <?php else: ?>
              Always on
            <?php endif; ?>
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
            <a href="?edit=<?= h($r['id']) ?>#spot-form" class="btn btn-sm btn-outline">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= h(addslashes($r['title'])) ?>?')">
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
