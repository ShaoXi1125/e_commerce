<?php

    include_once '../config/config.php';
    include_once '../config/auth.php';

    try{
        //Total Users
        $sqlUser = "SELECT COUNT(*) FROM Users 
                    u Join Roles r on u.RoleId = r.RoleId 
                    WHERE r.RoleName IN ('Member', 'Non-member')";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute();
        $totalUsers = $stmtUser->fetchColumn();

        //Total Products
        $sqlProduct = "SELECT COUNT(*) FROM products";
        $stmtProduct = $pdo->prepare($sqlProduct);
        $stmtProduct->execute();
        $totalProducts = $stmtProduct->fetchColumn();

        //Total Orders
        $sqlOrder = "SELECT COUNT(*) FROM orders";
        $stmtOrder = $pdo->prepare($sqlOrder);
        $stmtOrder->execute();
        $totalOrders = $stmtOrder->fetchColumn();

    }catch(Exception $e){
        die("Error fetching dashboard data: " . $e->getMessage());
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeng Store - Admin Dashboard</title>
   
</head>
<body>

<div class="container-fluid">
    <div class="row">
      <?php include '../layout/admin_nav.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Overview</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-primary p-2">Admin: <?php echo $_SESSION['email'] ?? 'Admin'; ?></span>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm bg-white p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                <i class="bi bi-people text-primary fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted mb-0 small">Total Members</p>
                                <h3 class="fw-bold mb-0"><?php echo number_format($totalUsers); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm bg-white p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="bi bi-box-seam text-success fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted mb-0 small">Products Online</p>
                                <h3 class="fw-bold mb-0"><?php echo number_format($totalProducts); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stat-card shadow-sm bg-white p-3">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                                <i class="bi bi-cart-check text-warning fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted mb-0 small">Total Orders</p>
                                <h3 class="fw-bold mb-0"><?php echo number_format($totalOrders); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-none">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Quick Access</h5>
                </div>
                <div class="card-body">
                    <p>Welcome to the Zeng Store admin system. Use the left sidebar to manage your store.</p>
                    <div class="row text-center mt-3">
                        <div class="col-md-4">
                            <a href="product_management.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="bi bi-plus-circle d-block mb-2 fs-4"></i> Add New Product
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="member_management.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-search d-block mb-2 fs-4"></i> Search Members
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="order_management.php" class="btn btn-outline-dark w-100 py-3">
                                <i class="bi bi-file-earmark-text d-block mb-2 fs-4"></i> View Sales Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
