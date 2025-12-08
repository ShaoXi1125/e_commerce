
<?php

include_once '../config/db.php';
include_once 'admin_header.php';
include_once 'admin_auth.php';

$sql = "SELECT products.*, categories.name AS category_name FROM products INNER JOIN categories ON products.category_id = categories.id";
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

    <div>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><img src="../<?= $row['image_URL'] ?>" alt="<?= $row['name'] ?>" style="width: 50px; height: 50px;"></td>
                        <td><?= $row["name"]; ?></td>
                        <td><?= $row["category_name"]; ?></td>
                        <td><?= $row["description"]; ?></td>
                        <td>$<?= number_format($row["price"], 2); ?></td>
                        <td><?= $row["stock"]; ?></td>
                        <td colspan= 2>
                            <a href="edit_product.php?id=<?= $row['id']; ?>" class="btn btn-secondary">Edit</a>
                            <a href="delete_product.php?id=<?= $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

<!-- <?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {

        echo "<div>" . $row["product_name"] . " - $" . $row["price"] . "</div>";
    }
} else {
    echo "No products found.";
}
?> -->

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
</main>

<!-- <?php include '../includes/footer.php'; ?> -->
