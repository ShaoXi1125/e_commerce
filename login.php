<?php

include 'config/db.php';

$error = "";

function write_log($message){
    $log_file = __DIR__ . '/config/login_log.txt';
    $date = date("Y-m-d H:i:s");
    $ip = $_SERVER['REMOTE_ADDR']?? 'unknown IP';
    $log_message = "[$date][IP: $ip] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

if(!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])){
    list($selector,$validator) = explode(':',$_COOKIE['remember_me']);
    $sql = "SELECT * FROM user_tokens WHERE selector = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s',$selector);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $token_row = $result->fetch_assoc();
        if(hash_equals($token_row['hashed_validator'], hash('sha256' ,$validator))){
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i',$token_row['user_id']);
            $stmt->execute();
            $user_result = $stmt->get_result();
            if($user_result->num_rows === 1){
                $user = $user_result->fetch_assoc();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                write_log("Auto Login Success: User Email {$user['email']} logged in via remember me. Role: {$user['role']}");
                header("Location: index.php");
                exit;
            }else{
                write_log("Auto Login Failed: No user found for user_id {$token_row['user_id']}.");
            }
        }
    }
}

try{
    if($_SERVER['REQUEST_METHOD']==="POST"){
        $email = $_POST['email'];
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
         if(!$stmt){
            throw new Exception("Database query failed: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->numrows === 1){
            $user = result->fetch_assoc();

            if(password_verify($password,$user['password'])){
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                if($remember){
                    $selector = bin2hex(random_bytes(6));
                    $validator = bin2hex(random_bytes(32));
                    $hashed_validator = hash('sha256', $validator);
                    $expiry = date("Y-m-d H:i:s", time() + (7 * 24 * 60 * 60));

                    $sql = "INSERT INTO user_token (user_id, selector, hashed_validator, expires_at) VALUES(?,?,?,?)";
                    $stmt_token = $conn->prepare($sql);
                    $stmt_token->bind_param('isss',$user['id'],$selector,$hashed_validator,$expiry);
                    $stmt_token->execute();

                    setcookie('remember_me', $selector. ":".$validator,[
                        'expires' => time() + 7 *24 *60 * 60,
                        'path' => '/',
                        'httponly' => true,
                        'secure' => isset($_SERVER['HTTPS']),
                        'samesite' => 'Lax'
                    ]);
                }

             write_log("Login Success: User Email {$user['email']} logged in successfully. Role: {$user['role']}");
 
                switch($user['role']){
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        exit();
                    default:
                        header("Location: index.php");
                        exit();
                }
                exit;
            }else{
                $error = "Invalid password.";
                write_log("Login Failed: Incorrect password for email $email.");
            }   
        }else{
            $error = "No account found with that email.";
            write_log("Login Failed: No account found for email $email.");
        }
    }
}catch(Exception $e){
    $error = "An error occurred. Please try again later.";
    write_log("Error: " . $e->getMessage());
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyShop | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body class="bg-light text-dark">
    <div class="container d-flex justify-content-center align-items-center"style="height: 100vh;">
        <div class="card shadow p-4 bg-white text-dark" style="width:400px;">
            <h3 class="text-center mb-4">Login</h3>

            <?php if($error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                 <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember Me</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <p style="text-align:center; margin-top:10px;">Don't have an account? <a href="register.php">Register here</a></p>
            <p style="text-align:center; margin-top:10px;"><a href="#forget_pass">Forget Password</a></p>
        </div>
    </div>
</body>
</html>