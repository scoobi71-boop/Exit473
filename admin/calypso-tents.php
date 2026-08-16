<?php
require_once 'auth.php';
require_auth();

$JSON_FILE = DATA_PATH . 'calypso-tents.json';
$JS_FILE   = realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR . 'calypso-tents.js';

$PARISHES = ["St. George's", "St. David's", "St. Andrew's", "St. Patrick's", "St. Mark's", "St. John's", "Carriacou", "Petite Martinique"];

function read_tents(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_tents(string $json_file, string $js_file, array $tents): void {
    $tents = array_values($tents);
    $json  = json_encode($tents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp   = $json_file . '.tmp';
    file_put_contents($tmp, $json);
    rename($tmp, $json_file);
    $js  = "window.CALYPSO_TENTS_DATA = " . json_encode($tents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
    $tmp = $js_file . '.tmp';
    file_put_contents($tmp, $js);
    rename($tmp, $js_file);
}

function slug_from(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/* Parse indexed schedule rows from POST */
function parse_schedule_rows(string $prefix, array $post): array {
    $count = (int)($post[$prefix . '_count'] ?? 0);
    $rows  = [];
    for ($i = 0; $i < $count; $i++) {
        if ($prefix === 'practice') {
            $day = trim($post["practice_day_{$i}"] ?? '');
            if ($day === '') continue;
            $rows[] = [
                'day'      => $day,
                'time'     => trim($post["practice_time_{$i}"]     ?? ''),
                'location' => trim($post["practice_location_{$i}"] ?? ''),
                'notes'    => trim($post["practice_notes_{$i}"]    ?? ''),
            ];
        } else {
            $date = trim($post["show_date_{$i}"] ?? '');
            $venue = trim($post["show_venue_{$i}"] ?? '');
            if ($date === '' && $venue === '') continue;
            $rows[] = [
                'date'  => $date,
                'venue' => $venue,
                'time'  => trim($post["show_time_{$i}"]  ?? ''),
                'notes' => trim($post["show_notes_{$i}"] ?? ''),
            ];
        }
    }
    return $rows;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $tents  = read_tents($JSON_FILE);

    if ($action === 'delete') {
        $del_id = trim($_POST['del_id'] ?? '');
        $tents  = array_values(array_filter($tents, fn($t) => $t['id'] !== $del_id));
        write_tents($JSON_FILE, $JS_FILE, $tents);
        $msg = 'ok:Tent deleted.';

    } elseif ($action === 'save') {
        $old_id = trim($_POST['old_id'] ?? '');
        $is_new = ($old_id === '');

        /* Generate slug ID from shortName or name if creating new */
        $raw_id = trim($_POST['tent_id'] ?? '');
        if ($raw_id === '') {
            $raw_id = slug_from(trim($_POST['shortName'] ?? '') ?: trim($_POST['name'] ?? ''));
        }
        $tent_id = preg_replace('/[^a-z0-9-]/', '', strtolower($raw_id));
        if ($tent_id === '') $tent_id = 'tent-' . time();

        /* Gallery images — one path per line */
        $img_raw = trim($_POST['images_text'] ?? '');
        $images  = $img_raw ? array_values(array_filter(array_map('trim', explode("\n", $img_raw)))) : [];

        $entry = [
            'id'              => $tent_id,
            'name'            => trim($_POST['name']      ?? ''),
            'shortName'       => trim($_POST['shortName'] ?? '') ?: null,
            'parish'          => trim($_POST['parish']    ?? ''),
            'community'       => trim($_POST['community'] ?? '') ?: null,
            'description'     => trim($_POST['description'] ?? '') ?: null,
            'address'         => trim($_POST['address']   ?? '') ?: null,
            'phone'           => trim($_POST['phone']     ?? '') ?: null,
            'email'           => trim($_POST['email']     ?? '') ?: null,
            'website'         => trim($_POST['website']   ?? '') ?: null,
            'facebook'        => trim($_POST['facebook']  ?? '') ?: null,
            'instagram'       => trim($_POST['instagram'] ?? '') ?: null,
            'logo'            => trim($_POST['logo']      ?? '') ?: null,
            'images'          => $images,
            'practiceSchedule'=> parse_schedule_rows('practice', $_POST),
            'showSchedule'    => parse_schedule_rows('show',     $_POST),
            'founded'         => trim($_POST['founded']   ?? '') !== '' ? (int)$_POST['founded'] : null,
            'active'          => isset($_POST['active']),
        ];

        if ($is_new) {
            $tents[] = $entry;
        } else {
            $replaced = false;
            foreach ($tents as &$t) {
                if ($t['id'] === $old_id) { $t = $entry; $replaced = true; break; }
            }
            unset($t);
            if (!$replaced) $tents[] = $entry;
        }
        write_tents($JSON_FILE, $JS_FILE, $tents);
        $msg = 'ok:Tent ' . ($is_new ? 'added' : 'updated') . ' successfully.';
    }
}

$tents   = read_tents($JSON_FILE);
$edit_id = $_GET['edit'] ?? '';
$editing = null;
foreach ($tents as $t) { if ($t['id'] === $edit_id) { $editing = $t; break; } }

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

/* Helpers for rendering schedule rows */
function practice_row_html(int $i, array $r = []): string {
    $day  = h($r['day']      ?? '');
    $time = h($r['time']     ?? '');
    $loc  = h($r['location'] ?? '');
    $note = h($r['notes']    ?? '');
    return <<<HTML
    <div class="sched-row" id="pr_{$i}">
      <input type="text"  name="practice_day_{$i}"      value="{$day}"  placeholder="e.g. Sunday">
      <input type="text"  name="practice_time_{$i}"     value="{$time}" placeholder="e.g. 4:00 PM">
      <input type="text"  name="practice_location_{$i}" value="{$loc}"  placeholder="e.g. Beaulieu Community Centre">
      <input type="text"  name="practice_notes_{$i}"    value="{$note}" placeholder="Notes (optional)">
      <button type="button" class="row-remove" onclick="removeRow('pr_{$i}','practice-count')">✕</button>
    </div>
HTML;
}

function show_row_html(int $i, array $r = []): string {
    $date  = h($r['date']  ?? '');
    $venue = h($r['venue'] ?? '');
    $time  = h($r['time']  ?? '');
    $note  = h($r['notes'] ?? '');
    return <<<HTML
    <div class="sched-row" id="sh_{$i}">
      <input type="date"  name="show_date_{$i}"  value="{$date}">
      <input type="text"  name="show_venue_{$i}" value="{$venue}" placeholder="e.g. Beaulieu Sports Complex">
      <input type="text"  name="show_time_{$i}"  value="{$time}"  placeholder="e.g. 7:00 PM">
      <input type="text"  name="show_notes_{$i}" value="{$note}"  placeholder="Notes (optional)">
      <button type="button" class="row-remove" onclick="removeRow('sh_{$i}','show-count')">✕</button>
    </div>
HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Calypso Tents — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.tent-table{width:100%;border-collapse:collapse;font-size:.88rem}
.tent-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.tent-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:top}
.tent-table tr:hover td{background:#FFF9F7}
.table-wrap{overflow-x:auto}
.parish-badge{display:inline-block;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:#FFF0DC;color:#7A4A00}
.active-badge{color:#1B5E20;font-weight:700}
.inactive-badge{color:#9E9E9E}
.section-divider{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#CC0000;padding:.6rem 0 .4rem;border-bottom:2px solid #F0E8E8;margin:1.5rem 0 1rem}
/* Schedule rows */
.sched-row{display:grid;gap:.4rem;margin-bottom:.4rem;position:relative;padding-right:2.25rem}
.practice-grid .sched-row{grid-template-columns:1fr 1fr 1.5fr 1.5fr}
.show-grid     .sched-row{grid-template-columns:140px 1.5fr 100px 1.5fr}
.row-remove{position:absolute;right:0;top:50%;transform:translateY(-50%);background:none;border:none;color:#BBB;font-size:1rem;cursor:pointer;padding:.2rem .4rem;border-radius:4px;transition:color .15s}
.row-remove:hover{color:#CC0000}
.add-row-btn{display:inline-flex;align-items:center;gap:.35rem;background:none;border:1px dashed #CCC;color:#7A6565;font-size:.82rem;font-weight:600;padding:.35rem .85rem;border-radius:6px;cursor:pointer;transition:all .15s;font-family:inherit;margin-top:.25rem}
.add-row-btn:hover{border-color:#CC0000;color:#CC0000}
.sched-labels{display:grid;gap:.4rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9E9E9E;margin-bottom:.25rem}
.practice-grid .sched-labels{grid-template-columns:1fr 1fr 1.5fr 1.5fr;padding-right:2.25rem}
.show-grid     .sched-labels{grid-template-columns:140px 1.5fr 100px 1.5fr;padding-right:2.25rem}
.tent-id-hint{font-size:.75rem;color:#7A6565;margin-top:.2rem}
@media(max-width:700px){
  .practice-grid .sched-row,.show-grid .sched-row,
  .practice-grid .sched-labels,.show-grid .sched-labels{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">Calypso Tents</h1>
    <a href="?new=1" class="btn btn-primary">+ Add New Tent</a>
  </div>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:1.5rem"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <?php if ($editing || isset($_GET['new'])): ?>
  <?php
    $pr = $editing['practiceSchedule'] ?? [];
    $sh = $editing['showSchedule']     ?? [];
    $pr_count = count($pr);
    $sh_count = count($sh);
    $img_lines = implode("\n", $editing['images'] ?? []);
  ?>
  <!-- ADD / EDIT FORM -->
  <div class="panel" id="tent-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Tent — ' . h($editing['name']) : 'Add New Tent' ?></h2>
    <form method="post">
      <input type="hidden" name="_csrf"   value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action"  value="save">
      <input type="hidden" name="old_id"  value="<?= h($editing['id'] ?? '') ?>">

      <div class="section-divider">🎪 Tent Information</div>
      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Tent Name *</label>
          <input type="text" name="name" id="f-name" required
                 value="<?= h($editing['name'] ?? '') ?>"
                 placeholder="e.g. Northeast Village Pulse"
                 oninput="autoSlug()">
        </div>
        <div class="form-row">
          <label>Short Name / Abbreviation</label>
          <input type="text" name="shortName" id="f-short"
                 value="<?= h($editing['shortName'] ?? '') ?>"
                 placeholder="e.g. NVP"
                 oninput="autoSlug()">
        </div>
        <div class="form-row">
          <label>Tent ID (URL slug) *</label>
          <input type="text" name="tent_id" id="f-id" required
                 value="<?= h($editing['id'] ?? '') ?>"
                 placeholder="e.g. nvp"
                 pattern="[a-z0-9-]+"
                 <?= $editing ? 'readonly style="background:#f5f5f5;color:#888"' : '' ?>>
          <div class="tent-id-hint">Lowercase letters, numbers, hyphens only. Used in URLs — cannot change after saving artists to this tent.</div>
        </div>
        <div class="form-row">
          <label>Parish *</label>
          <select name="parish" required>
            <option value="">Select parish…</option>
            <?php foreach ($PARISHES as $p): ?>
              <option value="<?= h($p) ?>" <?= ($editing['parish'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Community / Neighbourhood</label>
          <input type="text" name="community"
                 value="<?= h($editing['community'] ?? '') ?>"
                 placeholder="e.g. Beaulieu, Northeast St. George's">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Description</label>
          <textarea name="description" rows="6"
                    placeholder="The tent's mission, history, and community role…"><?= h($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label>Year Founded</label>
          <input type="number" name="founded" min="1900" max="2100"
                 value="<?= h($editing['founded'] ?? '') ?>" placeholder="e.g. 2022">
        </div>
        <div class="form-row">
          <label class="checkbox-label" style="margin-top:1.6rem">
            <input type="checkbox" name="active" value="1"
                   <?= ($editing['active'] ?? true) ? 'checked' : '' ?>>
            Active tent (visible on site)
          </label>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Logo / Banner Image Path <small style="font-weight:400;text-transform:none;color:#7A6565">(optional)</small></label>
          <input type="text" name="logo"
                 value="<?= h($editing['logo'] ?? '') ?>"
                 placeholder="e.g. images/carnival/tents/nvp-banner.jpg">
        </div>
      </div>

      <div class="section-divider">📍 Contact &amp; Links</div>
      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Address</label>
          <input type="text" name="address"
                 value="<?= h($editing['address'] ?? '') ?>"
                 placeholder="e.g. Beaulieu, Northeast St. George's, Grenada">
        </div>
        <div class="form-row">
          <label>Phone</label>
          <input type="text" name="phone"
                 value="<?= h($editing['phone'] ?? '') ?>" placeholder="+1 (473) 555-0000">
        </div>
        <div class="form-row">
          <label>Email</label>
          <input type="email" name="email"
                 value="<?= h($editing['email'] ?? '') ?>" placeholder="info@tentname.com">
        </div>
        <div class="form-row">
          <label>Website</label>
          <input type="url" name="website"
                 value="<?= h($editing['website'] ?? '') ?>" placeholder="https://...">
        </div>
        <div class="form-row">
          <label>Facebook URL</label>
          <input type="url" name="facebook"
                 value="<?= h($editing['facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
        </div>
        <div class="form-row">
          <label>Instagram URL</label>
          <input type="url" name="instagram"
                 value="<?= h($editing['instagram'] ?? '') ?>" placeholder="https://instagram.com/...">
        </div>
      </div>

      <div class="section-divider">🎵 Practice Schedule</div>
      <div class="practice-grid">
        <div class="sched-labels">
          <span>Day</span><span>Time</span><span>Location</span><span>Notes</span>
        </div>
        <div id="practice-rows">
          <?php for ($i = 0; $i < $pr_count; $i++): ?>
            <?= practice_row_html($i, $pr[$i]) ?>
          <?php endfor; ?>
        </div>
      </div>
      <input type="hidden" name="practice_count" id="practice-count" value="<?= $pr_count ?>">
      <button type="button" class="add-row-btn" onclick="addPracticeRow()">+ Add Practice Session</button>

      <div class="section-divider">🎭 Show Schedule</div>
      <div class="show-grid">
        <div class="sched-labels">
          <span>Date</span><span>Venue</span><span>Time</span><span>Notes</span>
        </div>
        <div id="show-rows">
          <?php for ($i = 0; $i < $sh_count; $i++): ?>
            <?= show_row_html($i, $sh[$i]) ?>
          <?php endfor; ?>
        </div>
      </div>
      <input type="hidden" name="show_count" id="show-count" value="<?= $sh_count ?>">
      <button type="button" class="add-row-btn" onclick="addShowRow()">+ Add Show</button>

      <div class="section-divider">🖼 Gallery Images</div>
      <div class="form-row">
        <label>Image Paths <small style="font-weight:400;text-transform:none;color:#7A6565">(one path per line)</small></label>
        <textarea name="images_text" rows="4"
                  placeholder="images/carnival/tents/nvp/photo1.jpg&#10;images/carnival/tents/nvp/photo2.jpg"><?= h($img_lines) ?></textarea>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">💾 Save Tent</button>
        <a href="calypso-tents.php" class="btn btn-outline">Cancel</a>
        <?php if ($editing): ?>
          <a href="../carnival/calypso-tent-detail.html?id=<?= h($editing['id']) ?>" target="_blank"
             class="btn btn-outline" style="margin-left:auto">👁 View Tent Page ↗</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- TENTS TABLE -->
  <div class="panel">
    <h2 class="panel-title"><?= count($tents) ?> Tent<?= count($tents) !== 1 ? 's' : '' ?></h2>
    <?php if (empty($tents)): ?>
      <p style="color:#7A6565;text-align:center;padding:2rem">No tents yet. Add your first tent above.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="tent-table">
      <thead>
        <tr>
          <th>Tent</th>
          <th>Parish</th>
          <th>Contact</th>
          <th>Schedule</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tents as $t): ?>
        <tr>
          <td>
            <strong><?= $t['shortName'] ? h($t['shortName']) . ' — ' : '' ?><?= h($t['name']) ?></strong>
            <?php if ($t['community']): ?>
              <div style="font-size:.78rem;color:#7A6565;margin-top:.2rem"><?= h($t['community']) ?></div>
            <?php endif; ?>
            <?php if ($t['description']): ?>
              <div style="font-size:.75rem;color:#9E9E9E;margin-top:.2rem;line-height:1.4"><?= h(mb_strimwidth($t['description'], 0, 80, '…')) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="parish-badge"><?= h($t['parish']) ?></span></td>
          <td style="font-size:.82rem">
            <?php if ($t['phone']):    ?><div><?= h($t['phone']) ?></div><?php endif; ?>
            <?php if ($t['email']):    ?><div><?= h($t['email']) ?></div><?php endif; ?>
            <?php if ($t['website']): ?><div style="color:#7A6565"><?= h(preg_replace('#^https?://#','',$t['website'])) ?></div><?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:#7A6565">
            <?php
              $pr = count($t['practiceSchedule'] ?? []);
              $sh = count($t['showSchedule']     ?? []);
              $im = count($t['images']           ?? []);
            ?>
            <?= $pr ? "{$pr} practice" : '' ?><?= $pr && ($sh || $im) ? ' · ' : '' ?>
            <?= $sh ? "{$sh} show" . ($sh !== 1 ? 's' : '') : '' ?><?= $sh && $im ? ' · ' : '' ?>
            <?= $im ? "{$im} image" . ($im !== 1 ? 's' : '') : '' ?>
            <?= (!$pr && !$sh && !$im) ? '<span style="color:#CCC">—</span>' : '' ?>
          </td>
          <td>
            <span class="<?= $t['active'] ? 'active-badge' : 'inactive-badge' ?>">
              <?= $t['active'] ? '● Active' : '○ Hidden' ?>
            </span>
          </td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= h($t['id']) ?>#tent-form" class="btn btn-sm btn-outline">Edit</a>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Delete <?= h(addslashes($t['name'])) ?>?')">
              <input type="hidden" name="_csrf"   value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="del_id"  value="<?= h($t['id']) ?>">
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

  <div style="margin-top:.5rem">
    <a href="../carnival/calypso-tents.html" target="_blank" class="btn btn-outline">👁 View Tents Page ↗</a>
  </div>
</main>

<script>
let prCount = <?= $pr_count ?>;
let shCount = <?= $sh_count ?>;

function addPracticeRow() {
  const i   = prCount++;
  const div = document.createElement('div');
  div.className = 'sched-row';
  div.id = 'pr_' + i;
  div.innerHTML = `
    <input type="text" name="practice_day_${i}"      placeholder="e.g. Sunday">
    <input type="text" name="practice_time_${i}"     placeholder="e.g. 4:00 PM">
    <input type="text" name="practice_location_${i}" placeholder="e.g. Community Centre">
    <input type="text" name="practice_notes_${i}"    placeholder="Notes (optional)">
    <button type="button" class="row-remove" onclick="removeRow('pr_${i}','practice-count')">✕</button>`;
  document.getElementById('practice-rows').appendChild(div);
  document.getElementById('practice-count').value = prCount;
}

function addShowRow() {
  const i   = shCount++;
  const div = document.createElement('div');
  div.className = 'sched-row';
  div.id = 'sh_' + i;
  div.innerHTML = `
    <input type="date" name="show_date_${i}">
    <input type="text" name="show_venue_${i}" placeholder="e.g. Sports Complex">
    <input type="text" name="show_time_${i}"  placeholder="e.g. 7:00 PM">
    <input type="text" name="show_notes_${i}" placeholder="Notes (optional)">
    <button type="button" class="row-remove" onclick="removeRow('sh_${i}','show-count')">✕</button>`;
  document.getElementById('show-rows').appendChild(div);
  document.getElementById('show-count').value = shCount;
}

function removeRow(rowId, countId) {
  document.getElementById(rowId)?.remove();
}

function autoSlug() {
  const idField = document.getElementById('f-id');
  if (!idField || idField.readOnly) return;
  const short = document.getElementById('f-short')?.value.trim();
  const name  = document.getElementById('f-name')?.value.trim();
  const src   = short || name || '';
  idField.value = src.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}
</script>
</body>
</html>
