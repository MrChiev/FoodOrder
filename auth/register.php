<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('/foodorder/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $username = trim(clean($conn, $_POST['username']));
    $email    = clean($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $full_name = clean($conn, $_POST['full_name']);
    $phone_local = trim(clean($conn, $_POST['phone']));
    $country_code = isset($_POST['country_code']) ? clean($conn, $_POST['country_code']) : '+855';
    $valid_codes = array_column(getCountryDialCodes(), 'code');
    if (!in_array($country_code, $valid_codes, true)) {
        $country_code = '+855';
    }
    $address  = clean($conn, $_POST['address']);
    $role     = 'customer'; // all self-registrations are customers; admins can promote via Manage Users

    if ($username === '' || $email === '' || $password === '' || $full_name === '') {
        $errors[] = "Please fill in all required fields.";
    }
    if ($username !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores (no spaces).";
    }
    if ($phone_local !== '' && !preg_match('/^[0-9]{6,12}$/', $phone_local)) {
        $errors[] = "Phone number must be 6–12 digits (without the leading 0).";
    }
    $phone = $phone_local !== '' ? $country_code . $phone_local : '';
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($errors)) {
        $check = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$username' OR email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Username or email is already taken.";
        }
    }

    if (empty($errors)) {
        $password_safe = mysqli_real_escape_string($conn, $password);
        $sql = "INSERT INTO users (username, email, password, full_name, phone, address, role)
                VALUES ('$username', '$email', '$password_safe', '$full_name', '$phone', '$address', '$role')";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['register_success'] = "Account created! Please log in.";
            redirect('/foodorder/auth/login.php');
        } else {
            // Log the real error server-side; never show DB internals (table/column
            // names, engine details) to an unauthenticated visitor.
            error_log("Registration insert failed: " . mysqli_error($conn));
            $errors[] = "Something went wrong creating your account. Please try again.";
        }
    }
}

$page_title = "Register";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="card-title mb-4">Create an Account</h3>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $err) echo "<li>" . htmlspecialchars($err) . "</li>"; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
          <?php echo csrfField(); ?>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required pattern="[A-Za-z0-9_]+" title="Letters, numbers, and underscores only — no spaces" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <?php
              $countries = getCountryDialCodes();
              $selected_code = isset($_POST['country_code']) ? $_POST['country_code'] : '+855';
              $selected_country = null;
              foreach ($countries as $c) {
                  if ($c['code'] === $selected_code) { $selected_country = $c; break; }
              }
              if (!$selected_country) { $selected_country = $countries[0]; }
            ?>
            <div class="input-group">
              <input type="hidden" name="country_code" id="country_code_input" value="<?php echo htmlspecialchars($selected_country['code']); ?>">
              <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 130px;">
                <img id="country_code_flag" src="https://flagcdn.com/w40/<?php echo $selected_country['iso']; ?>.png" width="24" height="18" alt="">
                <span id="country_code_label"><?php echo htmlspecialchars($selected_country['code']); ?></span>
              </button>
              <ul class="dropdown-menu" style="max-height: 260px; overflow-y: auto;">
                <?php foreach ($countries as $c): ?>
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="#"
                       onclick="selectCountryCode('<?php echo htmlspecialchars($c['code']); ?>', '<?php echo htmlspecialchars($c['iso']); ?>'); return false;">
                      <img src="https://flagcdn.com/w40/<?php echo $c['iso']; ?>.png" width="24" height="18" alt="">
                      <span><?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['code']; ?>)</span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
              <input type="text" name="phone" class="form-control" maxlength="12" inputmode="numeric" pattern="[0-9]{6,12}" placeholder="12 345 678" title="Number without the leading 0 (6–12 digits)" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            <div class="form-text">Select your country, then enter your number without the leading 0.</div>
          </div>
          <script>
            function selectCountryCode(code, iso) {
              document.getElementById('country_code_input').value = code;
              document.getElementById('country_code_label').textContent = code;
              document.getElementById('country_code_flag').src = 'https://flagcdn.com/w40/' + iso + '.png';
            }
          </script>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" placeholder="Used for delivery orders">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
        <p class="mt-3 text-center">Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
