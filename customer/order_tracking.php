<?php
$allowed_roles = ['customer'];
require_once __DIR__ . '/../includes/auth_check.php';

$customer_id = $_SESSION['user_id'];

$sql = "SELECT order_id, status, order_type, total_amount, created_at
        FROM orders
        WHERE customer_id = $customer_id
        ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

// Status -> Bootstrap badge class + progress step index
$status_steps = ['pending' => 1, 'preparing' => 2, 'ready' => 3, 'delivered' => 4, 'cancelled' => 0];
$status_labels = ['pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$status_classes = ['pending' => 'status-badge-pending', 'preparing' => 'status-badge-preparing', 'ready' => 'status-badge-ready', 'delivered' => 'status-badge-delivered', 'cancelled' => 'bg-danger'];

$page_title = "My Orders";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">My Orders</h2>

<?php if (isset($_GET['placed'])): ?>
  <div class="alert alert-success">Order #<?php echo (int)$_GET['placed']; ?> placed successfully! Track its progress below.</div>
<?php endif; ?>

<?php if (mysqli_num_rows($result) === 0): ?>
  <div class="alert alert-info">You haven't placed any orders yet. <a href="menu.php">Browse the menu</a>.</div>
<?php endif; ?>

<?php while ($order = mysqli_fetch_assoc($result)): ?>
  <?php
    $items_sql = "SELECT item_name, quantity, unit_price FROM order_items WHERE order_id = " . $order['order_id'];
    $items_result = mysqli_query($conn, $items_sql);
    $step = $status_steps[$order['status']];
  ?>
  <div class="card mb-3 card-ticket">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h5><span class="order-no">Order No. <?php echo str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></span></h5>
          <p class="text-muted mb-1"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?> &middot; <?php echo ucfirst($order['order_type']); ?></p>
        </div>
        <span class="badge <?php echo $status_classes[$order['status']]; ?> fs-6"><?php echo $status_labels[$order['status']]; ?></span>
      </div>

      <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="progress my-3" style="height: 8px;">
          <div class="progress-bar bg-success" style="width: <?php echo $step * 25; ?>%;"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted">
          <span>Pending</span><span>Preparing</span><span>Ready</span><span>Delivered</span>
        </div>
      <?php endif; ?>

      <ul class="list-group list-group-flush mt-3">
        <?php while ($it = mysqli_fetch_assoc($items_result)): ?>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span><?php echo htmlspecialchars($it['item_name']); ?> &times; <?php echo $it['quantity']; ?></span>
            <span><?php echo formatPrice($it['unit_price'] * $it['quantity']); ?></span>
          </li>
        <?php endwhile; ?>
      </ul>
      <div class="text-end fw-bold mt-2">Total: <?php echo formatPrice($order['total_amount']); ?></div>
    </div>
  </div>
<?php endwhile; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
