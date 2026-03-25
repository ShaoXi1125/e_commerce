 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #212529; color: white; }
        .sidebar a { color: rgba(255,255,255,.8); text-decoration: none; padding: 10px 20px; display: block; }
        .sidebar a:hover { background: #343a40; color: white; }
        .sidebar a.active { background: #0d6efd; color: white; }
        .stat-card { border: none; border-radius: 12px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>

<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-0">
    <div class="p-3 mb-4 text-center">
        <h4 class="fw-bold">E-commerce</h4>
        <small class="text-muted">Admin Panel</small>
    </div>
    <div class="nav flex-column">
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a href="category.php"><i class="bi bi-list me-2"></i> Category Management</a>
        <a href="product_management.php"><i class="bi bi-box-seam me-2"></i> Product Management</a>
        <a href="member_management.php"><i class="bi bi-people me-2"></i> Member Management</a>
        <a href="order_management.php"><i class="bi bi-cart-check me-2"></i> Order Management</a>
        <hr class="mx-3">
        <a href="admin_logout.php" class="text-danger"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>
</nav>

<script>
    // Highlight active link based on current URL
    document.querySelectorAll('.sidebar a').forEach(link => {
        if (link.href === window.location.href) {
            link.classList.add('active');
        }
    });
</script>