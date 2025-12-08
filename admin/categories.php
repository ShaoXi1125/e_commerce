<?php

include_once '../config/db.php';
include_once 'admin_header.php';
include_once 'admin_auth.php';

$sql = "SELECT * FROM categories";
$result = $conn->query($sql);

?>

<div class = 'content-wrapper'>
    <div>
        <h2>Category List</h2>
    </div>
    <div>
        <a href="add_category.php" class="btn btn-primary">Add New Category</a>
    </div>
    <div class="category-list">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()) : ?>
                    <tr>
                        <td><?= $row["name"]; ?></td>
                        <td>
                            <a href="edit_category.php?id=<?= $row['id']; ?>" class="btn btn-secondary">Edit</a>
                            <a href="delete_category.php?id=<?= $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>


    </div>
</div>