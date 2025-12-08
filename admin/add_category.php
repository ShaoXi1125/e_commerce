<?php

include_once '../config/db.php';
include_once 'admin_header.php';
include_once 'admin_auth.php';  

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = $_POST['category_name'];

    if(empty($category_name)) {
        $error = "Category name cannot be empty.";
    }else{
        $sql = "INSERT INTO categories (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category_name);
        $stmt->execute();
        if($stmt->affected_rows > 0) {
            header("Location: categories.php");
            exit();
        } else {
            $error = "Error adding category: " . $conn->error;      
        }
    }
}
?>

<div class = 'content-wrapper'>
    <div>
        <h2>Add New Category</h2>
    </div>
    <div><?php if(isset($error)) { echo "<p class='error'>$error</p>"; } ?></div>
    <div>
        <form method="POST" action="add_category.php">
            <div class="form-group">
                <label for="category_name">Category Name:</label>
                <input type="text" id="category_name" name="category_name" class="form-control" required>
                <button type="submit" class="btn btn-primary">Add Category</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='categories.php'">Cancel</button>
            </div>
        </form>

    </div>