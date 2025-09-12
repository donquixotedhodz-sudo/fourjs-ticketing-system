<?php
session_start();
require_once '../config/database.php';

// Authentication check
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

// Fetch admin info for header
$admin = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch technicians for dropdown
$tech_stmt = $pdo->query("SELECT id, name FROM technicians ORDER BY name ASC");
$technicians = $tech_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters

// New dynamic filter parameters
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

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM job_orders WHERE $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN service_type = 'repair' THEN 1 ELSE 0 END) as repair_orders,
    SUM(CASE WHEN service_type = 'installation' THEN 1 ELSE 0 END) as installation_orders,
    SUM(CASE WHEN service_type = 'cleaning' THEN 1 ELSE 0 END) as cleaning_orders,
    SUM(CASE WHEN service_type = 'maintenance' THEN 1 ELSE 0 END) as maintenance_orders,
    SUM(CASE WHEN service_type = 'survey' THEN 1 ELSE 0 END) as survey_orders,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
    SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) as total_completed_price
    FROM job_orders WHERE $where";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Get job orders (including cancelled)
$sql = "SELECT job_orders.*, 
               aircon_models.brand, 
               aircon_models.model_name,
               ac_parts.part_name,
               ac_parts.part_code,
               ac_parts.part_category
        FROM job_orders 
        LEFT JOIN aircon_models ON job_orders.aircon_model_id = aircon_models.id AND (job_orders.service_type = 'installation' OR job_orders.service_type = 'cleaning')
        LEFT JOIN ac_parts ON job_orders.part_id = ac_parts.id AND job_orders.service_type = 'repair'
        WHERE $where 
        ORDER BY job_orders.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
} elseif ($filter_type == 'service_type') {
    $filter_display_value = ucfirst($filter_value);
}

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
    <h3>Job Orders Report</h3>

    <!-- Print Header (hidden by default, shown only when printing) -->
    <div class="print-header-custom" style="display: none;">
        <img src="images/logo.png" alt="Company Logo" class="print-logo">
        <div class="print-admin-info">
            <div><strong>Administrator:</strong> <?= htmlspecialchars($admin['name'] ?? 'Admin') ?></div>
            <div><strong>Date:</strong> <?= date('F j, Y \\a\\t g:i A') ?></div>
        </div>
    </div>
    
    <!-- Report Title for Print -->
    <div class="print-report-title" style="display: none;">Job Orders Report</div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">View and print all job orders with filters</p>
        </div>
        <button class="btn btn-success" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

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

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Total Orders</h5>
                            <h3 class="card-text"><?= $summary['total_orders'] ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Repair Orders</h5>
                            <h3 class="card-text"><?= $summary['repair_orders'] ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Installation Orders</h5>
                            <h3 class="card-text"><?= $summary['installation_orders'] ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Cleaning Orders</h5>
                            <h3 class="card-text"><?= $summary['cleaning_orders'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Orders Table -->
            <div class="card mb-4" style="position: relative;">
                <div class="card-body">
                    <div id="job-orders-report-print">
                        <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                            <table class="table table-bordered table-hover align-middle">
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
                                        <th>Technician</th>
                                        <th>Created At</th>
                                        <th>Completed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['job_order_number'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($order['customer_name'] ?? '') ?></td>
                                            <td>
                                <?php
                                $badge_class = '';
                                switch($order['service_type']) {
                                    case 'installation': $badge_class = 'bg-primary'; break;
                                    case 'cleaning': $badge_class = 'bg-info'; break;
                                    case 'repair': $badge_class = 'bg-warning'; break;
                                    case 'survey': $badge_class = 'bg-secondary'; break;
                                    default: $badge_class = 'bg-secondary';
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>">
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
                                            <td>
                                                <?php
                                                $tech_name = '';
                                                if (!empty($order['assigned_technician_id'])) {
                                                    foreach ($technicians as $tech) {
                                                        if ($tech['id'] == $order['assigned_technician_id']) {
                                                            $tech_name = $tech['name'];
                                                            break;
                                                        }
                                                    }
                                                }
                                                echo htmlspecialchars($tech_name);
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($order['created_at'] ?? '') ?></td>
                                            <td>
                                                <?php if ($order['status'] == 'completed' && !empty($order['completed_at'])): ?>
                                                    <?= htmlspecialchars($order['completed_at']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($orders)): ?>
                                        <tr><td colspan="12" class="text-center">No job orders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary Section for Print -->
                        <div class="print-summary mt-4" style="border-top: 2px solid #34495e; padding-top: 20px; display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <h5 class="mb-2" style="color: #2c3e50; font-weight: bold;">Summary</h5>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span style="font-weight: 600;">Total Job Orders:</span>
                                            <span style="font-weight: bold; color: #27ae60;"><?= $summary['total_orders'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span style="font-weight: 600;">Completed Orders:</span>
                                            <span style="font-weight: bold; color: #28a745;"><?= $summary['completed_orders'] ?></span>
                                        </div>
                                      
                                        <div class="d-flex justify-content-between mb-2">
                                            <span style="font-weight: 600;">Repair Orders:</span>
                                            <span style="font-weight: bold; color: #f39c12;"><?= $summary['repair_orders'] ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span style="font-weight: 600;">Installation Orders:</span>
                                            <span style="font-weight: bold; color: #3498db;"><?= $summary['installation_orders'] ?></span>
                                        </div>
                                        <?php if ($summary['survey_orders'] > 0): ?>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span style="font-weight: 600;">Survey Orders:</span>
                                            <span style="font-weight: bold; color: #9b59b6;"><?= $summary['survey_orders'] ?></span>
                                        </div>
                                          <div class="d-flex justify-content-between mb-2">
                                            <span style="font-weight: 600;">Total Price (Completed):</span>
                                            <span style="font-weight: bold; color: #dc3545;">₱<?= number_format($summary['total_completed_price'] ?? 0, 2) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                        <!-- Loading overlay -->
                        <div id="loading-overlay" class="loading-overlay" style="display: none;">
                            <div class="loading-spinner">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="mt-2">Loading...</div>
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
<!-- Print Script -->
<script>
function printJobOrdersReport() {
    window.print();
}

function handleFilterTypeChange() {
    const filterTypeSelect = document.getElementById('filter_type');
    const filterValueSelect = document.getElementById('filter_value');
    const selectedType = filterTypeSelect.value;
    
    // Clear and disable the filter value dropdown
    filterValueSelect.innerHTML = '<option value="">Loading...</option>';
    filterValueSelect.disabled = true;
    
    if (!selectedType) {
        filterValueSelect.innerHTML = '<option value="">Select filter type first</option>';
        return;
    }
    
    // Fetch options for the selected filter type
    fetch(`controller/get_filter_options.php?filter_type=${selectedType}`)
        .then(response => response.json())
        .then(data => {
            filterValueSelect.innerHTML = '<option value="">All</option>';
            
            if (data.options && data.options.length > 0) {
                data.options.forEach(option => {
                    const optionElement = document.createElement('option');
                    
                    if (typeof option === 'object' && option.value !== undefined) {
                        // For technicians and date options
                        optionElement.value = option.value;
                        optionElement.textContent = option.label;
                    } else {
                        // For customers and service types
                        optionElement.value = option;
                        optionElement.textContent = option;
                    }
                    
                    filterValueSelect.appendChild(optionElement);
                });
            }
            
            filterValueSelect.disabled = false;
            
            // Restore selected value if it exists
            const currentFilterValue = '<?= htmlspecialchars($filter_value) ?>';
            if (currentFilterValue && selectedType === '<?= htmlspecialchars($filter_type) ?>') {
                filterValueSelect.value = currentFilterValue;
            }
        })
        .catch(error => {
            console.error('Error fetching filter options:', error);
            filterValueSelect.innerHTML = '<option value="">Error loading options</option>';
            filterValueSelect.disabled = false;
        });
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

function handleFilterValueChange() {
    const filterType = document.getElementById('filter_type').value;
    const filterValue = document.getElementById('filter_value').value;
    
    // If date filter and custom is selected, show date inputs
    if (filterType === 'date' && filterValue === 'custom') {
        document.getElementById('filterForm').submit();
    }
}

// Update the print button to use the new function
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.querySelector('button[onclick="window.print()"]');
    if (printBtn) {
        printBtn.setAttribute('onclick', 'printJobOrdersReport()');
    }
    
    // Initialize filter dropdowns
    const filterTypeSelect = document.getElementById('filter_type');
    const filterValueSelect = document.getElementById('filter_value');
    
    // Add event listener for filter value change
    if (filterValueSelect) {
        filterValueSelect.addEventListener('change', handleFilterValueChange);
    }
    
    // Initialize filter value dropdown if filter type is already selected
    if (filterTypeSelect && filterTypeSelect.value) {
        handleFilterTypeChange();
    }
    
    // Smooth pagination functionality
    const paginationLinks = document.querySelectorAll('.pagination-link');
    const tableCard = document.querySelector('.card.mb-4');
    const tableResponsive = document.querySelector('.table-responsive');
    const loadingOverlay = document.getElementById('loading-overlay');
    
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Don't do anything if clicking the current page
            if (this.closest('.page-item').classList.contains('active')) {
                return;
            }
            
            // Show loading state
            showLoadingState();
            
            // Navigate to the new page after a short delay for smooth transition
            setTimeout(() => {
                window.location.href = this.href;
            }, 200);
        });
    });
    
    function showLoadingState() {
        // Add loading classes
        if (tableCard) tableCard.classList.add('loading');
        if (tableResponsive) tableResponsive.classList.add('loading');
        if (loadingOverlay) loadingOverlay.style.display = 'flex';
        
        // Disable pagination links
        paginationLinks.forEach(link => {
            link.style.pointerEvents = 'none';
            link.style.opacity = '0.6';
        });
    }
    
    // Add smooth scroll to top when page loads
    if (window.location.search.includes('page=')) {
        setTimeout(() => {
            document.querySelector('.card.mb-4').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 100);
    }
    
    // Add hover effects to pagination
    paginationLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            if (!this.closest('.page-item').classList.contains('active')) {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            }
        });
        
        link.addEventListener('mouseleave', function() {
            if (!this.closest('.page-item').classList.contains('active')) {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            }
        });
    });
});
</script>

<style>
/* Table font size for screen */
.table {
    font-size: 14px !important;
}

/* Make first column (Order #) bold */
.table th:nth-child(1), .table td:nth-child(1) {
    font-weight: bold !important;
}

/* Smooth pagination transitions */
.table-responsive {
    transition: opacity 0.3s ease-in-out;
}

.table-responsive.loading {
    opacity: 0.5;
}

.pagination-link {
    transition: all 0.2s ease-in-out;
}

.pagination-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Loading overlay styles */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    border-radius: 0.375rem;
}

.loading-spinner {
    text-align: center;
    color: #0d6efd;
}

/* Card animation */
.card {
    transition: all 0.3s ease-in-out;
}

.card.loading {
    transform: scale(0.98);
    opacity: 0.7;
}

/* Fade in animation for table rows */
.table tbody tr {
    animation: fadeInUp 0.3s ease-in-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Pagination hover effects */
.page-link {
    transition: all 0.2s ease-in-out;
}

.page-item.active .page-link {
    transform: scale(1.05);
    box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
}

/* Print styles */
@media print {
    
    /* Set page orientation to landscape */
    @page {
        size: landscape;
        margin: 0.5in;
    }
    
    /* Hide all screen elements except table and summary */
    .navbar, .sidebar, #sidebar, .wrapper > #sidebar, .btn, .card-header, .modal, .pagination, form, .dropdown, #sidebarCollapse, #content nav,
    .row.mb-4, .card.text-bg-primary, .card.text-bg-warning, .card.text-bg-info, .card.text-bg-success,
    .d-flex.justify-content-between, .loading-overlay, .filter-section, .print-button,
    .container-fluid > .row:first-child, .d-flex.gap-2,
    .d-flex.justify-content-between.align-items-center.mb-4
    {
        display: none !important;
    }
    
    /* Show description text in print */
    .text-muted {
        display: block !important;
        color: #666 !important;
        font-size: 10px !important;
        margin-bottom: 15px !important;
        text-align: center !important;
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

    /* Enhanced Print Header Styles */
    .print-header-enhanced {
        display: block !important;
        margin-bottom: 25px !important;
        padding: 20px 0 !important;
        border-bottom: 2px solid #000 !important;
    }
    
    .print-header-top {
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        margin-bottom: 15px !important;
    }
    
    .print-logo-section {
        display: flex !important;
        align-items: center !important;
        gap: 15px !important;
    }
    
    .print-logo {
        max-height: 60px !important;
        width: auto !important;
    }
    
    .company-info h2 {
        font-size: 18px !important;
        font-weight: bold !important;
        margin: 0 !important;
        color: #000 !important;
    }
    
    .company-info p {
        font-size: 10px !important;
        margin: 2px 0 0 0 !important;
        color: #666 !important;
    }
    
    .print-admin-info {
        text-align: right !important;
        font-size: 10px !important;
        line-height: 1.4 !important;
    }
    
    .print-admin-info div {
        margin-bottom: 2px !important;
    }
    
    .print-admin-info strong {
        font-weight: bold !important;
    }
    
    .print-report-header h1 {
        font-size: 20px !important;
        font-weight: bold !important;
        margin: 0 0 10px 0 !important;
        color: #000 !important;
        text-align: center !important;
    }
    
    .filter-info-box {
        background: #f8f9fa !important;
        border: 1px solid #ddd !important;
        padding: 8px 12px !important;
        border-radius: 4px !important;
        font-size: 11px !important;
        text-align: center !important;
    }
    
    .filter-type {
        color: #007bff !important;
        font-weight: bold !important;
    }
    
    .filter-value {
        color: #28a745 !important;
        font-weight: bold !important;
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
    
    /* Job Orders Report title on left */
    .print-report-title {
        display: block !important;
        font-size: 16px !important;
        font-weight: bold !important;
        color: #2c3e50 !important;
        margin-bottom: 15px !important;
        text-align: left !important;
    }
    
    /* Show all columns including Completed At */
    .table th,
    .table td {
        display: table-cell !important;
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
        background: transparent !important;
    }
    
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 20px !important;
        font-size: 8px !important;
        table-layout: fixed !important;
        background: transparent !important;
    }

    .table th {
         background: transparent !important;
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
         background: transparent !important;
     }

    /* Optimized column widths for job orders */
    .table th:nth-child(1), .table td:nth-child(1) { width: 7% !important; }  /* Ticket # */
    .table th:nth-child(2), .table td:nth-child(2) { width: 11% !important; } /* Customer */
    .table th:nth-child(3), .table td:nth-child(3) { width: 9% !important; } /* Service Type */
    .table th:nth-child(4), .table td:nth-child(4) { width: 7% !important; }  /* Brand */
    .table th:nth-child(5), .table td:nth-child(5) { width: 7% !important; }  /* Model */
    .table th:nth-child(6), .table td:nth-child(6) { width: 7% !important; }  /* Part Code */
    .table th:nth-child(7), .table td:nth-child(7) { width: 11% !important; } /* Part Name */
    .table th:nth-child(8), .table td:nth-child(8) { width: 7% !important; }  /* Price */
    .table th:nth-child(9), .table td:nth-child(9) { width: 7% !important; }  /* Status */
    .table th:nth-child(10), .table td:nth-child(10) { width: 9% !important; } /* Technician */
    .table th:nth-child(11), .table td:nth-child(11) { width: 8% !important; } /* Created At */
    .table th:nth-child(12), .table td:nth-child(12) { width: 10% !important; } /* Completed At */

    .table tbody tr:nth-child(even) {
         background: transparent !important;
     }
     
     .table tbody tr {
         background: transparent !important;
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
</body>
</html>