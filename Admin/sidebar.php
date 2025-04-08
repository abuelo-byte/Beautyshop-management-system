<!-- File: ../includes/sidebar.php -->
<div class="d-flex flex-column flex-shrink-0 p-3 bg-light" style="width: 280px;">
    <a href="/inventory_management/Admin/dashboard.php"
        class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-dark text-decoration-none">
        <svg class="bi me-2" width="40" height="32">
            <use xlink:href="#bootstrap" />
        </svg>
        <span class="fs-4">Admin Panel</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="/inventory_management/Admin/dashboard.php" class="nav-link link-dark">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
        </li>
        <li>
            <a href="/inventory_management/Admin/settings.php" class="nav-link active" aria-current="page">
                <i class="fas fa-cog me-2"></i>Settings
            </a>
        </li>
        <li>
            <a href="/inventory_management/Admin/products.php" class="nav-link link-dark">
                <i class="fas fa-boxes me-2"></i>Products
            </a>
        </li>
        <li>
            <a href="/inventory_management/Admin/orders.php" class="nav-link link-dark">
                <i class="fas fa-shopping-cart me-2"></i>Orders
            </a>
        </li>
        <li>
            <a href="/inventory_management/Admin/users.php" class="nav-link link-dark">
                <i class="fas fa-users me-2"></i>Users
            </a>
        </li>
        <li>
            <a href="/inventory_management/Admin/reports.php" class="nav-link link-dark">
                <i class="fas fa-chart-line me-2"></i>Reports
            </a>
        </li>
    </ul>
    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle" id="dropdownUser2"
            data-bs-toggle="dropdown" aria-expanded="false">
            <img src="/inventory_management/assets/images/user.png" alt="" width="32" height="32"
                class="rounded-circle me-2">
            <strong>Admin</strong>
        </a>
        <ul class="dropdown-menu text-small shadow" aria-labelledby="dropdownUser2">
            <li><a class="dropdown-item" href="/inventory_management/Admin/profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="/inventory_management/Admin/settings.php">Settings</a></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="/inventory_management/logout.php">Sign out</a></li>
        </ul>
    </div>
</div>