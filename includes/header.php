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
  <title>Shopee Style Navbar</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Top bar 样式 */
    .top-bar {
      background-color: #2c2c2cff;
      color: #fff;
      font-size: 0.9rem;
      padding: 4px 0;
    }
    .top-bar a {
      color: #fff;
      text-decoration: none;
      margin: 0 10px;
    }
    .top-bar a:hover {
      text-decoration: underline;
    }

    /* 主导航 */
    .main-nav {
      background-color: #fff;
      padding: 10px 0;
      border-bottom: 1px solid #ddd;
    }

    .logo img {
     
      height: 45px;
    }

    /* 搜索框样式 */
    .search-bar input {
      border-radius: 0.25rem 0 0 0.25rem;
      border: 2px solid #2c2c2cff;
    }
    .search-bar button {
      background-color: #2c2c2cff;
      color: white;
      border: 2px solid #2c2c2cff;
      border-radius: 0 0.25rem 0.25rem 0;
      font-weight: bold;
    }
    .search-bar button:hover {
      background-color: #2c2c2cff;
      border-color: #2c2c2cff;
    }

    /* Cart */
    .cart-btn {
      color: #2c2c2cff;
      font-size: 1.2rem;
      font-weight: 600;
      text-decoration: none;
      position: relative;
    }
    .cart-btn span {
      position: absolute;
      top: -8px;
      right: -10px;
      background: #2c2c2cff;
      color: #fff;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 0.7rem;
    }

    /* Dropdown 样式 */
    .dropdown-menu a {
      color: #333 !important;
    }
  </style>
</head>
<body>

<!-- 顶部橘色导航 -->
<div class="top-bar">
  <div class="container d-flex justify-content-between align-items-center">
    <!-- 左边 -->
    <div>
      <a href="#">Help Center</a> |
      <a href="#">English / 中文</a>
    </div>
    <!-- 右边 -->
    <div>
      <a href="#">Notifications</a> |
      <a href="#">My Orders</a> |
      <a href="cart.php">Cart</a> |
      <?php if (isset($_SESSION['user'])): ?>
        <div class="dropdown d-inline">
          <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
            👋 <?= htmlspecialchars($_SESSION['user']['name']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
              <li><a class="dropdown-item" href="admin_dashboard.php">Admin Dashboard</a></li>
              <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="login.php">Login</a> / <a href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 主导航 -->
<nav class="main-nav">
  <div class="container d-flex flex-wrap justify-content-between align-items-center">
    <!-- 左：Logo -->
    <div class="logo">
      <a href="index.php">
        <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Shopee_logo.svg" alt="Logo">
      </a>
    </div>

    <!-- 中：搜索框 -->
    <form class="d-flex flex-grow-1 mx-3 search-bar" action="search.php" method="get">
      <input class="form-control" type="search" name="q" placeholder="Search for products, brands and more..." required>
      <button type="submit" class="btn">Search</button>
    </form>

    <!-- 右：购物车 -->
    <a href="cart.php" class="cart-btn">
      🛒
      <span>0</span>
    </a>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
