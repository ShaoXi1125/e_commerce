<?php
require_once 'config/config.php';

$user = null;

if(isset($_GET['token'])){
    $token = $_GET['token'];

    // 1. Fetch user first
    $stmt = $pdo->prepare("SELECT UserId, ResetTokenExpiry FROM Users WHERE ResetToken = :token LIMIT 1");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if(!$user || $user['ResetTokenExpiry'] < date("Y-m-d H:i:s")){
        die("Invalid or expired token.");
    }

    // 2. Only process update if form is submitted
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if($new_password !== $confirm_password){
            $error = "Passwords do not match.";
        } else {
            $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE Users SET PasswordHash = :password, ResetToken = NULL, ResetTokenExpiry = NULL WHERE UserId = :user_id");
            $update_stmt->execute([':password' => $passwordHash, ':user_id' => $user['UserId']]);
            
            die("Password has been reset successfully. <a href='login.php'>Login here</a>");
        }
    }
} else {
    die("No token provided.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce || Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    <?php if(isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST">
        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" required>
        <br><br>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <br><br>
        <input type="submit" value="Reset Password">
    </form>
</body>
</html>