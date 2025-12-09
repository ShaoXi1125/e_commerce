<?php
include_once '../config/db.php';
include_once 'admin_header.php';
include_once 'admin_auth.php';

$sql = "
    SELECT products.*, categories.name AS  category_name 
    FROM products 
    INNER JOIN categories ON products.category_id = categories.id
";
// $sql = "SELECT * FROM products INNER JOIN categories ON products.category_id = categories.id";
$result = $conn->query($sql);

// Fetch all categories (for filter)
$catSql = "SELECT * FROM categories";
$catResult = $conn->query($catSql);
?>

<main class="content-wrapper">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">Product List</h2>
        <a href="add_product.php" class="btn btn-primary">＋ Add New Product</a>
    </div>

    <!-- 🔍 Search + Filter Bar -->
    <div class="card p-3 mb-4 shadow-sm">
        <div class="row g-3">

            <!-- Search -->
            <div class="col-md-4 ms-auto">
                <input type="search" id="productSearch" class="form-control" placeholder="🔍 Search products..." onkeyup="searchProducts()">
            </div>

            <!-- Category Filter -->
            <div class="col-md-3">
                <select id="categoryFilter" class="form-select" onchange="filterProducts()">
                    <option value="">All Categories</option>
                    <?php while($cat = $catResult->fetch_assoc()): ?>
                        <option value="<?= $cat['name'] ?>"><?= $cat['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Price Sort -->
            <div class="col-md-3">
                <select id="priceSort" class="form-select" onchange="filterProducts()">
                    <option value="">Sort by Price</option>
                    <option value="asc">Low → High</option>
                    <option value="desc">High → Low</option>
                </select>
            </div>

        </div>
    </div>

    <!-- 📦 Product Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle" id="productTable">
            <thead class="table-dark">
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th style="width: 160px;">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <img src="../<?= $row['image_URL'] ?>" 
                                 alt="<?= $row['name'] ?>" 
                                 style="width: 55px; height: 55px; object-fit: cover;">
                        </td>

                        <td><?= $row["name"]; ?></td>
                        <td><?= $row["category_name"]; ?></td>
                        <td><?= $row["description"]; ?></td>

                        <td class="fw-bold text-success">RM <?= number_format($row["price"], 2); ?></td>

                        <td><?= $row["stock"]; ?></td>

                        <td>
                            <a href="edit_product.php?id=<?= $row['id']; ?>" 
                               class="btn btn-sm btn-secondary">
                                Edit
                            </a>

                            <a href="delete_product.php?id=<?= $row['id']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this product?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</main>

<script>
/* 🔎 Search Function */
function searchProducts() {
    let input = document.getElementById("productSearch").value.toLowerCase();
    let rows = document.querySelectorAll("#productTable tbody tr");

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

/* 🔽 Category + Price Filter */
function filterProducts() {
    let category = document.getElementById("categoryFilter").value;
    let sort = document.getElementById("priceSort").value;
    let table = document.querySelector("#productTable tbody");
    let rows = Array.from(table.querySelectorAll("tr"));

    // Filter category
    rows.forEach(row => {
        let cat = row.children[2].innerText;
        row.style.display = (category === "" || category === cat) ? "" : "none";
    });

    // Sort by price
    if (sort !== "") {
        rows.sort((a, b) => {
            let p1 = parseFloat(a.children[4].innerText.replace("$", ""));
            let p2 = parseFloat(b.children[4].innerText.replace("$", ""));
            return sort === "asc" ? p1 - p2 : p2 - p1;
        });
        rows.forEach(r => table.appendChild(r));
    }
}
</script>
