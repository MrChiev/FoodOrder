<?php
$allowed_roles = ['customer'];
require_once __DIR__ . '/../includes/auth_check.php';

$customer_id = $_SESSION['user_id'];

// Handle quantity update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    $cart_id = (int)$_POST['cart_id'];

    if ($_POST['action'] === 'update') {
        $quantity = min(20, max(1, (int)$_POST['quantity']));
        mysqli_query($conn, "UPDATE cart SET quantity = $quantity WHERE cart_id = $cart_id AND customer_id = $customer_id");
    } elseif ($_POST['action'] === 'remove') {
        mysqli_query($conn, "DELETE FROM cart WHERE cart_id = $cart_id AND customer_id = $customer_id");
    }
    redirect('/foodorder/customer/cart.php');
}

$sql = "SELECT c.cart_id, c.quantity, m.item_id, m.item_name, m.price, m.image, m.is_available
        FROM cart c
        JOIN menu_items m ON c.item_id = m.item_id
        WHERE c.customer_id = $customer_id
        ORDER BY c.added_at DESC";
$result = mysqli_query($conn, $sql);

$cart_items = [];
$total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $row['subtotal'] = $row['price'] * $row['quantity'];
    // Unavailable items are shown so the customer knows what happened, but
    // aren't counted toward the total -- checkout won't charge for them either.
    if ($row['is_available']) {
        $total += $row['subtotal'];
    }
    $cart_items[] = $row;
}

$page_title = "Your Cart";
include __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Your Cart</h2>

<?php if (isset($_SESSION['cart_notice'])): ?>
  <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['cart_notice']); unset($_SESSION['cart_notice']); ?></div>
<?php endif; ?>

<?php if (empty($cart_items)): ?>
  <div class="alert alert-info">
    Your cart is empty. <a href="menu.php">Browse the menu</a> to add items.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Item</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cart_items as $item): ?>
          <tr class="<?php echo $item['is_available'] ? '' : 'opacity-50'; ?>">
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?php echo $item['image'] ? '/foodorder/assets/uploads/' . htmlspecialchars($item['image']) : placeholderImage(60, 60); ?>" width="60" height="60" style="object-fit:cover;" class="rounded">
                <div>
                  <?php echo htmlspecialchars($item['item_name']); ?>
                  <?php if (!$item['is_available']): ?>
                    <div class="small text-danger">No longer available</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><?php echo formatPrice($item['price']); ?></td>
            <td style="max-width:100px;">
              <?php if ($item['is_available']): ?>
                <form method="POST" class="d-flex gap-1">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="20" class="form-control form-control-sm">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                </form>
              <?php else: ?>
                <?php echo $item['quantity']; ?>
              <?php endif; ?>
            </td>
            <td><?php echo $item['is_available'] ? formatPrice($item['subtotal']) : '&mdash;'; ?></td>
            <td>
              <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                <input type="hidden" name="action" value="remove">
                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center border-top pt-3">
    <h4>Total: <?php echo formatPrice($total); ?></h4>
    <a href="checkout.php" class="btn btn-success btn-lg">Proceed to Checkout</a>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
