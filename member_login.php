<?php

include_once 'config/config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 查询用户及其关联的角色名称
    $sql = "SELECT * 
            FROM Users u 
            JOIN Roles r ON u.RoleId = r.RoleId 
            WHERE u.Email = :email LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user){
        if($user['IsActive'] == 0){
            die(json_encode(["message" => "Your account is inactive. Please contact support."]));
        }
        if(password_verify($password, $user['PasswordHash'])){
            // 登录成功，设置 Session
            $_SESSION['user_id'] = $user['UserId'];
            $_SESSION['role'] = $user['RoleName'];
            
            echo json_encode(["message" => "Login Success....."]);
            header("Location: index.php");
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid email or password."]);
        }
    }else {
            http_response_code(401);
            echo json_encode(["message" => "User does not exist."]);
        }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce || Member Login </title>
</head>
<body>
    <h2>Member Login</h2>
    <form action="member_login.php" method="post">
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <br>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <br>
        <button type="submit">Login</button>
        <a href="forget_password.php">Forgot Password?</a>
    </form>
</body>
</html>