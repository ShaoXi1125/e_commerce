
<?php

include_once '../config/db.php';
include_once 'admin_header.php';
include_once 'admin_auth.php';

$sql = "SELECT * FROM products";
$result = $conn->query($sql);


?>

<main class="content-wrapper">
    <div>
        <h2>Product List</h2>
    </div>
    <div>
        <a href="add_product.php" class="btn btn-primary">Add New Product</a>
        <div style="display: inline-block; margin-left: 300px;">
            <input type="search" id="productSearch" placeholder="Search Products..." onkeyup="searchProducts()">
        </div>
    </div>

<?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {

        echo "<div>" . $row["product_name"] . " - $" . $row["price"] . "</div>";
    }
} else {
    echo "No products found.";
}
?>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
</main>

<!-- <?php include '../includes/footer.php'; ?> -->
