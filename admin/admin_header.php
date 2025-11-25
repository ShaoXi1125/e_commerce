<?php

include 'admin_auth.php';

?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
    /* Top Navbar (Grey Theme) */
    .top-nav {
        background: #3a3a3a; /* 深灰 */
        color: white;
        height: 60px;
        display: flex;
        align-items: center;
        padding: 0 20px;
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
        background: #2b2b2b; /* 更深一点 */
        height: 100vh;
        position: fixed;
        top: 60px;
        left: 0;
        padding-top: 10px;
    }

    .sidebar a {
        color: #ccc;
        padding: 12px 20px;
        display: block;
        text-decoration: none;
        font-size: 15px;
    }

    .sidebar a:hover {
        background: #3c3c3c;
        color: white;
    }

    /* Active Menu */
    .sidebar .active {
        background: #5a5a5a; /* 灰色高亮 */
        color: white !important;
    }

    /* Content */
    .content-wrapper {
        margin-left: 260px;
        margin-top: 60px;
        padding: 20px;
        color: #333;
    }
</style>

<!-- TOP NAV -->
<div class="top-nav d-flex justify-content-between">
    <div class="d-flex align-items-center">
        <strong style="font-size: 20px;">Admin Panel</strong>
    </div>

    <div class="d-flex align-items-center">
        <i class="bi bi-bell me-4 fs-4"></i>

        <div class="dropdown">
            <a class="text-white dropdown-toggle" href="#" data-bs-toggle="dropdown">
                Admin 👤
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="admin_dashboard.php" class="active">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>

    <a href="products.php">
        <i class="bi bi-box-seam me-2"></i> Products
    </a>

    <a href="categories.php">
        <i class="bi bi-tags me-2"></i> Categories
    </a>

    <a href="orders.php">
        <i class="bi bi-receipt me-2"></i> Orders
    </a>

    <a href="users.php">
        <i class="bi bi-people me-2"></i> Users
    </a>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
