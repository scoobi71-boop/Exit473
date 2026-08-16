<?php
require_once 'auth.php';
require_auth();

$CSV     = DATA_PATH . 'events.csv';
$JS_FILE  = realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR . 'events.js';
$HEADS = ['id','title','date','time','end_time','venue','description','category','website','free','recurring','recur_count','images'];
$CATS  = ['music','dance','food','art','workshop','general'];

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
        write_js_datafile($JS_FILE, 'EXIT473_EVENTS_CSV', $CSV);
        $msg = 'ok:Event deleted.';

    } elseif ($action === 'save') {
        $id       = trim($_POST['id'] ?? '');
        $is_new   = ($id === '');
        $new_id   = $is_new ? (string)next_id($rows) : $id;
        // Preserve existing images when editing
        $existing_images = '';
        if (!$is_new) {
            foreach ($rows as $r) {
                if ((string)$r['id'] === $id) { $existing_images = $r['images'] ?? ''; break; }
            }
        }
        $entry    = [
            'id'          => $new_id,
            'title'       => trim($_POST['title']       ?? ''),
            'date'        => trim($_POST['date']        ?? ''),
            'time'        => trim($_POST['time']        ?? ''),
            'end_time'    => trim($_POST['end_time']    ?? ''),
            'venue'       => trim($_POST['venue']       ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category'    => trim($_POST['category']    ?? 'general'),
            'website'     => trim($_POST['website']     ?? '#') ?: '#',
            'free'        => isset($_POST['free']) ? 'true' : 'false',
            'recurring'   => trim($_POST['recurring']   ?? ''),
            'recur_count' => max(0, (int)($_POST['recur_count'] ?? 0)),
            'images'      => $existing_images,
        ];
        if ($is_new) {
            $rows[] = $entry;
        } else {
            foreach ($rows as &$r) { if ((string)$r['id'] === $id) { $r = $entry; break; } }
            unset($r);
        }
        // Sort by date ascending
        usort($rows, fn($a,$b) => strcmp($a['date'], $b['date']));
        write_csv($CSV, $rows, $HEADS);
        write_js_datafile($JS_FILE, 'EXIT473_EVENTS_CSV', $CSV);
        $msg = 'ok:Event ' . ($is_new ? 'added' : 'updated') . ' successfully.';
    }
}

$rows    = read_csv($CSV);
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
<title>Events — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.events-table{width:100%;border-collapse:collapse;font-size:.88rem}
.events-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.events-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:top}
.events-table tr:hover td{background:#FFF9F7}
.cat-badge{display:inline-block;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.cat-music{background:#EDE0F7;color:#6A1B9A}
.cat-dance{background:#E0F2E9;color:#1B5E20}
.cat-food{background:#FFF0E0;color:#E65100}
.cat-art{background:#FDF6D8;color:#B8860B}
.cat-workshop{background:#E0EEFF;color:#1565C0}
.cat-general{background:#ECEFF1;color:#37474F}
.free-yes{color:#1B5E20;font-weight:700}
.free-no{color:#E65100;font-weight:700}
.table-wrap{overflow-x:auto}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">Events</h1>
    <a href="?new=1" class="btn btn-primary">+ Add New Event</a>
  </div>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:1.5rem"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <?php if ($editing || isset($_GET['new'])): ?>
  <!-- EDIT / ADD FORM -->
  <div class="panel" id="event-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Event' : 'Add New Event' ?></h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">

      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Event Title *</label>
          <input type="text" name="title" required value="<?= h($editing['title'] ?? '') ?>" placeholder="e.g. Friday Night Jazz & Rum">
        </div>
        <div class="form-row">
          <label>Date *</label>
          <input type="date" name="date" required value="<?= h($editing['date'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label>Start Time *</label>
          <input type="text" name="time" required value="<?= h($editing['time'] ?? '') ?>" placeholder="7:00 PM">
        </div>
        <div class="form-row">
          <label>End Time</label>
          <input type="text" name="end_time" value="<?= h($editing['end_time'] ?? '') ?>" placeholder="10:00 PM">
        </div>
        <div class="form-row">
          <label>Category *</label>
          <select name="category" required>
            <?php foreach ($CATS as $c): ?>
              <option value="<?= $c ?>" <?= ($editing['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Venue *</label>
          <input type="text" name="venue" required value="<?= h($editing['venue'] ?? '') ?>" placeholder="e.g. Mango Bay Restaurant">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Description *</label>
          <textarea name="description" required rows="4" placeholder="Describe the event..."><?= h($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label>Website / Ticket Link</label>
          <input type="text" name="website" value="<?= h($editing['website'] ?? '#') ?>" placeholder="https://... or leave blank">
        </div>
        <div class="form-row">
          <label>Recurring</label>
          <select name="recurring">
            <option value=""      <?= ($editing['recurring'] ?? '') === ''        ? 'selected' : '' ?>>One-time event</option>
            <option value="weekly"  <?= ($editing['recurring'] ?? '') === 'weekly'  ? 'selected' : '' ?>>🔄 Weekly (same day each week)</option>
            <option value="monthly" <?= ($editing['recurring'] ?? '') === 'monthly' ? 'selected' : '' ?>>🔄 Monthly (same date each month)</option>
          </select>
          <span class="hint">Weekly/monthly events auto-generate occurrences on the public calendar.</span>
        </div>
        <div class="form-row" id="recur-count-row" style="display:none">
          <label>Max Occurrences</label>
          <input type="number" name="recur_count" id="recur_count" min="1" max="52" value="<?= h($editing['recur_count'] ?? '') ?>" placeholder="e.g. 4  (leave blank = show all)">
          <span class="hint">Limit how many future occurrences appear on the calendar. Leave blank for no limit.</span>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label class="checkbox-label">
            <input type="checkbox" name="free" value="1" <?= ($editing['free'] ?? '') === 'true' ? 'checked' : '' ?>>
            Free entry (no ticket required)
          </label>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">💾 Save Event</button>
        <a href="events.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- EVENTS TABLE -->
  <div class="panel">
    <h2 class="panel-title"><?= count($rows) ?> Event<?= count($rows) !== 1 ? 's' : '' ?></h2>
    <?php if (empty($rows)): ?>
      <p style="color:#7A6565;text-align:center;padding:2rem">No events yet. Add your first event above.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="events-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Event</th>
          <th>Venue</th>
          <th>Category</th>
          <th>Entry</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td style="white-space:nowrap">
            <?= h(date('D d M Y', strtotime($r['date']))) ?><br>
            <small style="color:#7A6565"><?= h($r['time']) ?><?= $r['end_time'] ? ' – ' . h($r['end_time']) : '' ?></small>
          </td>
          <td><strong><?= h($r['title']) ?></strong><?= $r['recurring'] ? '<br><small style="color:#7A6565">🔄 ' . h($r['recurring']) . '</small>' : '' ?></td>
          <td><?= h($r['venue']) ?></td>
          <td><span class="cat-badge cat-<?= h($r['category']) ?>"><?= h($r['category']) ?></span></td>
          <td><span class="<?= $r['free'] === 'true' ? 'free-yes' : 'free-no' ?>"><?= $r['free'] === 'true' ? 'Free' : 'Ticketed' ?></span></td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= h($r['id']) ?>#event-form" class="btn btn-sm btn-outline">Edit</a>
            <a href="event_images.php?id=<?= h($r['id']) ?>" class="btn btn-sm btn-outline" title="Manage photos">
              📷<?php
                $img_count = $r['images'] ? count(array_filter(explode('|', $r['images']))) : 0;
                echo $img_count > 0 ? " {$img_count}" : '';
              ?>
            </a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this event?')">
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
<script>
(function(){
  var sel = document.querySelector('select[name="recurring"]');
  var row = document.getElementById('recur-count-row');
  if (!sel || !row) return;
  function toggle() { row.style.display = (sel.value === 'weekly' || sel.value === 'monthly') ? '' : 'none'; }
  sel.addEventListener('change', toggle);
  toggle();
})();
</script>
</body>
</html>
