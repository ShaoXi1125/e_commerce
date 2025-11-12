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

        if(empty($name) || empty($email) || empty($password) || empty($confirm_password)){
            throw new Exception("All field are required.");
        }

        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
            throw new Exception("Invalid email format.");
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
        $insertSQL->bind_param("ssssss", $name,$email,$hashedPass,$role,$status);

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