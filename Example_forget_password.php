<?php

include_once 'config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if($_SERVER['REQUEST_METHOD']=="POST"){

    $email = $_POST['email'];

    $sql = "SELECT * FROM Users u JOIN Roles r 
            ON u.RoleId = r.RoleId WHERE u.Email = :email 
            AND r.RoleName = 'Member' LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch();

    if($user){
        $token = bin2hex(random_bytes(16));
        $expiry_time = date("Y-m-d H:i:s", strtotime("+ 5 minutes"));

        $update_sql = "UPDATE Users SET ResetToken = :token,
                       ResetTokenExpiry = :expiry WHERE Email = :email";
        $update = $pdo ->prepare($update_sql);
        $update->execute([
            ':token' => $token,
            ':expiry' => $expiry_time,
            ':email' => $user['Email']
        ]);

        $mail = new PHPMailer(true);

        try{
            $mail->isSMTP();
            $mail->Host = 'smtp.exmple.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'yourexample.com';
            $mail->Password = 'yourpassword';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('yourexample.com','E-commerce');
            $mail->addAddress($email);

            $resetLink = $base_url ."/reset_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "Click the link to reset your password: <a href='$resetLink'>$resetLink</a>";
            $mail->AltBody = "The link will expire in 5 minutes.";

            $mail->send();
            echo json_encode(["message" => "Password reset link has been sent to your email."]);           
        
        }catch(Exception $e){
            echo json_encode(["message" => "Failed to send email. Please try again later."]);
        }
    }else{
        http_response_code(404);
        echo json_encode(["message" => "Email not found."]);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce || Forget Password</title>
</head>
<body>
    <h1>Forget Password</h1>
    <div>
        <form action="forget_password.php" method="post">
            <div>
                <label for="email">Enter your registered email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <br>
            <button type="submit">Send Reset Link</button>
        </form>
    </div>
</body>
</html>