<?php
require_once 'auth.php';
require_auth();
$slug  = current_slug();
$info  = read_info($slug);
$CSV   = menu_path($slug);
$HEADS = ['id','category','name','description','price','image'];
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $rows   = read_csv($CSV);

    if ($action === 'delete') {
        $del = (string)($_POST['del_id'] ?? '');
        $rows = array_values(array_filter($rows, fn($r) => (string)$r['id'] !== $del));
        write_csv($CSV, $rows, $HEADS);
        $msg = 'ok:Item deleted.';

    } elseif ($action === 'save') {
        $id     = trim($_POST['id'] ?? '');
        $is_new = ($id === '');
        $new_id = $is_new ? (string)next_id($rows) : $id;

        $image_path = $is_new ? '' : ($rows[array_search($id, array_column($rows, 'id'))]['image'] ?? '');
        $upload = handle_upload('item_image', $slug);
        if ($upload) $image_path = $upload;

        $entry = [
            'id'          => $new_id,
            'category'    => trim($_POST['category']    ?? ''),
            'name'        => trim($_POST['name']        ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price'       => trim($_POST['price']       ?? ''),
            'image'       => $image_path,
        ];
        if ($is_new) {
            $rows[] = $entry;
        } else {
            foreach ($rows as &$r) { if ((string)$r['id'] === $id) { $r = $entry; break; } }
            unset($r);
        }
        write_csv($CSV, $rows, $HEADS);
        $msg = 'ok:Item ' . ($is_new ? 'added' : 'updated') . '.';
    }
}

$rows    = read_csv($CSV);
$edit_id = $_GET['edit'] ?? '';
$editing = null;
foreach ($rows as $r) { if ((string)$r['id'] === $edit_id) { $editing = $r; break; } }

// Group by category for display
$grouped = [];
foreach ($rows as $r) $grouped[$r['category']][] = $r;

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Menu — <?= h($info['name'] ?? 'Admin') ?></title>
<?php include 'head_styles.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">Menu Items</h1>
    <a href="?new=1#item-form" class="btn btn-primary">+ Add Item</a>
  </div>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <?php if ($editing || isset($_GET['new'])): ?>
  <div class="panel" id="item-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Item' : 'Add Menu Item' ?></h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">
      <div class="form-grid">
        <div class="form-row">
          <label>Category *</label>
          <input type="text" name="category" required value="<?= h($editing['category'] ?? '') ?>" placeholder="e.g. Starters, Mains, Drinks">
        </div>
        <div class="form-row">
          <label>Price *</label>
          <input type="text" name="price" required value="<?= h($editing['price'] ?? '') ?>" placeholder="e.g. 12.50">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Item Name *</label>
          <input type="text" name="name" required value="<?= h($editing['name'] ?? '') ?>" placeholder="e.g. Grilled Snapper">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Description</label>
          <textarea name="description" rows="3" placeholder="Describe the item..."><?= h($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Image (optional)</label>
          <?php if (!empty($editing['image'])): ?>
            <img src="../<?= h($editing['image']) ?>" alt="Item image" class="img-preview" style="margin-bottom:.5rem">
          <?php endif; ?>
          <input type="file" name="item_image" accept="image/*">
          <span class="hint" style="font-size:.75rem;color:#718096">Upload a photo of this item (JPG, PNG, max 5MB)</span>
        </div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1.25rem">
        <button class="btn btn-primary" type="submit">💾 Save Item</button>
        <a href="menu_items.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($rows)): ?>
    <div class="panel" style="text-align:center;padding:3rem;color:#718096">
      No menu items yet. <a href="?new=1#item-form">Add your first item</a>.
    </div>
  <?php else: ?>
    <?php foreach ($grouped as $cat => $items): ?>
    <div class="panel">
      <h2 class="panel-title"><?= h($cat) ?> <small style="font-weight:400;color:#718096">(<?= count($items) ?>)</small></h2>
      <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Item</th><th>Description</th><th>Price</th><th>Image</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $r): ?>
          <tr>
            <td><strong><?= h($r['name']) ?></strong></td>
            <td style="color:#718096;max-width:280px"><?= h($r['description']) ?></td>
            <td style="white-space:nowrap;font-weight:600">$<?= h($r['price']) ?></td>
            <td>
              <?php if ($r['image']): ?>
                <img src="../<?= h($r['image']) ?>" alt="" class="img-preview">
              <?php else: ?>
                <span style="color:#718096;font-size:.8rem">None</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= h($r['id']) ?>#item-form" class="btn btn-sm btn-outline">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this item?')">
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
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
</body>
</html>
