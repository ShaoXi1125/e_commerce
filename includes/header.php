<!-- Header partial (assumes `includes/head.php` was included earlier) -->

<!-- ────────────────────────────────────── -->
<!-- 🔶 TOP BAR -->
<!-- ────────────────────────────────────── -->
<div class="top-bar">
  <div class="container d-flex justify-content-between align-items-center">

    <!-- Left -->
    <div>
      <a href="#">Help Center</a> |
      <a href="#">English / 中文</a>
    </div>

    <!-- Right -->
    <div class="d-flex align-items-center">

      <a href="#">Notifications</a> |
      <a href="#">My Orders</a> |
      <a href="cart.php">Cart</a> |

      <!-- User status -->
      <?php if (isset($_SESSION['user'])): ?>
        <div class="dropdown d-inline position-relative ms-2">
          <a href="#" class="dropdown-toggle text-white" role="button" data-bs-toggle="dropdown">
            👋 <?= htmlspecialchars($_SESSION['user']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a class="ms-2" href="login.php">Login</a> /
        <a href="register.php">Register</a>
      <?php endif; ?>

    </div>
  </div>
</div>


<!-- ────────────────────────────────────── -->
<!-- 🔶 MAIN NAV (Logo + Search + Cart) -->
<!-- ────────────────────────────────────── -->
<nav class="main-nav">
  <div class="container d-flex flex-wrap justify-content-between align-items-center">

    <!-- Logo -->
    <div class="logo">
      <a href="index.php">
        <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Shopee_logo.svg">
      </a>
    </div>

    <!-- Search -->
    <form class="d-flex flex-grow-1 mx-3 search-bar" action="search.php" method="get">
      <input type="search" class="form-control" name="q" placeholder="Search products..." required>
      <button type="submit" class="btn">Search</button>
    </form>

    <!-- Cart -->
    <a href="cart.php" class="cart-btn">
      🛒 <span>0</span>
    </a>

  </div>
</nav>

<!-- footer will include Bootstrap JS and close body/html -->
