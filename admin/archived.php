<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get filter parameters from the request
$search_customer = $_GET['search_customer'] ?? '';
$filter_service = $_GET['filter_service'] ?? '';
$filter_technician = $_GET['filter_technician'] ?? '';


try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get completed and cancelled job orders
    $sql = "
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            t.name as technician_name,
            t.profile_picture as technician_profile,
            CASE 
                WHEN jo.status = 'completed' THEN COALESCE(jo.completed_at, jo.updated_at)
                ELSE COALESCE(jo.updated_at, jo.created_at)
            END as status_date
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
        WHERE jo.status IN ('completed', 'cancelled')
    ";

    $params = [];

    if (!empty($search_customer)) {
        $sql .= " AND jo.customer_name LIKE ?";
        $params[] = '%' . $search_customer . '%';
    }

    if (!empty($filter_service)) {
        $sql .= " AND jo.service_type = ?";
        $params[] = $filter_service;
    }

    if (!empty($filter_technician)) {
        $sql .= " AND jo.assigned_technician_id = ?";
        $params[] = $filter_technician;
    }

    if (!empty($start_date)) {
        $sql .= " AND (jo.completed_at >= ? OR jo.updated_at >= ?)";
        $params[] = $start_date . ' 00:00:00';
        $params[] = $start_date . ' 00:00:00';
    }

    if (!empty($end_date)) {
        $sql .= " AND (jo.completed_at <= ? OR jo.updated_at <= ?)";
        $params[] = $end_date . ' 23:59:59';
        $params[] = $end_date . ' 23:59:59';
    }

    $sql .= "
        ORDER BY 
            CASE 
                WHEN jo.status = 'completed' THEN 1
                WHEN jo.status = 'cancelled' THEN 2
            END,
            status_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get technicians for dropdown
    $stmt = $pdo->query("SELECT id, name FROM technicians");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
require_once 'includes/header.php';
?>
<body></body>
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
    <h3>Archived Orders</h3>
    
    <!-- Print Header (hidden by default, shown only when printing) -->
    <div class="print-header" style="display: none;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <img src="images/logo.png" alt="Company Logo" style="height: 60px; width: auto;">
            </div>
            <div class="text-end">
                <div style="font-size: 14px; font-weight: bold; color: #2c3e50;">Date Generated:</div>
                <div style="font-size: 12px; color: #7f8c8d;"><?= date('F j, Y \a\t g:i A') ?></div>
            </div>
        </div>
    </div>
    
    <div class="mb-4">
        <p class="text-muted mb-0">View completed and cancelled job orders</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter Archived Orders</h5>
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="search_customer" class="form-label">Search Customer</label>
                    <input type="text" class="form-control" id="search_customer" name="search_customer" value="<?= htmlspecialchars($search_customer) ?>" placeholder="Enter customer name">
                </div>
                <div class="col-md-3">
                    <label for="filter_service" class="form-label">Service Type</label>
                    <select class="form-select" id="filter_service" name="filter_service">
                        <option value="">All Service Types</option>
                        <option value="installation" <?= $filter_service === 'installation' ? 'selected' : '' ?>>Installation</option>
                        <option value="repair" <?= $filter_service === 'repair' ? 'selected' : '' ?>>Repair</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_technician" class="form-label">Technician</label>
                    <select class="form-select" id="filter_technician" name="filter_technician">
                        <option value="">All Technicians</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['id'] ?>" <?= (string)$filter_technician === (string)$tech['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

            <!-- Orders Table -->
            <div class="card mb-4" style="position: relative;">
                    <div class="card-body">
                        <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                            <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ticket Number</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Model</th>
                                        <th>Technician</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Price</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($order['job_order_number']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['customer_phone']) ?>
                                            </small>
                                            <small class="text-muted d-block text-truncate" style="max-width: 200px;">
                                                <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($order['customer_address']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <?= ucfirst(htmlspecialchars($order['service_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($order['model_name']) ?></td>
                                        <td>
                                            <?php if ($order['technician_name']): ?>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($order['technician_profile']) ? '../' . htmlspecialchars($order['technician_profile']) : 'https://ui-avatars.com/api/?name=' . urlencode($order['technician_name']) . '&background=1a237e&color=fff' ?>" 
                                                         alt="<?= htmlspecialchars($order['technician_name']) ?>" 
                                                         class="rounded-circle me-2" 
                                                         width="24" height="24"
                                                         style="object-fit: cover;">
                                                    <?= htmlspecialchars($order['technician_name']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $order['status'] === 'completed' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-medium">
                                                <?php 
                                                if (!empty($order['status_date'])) {
                                                    echo date('M d, Y', strtotime($order['status_date']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">₱<?= number_format($order['price'], 2) ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <button 
                                                    class="btn btn-sm btn-light view-order-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewOrderModal"
                                                    data-order-id="<?= $order['id'] ?>"
                                                    title="View Details">
                                                    <i class="fas fa-eye text-primary"></i>
                                                 </button>

                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>

    <style>
        @media print {
            /* Hide screen elements */
            .navbar, .sidebar, .btn, .card-header, .form-control, .form-select, 
            .dropdown, .btn-group, .d-flex.gap-2 {
                display: none !important;
            }
            
            /* Show print header */
            .print-header {
                display: block !important;
                margin-bottom: 30px !important;
                padding-bottom: 20px !important;
                border-bottom: 2px solid #34495e !important;
                page-break-after: avoid !important;
            }
            
            .print-header img {
                display: block !important;
                max-height: 60px !important;
                width: auto !important;
            }
            
            .print-header .text-end {
                text-align: right !important;
            }
            
            /* Reset page layout */
            body {
                margin: 0 !important;
                padding: 20px !important;
                font-family: 'Arial', sans-serif !important;
                font-size: 12px !important;
                line-height: 1.4 !important;
                color: #000 !important;
                background: white !important;
            }
            
            /* Header styling */
            .container-fluid {
                max-width: none !important;
                padding: 0 !important;
            }
            
            /* Table styling for print */
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 20px !important;
                font-size: 11px !important;
            }
            
            .table th {
                background: #34495e !important;
                color: white !important;
                font-weight: bold !important;
                text-align: left !important;
                padding: 12px 8px !important;
                border: none !important;
                font-size: 12px !important;
            }
            
            .table td {
                padding: 10px 8px !important;
                border: none !important;
                vertical-align: top !important;
            }
            
            .table tbody tr:nth-child(even) {
                background: #f8f9fa !important;
            }
            
            /* Status badges for print */
            .badge {
                display: inline-block !important;
                padding: 4px 8px !important;
                font-size: 10px !important;
                font-weight: bold !important;
                border-radius: 3px !important;
                border: 1px solid !important;
            }
            
            .badge.bg-success {
                background: #d4edda !important;
                color: #155724 !important;
                border-color: #c3e6cb !important;
            }
            
            .badge.bg-danger {
                background: #f8d7da !important;
                color: #721c24 !important;
                border-color: #f5c6cb !important;
            }
            
            .badge.bg-info {
                background: #d1ecf1 !important;
                color: #0c5460 !important;
                border-color: #bee5eb !important;
            }
            
            /* Hide profile images in print */
            .table img {
                display: none !important;
            }
            
            /* Hide action column in print */
            .table th:last-child,
            .table td:last-child {
                display: none !important;
            }
            
            /* Customer information styling */
            .fw-medium {
                font-weight: bold !important;
                color: #2c3e50 !important;
            }
            
            .text-muted {
                color: #7f8c8d !important;
            }
            
            .fw-semibold {
                font-weight: bold !important;
                color: #2c3e50 !important;
            }
            
            /* Page breaks */
            .table-responsive {
                page-break-inside: auto !important;
            }
            
            .table tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
        }
    </style>

    <!-- View Order Modal -->
    <div class="modal fade" id="viewOrderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Customer Information -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Customer Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Name</label>
                                        <p class="mb-0" id="view_customer_name">-</p>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Phone</label>
                                        <p class="mb-0" id="view_customer_phone">-</p>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label text-muted">Address</label>
                                        <p class="mb-0" id="view_customer_address">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Service Information -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Service Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Service Type</label>
                                        <p class="mb-0" id="view_service_type">-</p>
                                    </div>
                                    <div class="mb-2" id="view_aircon_model_section">
                                        <label class="form-label text-muted">Aircon Model</label>
                                        <p class="mb-0" id="view_aircon_model">-</p>
                                    </div>
                                    <div class="mb-2" id="view_part_info_section" style="display: none;">
                                        <label class="form-label text-muted">Part Name</label>
                                        <p class="mb-0" id="view_part_name">-</p>
                                    </div>
                                    <div class="mb-2" id="view_part_code_section" style="display: none;">
                                        <label class="form-label text-muted">Part Code</label>
                                        <p class="mb-0" id="view_part_code">-</p>
                                    </div>
                                    <div class="mb-2" id="view_part_category_section" style="display: none;">
                                        <label class="form-label text-muted">Part Category</label>
                                        <p class="mb-0" id="view_part_category">-</p>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label text-muted">Job Order Number</label>
                                        <p class="mb-0" id="view_job_order_number">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Information -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Status Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Current Status</label>
                                        <p class="mb-0" id="view_status">-</p>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Created Date</label>
                                        <p class="mb-0" id="view_created_date">-</p>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label text-muted">Last Updated</label>
                                        <p class="mb-0" id="view_updated_date">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pricing Information -->
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Pricing Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Base Price</label>
                                        <p class="mb-0" id="view_base_price">-</p>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Additional Fee</label>
                                        <p class="mb-0" id="view_additional_fee">-</p>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Discount</label>
                                        <p class="mb-0" id="view_discount">-</p>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label text-muted">Total Price</label>
                                        <p class="mb-0 fw-bold text-primary" id="view_total_price">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Technician Information -->
                        <div class="col-12" id="view_technician_section" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Assigned Technician</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <img id="view_technician_avatar" src="" alt="Technician" class="rounded-circle me-3" width="48" height="48">
                                        <div>
                                            <h6 class="mb-1" id="view_technician_name">-</h6>
                                            <p class="text-muted mb-0" id="view_technician_phone">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View Order Modal Population
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.view-order-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var orderId = this.getAttribute('data-order-id');
                    if (!orderId) return;
                    
                    // Set order ID for print button
                    document.getElementById('viewOrderModal').dataset.orderId = orderId;
                    
                    // Fetch order details via AJAX
                    fetch('controller/get_order_details.php?id=' + orderId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                var order = data.order;
                                
                                // Populate customer information
                                document.getElementById('view_customer_name').textContent = order.customer_name || '-';
                                document.getElementById('view_customer_phone').textContent = order.customer_phone || '-';
                                document.getElementById('view_customer_address').textContent = order.customer_address || '-';
                                
                                // Populate service information
                                document.getElementById('view_service_type').textContent = order.service_type ? order.service_type.charAt(0).toUpperCase() + order.service_type.slice(1) : '-';
                                
                                // Show/hide sections based on service type
                                if (order.service_type === 'repair') {
                                    // Hide aircon model section and show parts information
                                    document.getElementById('view_aircon_model_section').style.display = 'none';
                                    document.getElementById('view_part_info_section').style.display = 'block';
                                    document.getElementById('view_part_code_section').style.display = 'block';
                                    document.getElementById('view_part_category_section').style.display = 'block';
                                    
                                    // Populate parts information
                                    document.getElementById('view_part_name').textContent = order.part_name || 'Not Specified';
                                    document.getElementById('view_part_code').textContent = order.part_code || 'Not Specified';
                                    document.getElementById('view_part_category').textContent = order.part_category ? order.part_category.charAt(0).toUpperCase() + order.part_category.slice(1) : 'Not Specified';
                                } else {
                                    // Show aircon model section and hide parts information
                                    document.getElementById('view_aircon_model_section').style.display = 'block';
                                    document.getElementById('view_part_info_section').style.display = 'none';
                                    document.getElementById('view_part_code_section').style.display = 'none';
                                    document.getElementById('view_part_category_section').style.display = 'none';
                                    
                                    // Populate aircon model information
                                    document.getElementById('view_aircon_model').textContent = order.model_name || 'Not Specified';
                                }
                                
                                document.getElementById('view_job_order_number').textContent = order.job_order_number || '-';
                                
                                // Populate status information
                                var statusBadge = '<span class="badge bg-' + 
                                    (order.status === 'completed' ? 'success' : 
                                    (order.status === 'in_progress' ? 'warning' : 
                                    (order.status === 'pending' ? 'danger' : 'secondary'))) + '">' + 
                                    (order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1).replace('_', ' ') : '-') + '</span>';
                                document.getElementById('view_status').innerHTML = statusBadge;
                                
                                document.getElementById('view_created_date').textContent = order.created_at ? new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                                document.getElementById('view_updated_date').textContent = order.updated_at ? new Date(order.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                                
                                // Populate pricing information
                                document.getElementById('view_base_price').textContent = order.base_price ? '₱' + parseFloat(order.base_price).toFixed(2) : '₱0.00';
                                document.getElementById('view_additional_fee').textContent = order.additional_fee ? '₱' + parseFloat(order.additional_fee).toFixed(2) : '₱0.00';
                                document.getElementById('view_discount').textContent = order.discount ? '₱' + parseFloat(order.discount).toFixed(2) : '₱0.00';
                                document.getElementById('view_total_price').textContent = order.price ? '₱' + parseFloat(order.price).toFixed(2) : '₱0.00';
                                
                                // Populate technician information
                                var techSection = document.getElementById('view_technician_section');
                                if (order.technician_name) {
                                    techSection.style.display = 'block';
                                    document.getElementById('view_technician_name').textContent = order.technician_name;
                                    document.getElementById('view_technician_phone').textContent = order.technician_phone || 'N/A';
                                    
                                    // Set technician avatar
                                    var avatar = document.getElementById('view_technician_avatar');
                                    if (order.technician_profile) {
                                        avatar.src = '../' + order.technician_profile;
                                    } else {
                                        avatar.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(order.technician_name) + '&background=1a237e&color=fff';
                                    }
                                } else {
                                    techSection.style.display = 'none';
                                }
                            } else {
                                alert('Error loading order details: ' + (data.message || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error loading order details. Please try again.');
                        });
                });
            });
        });
    </script>
</body>
</html>