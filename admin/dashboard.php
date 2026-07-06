<?php
$allowed_roles = ['admin'];
require_once __DIR__ . '/../includes/auth_check.php';

// Revenue from delivered orders
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status = 'delivered'"))['total'];

$today_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()"))['total'];

$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders"))['c'];
$pending_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status IN ('pending','preparing','ready')"))['c'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'customer'"))['c'];
$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM menu_items"))['c'];

// Top 5 best-selling items
$top_items = mysqli_query($conn, "
    SELECT item_name, SUM(quantity) AS total_sold
    FROM order_items
    GROUP BY item_name
    ORDER BY total_sold DESC
    LIMIT 5
");

// Last 10 orders
$recent_orders = mysqli_query($conn, "
    SELECT o.order_id, o.status, o.total_amount, o.created_at, u.full_name
    FROM orders o JOIN users u ON o.customer_id = u.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
");

$page_title = "Admin Dashboard";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Dashboard</h2>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card text-white bg-success h-100">
      <div class="card-body">
        <div class="small">Total Revenue (Delivered)</div>
        <div class="fs-3 fw-bold"><?php echo formatPrice($total_revenue); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card text-white bg-primary h-100">
      <div class="card-body">
        <div class="small">Today's Revenue</div>
        <div class="fs-3 fw-bold"><?php echo formatPrice($today_revenue); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card h-100">
      <div class="card-body">
        <div class="small text-muted">Total Orders</div>
        <div class="fs-3 fw-bold"><?php echo $total_orders; ?></div>
        <div class="small text-warning"><?php echo $pending_orders; ?> active</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card h-100">
      <div class="card-body">
        <div class="small text-muted">Registered Customers</div>
        <div class="fs-3 fw-bold"><?php echo $total_customers; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card h-100">
      <div class="card-body">
        <div class="small text-muted">Menu Items</div>
        <div class="fs-3 fw-bold"><?php echo $total_items; ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-5">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Top Selling Items</h5>
        <ul class="list-group list-group-flush">
          <?php if (mysqli_num_rows($top_items) === 0): ?>
            <li class="list-group-item text-muted">No sales data yet.</li>
          <?php endif; ?>
          <?php while ($row = mysqli_fetch_assoc($top_items)): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?php echo htmlspecialchars($row['item_name']); ?></span>
              <span class="badge badge-neutral"><?php echo $row['total_sold']; ?> sold</span>
            </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Recent Orders</h5>
        <table class="table table-sm">
          <thead>
            <tr><th>#</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php while ($o = mysqli_fetch_assoc($recent_orders)): ?>
              <tr>
                <td><span class="order-no">No. <?php echo str_pad($o['order_id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                <td><?php echo htmlspecialchars($o['full_name']); ?></td>
                <td><span class="badge status-badge-<?php echo $o['status']; ?>"><?php echo ucfirst($o['status']); ?></span></td>
                <td><?php echo formatPrice($o['total_amount']); ?></td>
                <td class="small text-muted"><?php echo date('M j, g:i A', strtotime($o['created_at'])); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
