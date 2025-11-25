<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Shop</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link rel="stylesheet" href="css/style.css">

  <style>
    /* Top bar */
    .top-bar {
      background-color: #000000ff;
      color: #000000ff;
      font-size: 0.9rem;
      padding: 4px 0;
      position: relative;
      z-index: 1000;
    }
    .top-bar a {
      color: #fff;
      text-decoration: none;
      margin: 0 10px;
    }
    .top-bar a:hover {
      text-decoration: underline;
    }

    /* Main nav */
    .main-nav {
      background-color: #fff;
      padding: 10px 0;
      border-bottom: 1px solid #ddd;
    }

    .logo img {
      height: 45px;
    }

    /* Search bar */
    .search-bar input {
      border-radius: 0.25rem 0 0 0.25rem;
      border: 2px solid #2c2c2c;
    }
    .search-bar button {
      background-color: #2c2c2c;
      color: white;
      border: 2px solid #2c2c2c;
      border-radius: 0 0.25rem 0.25rem 0;
      font-weight: bold;
    }

    .cart-btn {
      color: #2c2c2c;
      font-size: 1.2rem;
      font-weight: 600;
      text-decoration: none;
      position: relative;
    }
    .cart-btn span {
      position: absolute;
      top: -8px;
      right: -10px;
      background: #2c2c2c;
      color: #fff;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 0.7rem;
    }

   .dropdown-menu {
      background: #fff !important;
      color: #000 !important;
    }

    .dropdown-item {
        color: #000 !important;
    }
    .dropdown-item:hover {
        background: #f2f2f2 !important;
    }

    /* Mobile adjustments */
    @media (max-width: 768px) {
      .search-bar {
        width: 100%;
        margin-top: 10px;
      }
    }
  </style>
</head>
<body>
