<?php

include_once '../config/db.php';
include_once 'admin_header.php';
include_once 'admin_auth.php';

$category = $conn->query("SELECT * FROM categories");

$success = "";
$error = "";

if($_SERVER['REQUEST_METHOD']=="POST"){
    $category_id = $_POST['category'];
    $product_name = $_POST['product_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $image_path = null;

    if(!empty($_FILES['image']['name'])){
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $target_dir = "../uploads/products". $filename;

        if(move_uploaded_file($_FILES['image']['tmp_name'], $target_dir)){
            $image_path = 'uploads/products/' . $filename;
        }else{
            $error = "Image upload failed.";
        }
    }

    if($error === ""){
        $stmt = $conn->prepare("INSERT INTO products (`category_id`,`name`,`description`,`price`,`stock`, `image_URL`) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("issdis",$category_id,$product_name,$description,$price,$stock,$image_path);
        $stmt->execute();
        if($stmt->affected_rows > 0){
            $success = "Product added successfully!";
        }else{
            $error = "Error: " .$conn->error;
        }
    }
}

?>

<div class = 'content-wrapper'>
    <div>
        <h2>Add New Product</h2>
    </div>
    <div>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"   "><?= $success ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert""><?= $error ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>  
            </div>
        <?php endif; ?>
    </div>
  
    <div>
        <form action="add_product.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <div>
                    <label for="category_id">Category:</label>
                    <select class="form-control" name="category" id="category_id">
                        <option value=""> -- SELECT CATEGORY --</option>
                        <?php while ($cat = $category->fetch_assoc()) : ?>
                            <option value="<?= $cat['id']?>"><?= $cat['name']?></option>
                        <?php endwhile;?>
                    </select>
                </div>
                <div>
                    <label for="product_name">Product Name:</label>
                    <input type="text" id="product_name" name="product_name" class="form-control" required>
                </div>
                <div>
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" class="form-control" required></textarea>
                </div>
                <div>
                    <label for="price">Price:</label>
                    <input type="number" step="0.01" id="price" name="price" class="form-control" required>
                </div>
                   <div>
                    <label for="Stock">Stock:</label>
                    <input type="number" id="stock" name="stock" class="form-control" required>
                </div>
                <div>
                    <label for="image">Image:</label>
                    <input type="file" id="image" name="image" class="form-control" required>
                </div><br>
                <div>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='products.php'">Cancel</button>
                </div>
                
            </div>
        </form>
    </div>
</div>
