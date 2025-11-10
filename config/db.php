<?php

session_start();

$localhost = 'localhost';
$server = 'root';
$password = '123qwe';
$database = 'ecommerce_db';

$conn = new mysqli($localhost, $server, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>