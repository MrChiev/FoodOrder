<?php
$allowed_roles = ['customer'];
require_once __DIR__ . '/../includes/auth_check.php';

$customer_id = $_SESSION['user_id'];
$errors = [];

// Load cart -- only items still available. An item can go unavailable
// (86'd, taken off the menu) between being added to the cart and checkout;
// without this filter, it would still get ordered even though the kitchen
// can no longer make it.
$sql = "SELECT c.cart_id, c.quantity, m.item_id, m.item_name, m.price
        FROM cart c
        JOIN menu_items m ON c.item_id = m.item_id
        WHERE c.customer_id = $customer_id AND m.is_available = 1";
$result = mysqli_query($conn, $sql);
$cart_items = [];
$total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $total += $row['subtotal'];
    $cart_items[] = $row;
}

// Clean out any cart rows that are no longer available (deleted item or
// is_available = 0) and let the customer know, rather than have them
// silently vanish from the total with no explanation.
$removed = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM cart c
    LEFT JOIN menu_items m ON c.item_id = m.item_id AND m.is_available = 1
    WHERE c.customer_id = $customer_id AND m.item_id IS NULL
"))['c'];
if ($removed > 0) {
    mysqli_query($conn, "
        DELETE c FROM cart c
        LEFT JOIN menu_items m ON c.item_id = m.item_id AND m.is_available = 1
        WHERE c.customer_id = $customer_id AND m.item_id IS NULL
    ");
    $errors[] = $removed === 1
        ? "One item in your cart is no longer available and was removed. Please review your order below."
        : "$removed items in your cart are no longer available and were removed. Please review your order below.";
}

if (empty($cart_items)) {
    if (!empty($errors)) {
        $_SESSION['cart_notice'] = implode(' ', $errors);
    }
    redirect('/foodorder/customer/cart.php');
}

// Pre-fill address from profile
$profile = mysqli_fetch_assoc(mysqli_query($conn, "SELECT address FROM users WHERE user_id = $customer_id"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $order_type = clean($conn, $_POST['order_type']);
    $delivery_address = clean($conn, $_POST['delivery_address']);
    $payment_method = clean($conn, $_POST['payment_method']);
    $submitted_token = $_POST['order_token'] ?? '';

    if (!in_array($order_type, ['delivery', 'pickup'])) $order_type = 'delivery';
    if (!in_array($payment_method, ['cod', 'card'])) $payment_method = 'cod';
    if ($order_type === 'delivery' && $delivery_address === '') {
        $errors[] = "Please provide a delivery address.";
    }

    // One-time submission token: guards against duplicate orders from a double-click,
    // a slow double-submit, or hitting Back and resubmitting the form. A valid token
    // must be present in the session (i.e. this page was freshly loaded) and must match
    // what was rendered into the form. It's cleared immediately so it can only ever be
    // used once, whether the order succeeds or fails validation.
    if (empty($submitted_token) || !isset($_SESSION['order_token']) || !hash_equals($_SESSION['order_token'], $submitted_token)) {
        // Already-used or missing token: this is a resubmission of an order already
        // placed (or a stale/tampered form). Send them to their orders instead of
        // silently re-charging/re-creating anything.
        redirect('/foodorder/customer/order_tracking.php');
    }
    unset($_SESSION['order_token']);

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $sql = "INSERT INTO orders (customer_id, status, order_type, delivery_address, payment_method, total_amount)
                    VALUES ($customer_id, 'pending', '$order_type', '$delivery_address', '$payment_method', $total)";
            mysqli_query($conn, $sql);
            $order_id = mysqli_insert_id($conn);

            foreach ($cart_items as $item) {
                $item_id = $item['item_id'];
                $name = clean($conn, $item['item_name']);
                $price = $item['price'];
                $qty = $item['quantity'];
                $subtotal = $item['subtotal'];
                mysqli_query($conn, "INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity, subtotal)
                                      VALUES ($order_id, $item_id, '$name', $price, $qty, $subtotal)");
            }

            mysqli_query($conn, "INSERT INTO order_status_log (order_id, old_status, new_status, changed_by)
                                  VALUES ($order_id, NULL, 'pending', $customer_id)");

            mysqli_query($conn, "DELETE FROM cart WHERE customer_id = $customer_id");

            mysqli_commit($conn);
            redirect('/foodorder/customer/order_tracking.php?placed=' . $order_id);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Something went wrong placing your order. Please try again.";
        }
    }
}

// Issue a fresh one-time token whenever the form is about to be (re)displayed --
// on the initial GET, and again if a POST fell through to redisplay due to
// validation errors. This is what the hidden field below is checked against above.
$_SESSION['order_token'] = bin2hex(random_bytes(32));

$page_title = "Checkout";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Checkout</h2>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $err) echo "<div>" . htmlspecialchars($err) . "</div>"; ?>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-md-7">
    <form method="POST" id="checkoutForm" onsubmit="return disableSubmitButton()">
      <?php echo csrfField(); ?>
      <input type="hidden" name="order_token" value="<?php echo htmlspecialchars($_SESSION['order_token']); ?>">
      <div class="mb-3">
        <label class="form-label">Order Type</label>
        <select name="order_type" id="order_type" class="form-select" onchange="toggleAddress()">
          <option value="delivery">Delivery</option>
          <option value="pickup">Pickup</option>
        </select>
      </div>
      <div class="mb-3" id="address_field">
        <label class="form-label">Delivery Address</label>
        <input type="text" name="delivery_address" class="form-control" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Payment Method</label>
        <select name="payment_method" class="form-select">
          <option value="cod">Cash on Delivery / Pickup</option>
          <option value="card">Card</option>
        </select>
      </div>
      <button type="submit" id="placeOrderBtn" class="btn btn-success btn-lg w-100">Place Order</button>
    </form>
  </div>

  <div class="col-md-5">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Order Summary</h5>
        <ul class="list-group list-group-flush">
          <?php foreach ($cart_items as $item): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?php echo htmlspecialchars($item['item_name']); ?> &times; <?php echo $item['quantity']; ?></span>
              <span><?php echo formatPrice($item['subtotal']); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex justify-content-between mt-3 fw-bold fs-5">
          <span>Total</span>
          <span><?php echo formatPrice($total); ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleAddress() {
  const type = document.getElementById('order_type').value;
  document.getElementById('address_field').style.display = (type === 'delivery') ? 'block' : 'none';
}

// Belt-and-suspenders: stop a double-click from firing two submits in the same
// instant. The real protection is the server-side one-time order_token, since
// this can't stop a resubmit via browser Back/Forward or a replayed request.
function disableSubmitButton() {
  const btn = document.getElementById('placeOrderBtn');
  if (btn.disabled) return false; // already submitting, ignore extra clicks
  btn.disabled = true;
  btn.textContent = 'Placing order...';
  return true;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
