<?php
require_once __DIR__ . '/includes/functions.php';

// Logged-in users go straight to their role's home page
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect('/foodorder/admin/dashboard.php');
        case 'staff':
            redirect('/foodorder/staff/order_queue.php');
        default:
            redirect('/foodorder/customer/menu.php');
    }
}

$sql = "SELECT m.item_id, m.item_name, m.description, m.price, m.image, c.category_name,
               COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN oi.quantity END), 0) AS total_sold
        FROM menu_items m
        JOIN categories c ON m.category_id = c.category_id
        LEFT JOIN order_items oi ON oi.item_id = m.item_id
        LEFT JOIN orders o ON o.order_id = oi.order_id
        WHERE m.is_available = 1
        GROUP BY m.item_id, m.item_name, m.description, m.price, m.image, c.category_name
        ORDER BY total_sold DESC, m.created_at DESC
        LIMIT 6";
$result = mysqli_query($conn, $sql);

$page_title = "Welcome";
include __DIR__ . '/includes/header.php';
?>

<div class="hero-ticket mb-5">
  <div class="hero-eyebrow">Order Online &middot; Phnom Penh</div>
  <h1 class="display-5 fw-bold">Order great food, fast.</h1>
  <p class="fs-5 lead text-muted">Browse our menu and get your favorites delivered or ready for pickup.</p>
  <div class="hero-actions">
    <a href="/foodorder/auth/register.php" class="btn btn-primary btn-lg me-2">Get Started</a>
    <a href="/foodorder/auth/login.php" class="btn btn-outline-secondary btn-lg">Login</a>
  </div>
</div>

<h3 class="mb-3">Popular Right Now</h3>
<div class="row g-4">
  <?php while ($item = mysqli_fetch_assoc($result)): ?>
    <div class="col-md-4">
      <div class="card h-100 menu-card">
        <img src="<?php echo $item['image'] ? '/foodorder/assets/uploads/' . htmlspecialchars($item['image']) : placeholderImage(400, 180); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
        <div class="card-body">
          <span class="badge badge-category mb-2"><?php echo htmlspecialchars($item['category_name']); ?></span>
          <?php if ($item['total_sold'] > 0): ?>
            <span class="badge badge-neutral mb-2"><?php echo (int)$item['total_sold']; ?> sold</span>
          <?php endif; ?>
          <h5 class="card-title"><?php echo htmlspecialchars($item['item_name']); ?></h5>
          <p class="card-text text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
          <p class="fw-bold"><?php echo formatPrice($item['price']); ?></p>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
