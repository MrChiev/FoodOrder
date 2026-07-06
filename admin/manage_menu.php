<?php
$allowed_roles = ['admin'];
require_once __DIR__ . '/../includes/auth_check.php';

if (isset($_SESSION['menu_message'])) {
    $menu_message = $_SESSION['menu_message'];
    unset($_SESSION['menu_message']);
}

list($page, $offset, $limit) = paginateParams();
$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM menu_items"))['c'];
$sql = "SELECT m.item_id, m.item_name, m.price, m.image, m.is_available, c.category_name
        FROM menu_items m
        JOIN categories c ON m.category_id = c.category_id
        ORDER BY c.category_name, m.item_name
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

$page_title = "Manage Menu";
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2>Manage Menu</h2>
  <div>
    <a href="manage_categories.php" class="btn btn-outline-secondary me-2">Categories</a>
    <a href="add_item.php" class="btn btn-primary">+ Add New Item</a>
  </div>
</div>

<?php if (isset($menu_message)): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($menu_message); ?></div>
<?php endif; ?>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php while ($item = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><img src="<?php echo $item['image'] ? '/foodorder/assets/uploads/' . htmlspecialchars($item['image']) : placeholderImage(60, 60); ?>" width="60" height="60" style="object-fit:cover;" class="rounded"></td>
          <td><?php echo htmlspecialchars($item['item_name']); ?></td>
          <td><?php echo htmlspecialchars($item['category_name']); ?></td>
          <td><?php echo formatPrice($item['price']); ?></td>
          <td>
            <?php if ($item['is_available']): ?>
              <span class="badge bg-success">Available</span>
            <?php else: ?>
              <span class="badge badge-neutral">Hidden</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a href="edit_item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
            <form method="POST" action="delete_item.php" class="d-inline" onsubmit="return confirm('Delete this item? This cannot be undone.');">
              <?php echo csrfField(); ?>
              <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php renderPagination($page, $total_items, 'manage_menu.php'); ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
