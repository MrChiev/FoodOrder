<?php
$allowed_roles = ['customer'];
require_once __DIR__ . '/../includes/auth_check.php';

$customer_id = $_SESSION['user_id'];

// Category filter
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$cat_result = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");

$sql = "SELECT m.item_id, m.item_name, m.description, m.price, m.image, c.category_name
        FROM menu_items m
        JOIN categories c ON m.category_id = c.category_id
        WHERE m.is_available = 1";
if ($category_filter > 0) {
    $sql .= " AND m.category_id = $category_filter";
}
$sql .= " ORDER BY c.category_name, m.item_name";
$result = mysqli_query($conn, $sql);

$page_title = "Menu";
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2>Our Menu</h2>
  <a href="cart.php" class="btn btn-outline-primary">View Cart (<?php echo getCartCount($conn); ?>)</a>
</div>

<?php if (isset($_SESSION['cart_message'])): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['cart_message']); unset($_SESSION['cart_message']); ?></div>
<?php endif; ?>

<div class="mb-4">
  <a href="menu.php" class="btn btn-sm <?php echo $category_filter === 0 ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
  <?php while ($cat = mysqli_fetch_assoc($cat_result)): ?>
    <a href="menu.php?category=<?php echo $cat['category_id']; ?>" class="btn btn-sm <?php echo $category_filter === (int)$cat['category_id'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
      <?php echo htmlspecialchars($cat['category_name']); ?>
    </a>
  <?php endwhile; ?>
</div>

<div class="row g-4">
  <?php if (mysqli_num_rows($result) === 0): ?>
    <p class="text-muted">No items available in this category right now.</p>
  <?php endif; ?>

  <?php while ($item = mysqli_fetch_assoc($result)): ?>
    <div class="col-md-4">
      <div class="card h-100 menu-card">
        <img src="<?php echo $item['image'] ? '/foodorder/assets/uploads/' . htmlspecialchars($item['image']) : placeholderImage(400, 180); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
        <div class="card-body d-flex flex-column">
          <span class="badge badge-category mb-2 align-self-start"><?php echo htmlspecialchars($item['category_name']); ?></span>
          <h5 class="card-title"><?php echo htmlspecialchars($item['item_name']); ?></h5>
          <p class="card-text text-muted flex-grow-1"><?php echo htmlspecialchars($item['description']); ?></p>
          <p class="fw-bold fs-5"><?php echo formatPrice($item['price']); ?></p>
          <form method="POST" action="add_to_cart.php" class="d-flex gap-2">
            <?php echo csrfField(); ?>
            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
            <input type="number" name="quantity" value="1" min="1" max="20" class="form-control" style="width: 80px;">
            <button type="submit" class="btn btn-primary flex-grow-1">Add to Cart</button>
          </form>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
