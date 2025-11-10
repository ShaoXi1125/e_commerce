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
    <title>Document</title>
</head>
<body>
    
</body>
</html>