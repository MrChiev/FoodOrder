<?php
$allowed_roles = ['admin'];
require_once __DIR__ . '/../includes/auth_check.php';

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM menu_items WHERE item_id = $item_id"));

if (!$item) {
    redirect('/foodorder/admin/manage_menu.php');
}

$errors = [];
$categories = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $item_name = clean($conn, $_POST['item_name']);
    $description = clean($conn, $_POST['description']);
    $price = (float)$_POST['price'];
    $category_id = (int)$_POST['category_id'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $image_name = $item['image']; // keep existing unless replaced

    if ($item_name === '' || $price <= 0 || $category_id <= 0) {
        $errors[] = "Please fill in item name, a valid price, and category.";
    }

    if (!empty($_FILES['image']['name'])) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            $errors[] = "Image must be jpg, jpeg, png, or webp.";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Image must be smaller than 5MB.";
        } elseif (!isValidImageUpload($_FILES['image']['tmp_name'])) {
            $errors[] = "That file isn't a valid image.";
        } else {
            $upload_dir = __DIR__ . '/../assets/uploads';
            $dir_error = ensureUploadsDir($upload_dir);
            if ($dir_error) {
                $errors[] = $dir_error;
            } else {
                $new_image_name = uniqid('item_') . '.' . $ext;
                $upload_path = $upload_dir . '/' . $new_image_name;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Remove old image file if it existed
                    if ($item['image'] && file_exists($upload_dir . '/' . $item['image'])) {
                        unlink($upload_dir . '/' . $item['image']);
                    }
                    $image_name = $new_image_name;
                } else {
                    $errors[] = "Failed to upload new image.";
                }
            }
        }
    }

    if (empty($errors)) {
        $image_sql = $image_name ? "'" . clean($conn, $image_name) . "'" : "NULL";
        $sql = "UPDATE menu_items SET
                    category_id = $category_id,
                    item_name = '$item_name',
                    description = '$description',
                    price = $price,
                    image = $image_sql,
                    is_available = $is_available
                WHERE item_id = $item_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['menu_message'] = "Item updated successfully.";
            redirect('/foodorder/admin/manage_menu.php');
        } else {
            $errors[] = "Failed to update item: " . mysqli_error($conn);
        }
    }
}

$page_title = "Edit Menu Item";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Edit Menu Item</h2>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $err) echo "<div>" . htmlspecialchars($err) . "</div>"; ?>
  </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="col-md-6">
  <?php echo csrfField(); ?>
  <div class="mb-3">
    <label class="form-label">Item Name</label>
    <input type="text" name="item_name" class="form-control" required value="<?php echo htmlspecialchars($item['item_name']); ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
  </div>
  <div class="mb-3">
    <label class="form-label d-flex justify-content-between align-items-center">
      <span>Category</span>
      <a href="manage_categories.php" class="small">+ New category</a>
    </label>
    <select name="category_id" class="form-select" required>
      <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
        <option value="<?php echo $cat['category_id']; ?>" <?php echo $cat['category_id'] == $item['category_id'] ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($cat['category_name']); ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">Price (USD)</label>
    <input type="number" name="price" step="0.01" min="0.01" class="form-control" required value="<?php echo htmlspecialchars($item['price']); ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Current Image</label><br>
    <img src="<?php echo $item['image'] ? '/foodorder/assets/uploads/' . htmlspecialchars($item['image']) : placeholderImage(120, 120); ?>" width="120" class="rounded mb-2"><br>
    <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
    <div class="form-text">Leave blank to keep the current image.</div>
  </div>
  <div class="form-check mb-3">
    <input type="checkbox" name="is_available" class="form-check-input" id="is_available" <?php echo $item['is_available'] ? 'checked' : ''; ?>>
    <label class="form-check-label" for="is_available">Available on menu</label>
  </div>
  <button type="submit" class="btn btn-primary">Save Changes</button>
  <a href="manage_menu.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
