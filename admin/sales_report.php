<?php
session_start();
require_once '../config/database.php';

// Example: Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch technicians for dropdown
$tech_stmt = $pdo->query("SELECT id, name FROM technicians ORDER BY name ASC");
$technicians = $tech_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? '';
$filter_value = $_GET['filter_value'] ?? '';
$custom_from = $_GET['from'] ?? '';
$custom_to = $_GET['to'] ?? '';

$where = '1';
$params = [];

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
        
    case 'technician':
        if (!empty($filter_value)) {
            $where .= " AND job_orders.assigned_technician_id = ?";
            $params[] = $filter_value;
        }
        break;
        
    case 'date':
        switch ($filter_value) {
            case 'day': $where .= " AND DATE(job_orders.completed_at) = CURDATE()"; break;
            case 'week': $where .= " AND YEARWEEK(job_orders.completed_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
            case 'month': $where .= " AND YEAR(job_orders.completed_at) = YEAR(CURDATE()) AND MONTH(job_orders.completed_at) = MONTH(CURDATE())"; break;
            case 'year': $where .= " AND YEAR(job_orders.completed_at) = YEAR(CURDATE())"; break;
            case 'custom':
                if ($custom_from && $custom_to) {
                    $where .= " AND DATE(job_orders.completed_at) BETWEEN ? AND ?";
                    $params[] = $custom_from;
                    $params[] = $custom_to;
                }
                break;
        }
        break;
}
$sql = "SELECT * FROM job_orders WHERE status = 'completed' AND $where";
// Remove the conditional check since $where now always has a value (starts with '1')
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_sales = 0;
foreach ($sales as $sale) {
    $total_sales += $sale['price'];
}

// Create display value for filter
$filter_display_value = $filter_value;
if ($filter_type == 'technician' && !empty($filter_value)) {
    // Find technician name by ID
    foreach ($technicians as $tech) {
        if ($tech['id'] == $filter_value) {
            $filter_display_value = $tech['name'];
            break;
        }
    }
} elseif ($filter_type == 'date') {
    switch ($filter_value) {
        case 'day': $filter_display_value = 'Today'; break;
        case 'week': $filter_display_value = 'This Week'; break;
        case 'month': $filter_display_value = 'This Month'; break;
        case 'year': $filter_display_value = 'This Year'; break;
        case 'custom': $filter_display_value = 'Custom Range'; break;
    }
}

// Fetch admin info for header
$admin = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Now include the header
require_once 'includes/header.php';
?>
<body>
 <div class="wrapper">
        <?php
        // Include sidebar
        require_once 'includes/sidebar.php';
        ?>

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
                                <img src="<?= !empty($admin['profile_picture']) ? '../' . htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name'] ?: 'Admin') . '&background=1a237e&color=fff' ?>" 
                                     alt="Admin" 
                                     class="rounded-circle me-2" 
                                     width="32" 
                                     height="32"
                                     style="object-fit: cover; border: 2px solid #4A90E2;">
                                <span class="me-3">Welcome, <?= htmlspecialchars($admin['name'] ?: 'Admin') ?></span>
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
                                    <a class="dropdown-item d-flex align-items-center py-2 text-danger" href="logout.php">
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
    <h3>Sales Report</h3>
    
    <!-- Print Header (hidden by default, shown only when printing) -->
    <div class="print-header-custom" style="display: none;">
        <img src="images/logo.png" alt="Company Logo" class="print-logo">
        <div class="print-admin-info">
            <div><strong>Administrator:</strong> <?= htmlspecialchars($admin['name'] ?? 'Admin') ?></div>
            <div><strong>Date:</strong> <?= date('F j, Y \a\t g:i A') ?></div>
        </div>
    </div>
    
    <!-- Report Title for Print -->
    <div class="print-report-title" style="display: none;">
        Sales Report
    </div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">View and print all completed sales with filters</p>
        </div>
        <button class="btn btn-success" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter Sales Report</h5>
            <form method="get" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <label for="filter_type" class="form-label">Filter By</label>
                    <select name="filter_type" id="filter_type" class="form-select" onchange="handleFilterTypeChange()">
                        <option value="">Select Filter Type</option>
                        <option value="customer" <?= $filter_type=='customer'?'selected':'' ?>>Customer</option>
                        <option value="service_type" <?= $filter_type=='service_type'?'selected':'' ?>>Service Type</option>
                        <option value="technician" <?= $filter_type=='technician'?'selected':'' ?>>Technician</option>
                        <option value="date" <?= $filter_type=='date'?'selected':'' ?>>Date</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_value" class="form-label">Filter Value</label>
                    <select name="filter_value" id="filter_value" class="form-select" disabled>
                        <option value="">Select filter type first</option>
                    </select>
                </div>
                <?php if ($filter_type == 'date' && $filter_value == 'custom'): ?>
                <div class="col-md-2">
                    <label for="from" class="form-label">From</label>
                    <input type="date" name="from" id="from" value="<?= htmlspecialchars($custom_from) ?>" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label for="to" class="form-label">To</label>
                    <input type="date" name="to" id="to" value="<?= htmlspecialchars($custom_to) ?>" class="form-control" required>
                </div>
                <?php endif; ?>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-secondary w-100" onclick="clearFilters()">Clear</button>
                </div>
            </form>
        </div>
    </div>

            <!-- Print Button and Filter Info -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <?php if ($filter_type && $filter_value): ?>
                        <small class="text-muted">
                            Showing results for: <?= htmlspecialchars(ucfirst($filter_type) . ': ' . $filter_display_value) ?>
                            <?php if ($filter_type == 'date' && $filter_value == 'custom'): ?>
                                (<?= date('M d, Y', strtotime($custom_from)) ?> - <?= date('M d, Y', strtotime($custom_to)) ?>)
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-bg-primary mb-3 compact-card">
                        <div class="card-body">
                            <h6 class="card-title mb-1">Total Sales</h6>
                            <h4 class="card-text mb-0">₱<?= number_format($total_sales, 2) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-info mb-3 compact-card">
                        <div class="card-body">
                            <h6 class="card-title mb-1">Number of Transactions</h6>
                            <h4 class="card-text mb-0"><?= count($sales) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-success mb-3 compact-card">
                        <div class="card-body">
                            <h6 class="card-title mb-1">Filter</h6>
                            <p class="card-text text-capitalize mb-0"><?= htmlspecialchars($filter_type ? $filter_type . ': ' . $filter_display_value : 'All Records') ?></p>
                            <?php if ($filter_type == 'date' && $filter_value == 'custom'): ?>
                                <div class="small mt-1">From: <?= htmlspecialchars($custom_from) ?><br>To: <?= htmlspecialchars($custom_to) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div id="sales-report-print">
                        <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ticket Number</th>
                                        <th>Customer</th>
                                        <th>Price</th>
                                        <th>Completed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sale['job_order_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($sale['customer_name'] ?? '') ?></td>
                                            <td>₱<?= number_format($sale['price'] ?? 0,2) ?></td>
                                            <td><?= htmlspecialchars($sale['completed_at'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($sales)): ?>
                                        <tr><td colspan="4" class="text-center">No sales found.</td></tr>
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
                                            <span style="font-weight: 600;">Number of Aircons Sold:</span>
                                            <span style="font-weight: bold; color: #27ae60;"><?= count($sales) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span style="font-weight: 600;">Total Sales Amount:</span>
                                            <span style="font-weight: bold; color: #e74c3c; font-size: 1.1em;">₱<?= number_format($total_sales, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
 <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <!-- <script src="../js/dashboard.js"></script> -->
<script>
function printSalesReport() {
    var printContents = document.getElementById('sales-report-print').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

</script>
<style>
/* Compact card styling for summary cards */
.compact-card .card-body {
    padding: 0.75rem !important;
}

.compact-card .card-title {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    margin-bottom: 0.25rem !important;
}

.compact-card .card-text {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    margin-bottom: 0 !important;
}

.compact-card {
    min-height: auto !important;
}

/* Make first column (Ticket Number) bold for screen view */
.table th:nth-child(1), .table td:nth-child(1) {
    font-weight: bold !important;
}

@media print {
    /* Hide screen elements */
    .navbar, .sidebar, #sidebar, .wrapper > #sidebar, .btn, .card-header, .modal, .d-flex.gap-2, .pagination, form, .dropdown, #sidebarCollapse, #content nav,
    .row.mb-4, /* This hides the summary cards row */
    .card.text-bg-primary, .card.text-bg-info, .card.text-bg-success /* Extra safety for summary cards */
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
    
    /* Hide main page title from print */
    h3, h5 {
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
    
    /* Print header content */
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
    
    .print-admin-info strong {
        font-size: 11px !important;
    }
    
    /* Sales Report title on left */
    .print-report-title {
        display: block !important;
        font-size: 16px !important;
        font-weight: bold !important;
        color: #2c3e50 !important;
        margin-bottom: 15px !important;
        text-align: left !important;
    }

    /* Table styling for clean print */
    .table-wrapper {
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
        border-radius: 0 !important;
    }
    
    .table-responsive {
        overflow: visible !important;
    }
    
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 20px !important;
        font-size: 8px !important;
        table-layout: fixed !important;
    }

    .table th {
         background: #000 !important;
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
     }

    /* Optimized column widths for sales report */
    .table th:nth-child(1), .table td:nth-child(1) { width: 25% !important; font-weight: bold !important; }  /* Order # */
    .table th:nth-child(2), .table td:nth-child(2) { width: 35% !important; } /* Customer */
    .table th:nth-child(3), .table td:nth-child(3) { width: 20% !important; }  /* Price */
    .table th:nth-child(4), .table td:nth-child(4) { width: 20% !important; }  /* Completed At */

    .table tbody tr:nth-child(even) {
         background: #fff !important;
     }
     

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
    
    /* Hide filter information card during printing */
    .text-bg-success {
        display: none !important;
    }
}
</style>

<script>
let filterOptions = {
    customer: [],
    technician: [],
    date: [
        { value: 'day', label: 'Today' },
        { value: 'week', label: 'This Week' },
        { value: 'month', label: 'This Month' },
        { value: 'year', label: 'This Year' },
        { value: 'custom', label: 'Custom Range' }
    ]
};

function handleFilterTypeChange() {
    const filterType = document.getElementById('filter_type').value;
    const filterValue = document.getElementById('filter_value');
    const customDateInputs = document.querySelectorAll('input[type="date"]');
    
    // Hide custom date inputs by default
    customDateInputs.forEach(input => {
        const parent = input.closest('.col-md-2');
        if (parent) parent.style.display = 'none';
    });
    
    if (!filterType) {
        filterValue.innerHTML = '<option value="">Select filter type first</option>';
        filterValue.disabled = true;
        return;
    }
    
    filterValue.disabled = false;
    
    if (filterType === 'customer' || filterType === 'service_type' || filterType === 'technician') {
        // Fetch options from server
        fetch(`controller/get_filter_options.php?filter_type=${filterType}`)
            .then(response => response.json())
            .then(data => {
                filterValue.innerHTML = '<option value="">Select ' + filterType.replace('_', ' ') + '</option>';
                
                if (data.options && data.options.length > 0) {
                    if (filterType === 'technician') {
                        data.options.forEach(option => {
                            filterValue.innerHTML += `<option value="${option.value}">${option.label}</option>`;
                        });
                    } else {
                        data.options.forEach(option => {
                            filterValue.innerHTML += `<option value="${option}">${option}</option>`;
                        });
                    }
                }
                
                // Set selected value if exists
                const currentValue = '<?= htmlspecialchars($filter_value) ?>';
                if (currentValue) {
                    filterValue.value = currentValue;
                }
            })
            .catch(error => {
                console.error('Error fetching filter options:', error);
                filterValue.innerHTML = '<option value="">Error loading options</option>';
            });
    } else if (filterType === 'date') {
        filterValue.innerHTML = '<option value="">Select date range</option>';
        filterOptions.date.forEach(option => {
            filterValue.innerHTML += `<option value="${option.value}">${option.label}</option>`;
        });
        
        // Set selected value if exists
        const currentValue = '<?= htmlspecialchars($filter_value) ?>';
        if (currentValue) {
            filterValue.value = currentValue;
            handleFilterValueChange();
        }
    }
}

function handleFilterValueChange() {
    const filterType = document.getElementById('filter_type').value;
    const filterValue = document.getElementById('filter_value').value;
    const customDateInputs = document.querySelectorAll('input[type="date"]');
    
    if (filterType === 'date' && filterValue === 'custom') {
        customDateInputs.forEach(input => {
            const parent = input.closest('.col-md-2');
            if (parent) parent.style.display = 'block';
        });
    } else {
        customDateInputs.forEach(input => {
            const parent = input.closest('.col-md-2');
            if (parent) parent.style.display = 'none';
        });
    }
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const filterType = document.getElementById('filter_type').value;
    if (filterType) {
        handleFilterTypeChange();
    }
    
    // Add event listener for filter value change
    document.getElementById('filter_value').addEventListener('change', handleFilterValueChange);
});
</script>

</body>
</html>