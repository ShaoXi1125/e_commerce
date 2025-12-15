<!-- Header partial (assumes `includes/head.php` was included earlier) -->

<!-- ────────────────────────────────────── -->
<!-- 🔶 TOP BAR -->
<!-- ────────────────────────────────────── -->
<div class="top-bar bg-dark text-white py-1 d-none d-lg-block">
  <div class="container d-flex justify-content-between align-items-center">

    <!-- Left -->
    <div>
      <a href="#" class="text-white text-decoration-none">Help Center</a> |
      <a href="#" class="text-white text-decoration-none">English / 中文</a>
    </div>

    <!-- Right -->
    <div class="d-flex align-items-center">

      <a href="#" class="text-white text-decoration-none">Notifications</a> |
      <a href="#" class="text-white text-decoration-none">My Orders</a> |
      <a href="cart.php" class="text-white text-decoration-none">Cart</a> |

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
        <a class="ms-2" href="login.php">Login</a>
        |
        <a href="register.php">Register</a>
      <?php endif; ?>

    </div>
  </div>
</div>


<!-- ────────────────────────────────────── -->
<!-- 🔶 MAIN NAV (Logo + Search + Cart) -->
<!-- ────────────────────────────────────── -->
<nav class="main-nav navbar navbar-expand-lg border-bottom">
  <div class="container d-flex flex-wrap justify-content-between align-items-center">

    <!-- Logo -->
    <div class="logo">
      <a href="index.php" class="navbar-brand fs-2 text-dark text-decoration-none">
          <i class="bi bi-shop fs-1"></i>
      </a>
    </div>

      <!--burger button (Mobile only)-->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNavbar">
    <!-- Search -->
    <form class="d-flex mx-lg-4 my-3 my-lg-0 flex-grow-1 search-bar" action="search.php" method="get">
      <input type="search" class="form-control rounded-0" name="q" placeholder="Search products..." required>
      <button type="submit" class="btn btn-dark rounded-0">Search</button>
    </form>

    <!-- Cart -->
     <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item">
          <a href="cart.php" class="nav-link fs-4">
            🛒 <span class="badge bg-dark">0</span>
          </a>
      </li>

      <li class="nav-item d-lg-none text-dark">
        <?php if(isset($_SESSION['user'])): ?>
          <a class="nav-link text-dark" href="profile.php">👋 <?= htmlspecialchars($_SESSION['user']) ?></a>
          <a class="nav-link text-dark" href="../logout.php">Logout</a>
          <?php else: ?>
          <a class="nav-link text-dark" href="login.php">Login</a>
          <a class="nav-link text-dark" href="register.php">Register</a>
          <?php endif; ?>
        </li>
     </ul>
     
    
    </div>
  </div>
</nav>

<!-- footer will include Bootstrap JS and close body/html -->
