<?php
$allowed_roles = ['admin'];
require_once __DIR__ . '/../includes/auth_check.php';

$admin_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'unlock_login') {
        // Username, not user_id, is what login_attempts is keyed on
        $target = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE user_id = $user_id"));
        if ($target) {
            clearFailedLogins($conn, $target['username']);
        }
        redirect('/foodorder/admin/manage_users.php');
    }

    if ($user_id !== $admin_id) { // safety: don't let an admin lock themselves out
        if ($action === 'toggle_active') {
            mysqli_query($conn, "UPDATE users SET is_active = 1 - is_active WHERE user_id = $user_id");
        } elseif ($action === 'change_role') {
            $new_role = clean($conn, $_POST['role']);
            if (in_array($new_role, ['customer', 'staff', 'admin'])) {
                mysqli_query($conn, "UPDATE users SET role = '$new_role' WHERE user_id = $user_id");
            }
        } elseif ($action === 'set_password') {
            $new_password = $_POST['new_password'];
            if (strlen($new_password) >= 6) {
                $safe_password = mysqli_real_escape_string($conn, $new_password);
                mysqli_query($conn, "UPDATE users SET password = '$safe_password' WHERE user_id = $user_id");
            }
        }
    }
    redirect('/foodorder/admin/manage_users.php');
}

// Usernames currently locked out from failed login attempts, so we can offer a one-click unlock
ensureLoginAttemptsTable($conn);
$locked_usernames = [];
$locked_result = mysqli_query($conn, "SELECT username FROM login_attempts WHERE locked_until IS NOT NULL AND locked_until > NOW()");
while ($row = mysqli_fetch_assoc($locked_result)) {
    $locked_usernames[$row['username']] = true;
}

list($page, $offset, $limit) = paginateParams();
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$sql = "SELECT user_id, username, email, full_name, phone, address, role, is_active, created_at
        FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

$page_title = "Manage Users";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Manage Users</h2>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Address</th><th>Role</th><th>Status</th><th>Joined</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?php echo htmlspecialchars($u['full_name']); ?></td>
          <td><?php echo htmlspecialchars($u['username']); ?></td>
          <td><?php echo htmlspecialchars($u['email']); ?></td>
          <td><?php echo $u['phone'] ? htmlspecialchars($u['phone']) : '<span class="text-muted">&mdash;</span>'; ?></td>
          <td><?php echo $u['address'] ? htmlspecialchars($u['address']) : '<span class="text-muted">&mdash;</span>'; ?></td>
          <td>
            <?php if ($u['user_id'] == $admin_id): ?>
              <span class="badge badge-emphasis">admin (you)</span>
            <?php else: ?>
              <form method="POST" class="d-flex gap-1">
                <?php echo csrfField(); ?>
                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                <input type="hidden" name="action" value="change_role">
                <select name="role" class="form-select form-select-sm" data-current="<?php echo htmlspecialchars($u['role']); ?>" data-name="<?php echo htmlspecialchars($u['full_name']); ?>" onchange="confirmRoleChange(this)">
                  <option value="customer" <?php echo $u['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                  <option value="staff" <?php echo $u['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                  <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['is_active']): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge badge-neutral">Deactivated</span>
            <?php endif; ?>
            <?php if (isset($locked_usernames[$u['username']])): ?>
              <div class="mt-1">
                <span class="badge status-badge-pending">Locked out</span>
                <form method="POST" class="d-inline">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                  <input type="hidden" name="action" value="unlock_login">
                  <button type="submit" class="btn btn-link btn-sm p-0 ms-1 align-baseline">Unlock now</button>
                </form>
              </div>
            <?php endif; ?>
          </td>
          <td class="small text-muted"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
          <td>
            <?php if ($u['user_id'] != $admin_id): ?>
              <div class="d-flex gap-1">
                <form method="POST">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                  <input type="hidden" name="action" value="toggle_active">
                  <button type="submit" class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                    <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                  </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSetPassword(<?php echo $u['user_id']; ?>)">Set Password</button>
              </div>
              <div id="set-password-<?php echo $u['user_id']; ?>" class="mt-2" style="display: none;">
                <form method="POST" class="d-flex gap-1">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                  <input type="hidden" name="action" value="set_password">
                  <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New password" minlength="6" required>
                  <button type="submit" class="btn btn-sm btn-primary text-nowrap">Save</button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php renderPagination($page, $total_users, 'manage_users.php'); ?>

<script>
function confirmRoleChange(select) {
  const name = select.dataset.name;
  const newRole = select.options[select.selectedIndex].text;
  const message = select.value === 'admin'
    ? `Give ${name} full admin access? Admins can manage every user and the whole menu.`
    : `Change ${name}'s role to ${newRole}?`;
  if (confirm(message)) {
    select.form.submit();
  } else {
    select.value = select.dataset.current;
  }
}

function toggleSetPassword(userId) {
  const el = document.getElementById('set-password-' + userId);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
