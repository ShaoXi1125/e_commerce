<?php 

include_once '../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 1. 查询用户及其关联的角色名称
    $sql = "SELECT u.UserId, u.PasswordHash, r.RoleName 
            FROM Users u 
            JOIN Roles r ON u.RoleId = r.RoleId 
            WHERE u.Email = :email LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. 验证密码哈希
        if (password_verify($password, $user['PasswordHash'])) {
            
            // 3. 核心权限校验：如果是 Member 尝试登录 Admin 页面
            if ($user['RoleName'] !== 'Admin') {
                // 返回 403 权限不足状态码
                http_response_code(403);
                echo json_encode(["message" => "Error: You do not have administrator privileges to access this page."]);
                exit;
            }

            // 4. 登录成功，设置管理员 Session
            $_SESSION['admin_id'] = $user['UserId'];
            $_SESSION['role'] = $user['RoleName'];
            
            echo json_encode(["message" => "Login Success....."]);
            // header("Location: admin_dashboard.php");
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid email or password."]);
        }
    } else {
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
    <title>Document</title>
</head>
<body>
    <form action="login.php" method="post">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <button type="submit">Login</button>
    </form>
</body>
</html>