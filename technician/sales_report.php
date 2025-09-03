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

// Filter logic
$filter = $_GET['filter'] ?? 'day';
$custom_from = $_GET['from'] ?? '';
$custom_to = $_GET['to'] ?? '';
$where = '';
$params = [$_SESSION['user_id']];
switch ($filter) {
    case 'day': $where = "DATE(completed_at) = CURDATE()"; break;
    case 'week': $where = "YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'month': $where = "YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE())"; break;
    case 'year': $where = "YEAR(completed_at) = YEAR(CURDATE())"; break;
    case 'custom':
        if ($custom_from && $custom_to) {
            $where = "DATE(completed_at) BETWEEN ? AND ?";
            $params[] = $custom_from;
            $params[] = $custom_to;
        }
        break;
}
$sql = "SELECT * FROM job_orders WHERE assigned_technician_id = ? AND status = 'completed'";
if ($where) $sql .= " AND $where";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_sales = 0;
foreach ($sales as $sale) {
    $total_sales += $sale['price'];
}

// Fetch technician info for header
$technician = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM technicians WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Now include the header
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
                    <div><strong>Technician:</strong> <?= htmlspecialchars($technician['name'] ?? 'Technician') ?></div>
                    <div><strong>Date:</strong> <?= date('F j, Y \a\t g:i A') ?></div>
                </div>
            </div>
            
            <!-- Report Title for Print -->
            <div class="print-report-title" style="display: none;">Sales Report</div>
                <h3>Sales Report</h3>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <p class="text-muted mb-0">View and print all completed sales with filters</p>
                    </div>
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            <form method="get" class="mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="filter" class="form-label">Filter By</label>
                        <select name="filter" id="filter" onchange="this.form.submit()" class="form-select">
                            <option value="day" <?= $filter=='day'?'selected':'' ?>>Today</option>
                            <option value="week" <?= $filter=='week'?'selected':'' ?>>This Week</option>
                            <option value="month" <?= $filter=='month'?'selected':'' ?>>This Month</option>
                            <option value="year" <?= $filter=='year'?'selected':'' ?>>This Year</option>
                            <option value="custom" <?= $filter=='custom'?'selected':'' ?>>Custom</option>
                        </select>
                    </div>
                    <?php if ($filter == 'custom'): ?>
                    <div class="col-md-3">
                        <label for="from" class="form-label">From</label>
                        <input type="date" name="from" id="from" value="<?= htmlspecialchars($custom_from) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="to" class="form-label">To</label>
                        <input type="date" name="to" id="to" value="<?= htmlspecialchars($custom_to) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Sales</h6>
                                    <h4>₱<?= number_format($total_sales, 2) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-peso-sign fa-2x"></i>
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
                                    <h6 class="card-title">Number of Transactions</h6>
                                    <h4><?= count($sales) ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (count($sales) > 0): ?>
                    <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0" id="salesTable">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($sale['job_order_number']) ?></td>
                                    <td><?= htmlspecialchars($sale['customer_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($sale['completed_at'])) ?></td>
                                    <td>₱<?= number_format($sale['price'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No sales data found for the selected period.</p>
                    </div>
                    <?php endif; ?>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../js/dashboard.js"></script>

    <script>
        function printSalesReport() {
            window.print();
        }
    </script>

    <style>
        /* Table font size for screen */
        .table {
            font-size: 14px !important;
        }

        @media print {
            /* Hide screen elements */
            .navbar, .sidebar, #sidebar, .wrapper > #sidebar, .btn, .card-header, .modal, .d-flex.gap-2, .pagination, form, .dropdown, #sidebarCollapse, #content nav,
            .row.mb-4, /* This hides the summary cards row */
            .card.text-bg-primary, .card.text-bg-info, .card.text-bg-success, .card.bg-primary, .card.bg-success, .card.bg-info /* Extra safety for summary cards */
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
                color: #000 !important;
                margin-bottom: 15px !important;
                text-align: left !important;
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

            /* Optimized column widths for sales report */
            .table th:nth-child(1), .table td:nth-child(1) { width: 25% !important; }  /* Order # */
            .table th:nth-child(2), .table td:nth-child(2) { width: 35% !important; } /* Customer */
            .table th:nth-child(3), .table td:nth-child(3) { width: 20% !important; }  /* Price */
            .table th:nth-child(4), .table td:nth-child(4) { width: 20% !important; }  /* Completed At */

            .table tbody tr:nth-child(even) {
                 background: transparent !important;
             }
             
             .table tbody tr {
                 background: transparent !important;
             }

            /* Print summary styling - positioned on left */
            .print-summary {
                display: block !important;
                margin-top: 20px !important;
                padding-top: 15px !important;
                page-break-inside: avoid !important;
                width: 50% !important;
                float: left !important;
            }

            .print-summary h5 {
                color: #000 !important;
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