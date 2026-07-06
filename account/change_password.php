<?php
// No $allowed_roles set -> auth_check.php only requires that *someone* is logged in,
// regardless of role. Customers, staff, and admins all use this same page.
require_once __DIR__ . '/../includes/auth_check.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $current_password = $_POST['current_password'];
    $new_password      = $_POST['new_password'];
    $confirm_password  = $_POST['confirm_password'];

    $user_id = $_SESSION['user_id'];
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE user_id = $user_id"));

    if (!$row || $current_password !== $row['password']) {
        $errors[] = "Your current password is incorrect.";
    }
    if ($new_password === '' || strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation do not match.";
    }
    if (empty($errors) && $current_password === $new_password) {
        $errors[] = "New password must be different from your current password.";
    }

    if (empty($errors)) {
        $safe_password = mysqli_real_escape_string($conn, $new_password);
        mysqli_query($conn, "UPDATE users SET password = '$safe_password' WHERE user_id = $user_id");
        $success = "Your password has been updated.";
    }
}

$page_title = "Change Password";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title mb-4">Change Password</h3>

        <?php if ($success): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $err) echo "<li>" . htmlspecialchars($err) . "</li>"; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST">
          <?php echo csrfField(); ?>
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6">
          </div>
          <button type="submit" class="btn btn-primary w-100">Update Password</button>
        </form>

        <p class="mt-3 text-center small text-muted">
          Forgot your current password instead? <a href="https://t.me/MrChiev" target="_blank" rel="noopener">Contact an admin on Telegram</a>.
        </p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
