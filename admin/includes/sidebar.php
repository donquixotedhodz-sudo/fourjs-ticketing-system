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
            
            <li class="menu-item <?= $current_page === 'technicians.php' ? 'active' : '' ?>">
                <a href="technicians.php" class="menu-link">
                    <i class="fas fa-users-cog"></i>
                    <span class="menu-text">Technicians</span>
                </a>
            </li>
            
            <li class="menu-item <?= in_array($current_page, ['reports.php', 'sales_report.php', 'job_orders_report.php']) ? 'active' : '' ?>">
                <a href="#reportsSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($current_page, ['reports.php', 'sales_report.php', 'job_orders_report.php']) ? 'true' : 'false' ?>" class="menu-link dropdown-toggle">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Reports</span>
                </a>
                <ul class="submenu collapse <?= in_array($current_page, ['reports.php', 'sales_report.php', 'job_orders_report.php']) ? 'show' : '' ?>" id="reportsSubmenu">
                    <li class="submenu-item <?= $current_page === 'sales_report.php' ? 'active' : '' ?>">
                        <a href="sales_report.php" class="submenu-link">
                            <i class="fas fa-coins"></i>
                            <span class="menu-text">Sales Report</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page === 'job_orders_report.php' ? 'active' : '' ?>">
                        <a href="job_orders_report.php" class="submenu-link">
                            <i class="fas fa-clipboard-list"></i>
                            <span class="menu-text">Job Orders Report</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="menu-item <?= in_array($current_page, ['settings.php', 'aircon_models.php', 'cleaning_services.php', 'parts_management.php']) ? 'active' : '' ?>">
                <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="<?= in_array($current_page, ['settings.php', 'aircon_models.php', 'cleaning_services.php', 'parts_management.php']) ? 'true' : 'false' ?>" class="menu-link dropdown-toggle">
                    <i class="fas fa-cog"></i>
                    <span class="menu-text">Settings</span>

                </a>
                <ul class="submenu collapse <?= in_array($current_page, ['settings.php', 'aircon_models.php', 'cleaning_services.php', 'parts_management.php']) ? 'show' : '' ?>" id="settingsSubmenu">
                    <li class="submenu-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                        <a href="settings.php" class="submenu-link">
                            <i class="fas fa-user-shield"></i>
                            <span class="menu-text">Account Settings</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page === 'aircon_models.php' ? 'active' : '' ?>">
                        <a href="aircon_models.php" class="submenu-link">
                            <i class="fas fa-snowflake"></i>
                            <span class="menu-text">Aircon Models</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page === 'cleaning_services.php' ? 'active' : '' ?>">
                        <a href="cleaning_services.php" class="submenu-link">
                            <i class="fas fa-broom"></i>
                            <span class="menu-text">Cleaning Services</span>
                        </a>
                    </li>
                    <li class="submenu-item <?= $current_page === 'parts_management.php' ? 'active' : '' ?>">
                        <a href="parts_management.php" class="submenu-link">
                            <i class="fas fa-calculator"></i>
                            <span class="menu-text">Parts</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>