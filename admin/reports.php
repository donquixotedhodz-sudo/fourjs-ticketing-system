<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Redirect to job orders report as this is the main reports page
header('Location: job_orders_report.php');
exit();

// Get filter parameters from the request
$search_customer = $_GET['search_customer'] ?? '';
$filter_service = $_GET['filter_service'] ?? '';
$filter_technician = $_GET['filter_technician'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Build the main query for all job orders
    $sql = "
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            COALESCE(am.brand, 'Not Specified') as brand,
            COALESCE(am.price, 0) as model_price,
            t.name as technician_name,
            t.profile_picture as technician_profile,
            CASE 
                WHEN jo.status = 'completed' THEN COALESCE(jo.completed_at, jo.updated_at)
                WHEN jo.status = 'cancelled' THEN COALESCE(jo.updated_at, jo.created_at)
                ELSE jo.created_at
            END as status_date
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
        WHERE 1=1
    ";

    $params = [];

    // Apply filters
    if (!empty($search_customer)) {
        $sql .= " AND (jo.customer_name LIKE ? OR jo.customer_phone LIKE ? OR jo.job_order_number LIKE ?)";
        $params[] = '%' . $search_customer . '%';
        $params[] = '%' . $search_customer . '%';
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

    if (!empty($filter_status)) {
        $sql .= " AND jo.status = ?";
        $params[] = $filter_status;
    }

    if (!empty($start_date)) {
        $sql .= " AND jo.created_at >= ?";
        $params[] = $start_date . ' 00:00:00';
    }

    if (!empty($end_date)) {
        $sql .= " AND jo.created_at <= ?";
        $params[] = $end_date . ' 23:59:59';
    }

    // Apply sorting
    $valid_sort_fields = ['created_at', 'customer_name', 'service_type', 'status', 'due_date', 'price'];
    $sort_by = in_array($sort_by, $valid_sort_fields) ? $sort_by : 'created_at';
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    $sql .= " ORDER BY jo.$sort_by $sort_order";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get technicians for dropdown
    $stmt = $pdo->query("SELECT id, name FROM technicians ORDER BY name");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $total_orders = count($orders);
    $total_revenue = 0;
    $status_counts = [
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    $service_counts = [
        'installation' => 0,
        'repair' => 0
    ];

    foreach ($orders as $order) {
        $total_revenue += (float)$order['price'];
        $status_counts[$order['status']]++;
        $service_counts[$order['service_type']]++;
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
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

            <div class="container-fluid">
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
               
                <!-- Screen Header -->
                <div class="screen-header">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="mb-0">Job Orders Report</h4>
                            <p class="text-muted mb-0">Comprehensive view of all job orders with detailed analytics</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Report
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="exportToCSV()">
                                <i class="fas fa-file-csv me-2"></i>Export CSV
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-2"></i>Export PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="summary-section">
                    <h5 class="print-only" style="display: none;">Report Summary</h5>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Total Orders</h6>
                                            <h3 class="mb-0"><?= number_format($total_orders) ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clipboard-list fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Total Revenue</h6>
                                            <h3 class="mb-0">₱<?= number_format($total_revenue, 2) ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-money-bill-wave fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Completed</h6>
                                            <h3 class="mb-0"><?= $status_counts['completed'] ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Pending</h6>
                                            <h3 class="mb-0"><?= $status_counts['pending'] + $status_counts['in_progress'] ?></h3>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Search and Filter Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Advanced Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" id="filterForm">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="search_customer" class="form-label">Search Customer/Order #</label>
                                    <input type="text" class="form-control" id="search_customer" name="search_customer" 
                                           value="<?= htmlspecialchars($search_customer) ?>" 
                                           placeholder="Customer name, phone, or order number">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_service" class="form-label">Service Type</label>
                                    <select class="form-select" id="filter_service" name="filter_service">
                                        <option value="">All Services</option>
                                        <option value="installation" <?= $filter_service === 'installation' ? 'selected' : '' ?>>Installation</option>
                                        <option value="repair" <?= $filter_service === 'repair' ? 'selected' : '' ?>>Repair</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_technician" class="form-label">Technician</label>
                                    <select class="form-select" id="filter_technician" name="filter_technician">
                                        <option value="">All Technicians</option>
                                        <?php foreach ($technicians as $tech): ?>
                                            <option value="<?= $tech['id'] ?>" <?= (string)$filter_technician === (string)$tech['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tech['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_status" class="form-label">Status</label>
                                    <select class="form-select" id="filter_status" name="filter_status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Date Range</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                                        <span class="input-group-text">to</span>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-3">
                                    <label for="sort_by" class="form-label">Sort By</label>
                                    <select class="form-select" id="sort_by" name="sort_by">
                                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                                        <option value="customer_name" <?= $sort_by === 'customer_name' ? 'selected' : '' ?>>Customer Name</option>
                                        <option value="service_type" <?= $sort_by === 'service_type' ? 'selected' : '' ?>>Service Type</option>
                                        <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>Status</option>
                                        <option value="due_date" <?= $sort_by === 'due_date' ? 'selected' : '' ?>>Due Date</option>
                                        <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Price</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="sort_order" class="form-label">Order</label>
                                    <select class="form-select" id="sort_order" name="sort_order">
                                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                                        <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Apply Filters
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                            <i class="fas fa-times me-2"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-0">Showing <?= number_format($total_orders) ?> job orders</h6>
                        <small class="text-muted">
                            <?php if (!empty($search_customer) || !empty($filter_service) || !empty($filter_technician) || !empty($filter_status) || !empty($start_date) || !empty($end_date)): ?>
                                Filtered results
                            <?php else: ?>
                                All job orders
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary">Installation: <?= $service_counts['installation'] ?></span>
                        <span class="badge bg-info">Repair: <?= $service_counts['repair'] ?></span>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="card" data-date="<?= date('F d, Y \a\t h:i A') ?>">
                    <h5 class="print-only" style="display: none;">Job Orders Details</h5>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="ordersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Service</th>
                                        <th>Model</th>
                                        <th>Technician</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Due Date</th>
                                        <th>Price</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No job orders found matching your criteria</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($order['job_order_number']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                                <small class="text-muted d-block"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                                <small class="text-muted d-block text-truncate" style="max-width: 200px;"><?= htmlspecialchars($order['customer_address']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info bg-opacity-10 text-info">
                                                    <?= ucfirst(htmlspecialchars($order['service_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars($order['model_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($order['brand']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($order['technician_name']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= !empty($order['technician_profile']) ? '../' . htmlspecialchars($order['technician_profile']) : 'https://ui-avatars.com/api/?name=' . urlencode($order['technician_name']) . '&background=4A90E2&color=fff' ?>" 
                                                             alt="Technician" 
                                                             class="rounded-circle me-2" 
                                                             width="24" 
                                                             height="24"
                                                             style="object-fit: cover;">
                                                        <span><?= htmlspecialchars($order['technician_name']) ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($order['status']) {
                                                    case 'pending':
                                                        $statusClass = 'bg-warning';
                                                        $statusText = 'Pending';
                                                        break;
                                                    case 'in_progress':
                                                        $statusClass = 'bg-primary';
                                                        $statusText = 'In Progress';
                                                        break;
                                                    case 'completed':
                                                        $statusClass = 'bg-success';
                                                        $statusText = 'Completed';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'bg-danger';
                                                        $statusText = 'Cancelled';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <div><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                                <small class="text-muted"><?= date('h:i A', strtotime($order['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($order['due_date']): ?>
                                                    <div><?= date('M d, Y', strtotime($order['due_date'])) ?></div>
                                                    <small class="text-muted"><?= date('h:i A', strtotime($order['due_date'])) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-semibold">₱<?= number_format($order['price'], 2) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-order-btn" data-bs-toggle="modal" data-bs-target="#viewOrderModal" data-order-id="<?= $order['id'] ?>" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                                        <a href="edit-order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit Order">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clearFilters() {
            document.getElementById('search_customer').value = '';
            document.getElementById('filter_service').value = '';
            document.getElementById('filter_technician').value = '';
            document.getElementById('filter_status').value = '';
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('sort_by').value = 'created_at';
            document.getElementById('sort_order').value = 'DESC';
            document.getElementById('filterForm').submit();
        }

        function exportToCSV() {
            const table = document.getElementById('ordersTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td, th');
                let csvRow = [];
                
                for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
                    let text = cols[j].innerText.replace(/"/g, '""');
                    csvRow.push('"' + text + '"');
                }
                
                csv.push(csvRow.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'job_orders_report.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportToPDF() {
            // This would require a PDF library like jsPDF
            alert('PDF export functionality would require additional libraries. Please use the print function for now.');
        }

        // Auto-submit form when date range changes
        document.getElementById('start_date').addEventListener('change', function() {
            if (this.value && document.getElementById('end_date').value) {
                document.getElementById('filterForm').submit();
            }
        });

        document.getElementById('end_date').addEventListener('change', function() {
            if (this.value && document.getElementById('start_date').value) {
                document.getElementById('filterForm').submit();
            }
        });
    </script>

    <style>
        @media print {
            /* Hide screen elements */
            .navbar, .sidebar, .btn, .card-header, .modal, .d-flex.gap-2, .screen-header, .card.mb-4 {
                display: none !important;
            }
            
            /* Show summary cards as plain text in one line */
            .summary-section {
                display: block !important;
                margin-bottom: 30px !important;
                padding: 0 !important;
                background: none !important;
                border: none !important;
            }
            
            .summary-section .row {
                display: flex !important;
                justify-content: space-between !important;
                flex-wrap: nowrap !important;
                margin: 0 !important;
            }
            
            .summary-section .col-md-3 {
                flex: 1 !important;
                margin: 0 15px !important;
                text-align: center !important;
                padding: 0 !important;
            }
            
            .summary-section .card {
                background: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
            
            .summary-section .card-body {
                padding: 0 !important;
            }
            
            .summary-section .card-title {
                font-size: 16px !important;
                font-weight: bold !important;
                color: #2c3e50 !important;
                margin-bottom: 10px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }
            
            .summary-section h3 {
                font-size: 28px !important;
                font-weight: bold !important;
                color: #2c3e50 !important;
                margin: 0 !important;
            }
            
            .summary-section .fa-2x {
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
            
            /* Table styling for print - Redesigned for better readability */
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 25px !important;
                font-size: 12px !important;
                border: none !important;
                table-layout: fixed !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }
            
            .table th {
                background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%) !important;
                color: white !important;
                font-weight: 700 !important;
                text-align: left !important;
                padding: 15px 10px !important;
                border: none !important;
                font-size: 13px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                white-space: nowrap !important;
                position: relative !important;
            }
            
            .table th:not(:last-child)::after {
                content: none !important;
            }
            
            .table td {
                padding: 12px 10px !important;
                border: none !important;
                vertical-align: top !important;
                line-height: 1.3 !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                white-space: normal !important;
                background: white !important;
                max-width: 0 !important;
            }
            
            .table tbody tr:nth-child(even) {
                background: #f8f9fa !important;
            }
            
            .table tbody tr:nth-child(odd) {
                background: white !important;
            }
            
            .table tbody tr:hover {
                background: #e8f4fd !important;
                transform: scale(1.01) !important;
                transition: all 0.2s ease !important;
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
            
            /* Specifically hide actions column in reports table */
            #ordersTable th:last-child,
            #ordersTable td:last-child {
                display: none !important;
            }
            
            /* Hide badges and status indicators in print */
            .badge {
                display: none !important;
            }
            
            /* Show status as text - Enhanced styling */
            .badge + span,
            .badge {
                display: inline-block !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                font-size: 10px !important;
                letter-spacing: 0.5px !important;
                padding: 4px 8px !important;
                border-radius: 0 !important;
                border: none !important;
            }
            
            /* Status-specific colors for better visibility */
            .badge.bg-success,
            .badge + span:contains('completed') {
                background: #d4edda !important;
                color: #155724 !important;
                border-color: #c3e6cb !important;
            }
            
            .badge.bg-warning,
            .badge + span:contains('pending') {
                background: #fff3cd !important;
                color: #856404 !important;
                border-color: #ffeaa7 !important;
            }
            
            .badge.bg-info,
            .badge + span:contains('in_progress') {
                background: #d1ecf1 !important;
                color: #0c5460 !important;
                border-color: #bee5eb !important;
            }
            
            .badge.bg-danger,
            .badge + span:contains('cancelled') {
                background: #f8d7da !important;
                color: #721c24 !important;
                border-color: #f5c6cb !important;
            }
            
            /* Improve text readability - Enhanced */
            .fw-semibold {
                font-weight: 700 !important;
                color: #2c3e50 !important;
                font-size: 12px !important;
                letter-spacing: 0.2px !important;
            }
            
            .text-muted {
                color: #6c757d !important;
                font-size: 10px !important;
                line-height: 1.2 !important;
            }
            
            /* Better spacing for customer info - Enhanced */
            .table td div {
                margin-bottom: 3px !important;
            }
            
            .table td small {
                display: block !important;
                margin-top: 2px !important;
                line-height: 1.2 !important;
            }
            
            /* Enhanced customer information layout */
            .table td div.fw-medium {
                font-weight: 600 !important;
                color: #2c3e50 !important;
                font-size: 11px !important;
                margin-bottom: 3px !important;
            }
            
            /* Price formatting for better visibility */
            .table td:contains('₱') {
                font-weight: 700 !important;
                color: #27ae60 !important;
                font-size: 11px !important;
            }
            
            /* Date formatting */
            .table td:contains('2025') {
                font-size: 9px !important;
                color: #495057 !important;
                font-weight: 500 !important;
            }
            
            /* Optimize column widths for print - Enhanced layout */
            .table th:nth-child(1),
            .table td:nth-child(1) {
                width: 11% !important;
                min-width: 80px !important;
            }
            
            .table th:nth-child(2),
            .table td:nth-child(2) {
                width: 20% !important;
                min-width: 150px !important;
            }
            
            .table th:nth-child(3),
            .table td:nth-child(3) {
                width: 9% !important;
                min-width: 70px !important;
            }
            
            .table th:nth-child(4),
            .table td:nth-child(4) {
                width: 14% !important;
                min-width: 100px !important;
            }
            
            .table th:nth-child(5),
            .table td:nth-child(5) {
                width: 12% !important;
                min-width: 90px !important;
            }
            
            .table th:nth-child(6),
            .table td:nth-child(6) {
                width: 10% !important;
                min-width: 80px !important;
            }
            
            .table th:nth-child(7),
            .table td:nth-child(7) {
                width: 11% !important;
                min-width: 85px !important;
            }
            
            .table th:nth-child(8),
            .table td:nth-child(8) {
                width: 9% !important;
                min-width: 70px !important;
            }
            
            .table th:nth-child(9),
            .table td:nth-child(9) {
                width: 11% !important;
                min-width: 85px !important;
            }
            
            /* Report information styling - Enhanced */
            .text-muted {
                color: #7f8c8d !important;
            }
            
            .fw-semibold {
                font-weight: 700 !important;
                color: #2c3e50 !important;
            }
            
            /* Enhanced table row styling */
            .table tbody tr {
                transition: all 0.2s ease !important;
                border-bottom: none !important;
            }
            
            .table tbody tr:last-child {
                border-bottom: none !important;
            }
            
            /* Alternating row colors for better readability */
            .table tbody tr:nth-child(even) {
                background: #f8f9fa !important;
            }
            
            .table tbody tr:nth-child(odd) {
                background: #ffffff !important;
            }
            
            /* Hover effect for better interaction */
            .table tbody tr:hover {
                background: #e8f4fd !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            }
            
            /* Service type styling */
            .table td:contains('Installation'),
            .table td:contains('Repair') {
                font-weight: 600 !important;
                text-transform: capitalize !important;
                font-size: 8px !important;
            }
            
            /* Order number styling */
            .table td:contains('2025') {
                font-family: 'Courier New', monospace !important;
                font-weight: 700 !important;
                color: #2c3e50 !important;
                font-size: 8px !important;
            }
            
            /* Page breaks */
            .table-responsive {
                page-break-inside: auto !important;
            }
            
            .table tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            
            /* Show print-only elements - Enhanced */
            .print-only {
                display: block !important;
                margin-bottom: 25px !important;
                font-size: 18px !important;
                font-weight: 700 !important;
                color: #2c3e50 !important;
                border-bottom: none !important;
                padding-bottom: 15px !important;
                text-align: center !important;
                letter-spacing: 0.5px !important;
            }
            
            /* Enhanced print header */
            .print-header {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
                padding: 20px !important;
                border-radius: 0 !important;
                margin-bottom: 30px !important;
                border: none !important;
            }
            
            /* Summary cards styling for print */
            .card {
                border: none !important;
                border-radius: 0 !important;
                margin-bottom: 20px !important;
                box-shadow: none !important;
            }
            
            .card-body {
                padding: 15px !important;
            }
            
            .card-title {
                font-size: 14px !important;
                font-weight: 700 !important;
                color: #2c3e50 !important;
                margin-bottom: 10px !important;
                border-bottom: none !important;
                padding-bottom: 8px !important;
            }
        }
    </style>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <!-- <script src="../js/dashboard.js"></script> -->
</body>
</html>