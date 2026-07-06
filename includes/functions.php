<?php
// Shared helper functions

if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie. Must be set before session_start().
    // - HttpOnly: JavaScript (including injected XSS) can't read the cookie.
    // - SameSite=Lax: the cookie isn't sent on cross-site POSTs (e.g. a form
    //   auto-submitted from another site), which also backstops CSRF protection.
    //   Lax (not Strict) so a normal top-level link into the site still carries
    //   the session, rather than dumping the user back to a logged-out state.
    // - Secure: only sent over HTTPS, when the site is actually served over HTTPS.
    //   Detected rather than hardcoded so local HTTP development still works.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// --- Security headers (defense in depth, sent on every response) ---
if (!headers_sent()) {
    // Stop the site being loaded in an <iframe> on another domain -- blocks
    // clickjacking attacks that overlay invisible buttons on top of this UI.
    header('X-Frame-Options: SAMEORIGIN');
    // Stop the browser guessing a file's type from its content instead of
    // trusting the Content-Type header (used in some XSS/upload attacks).
    header('X-Content-Type-Options: nosniff');
    // Don't leak the full referring URL (which can contain query params) to
    // other sites when a link is followed.
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content-Security-Policy: only allow scripts/styles/fonts from this site,
    // the CDN, and Google Fonts. 'unsafe-inline' is needed because the app
    // uses inline onclick/onchange handlers and <script> blocks throughout --
    // tightening that further would mean rewriting every page to use nonces
    // or external JS files, which is a bigger change than a header tweak.
    // Even with 'unsafe-inline', this still blocks loading script/CSS/fonts
    // from any domain other than the ones explicitly listed below.
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com; "
         . "img-src 'self' data: https://flagcdn.com; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "frame-ancestors 'self';");
}

// Sanitize user input before using in queries/output
function clean($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

// --- CSRF protection ---
// Every form that triggers a state-changing action (POST) must include this
// token, and every handler for such a form must call verifyCsrfToken() before
// doing anything. The token is tied to the session and stays constant for the
// life of the session (regenerated on login) so it works fine with multiple
// tabs open at once.

// Get the current session's CSRF token, creating one if none exists yet.
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Convenience: echo this inside any <form method="POST"> that changes state.
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// Call at the top of any POST handler that changes state. Halts the request
// with 403 if the token is missing or doesn't match the session's token --
// which is exactly what happens when the POST didn't originate from our own
// form (e.g. a hidden auto-submitting form on another site).
function verifyCsrfToken() {
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($submitted) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Your session has expired or this request could not be verified. Please go back, refresh the page, and try again.');
    }
}

// Check if a user is currently logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if logged-in user has a specific role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Redirect helper
function redirect($path) {
    header("Location: " . $path);
    exit();
}

// Format a number as USD currency
function formatPrice($amount) {
    return '<span class="money">$' . number_format((float)$amount, 2) . '</span>';
}

// Inline SVG placeholder (palette-matched) for menu items with no uploaded image.
// Replaces the discontinued via.placeholder.com service.
function placeholderImage($width = 400, $height = 180) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
         . '<rect width="100%" height="100%" fill="#1F2732"/>'
         . '<g stroke="#2B3440" stroke-width="1">'
         . '<line x1="0" y1="0" x2="' . $width . '" y2="' . $height . '"/>'
         . '<line x1="' . $width . '" y1="0" x2="0" y2="' . $height . '"/>'
         . '</g>'
         . '<circle cx="' . ($width / 2) . '" cy="' . ($height / 2) . '" r="' . (min($width, $height) * 0.14) . '" fill="none" stroke="#2FA88C" stroke-width="1.5"/>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// --- Login rate limiting (brute-force protection, keyed by username) ---

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 5;

// Self-provision the login_attempts table so existing installs don't need a manual migration.
function ensureLoginAttemptsTable($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_attempts (
        username      VARCHAR(50) PRIMARY KEY,
        failed_count  INT NOT NULL DEFAULT 0,
        locked_until  DATETIME DEFAULT NULL,
        updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
}

// Returns remaining lockout minutes (int, >0) if this username is currently locked out, else null.
function getLoginLockoutMinutes($conn, $username) {
    $safe = mysqli_real_escape_string($conn, $username);
    // TIMESTAMPDIFF is computed entirely by MySQL using its own NOW() — this avoids
    // ever comparing MySQL's clock to PHP's clock, which can silently differ by
    // hours if the two are configured with different timezones.
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS remaining_seconds
         FROM login_attempts
         WHERE username = '$safe' AND locked_until IS NOT NULL AND locked_until > NOW()"));
    if (!$row) return null;
    return max(1, (int)ceil($row['remaining_seconds'] / 60));
}

// Record a failed login attempt; locks the account once LOGIN_MAX_ATTEMPTS is reached.
function recordFailedLogin($conn, $username) {
    $safe = mysqli_real_escape_string($conn, $username);
    mysqli_query($conn, "INSERT INTO login_attempts (username, failed_count)
                          VALUES ('$safe', 1)
                          ON DUPLICATE KEY UPDATE failed_count = failed_count + 1");
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT failed_count FROM login_attempts WHERE username = '$safe'"));
    if ($row && $row['failed_count'] >= LOGIN_MAX_ATTEMPTS) {
        mysqli_query($conn, "UPDATE login_attempts
                              SET locked_until = DATE_ADD(NOW(), INTERVAL " . LOGIN_LOCKOUT_MINUTES . " MINUTE), failed_count = 0
                              WHERE username = '$safe'");
    }
}

// Clear any failed-attempt history for a username after a successful login.
function clearFailedLogins($conn, $username) {
    $safe = mysqli_real_escape_string($conn, $username);
    mysqli_query($conn, "DELETE FROM login_attempts WHERE username = '$safe'");
}

// --- Pagination ---
const PAGINATE_PER_PAGE = 10;

// Returns [$page, $offset, $limit] from $_GET['page'], clamped to a sane minimum.
function paginateParams() {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * PAGINATE_PER_PAGE;
    return [$page, $offset, PAGINATE_PER_PAGE];
}

// Renders Prev/Next + page-count controls. $base_url should already include any
// non-page query params (e.g. "manage_menu.php" or "menu.php?category=3").
function renderPagination($page, $total_rows, $base_url) {
    $total_pages = max(1, (int)ceil($total_rows / PAGINATE_PER_PAGE));
    if ($total_pages <= 1) return;

    $sep = strpos($base_url, '?') !== false ? '&' : '?';
    echo '<nav class="d-flex justify-content-between align-items-center mt-3">';
    echo '<span class="small text-muted">Page ' . $page . ' of ' . $total_pages . ' &middot; ' . $total_rows . ' total</span>';
    echo '<div class="btn-group">';
    if ($page > 1) {
        echo '<a href="' . $base_url . $sep . 'page=' . ($page - 1) . '" class="btn btn-sm btn-outline-secondary">&laquo; Prev</a>';
    } else {
        echo '<span class="btn btn-sm btn-outline-secondary disabled">&laquo; Prev</span>';
    }
    if ($page < $total_pages) {
        echo '<a href="' . $base_url . $sep . 'page=' . ($page + 1) . '" class="btn btn-sm btn-outline-secondary">Next &raquo;</a>';
    } else {
        echo '<span class="btn btn-sm btn-outline-secondary disabled">Next &raquo;</span>';
    }
    echo '</div></nav>';
}

// Verify an uploaded file is actually an image (checks real content via getimagesize,
// not just the filename extension, which is trivial to fake).
function isValidImageUpload($tmp_path) {
    $info = @getimagesize($tmp_path);
    if ($info === false) {
        return false;
    }
    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    return in_array($info[2], $allowed_types, true);
}

// Ensure the uploads directory exists and is writable before we try to move a file into it.
// Returns null on success, or an error message string on failure.
function ensureUploadsDir($uploadsDir) {
    if (!is_dir($uploadsDir)) {
        if (!@mkdir($uploadsDir, 0755, true)) {
            return "Upload folder is missing and couldn't be created automatically. Create the folder at "
                 . $uploadsDir . " and make sure the web server can write to it.";
        }
    }
    if (!is_writable($uploadsDir)) {
        return "Upload folder exists but isn't writable: " . $uploadsDir
             . ". Check its permissions and try again.";
    }
    return null;
}

// Get the number of items currently in the logged-in customer's cart
function getCartCount($conn) {
    if (!isLoggedIn()) return 0;
    $customer_id = $_SESSION['user_id'];
    $sql = "SELECT SUM(c.quantity) AS total
            FROM cart c
            JOIN menu_items m ON c.item_id = m.item_id
            WHERE c.customer_id = $customer_id AND m.is_available = 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ? (int)$row['total'] : 0;
}

// List of countries with dial code + ISO code (for flag images), used for the phone number country picker
function getCountryDialCodes() {
    return [
        ['name' => 'Cambodia',       'code' => '+855', 'iso' => 'kh'],
        ['name' => 'Thailand',       'code' => '+66',  'iso' => 'th'],
        ['name' => 'Vietnam',        'code' => '+84',  'iso' => 'vn'],
        ['name' => 'Laos',           'code' => '+856', 'iso' => 'la'],
        ['name' => 'Myanmar',        'code' => '+95',  'iso' => 'mm'],
        ['name' => 'Malaysia',       'code' => '+60',  'iso' => 'my'],
        ['name' => 'Singapore',      'code' => '+65',  'iso' => 'sg'],
        ['name' => 'Indonesia',      'code' => '+62',  'iso' => 'id'],
        ['name' => 'Philippines',    'code' => '+63',  'iso' => 'ph'],
        ['name' => 'China',          'code' => '+86',  'iso' => 'cn'],
        ['name' => 'Japan',          'code' => '+81',  'iso' => 'jp'],
        ['name' => 'South Korea',    'code' => '+82',  'iso' => 'kr'],
        ['name' => 'India',          'code' => '+91',  'iso' => 'in'],
        ['name' => 'Australia',      'code' => '+61',  'iso' => 'au'],
        ['name' => 'United Kingdom', 'code' => '+44',  'iso' => 'gb'],
        ['name' => 'France',         'code' => '+33',  'iso' => 'fr'],
        ['name' => 'Germany',        'code' => '+49',  'iso' => 'de'],
        ['name' => 'Canada',         'code' => '+1',   'iso' => 'ca'],
        ['name' => 'United States',  'code' => '+1',   'iso' => 'us'],
    ];
}
?>
