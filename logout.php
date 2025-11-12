<?php

    include_once 'db.php';

    if(isset($_SESSION['user_id'])){
        if(isset($_COOKIE['remember_me'])){
            list($selector,) = explode(':', $_COOKIE['remember_me']);
            $stmt= $conn->prepare("DELETE FROM user_token WHERE selector = ?");
            $stmt->bind_param('s',$selector);
            $stmt->execute();
            setcookie('remember_me','',time()-3600,'/');
        }
    }

    session_destroy();
    header("Location:index.php");
    exit; 

?>