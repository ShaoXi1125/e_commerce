<?php

session_start();

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '123qwe');
define('DB_NAME', 'e_commerce_db');

function getPDOConnection(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
    }
}

$pdo = getPDOConnection();


$protocal = (!empty($_SERVER['HTTPS']) 
            && $_SERVER['HTTPS'] !=='off' ) ? 'https://' : 'http://';

$domain = $_SERVER['HTTP_HOST'];

$base_url = $protocal . $domain;
?>

