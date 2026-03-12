<?php

require_once 'config/config.php';

if(isset($_GET['token'])){
    $token = $_GET['token'];

    $sql = "SELECT * FROM Users WHERE ResetToken = :token 
            AND ResetTokenExpiry > NOW() LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['token' =>$token]);
    $user = $stmt->fetch();

    if(!$user){
        die("Invalid or expired token.");
    }

    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if($new_password !== $confirm_password){
            die("Passwords do not match.");
        }

        $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);

        $update_sql = "UPDATE Users SET PasswordHash = :password, 
                       ResetToken = NULL, ResetTokenExpiry = NULL 
                       WHERE UserId = :user_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':password' => $passwordHash,
            ':user_id' => $user['UserId']
        ]);

        echo "Password has been reset successfully.";
    }
}

?>