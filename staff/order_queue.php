<?php
$allowed_roles = ['staff', 'admin'];
require_once __DIR__ . '/../includes/auth_check.php';

$sql = "SELECT o.order_id, o.status, o.order_type, o.total_amount, o.created_at, u.full_name, u.phone
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        WHERE o.status IN ('pending', 'preparing', 'ready')
        ORDER BY o.created_at ASC";
$result = mysqli_query($conn, $sql);

$next_status = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'delivered'];
$next_label  = ['pending' => 'Start Preparing', 'preparing' => 'Mark Ready', 'ready' => 'Mark Delivered'];
$status_classes = ['pending' => 'status-badge-pending', 'preparing' => 'status-badge-preparing', 'ready' => 'status-badge-ready'];

$page_title = "Order Queue";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Order Queue</h2>

<div class="row g-3">
  <?php if (mysqli_num_rows($result) === 0): ?>
    <p class="text-muted">No active orders right now.</p>
  <?php endif; ?>

  <?php while ($order = mysqli_fetch_assoc($result)): ?>
    <?php
      $items_result = mysqli_query($conn, "SELECT item_name, quantity FROM order_items WHERE order_id = " . $order['order_id']);
    ?>
    <div class="col-md-4">
      <div class="card h-100 card-ticket">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5><span class="order-no">Order No. <?php echo str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></span></h5>
            <span class="badge <?php echo $status_classes[$order['status']]; ?>"><?php echo ucfirst($order['status']); ?></span>
          </div>
          <p class="mb-1"><strong><?php echo htmlspecialchars($order['full_name']); ?></strong> &middot; <?php echo htmlspecialchars($order['phone'] ?: 'No phone'); ?></p>
          <p class="text-muted small mb-2"><?php echo ucfirst($order['order_type']); ?> &middot; <?php echo date('g:i A', strtotime($order['created_at'])); ?></p>

          <ul class="list-group list-group-flush mb-3 flex-grow-1">
            <?php while ($it = mysqli_fetch_assoc($items_result)): ?>
              <li class="list-group-item px-0 py-1"><?php echo $it['quantity']; ?> &times; <?php echo htmlspecialchars($it['item_name']); ?></li>
            <?php endwhile; ?>
          </ul>

          <div class="fw-bold mb-2">Total: <?php echo formatPrice($order['total_amount']); ?></div>

          <form method="POST" action="update_status.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
            <input type="hidden" name="new_status" value="<?php echo $next_status[$order['status']]; ?>">
            <button type="submit" class="btn btn-primary w-100"><?php echo $next_label[$order['status']]; ?></button>
          </form>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
