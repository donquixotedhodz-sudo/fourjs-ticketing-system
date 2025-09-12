<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch technician info for header
$technician = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM technicians WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? '';
$filter_value = $_GET['filter_value'] ?? '';
$custom_from = $_GET['from'] ?? '';
$custom_to = $_GET['to'] ?? '';
$where = 'job_orders.assigned_technician_id = ?';
$params = [$_SESSION['user_id']];

// Apply filters based on filter type
switch ($filter_type) {
    case 'customer':
        if (!empty($filter_value)) {
            $where .= " AND job_orders.customer_name = ?";
            $params[] = $filter_value;
        }
        break;
        
    case 'service_type':
        if (!empty($filter_value)) {
            $where .= " AND job_orders.service_type = ?";
            $params[] = $filter_value;
        }
        break;
        
    case 'date':
        switch ($filter_value) {
            case 'day': $where .= " AND DATE(job_orders.created_at) = CURDATE()"; break;
            case 'week': $where .= " AND YEARWEEK(job_orders.created_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
            case 'month': $where .= " AND YEAR(job_orders.created_at) = YEAR(CURDATE()) AND MONTH(job_orders.created_at) = MONTH(CURDATE())"; break;
            case 'year': $where .= " AND YEAR(job_orders.created_at) = YEAR(CURDATE())"; break;
            case 'custom':
                if ($custom_from && $custom_to) {
                    $where .= " AND DATE(job_orders.created_at) BETWEEN ? AND ?";
                    $params[] = $custom_from;
                    $params[] = $custom_to;
                }
                break;
        }
        break;
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM job_orders WHERE $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($total / $records_per_page);

// Get job orders (including cancelled) with pagination
$sql = "SELECT job_orders.*, 
               aircon_models.brand, 
               aircon_models.model_name,
               ac_parts.part_name,
               ac_parts.part_code,
               ac_parts.part_category
        FROM job_orders 
        LEFT JOIN aircon_models ON job_orders.aircon_model_id = aircon_models.id 
        LEFT JOIN ac_parts ON job_orders.part_id = ac_parts.id
        WHERE $where 
        ORDER BY job_orders.created_at DESC
        LIMIT $records_per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create display value for filter
$filter_display_value = $filter_value;
switch ($filter_type) {
    case 'customer':
        $filter_display_value = $filter_value;
        break;
    case 'service_type':
        $filter_display_value = ucfirst($filter_value);
        break;
    case 'date':
        switch ($filter_value) {
            case 'day': $filter_display_value = 'Today'; break;
            case 'week': $filter_display_value = 'This Week'; break;
            case 'month': $filter_display_value = 'This Month'; break;
            case 'year': $filter_display_value = 'This Year'; break;
            case 'custom': $filter_display_value = 'Custom Range'; break;
        }
        break;
}

require_once 'includes/header.php';
?>
<body>
    
<div class="wrapper">
    <?php require_once 'includes/sidebar.php'; ?>

     <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-white">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown">
                            <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <img src="<?= !empty($technician['profile_picture']) ? '../' . htmlspecialchars($technician['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($technician['name'] ?: 'Technician') . '&background=1a237e&color=fff' ?>" 
                                     alt="Technician" 
                                     class="rounded-circle me-2" 
                                     width="32" 
                                     height="32"
                                     style="object-fit: cover; border: 2px solid #4A90E2;">
                                <span class="me-3">Welcome, <?= htmlspecialchars($technician['name'] ?: 'Technician') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="min-width: 200px;">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2" href="profile.php">
                                        <i class="fas fa-user me-2 text-primary"></i>
                                        <span>Profile</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-2"></li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="../admin/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        <span>Logout</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="container mt-4">
            <!-- Print Header (hidden by default, shown only when printing) -->
            <div class="print-header-custom" style="display: none;">
                <img src="../images/logo.png" alt="Company Logo" class="print-logo">
                <div class="print-admin-info">
                    <div><strong>Technician:</strong> <?= htmlspecialchars($technician['name'] ?: 'Technician') ?></div>
                    <div><strong>Date:</strong> <?= date('F j, Y \a\t g:i A') ?></div>
                </div>
            </div>
            
            <!-- Report Title -->
            <div class="print-report-title" style="display: none;">
                Job Orders Report
                <div style="font-size: 12px; font-weight: normal; margin-top: 0px; margin-bottom: 0px; color: #666;">
                    All your assigned job orders including cancelled, with filters and pagination.
                </div>
                <?php if ($filter_type && $filter_value): ?>
                    <div style="font-size: 12px; font-weight: normal; margin-top: 0px; margin-bottom: 0px; color: #666;">
                        Filter: <?= htmlspecialchars(ucfirst($filter_type) . ': ' . $filter_display_value) ?>
                        <?php if ($filter_type == 'date' && $filter_value == 'custom' && $custom_from && $custom_to): ?>
                            (<?= htmlspecialchars($custom_from) ?> to <?= htmlspecialchars($custom_to) ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-3">Job Orders Report</h3>
                <p class="text-muted mb-4">All your assigned job orders including cancelled, with filters and pagination.</p>
                </div>
                <button class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Filter Job Orders Report</h5>
                    <form method="get" class="row g-3" id="filterForm">
                        <div class="col-md-3">
                            <label for="filter_type" class="form-label">Filter By</label>
                            <select name="filter_type" id="filter_type" class="form-select" onchange="handleFilterTypeChange()">
                                <option value="">Select Filter Type</option>
                                <option value="customer" <?= $filter_type=='customer'?'selected':'' ?>>Customer</option>
                                <option value="service_type" <?= $filter_type=='service_type'?'selected':'' ?>>Service Type</option>
                                <option value="date" <?= $filter_type=='date'?'selected':'' ?>>Date</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_value" class="form-label">Filter Value</label>
                            <select name="filter_value" id="filter_value" class="form-select" disabled>
                                <option value="">Select filter type first</option>
                            </select>
                        </div>
                        <div id="custom_date_range" class="col-md-4" style="display: <?= ($filter_type == 'date' && $filter_value == 'custom') ? 'block' : 'none' ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="from" class="form-label">From</label>
                                    <input type="date" name="from" id="from" value="<?= htmlspecialchars($custom_from) ?>" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="to" class="form-label">To</label>
                                    <input type="date" name="to" id="to" value="<?= htmlspecialchars($custom_to) ?>" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary w-100" onclick="clearFilters()">Clear</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filter Info -->
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                    <?php if ($filter_type && $filter_value): ?>
                        <small class="text-muted">
                            Showing results for: <?= htmlspecialchars(ucfirst($filter_type) . ': ' . $filter_display_value) ?>
                            <?php if ($filter_type == 'date' && $filter_value == 'custom' && $custom_from && $custom_to): ?>
                                (<?= date('M d, Y', strtotime($custom_from)) ?> - <?= date('M d, Y', strtotime($custom_to)) ?>)
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-1">
                <div class="col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Job Orders</h6>
                                    <h4><?= $total ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clipboard-list fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Active Filter</h6>
                                    <h4><?= $filter_type ? ucfirst($filter_type) : 'None' ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-filter fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Orders Table -->
            <div class="card mb-4">
                <div class="card-body">
                    <div id="job-orders-report-print">
                        <div class="table-wrapper" style="max-height: 600px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ticket Number</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Brand</th>
                                        <th>Model</th>
                                        <th>Part Code</th>
                                        <th>Part Name</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($order['job_order_number'] ?? '') ?></strong></td>
                                            <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($order['service_type']) {
                                                        case 'installation': echo 'bg-primary'; break;
                                                        case 'repair': echo 'bg-warning text-dark'; break;
                                                        case 'maintenance': echo 'bg-success'; break;
                                                        case 'cleaning': echo 'bg-primary'; break;
                                                        case 'survey': echo 'bg-info'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>
                                                ">
                                                    <?= ucfirst(htmlspecialchars($order['service_type'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <!-- Brand Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'installation' || $order['service_type'] == 'cleaning'): ?>
                                                    <?= htmlspecialchars($order['brand'] ?? 'N/A') ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Model Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'installation' || $order['service_type'] == 'cleaning'): ?>
                                                    <?= htmlspecialchars($order['model_name'] ?? 'N/A') ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Part Code Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'repair'): ?>
                                                    <?php if (!empty($order['part_name'])): ?>
                                                        <?= htmlspecialchars($order['part_code'] ?? 'N/A') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Part Name Column -->
                                            <td>
                                                <?php if ($order['service_type'] == 'repair'): ?>
                                                    <?php if (!empty($order['part_name'])): ?>
                                                        <?= htmlspecialchars($order['part_name'] ?? 'N/A') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₱<?= number_format($order['price'] ?? 0,2) ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch($order['status']) {
                                                    case 'pending': $status_class = 'bg-secondary'; break;
                                                    case 'in_progress': $status_class = 'bg-info'; break;
                                                    case 'completed': $status_class = 'bg-success'; break;
                                                    case 'cancelled': $status_class = 'bg-danger'; break;
                                                    default: $status_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?= $status_class ?>">
                                                    <?= ucfirst(htmlspecialchars($order['status'] ?? '')) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($order['created_at'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($orders)): ?>
                                        <tr><td colspan="10" class="text-center">No job orders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                </table>
                            </div>
                        </div>

                        
                        <!-- Summary Section for Print -->
                        <div class="print-summary mt-4" style="border-top: 2px solid #34495e; padding-top: 20px; display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <h5 class="mb-2" style="color: #2c3e50; font-weight: bold;">Summary</h5>
                                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Total Job Orders:</span>
                            <span style="font-weight: bold; color: #27ae60;"><?= $total ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Repair Orders:</span>
                            <span style="font-weight: bold; color: #f39c12;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'repair'; })) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Installation Orders:</span>
                            <span style="font-weight: bold; color: #3498db;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'installation'; })) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="font-weight: 600;">Survey Orders:</span>
                            <span style="font-weight: bold; color: #17a2b8;"><?= count(array_filter($orders, function($order) { return $order['service_type'] == 'survey'; })) ?></span>
                        </div>
                                        <?php if ($filter_type == 'date' && $filter_value == 'custom'): ?>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span style="font-weight: 600;">Date Range:</span>
                                            <span style="font-weight: bold; color: #9b59b6;"><?= htmlspecialchars($custom_from) ?> to <?= htmlspecialchars($custom_to) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <small class="text-muted">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total) ?> of <?= $total ?> entries
                    </small>
                </div>
                <nav aria-label="Job orders pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
            </div>
        </div>
</div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../js/dashboard.js"></script>
<!-- Print Script -->
<script>
function printJobOrdersReport() {
    var printContents = document.getElementById('job-orders-report-print').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
</script>

<style>
/* Table font size for screen */
.table {
    font-size: 14px !important;
}

/* Table font size for print */
@media print {
     /* Hide screen elements */
            .navbar, .sidebar, #sidebar, .wrapper > #sidebar, .btn, .card-header, .modal, .d-flex.gap-2, .pagination, form, .dropdown, #sidebarCollapse, #content nav,
            .row.mb-4, /* This hides the summary cards row */
            .card.text-bg-primary, .card.text-bg-info, .card.text-bg-success, .card.bg-primary, .card.bg-success, .card.bg-info, /* Extra safety for summary cards */
            .d-flex.justify-content-between.align-items-center.mt-4 /* Hide pagination controls */
            {
                display: none !important;
            }
    
    /* Hide wrapper sidebar structure */
    .wrapper {
        display: block !important;
    }
    
    #content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    /* Hide main page title and description from print */
    h3, h5, .text-muted {
        display: none !important;
    }

    /* Reset page layout for clean print */
    body {
        margin: 0 !important;
        padding: 10px !important;
        font-family: 'Arial', sans-serif !important;
        font-size: 10px !important;
        line-height: 1.2 !important;
        color: #000 !important;
        background: white !important;
    }

    /* Container adjustments */
    .container, .container-fluid {
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Card styling - remove all design elements */
    .card {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    
    .card-body {
        padding: 0 !important;
    }

    /* Print header with logo and admin info */
    body::before {
        content: "";
        display: none;
    }
    
    /* Show print header */
    .print-header-custom {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 15px 0 !important;
        margin-bottom: 20px !important;
    }
    
    .print-logo {
        display: block !important;
        max-height: 80px !important;
        width: auto !important;
        margin-top: 0 !important;
    }
    
    .print-admin-info {
        text-align: right !important;
        font-size: 10px !important;
        line-height: 1.3 !important;
    }
    
    /* Job Orders Report title on left */
    .print-report-title {
        display: block !important;
        font-size: 16px !important;
        font-weight: bold !important;
        color: #2c3e50 !important;
        margin-bottom: 0px !important;
        text-align: left !important;
    }
    
    /* Filter information styling */
    .print-report-title div {
        margin-top: 0px !important;
        margin-bottom: 0px !important;
    }
    
    /* Reduce space before table */
    .table-responsive {
        margin-top: 0px !important;
    }

    /* Table styling for clean print */
    .table-responsive {
        overflow: visible !important;
    }
    
    .table-wrapper {
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
        border-radius: 0 !important;
    }
    
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 20px !important;
        font-size: 8px !important;
        table-layout: fixed !important;
    }

    .table th {
         background: #fff !important;
         color: #000 !important;
         font-weight: bold !important;
         text-align: center !important;
         padding: 6px 3px !important;
         border: 1px solid #000 !important;
         font-size: 9px !important;
         word-wrap: break-word !important;
     }

     .table td {
         padding: 4px 3px !important;
         border: 1px solid #000 !important;
         vertical-align: top !important;
         word-wrap: break-word !important;
         text-align: center !important;
         font-size: 8px !important;
         color: #000 !important;
         background: #fff !important;
     }

    /* Optimized column widths for technician job orders report */
    .table th:nth-child(1), .table td:nth-child(1) { width: 8% !important; } /* Order # */
    .table th:nth-child(2), .table td:nth-child(2) { width: 12% !important; } /* Customer */
    .table th:nth-child(3), .table td:nth-child(3) { width: 10% !important; } /* Service Type */
    .table th:nth-child(4), .table td:nth-child(4) { width: 10% !important; } /* Brand */
    .table th:nth-child(5), .table td:nth-child(5) { width: 12% !important; } /* Model */
    .table th:nth-child(6), .table td:nth-child(6) { width: 10% !important; } /* Part Code */
    .table th:nth-child(7), .table td:nth-child(7) { width: 12% !important; } /* Part Name */
    .table th:nth-child(8), .table td:nth-child(8) { width: 8% !important; } /* Price */
    .table th:nth-child(9), .table td:nth-child(9) { width: 8% !important; } /* Status */
    .table th:nth-child(10), .table td:nth-child(10) { width: 10% !important; } /* Created At */

    .table tbody tr {
         background: #fff !important;
     }

    /* Badge styling for print */
    .badge {
        background: #000 !important;
        color: #000 !important;
        padding: 1px 3px !important;
        border-radius: 2px !important;
        font-size: 7px !important;
        font-weight: bold !important;
        border: none !important;
    }
    
    /* Specific badge colors for print - all black */
    .bg-primary { background: #000 !important; color: #000 !important; }
    .bg-warning { background: #000 !important; color: #000 !important; }
    .bg-success { background: #000 !important; color: #000 !important; }
    .bg-info { background: #000 !important; color: #000 !important; }
    .bg-secondary { background: #000 !important; color: #000 !important; }
    .bg-danger { background: #000 !important; color: #000 !important; }

    /* Print summary styling - positioned on left */
    .print-summary {
        display: block !important;
        margin-top: 20px !important;
        padding-top: 15px !important;
        border-top: 2px solid #34495e !important;
        page-break-inside: avoid !important;
        width: 50% !important;
        float: left !important;
    }

    .print-summary h5 {
        color: #2c3e50 !important;
        font-weight: bold !important;
        font-size: 12px !important;
        margin-bottom: 10px !important;
        text-align: left !important;
    }

    .print-summary .d-flex {
        display: flex !important;
        justify-content: space-between !important;
        margin-bottom: 5px !important;
        padding: 3px 0 !important;
    }

    .print-summary span {
        font-size: 10px !important;
    }

    .print-summary span[style*="font-weight: bold"] {
        font-weight: bold !important;
    }

    /* Ensure table doesn't break across pages */
    .table {
        page-break-inside: auto !important;
    }
    
    .table tr {
        page-break-inside: avoid !important;
        page-break-after: auto !important;
    }
    
    .table thead {
        display: table-header-group !important;
    }
    
    /* Hide empty cells cleanly */
    .text-muted {
        color: #999 !important;
    }
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../js/dashboard.js"></script>

<script>
function handleFilterTypeChange() {
    updateFilterOptions();
}

function updateFilterOptions() {
    const filterType = document.getElementById('filter_type').value;
    const filterValue = document.getElementById('filter_value');
    
    filterValue.disabled = false;
    
    if (filterType === 'customer') {
        // Restore select for customer dropdown
        if (filterValue.tagName !== 'SELECT') {
            filterValue.outerHTML = `<select name="filter_value" id="filter_value" class="form-select"></select>`;
        }
        const newFilterValue = document.getElementById('filter_value');
        // Fetch customers from database
        fetch('../admin/controller/get_filter_options.php?filter_type=customer')
            .then(response => response.json())
            .then(data => {
                newFilterValue.innerHTML = '<option value="">Select Customer</option>';
                if (data.options && data.options.length > 0) {
                    data.options.forEach(customer => {
                        const selected = '<?= $filter_value ?>' === customer ? 'selected' : '';
                        newFilterValue.innerHTML += `<option value="${customer}" ${selected}>${customer}</option>`;
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching customers:', error);
                newFilterValue.innerHTML = '<option value="">Error loading customers</option>';
            });
    } else if (filterType === 'service_type') {
        // Restore select for service type dropdown
        if (filterValue.tagName !== 'SELECT') {
            filterValue.outerHTML = `<select name="filter_value" id="filter_value" class="form-select"></select>`;
        }
        const newFilterValue = document.getElementById('filter_value');
        // Fetch service types from database
        fetch('../admin/controller/get_filter_options.php?filter_type=service_type')
            .then(response => response.json())
            .then(data => {
                newFilterValue.innerHTML = '<option value="">Select Service Type</option>';
                if (data.options && data.options.length > 0) {
                    data.options.forEach(serviceType => {
                        const selected = '<?= $filter_value ?>' === serviceType ? 'selected' : '';
                        newFilterValue.innerHTML += `<option value="${serviceType}" ${selected}>${serviceType.charAt(0).toUpperCase() + serviceType.slice(1)}</option>`;
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching service types:', error);
                newFilterValue.innerHTML = '<option value="">Error loading service types</option>';
            });
    } else if (filterType === 'date') {
        // Restore select for date options
        if (filterValue.tagName !== 'SELECT') {
            filterValue.outerHTML = `<select name="filter_value" id="filter_value" class="form-select"></select>`;
            const newFilterValue = document.getElementById('filter_value');
            const dateOptions = [
                {value: 'day', text: 'Today'},
                {value: 'week', text: 'This Week'},
                {value: 'month', text: 'This Month'},
                {value: 'year', text: 'This Year'},
                {value: 'custom', text: 'Custom Range'}
            ];
            newFilterValue.innerHTML = '<option value="">Select Date Range</option>';
            dateOptions.forEach(option => {
                const selected = '<?= $filter_value ?>' === option.value ? 'selected' : '';
                newFilterValue.innerHTML += `<option value="${option.value}" ${selected}>${option.text}</option>`;
            });
            
            // Add event listener for custom date range
            newFilterValue.addEventListener('change', function() {
                if (this.value === 'custom') {
                    showCustomDateRange();
                } else {
                    hideCustomDateRange();
                }
            });
        } else {
            const dateOptions = [
                {value: 'day', text: 'Today'},
                {value: 'week', text: 'This Week'},
                {value: 'month', text: 'This Month'},
                {value: 'year', text: 'This Year'},
                {value: 'custom', text: 'Custom Range'}
            ];
            filterValue.innerHTML = '<option value="">Select Date Range</option>';
            dateOptions.forEach(option => {
                const selected = '<?= $filter_value ?>' === option.value ? 'selected' : '';
                filterValue.innerHTML += `<option value="${option.value}" ${selected}>${option.text}</option>`;
            });
            
            // Add event listener for custom date range
            filterValue.addEventListener('change', function() {
                if (this.value === 'custom') {
                    showCustomDateRange();
                } else {
                    hideCustomDateRange();
                }
            });
            
            // Check if custom is already selected on page load
            if (filterValue.value === 'custom') {
                showCustomDateRange();
            }
        }
    } else {
        if (filterValue.tagName !== 'SELECT') {
            filterValue.outerHTML = `<select name="filter_value" id="filter_value" class="form-select" disabled><option value="">Select filter type first</option></select>`;
        } else {
            filterValue.innerHTML = '<option value="">Select filter type first</option>';
            filterValue.disabled = true;
        }
    }
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

function showCustomDateRange() {
    const customDateDiv = document.getElementById('custom_date_range');
    if (customDateDiv) {
        customDateDiv.style.display = 'block';
    }
}

function hideCustomDateRange() {
    const customDateDiv = document.getElementById('custom_date_range');
    if (customDateDiv) {
        customDateDiv.style.display = 'none';
    }
}

// Initialize filter options on page load
document.addEventListener('DOMContentLoaded', function() {
    updateFilterOptions();
    
    // Check if custom date range should be shown on page load
    const filterValue = document.getElementById('filter_value');
    if (filterValue && filterValue.value === 'custom') {
        showCustomDateRange();
    }
});

// Add event listener to filter value changes
document.addEventListener('change', function(e) {
    if (e.target.id === 'filter_value' && e.target.value === 'custom') {
        showCustomDateRange();
    } else if (e.target.id === 'filter_value' && e.target.value !== 'custom') {
        hideCustomDateRange();
    }
});
</script>

</body>
</html>