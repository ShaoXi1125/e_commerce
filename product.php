<?php

include_once 'config/config.php';

$search = $_GET['search'] ?? '';
$filter = $_GET['category'] ?? '';

$params = [];
$whereClauses = ["1 = 1"];

if($search !==''){
    $whereClauses[]="(ProductName LIKE :search OR Description LIKE :search)";
    $params[':search'] = "%$search%";
}
//SELECT * FROM products WHERE name LIKE '%search%'

if($filter !==""){
    $whereClauses[]="CategoryId = :cat_id";
    $params[':cat_id'] = $filter;
}
//SELECT * FROM products WHERE CategoryID = :cat_id;

$whereSQL = implode(' AND ', $whereClauses);

$sql = "SELECT p.*,
        (SELECT ImageUrl FROM productimages WHERE ProductId = p.ProductId LIMIT 1) 
        as MainImage FROM Products p WHERE $whereSQL ORDER BY p.CreateDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM category")->fetchAll();



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce || <?php echo htmlspecialchars($_GET['productName'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <?php include 'layout/nav.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <aside class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">Filter</div>
                    <div class="list-group list-group-flush">
                        <a href="product.php" class="list-group-item list-group-item-action <?= empty($filter) ? 'active' : '' ?>">All Categories</a>
                        <?php foreach ($categories as $category): ?>
                            <a href="product.php?category=<?= $category['CategoryId'] ?>" class="list-group-item list-group-item-action <?= $filter === $category['CategoryId'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($category['CategoryName']) ?>
                            </a>
                        <!--
                        <a href="product.php?category=123qwe">Category Name</a>    
                        
                        -->
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

             <main class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><?= htmlspecialchars($filter ? $categories[array_search($filter, array_column($categories, 'CategoryId'))]['CategoryName'] : 'All Products') ?></h5>
                    <span class="text-muted"><?= count($products) ?> products</span>

                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <?php if(empty($products)): ?>
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-search fs-1 text-muted"></i>
                                <p class="mt-2">No products found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($products as $product): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm border-0 product-card">
                                        <img src="<?= $product['MainImage']? : 'asset/image/no-image.jpg' ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?= htmlspecialchars($product['ProductName']) ?>">
                                        <div class="card-body">
                                            <h6 class="card-title text-truncate"><?= htmlspecialchars($product['ProductName']) ?></h6>
                                            <p class="card-text">RM <?= number_format($product['Price'], 2) ?></p>
                                            <p class="text-muted small mb-0">Quantity: <?= $product['StockQuantity'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>
             </main>
        </div>
    </div>
</body>
</html>