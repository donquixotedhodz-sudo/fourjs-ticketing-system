<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<nav id="sidebar" class="modern-sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="../images/logo.png" alt="CodingLab" class="sidebar-logo">
        </div>
    </div>

    <div class="sidebar-menu">
        <ul class="menu-list">
            <li class="menu-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <a href="dashboard.php" class="menu-link">
                    <i class="fas fa-home"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            
            <li class="menu-item <?= in_array($current_page, ['orders.php', 'archived.php']) ? 'active' : '' ?>">
                <a href="#jobOrdersSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($current_page, ['orders.php', 'archived.php']) ? 'true' : 'false' ?>" class="menu-link dropdown-toggle">
                    <i class="fas fa-clipboard-list"></i>
                    <span class="menu-text">Job Orders</span>
                    
                </a>
                <ul class="submenu collapse <?= in_array($current_page, ['orders.php', 'archived.php']) ? 'show' : '' ?>" id="jobOrdersSubmenu">
                    <li class="submenu-item <?= $current_page == 'orders.php' ? 'active' : '' ?>">
                        <a href="orders.php" class="submenu-link">
                            <i class="fas fa-file-alt"></i>
                            <span class="menu-text">Orders</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page == 'archived.php' ? 'active' : '' ?>">
                        <a href="archived.php" class="submenu-link">
                            <i class="fas fa-archive"></i>
                            <span class="menu-text">Archived</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="menu-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                <a href="profile.php" class="menu-link">
                    <i class="fas fa-user"></i>
                    <span class="menu-text">Profile</span>
                </a>
            </li>
            
            <li class="menu-item <?= in_array($current_page, ['reports.php', 'sales_report.php']) ? 'active' : '' ?>">
                <a href="#reportsSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($current_page, ['reports.php', 'sales_report.php']) ? 'true' : 'false' ?>" class="menu-link dropdown-toggle">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Reports</span>
                </a>
                <ul class="submenu collapse <?= in_array($current_page, ['reports.php', 'sales_report.php']) ? 'show' : '' ?>" id="reportsSubmenu">
                    <li class="submenu-item <?= $current_page === 'sales_report.php' ? 'active' : '' ?>">
                        <a href="sales_report.php" class="submenu-link">
                            <i class="fas fa-coins"></i>
                            <span class="menu-text">Sales Report</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                        <a href="reports.php" class="submenu-link">
                            <i class="fas fa-clipboard-list"></i>
                            <span class="menu-text">My Job Orders</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="menu-item <?= in_array($current_page ?? '', ['aircon_brands.php', 'repair.php', 'estimation_builder.php']) ? 'active' : '' ?>">
                <a href="#servicesSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($current_page ?? '', ['aircon_brands.php', 'repair.php', 'estimation_builder.php']) ? 'true' : 'false' ?>" class="menu-link dropdown-toggle">
                    <i class="fas fa-tools"></i>
                    <span class="menu-text">Services</span>

                </a>
                <ul class="submenu collapse <?= in_array($current_page ?? '', ['aircon_brands.php', 'repair.php', 'visual_estimation.php', 'estimation_builder.php']) ? 'show' : '' ?>" id="servicesSubmenu">
                    <li class="submenu-item <?= ($current_page ?? '') === 'aircon_brands.php' ? 'active' : '' ?>">
                        <a href="aircon_brands.php" class="submenu-link">
                            <i class="fas fa-snowflake"></i>
                            <span class="menu-text">Aircon Brands</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= ($current_page ?? '') === 'estimation_builder.php' ? 'active' : '' ?>">
                        <a href="estimation_builder.php" class="submenu-link">
                            <i class="fas fa-calculator"></i>
                            <span class="menu-text">Estimation Builder</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>