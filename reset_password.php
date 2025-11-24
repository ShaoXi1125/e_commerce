<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

// Helper: find token row
function findTokenRow($conn, $selector) {
    $sql = "SELECT * FROM password_resets WHERE selector = ? AND expires_at > NOW() LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows === 1) ? $res->fetch_assoc() : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selector = $_POST['selector'] ?? '';
    $validator = $_POST['validator'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($selector) || empty($validator) || empty($password)) {
        $error = 'Invalid request.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $row = findTokenRow($conn, $selector);
        if (!$row) {
            $error = 'Invalid or expired token.';
        } else {
            $hashed = $row['hashed_token'];
            if (hash_equals($hashed, hash('sha256', $validator))) {
                // Update user password
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param('si', $newHash, $row['user_id']);
                $update->execute();

                // delete all reset tokens for this user
                $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $del->bind_param('i', $row['user_id']);
                $del->execute();

                $success = 'Password updated successfully. You can now <a href="login.php">login</a>.';
            } else {
                $error = 'Invalid token.';
            }
        }
    }
}

// If GET with selector+validator, show form
$selector = $_GET['selector'] ?? ($_POST['selector'] ?? '');
$validator = $_GET['validator'] ?? ($_POST['validator'] ?? '');

?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/header.php'; ?>

<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h3>Reset Password</h3>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="post" action="reset_password.php">
        <input type="hidden" name="selector" value="<?= htmlspecialchars($selector) ?>">
        <input type="hidden" name="validator" value="<?= htmlspecialchars($validator) ?>">
        <div class="mb-3">
          <label for="password" class="form-label">New password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
          <label for="password2" class="form-label">Confirm password</label>
          <input type="password" class="form-control" id="password2" name="password2" required>
        </div>
        <button type="submit" class="btn btn-primary">Set new password</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>
