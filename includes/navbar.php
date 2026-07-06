<nav class="navbar navbar-expand-lg" data-bs-theme="dark">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/foodorder/index.php">
      <span class="brand-mark">F</span>
      <span>FoodOrder</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto">
        <?php if (isLoggedIn() && $_SESSION['role'] === 'customer'): ?>
          <li class="nav-item"><a class="nav-link" href="/foodorder/customer/menu.php">Menu</a></li>
          <li class="nav-item"><a class="nav-link" href="/foodorder/customer/cart.php">Cart (<?php echo getCartCount($conn); ?>)</a></li>
          <li class="nav-item"><a class="nav-link" href="/foodorder/customer/order_tracking.php">My Orders</a></li>
        <?php elseif (isLoggedIn() && $_SESSION['role'] === 'staff'): ?>
          <li class="nav-item"><a class="nav-link" href="/foodorder/staff/order_queue.php">Order Queue</a></li>
        <?php elseif (isLoggedIn() && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="/foodorder/admin/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="/foodorder/admin/manage_menu.php">Manage Menu</a></li>
          <li class="nav-item"><a class="nav-link" href="/foodorder/admin/manage_categories.php">Categories</a></li>
          <li class="nav-item"><a class="nav-link" href="/foodorder/admin/manage_users.php">Manage Users</a></li>
        <?php endif; ?>

        <?php if (isLoggedIn()): ?>
          <li class="nav-item"><a class="nav-link" href="/foodorder/account/change_password.php">Change Password</a></li>
          <li class="nav-item"><span class="nav-link" style="color: var(--text-muted);">Hi, <span style="color: var(--text);"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span></span></li>
          <li class="nav-item d-flex align-items-center">
            <form method="POST" action="/foodorder/auth/logout.php" class="d-inline m-0">
              <?php echo csrfField(); ?>
              <button type="submit" class="nav-link btn btn-link p-0 border-0 m-0" style="line-height: normal;">Logout</button>
            </form>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/foodorder/auth/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/foodorder/auth/register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
