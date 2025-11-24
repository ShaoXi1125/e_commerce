<?php

include_once "config/db.php";

$error = "";
$success = "";



try{
    if($_SERVER['REQUEST_METHOD'] === "POST"){
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_pass'];
        $phone = $_POST['phone'];

        if(empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($phone)){
            throw new Exception("All field are required.");
        }

        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
            throw new Exception("Invalid email format.");
        }

       
        if (!preg_match('/^(011[0-9]{8}|01[0-9][0-9]{7})$/', $phone)) {
            echo "Invalid phone number";
        } else {
            echo "Valid phone number";
        }

        if($password !== $confirm_password){
            throw new Exception ("Passwords do not match.");
        }

        $checkSQL = "SELECT * FROM users WHERE email = ?";
        $checkStmt = $conn->prepare($checkSQL);
        $checkStmt->bind_param('s',$email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if($checkStmt->num_rows > 0){
            throw new Exception("Account already registered.");
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);
        $role = "customer";
        $status = "active";

        $insertSQL = "INSERT INTO users (`name`,`email`,`password`,`phone`,`role`,`status`,`created_at`) 
                        VALUES (?,?,?,?,?,?,NOW())";
        $insertSQL = $conn->prepare($insertSQL);
        $insertSQL->bind_param("ssssss", $name,$email,$hashedPass,$phone,$role,$status);

        if(!$insertSQL->execute()){
            throw new Exception ("Database insert failed: " . $conn->error);
        }

        $success = "Account registered successfully. You can now login.";
    }
}catch(Exception $e){
    $error = $e->getMEssage();

    $logMsg = "[". date('Y-m-d H:i:s')."] REGISTER ERROR: " .$error." | Email: ".($email ?? '-')."\n";
    file_put_content('error_log.txt', $logMsg, FILE_APPEND);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Document</title>
</head>

<body class="bg-light text-dark">
    <div class="container d-flex justify-content-center align-items-center"style="height: 100vh;">
        <div class="card shadow p-4 bg-white text-dark" style="width:400px;">
            <h3 class="text-center mb-4">Login</h3>

            <?php if($error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter your name" required>
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                 <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
                 <div class="mb-3">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_pass" class="form-control" placeholder="Confirm your password" required>
                </div>
                 <div class="mb-3">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="012-3456789" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            </form>
            <p style="text-align:center; margin-top:10px;">Have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>

</html>