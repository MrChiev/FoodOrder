<?php
$allowed_roles = ['admin'];
require_once __DIR__ . '/../includes/auth_check.php';

$errors = [];

if (isset($_SESSION['category_message'])) {
    $category_message = $_SESSION['category_message'];
    unset($_SESSION['category_message']);
}

// Add a new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    verifyCsrfToken();
    $category_name = clean($conn, $_POST['category_name']);

    if ($category_name === '') {
        $errors[] = "Category name can't be empty.";
    } else {
        $check = mysqli_query($conn, "SELECT category_id FROM categories WHERE category_name = '$category_name'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "A category named \"$category_name\" already exists.";
        } else {
            $sql = "INSERT INTO categories (category_name) VALUES ('$category_name')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['category_message'] = "Category \"$category_name\" added.";
                redirect('/foodorder/admin/manage_categories.php');
            } else {
                $errors[] = "Failed to add category: " . mysqli_error($conn);
            }
        }
    }
}

// Delete a category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verifyCsrfToken();
    $category_id = (int)$_POST['category_id'];

    $count_check = mysqli_query($conn, "SELECT COUNT(*) AS total FROM menu_items WHERE category_id = $category_id");
    $count_row = mysqli_fetch_assoc($count_check);

    if ($count_row['total'] > 0) {
        $errors[] = "Can't delete that category — it still has {$count_row['total']} menu item(s) in it. Move or delete those items first.";
    } else {
        $sql = "DELETE FROM categories WHERE category_id = $category_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['category_message'] = "Category deleted.";
            redirect('/foodorder/admin/manage_categories.php');
        } else {
            $errors[] = "Failed to delete category: " . mysqli_error($conn);
        }
    }
}

$sql = "SELECT c.category_id, c.category_name, COUNT(m.item_id) AS item_count
        FROM categories c
        LEFT JOIN menu_items m ON m.category_id = c.category_id
        GROUP BY c.category_id, c.category_name
        ORDER BY c.category_name";
$result = mysqli_query($conn, $sql);

$page_title = "Manage Categories";
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2>Manage Categories</h2>
  <a href="manage_menu.php" class="btn btn-outline-secondary">Back to Menu</a>
</div>

<?php if (isset($category_message)): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($category_message); ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $err) echo "<div>" . htmlspecialchars($err) . "</div>"; ?>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="card-title mb-3">Add a category</h5>
        <form method="POST">
          <?php echo csrfField(); ?>
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label">Category name</label>
            <input type="text" name="category_name" class="form-control" maxlength="50" required placeholder="e.g. Noodles">
          </div>
          <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Category</th><th>Items</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($result) === 0): ?>
            <tr><td colspan="3" class="text-muted">No categories yet — add one to get started.</td></tr>
          <?php endif; ?>
          <?php while ($cat = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
              <td><span class="badge badge-neutral"><?php echo $cat['item_count']; ?></span></td>
              <td class="text-end">
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?');">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" <?php echo $cat['item_count'] > 0 ? 'disabled title="Move or delete its items first"' : ''; ?>>Delete</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
