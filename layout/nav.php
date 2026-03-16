<?php

require_once 'config/config.php';

$sql = "";

?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">E-commerce</a>
    </div>

    <div class="collapse navbar-collapse" id="navbarContent">
        <form class="d-flex me-2" action="index.php" method="get" role="search">
            <input
                class="form-control me-2"
                type="search"
                name="q"
                placeholder="Search"
                aria-label="Search"
            >
            <button class="btn btn-outline-success" type="submit">Search</button>
        </form>

        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
            <?php if(isset($_SESSION['user_id'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <img src="<?php echo $userAvatar; ?>" class="rounded-circle me-2" width="32" height="32" alt="User Avatar">
                        <span><?php echo $_SESSION['userId']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="member_login.php">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="member_register.php">Register</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>