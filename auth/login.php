<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('/foodorder/index.php');
}

$error = '';
$success = isset($_SESSION['register_success']) ? $_SESSION['register_success'] : '';
unset($_SESSION['register_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    ensureLoginAttemptsTable($conn);
    $username = clean($conn, $_POST['username']);
    $password = $_POST['password'];

    $locked_minutes = getLoginLockoutMinutes($conn, $username);
    if ($locked_minutes !== null) {
        $error = "Too many failed attempts. Try again in $locked_minutes minute" . ($locked_minutes === 1 ? '' : 's') . ".";
    } else {
        $sql = "SELECT user_id, username, password, full_name, role, is_active FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if (!$user['is_active']) {
                $error = "This account has been deactivated. Contact an admin.";
            } elseif ($password === $user['password']) {
                clearFailedLogins($conn, $username);
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']); // force a fresh CSRF token for the new session
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                switch ($user['role']) {
                    case 'admin':
                        redirect('/foodorder/admin/dashboard.php');
                        break;
                    case 'staff':
                        redirect('/foodorder/staff/order_queue.php');
                        break;
                    default:
                        redirect('/foodorder/customer/menu.php');
                }
            } else {
                recordFailedLogin($conn, $username);
                $error = "Incorrect username or password.";
            }
        } else {
            recordFailedLogin($conn, $username);
            $error = "Incorrect username or password.";
        }
    }
}

$page_title = "Login";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title mb-4">Login</h3>

        <?php if ($success): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
          <?php echo csrfField(); ?>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
          <div class="text-center mt-2">
              <a href="https://t.me/MrChiev" target="_blank" rel="noopener" class="small">
                  Forgot your password?
                </a>
            </div>
        <p class="mt-3 text-center">Don't have an account? <a href="register.php">Register here</a></p>

        <div class="alert alert-light border mt-3 small mb-0">
          <strong>Demo accounts</strong> (password: <code>password123</code>)<br>
          admin1 &middot; staff1 &middot; customer1
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
