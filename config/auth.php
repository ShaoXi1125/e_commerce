<?php

if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo "Access denied. You do not have permission to access this page.";
    exit();
}

?>