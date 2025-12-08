<?php

include 'config/db.php';

$error = "";
$success = "";

# Function to write logs
function write_log($message){
    $log_file = __DIR__ . '/config/forgot_password_log.txt';
    $date = date("Y-m-d H:i:s");
    $ip = $_SERVER['REMOTE_ADDR']?? 'unknown IP';
    $log_message = "[$date][IP: $ip] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

if($_SERVER['REQUEST_METHOD'] === "POST"){
    $email = $_POST['email'];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($stmt->num_rows === 0){
        $error = "No account found with that email address.";
        write_log("Failed password reset attempt for email: $email");   
    }else{
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())");
        // $stmt2->bind_param("ss", $email, $token);
        // $stmt2->execute();

        echo $reset_link = "http://yourdomain.com/reset_password.php?token=$token&email=" . urlencode($email);
        
        //TODO: integrate PHPMailer

        $success = "A password reset link has been sent to your email.";
        write_log("Password reset link generated for email: $email");
    
    }



}


?>

 <style>
        body {
            background-color: #f4f4f4;
        }

        .reset-card {
            max-width: 450px;
            margin: 60px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.08);
        }

        .reset-card h3 {
            font-weight: 700;
            margin-bottom: 15px;
        }

        .btn-dark {
            background-color: #2c2c2c;
            border: none;
        }

        .btn-dark:hover {
            background-color: #000;
        }

        a {
            text-decoration: none;
        }
    </style>

    <div class="reset-card">
    <h3>Forgot Password</h3>
    <p class="text-muted">Enter your email and we will send you a reset link.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required
                   placeholder="example@email.com">
        </div>

        <button type="submit" class="btn btn-dark w-100">Send Reset Link</button>

        <div class="text-center mt-3">
            <a href="login.php" class="text-secondary">Back to Login</a>
        </div>
    </form>
</div>
