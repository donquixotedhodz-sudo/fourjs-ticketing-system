<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get technician details
    $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get search parameter
    $search_ticket = $_GET['search_ticket'] ?? '';

    // Get completed and cancelled orders
    $sql = "
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            am.brand,
            ap.part_name,
            ap.part_code,
            ap.part_category
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        LEFT JOIN ac_parts ap ON jo.part_id = ap.id
        WHERE jo.assigned_technician_id = ? 
        AND jo.status IN ('completed', 'cancelled')
    ";
    
    $params = [$_SESSION['user_id']];
    
    if (!empty($search_ticket)) {
        $sql .= " AND jo.job_order_number LIKE ?";
        $params[] = '%' . $search_ticket . '%';
    }
    
    $sql .= "
        ORDER BY 
            CASE 
                WHEN jo.status = 'completed' THEN 1
                WHEN jo.status = 'cancelled' THEN 2
            END,
            jo.completed_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Orders - Job Order System</title>
    <link rel="icon" href="../images/logo-favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <style>
        #sidebar .components li a[aria-expanded="true"] {
            background: rgba(255, 255, 255, 0.1);
        }
        #sidebar .components li .collapse {
            padding-left: 1rem;
        }
        #sidebar .components li .collapse a {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        #sidebar .components li .collapse a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
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
                <h3>Archived Orders</h3>
                
                <div class="mb-4">
                    <p class="text-muted mb-0">View completed and cancelled job orders</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Archived Orders</h5>
                        </div>

                        <!-- Search Form -->
                        <form method="GET" action="" class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="search_ticket" class="form-label">Search Ticket Number</label>
                                <input type="text" class="form-control" id="search_ticket" name="search_ticket" value="<?= htmlspecialchars($_GET['search_ticket'] ?? '') ?>" placeholder="Enter ticket number">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="archived.php" class="btn btn-outline-secondary w-100">Clear Filter</a>
                            </div>
                        </form>
                        
                        <?php if (empty($orders)): ?>
                            <p class="text-muted">No completed orders found.</p>
                        <?php else: ?>
                            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                                <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ticket Number
                                            </th>
                                            <th>Customer</th>
                                            <th>Service Type</th>
                                            <th>Model</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Date</th>
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
                                                    <div class="fw-semibold"><?= htmlspecialchars($order['customer_name']) ?></div>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($order['customer_phone']) ?><br>
                                                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($order['customer_address']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info bg-opacity-10 text-info">
                                                        <?= ucfirst(htmlspecialchars($order['service_type'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($order['model_name']) ?></td>
                                                <td>₱<?= number_format($order['price'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $order['status'] === 'completed' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($order['completed_at'])) ?></td>
                                                <td>
                                                    <div class="d-flex justify-content-center">
                                                        <button class="btn btn-sm btn-light" 
                                                                onclick="viewOrder(<?= $order['id'] ?>)" 
                                                                data-bs-toggle="tooltip" 
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div class="modal fade" id="viewOrderModal" tabindex="-1" aria-labelledby="viewOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewOrderModalLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Customer Information</h6>
                            <p><strong>Name:</strong> <span id="modal_customer_name"></span></p>
                            <p><strong>Phone:</strong> <span id="modal_customer_phone"></span></p>
                            <p><strong>Address:</strong> <span id="modal_customer_address"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Service Information</h6>
                            <p><strong>Service Type:</strong> <span id="modal_service_type"></span></p>
                            <p><strong>Description:</strong> <span id="modal_description"></span></p>
                            
                            <!-- Aircon Model Section -->
                            <div id="aircon_model_section">
                                <p><strong>Brand:</strong> <span id="modal_brand"></span></p>
                                <p><strong>Model:</strong> <span id="modal_model"></span></p>
                            </div>
                            
                            <!-- AC Parts Section -->
                            <div id="ac_parts_section" style="display: none;">
                                <p><strong>Part Name:</strong> <span id="modal_part_name"></span></p>
                                <p><strong>Part Code:</strong> <span id="modal_part_code"></span></p>
                                <p><strong>Part Category:</strong> <span id="modal_part_category"></span></p>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Status & Pricing</h6>
                            <p><strong>Status:</strong> <span id="modal_status"></span></p>
                            <p><strong>Price:</strong> ₱<span id="modal_price"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Technician Information</h6>
                            <p><strong>Assigned Technician:</strong> <span id="modal_technician"></span></p>
                            <p><strong>Created:</strong> <span id="modal_created_at"></span></p>
                            <p><strong>Completed:</strong> <span id="modal_completed_at"></span></p>
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
    function viewOrder(orderId) {
        fetch('../admin/controller/get_order_details.php?id=' + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order;
                    
                    // Populate customer information
                    document.getElementById('modal_customer_name').textContent = order.customer_name || 'N/A';
                    document.getElementById('modal_customer_phone').textContent = order.customer_phone || 'N/A';
                    document.getElementById('modal_customer_address').textContent = order.customer_address || 'N/A';
                    
                    // Populate service information
                    document.getElementById('modal_service_type').textContent = order.service_type ? order.service_type.charAt(0).toUpperCase() + order.service_type.slice(1) : 'N/A';
                    document.getElementById('modal_description').textContent = order.description || 'N/A';
                    
                    // Show/hide sections based on service type
                    const airconSection = document.getElementById('aircon_model_section');
                    const partsSection = document.getElementById('ac_parts_section');
                    
                    if (order.service_type === 'repair') {
                        // Show AC parts information for repair orders
                        airconSection.style.display = 'none';
                        partsSection.style.display = 'block';
                        
                        document.getElementById('modal_part_name').textContent = order.part_name || 'Not specified';
                        document.getElementById('modal_part_code').textContent = order.part_code || 'Not specified';
                        document.getElementById('modal_part_category').textContent = order.part_category || 'Not specified';
                    } else {
                        // Show aircon model information for other service types
                        airconSection.style.display = 'block';
                        partsSection.style.display = 'none';
                        
                        document.getElementById('modal_brand').textContent = order.brand || 'N/A';
                        document.getElementById('modal_model').textContent = order.model_name || 'N/A';
                    }
                    
                    // Populate status and pricing
                    const statusBadge = order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'N/A';
                    document.getElementById('modal_status').innerHTML = `<span class="badge bg-${order.status === 'completed' ? 'success' : 'danger'}">${statusBadge}</span>`;
                    document.getElementById('modal_price').textContent = order.price ? parseFloat(order.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00';
                    
                    // Populate technician information
                    document.getElementById('modal_technician').textContent = order.technician_name || 'N/A';
                    document.getElementById('modal_created_at').textContent = order.created_at ? new Date(order.created_at).toLocaleString() : 'N/A';
                    document.getElementById('modal_completed_at').textContent = order.completed_at ? new Date(order.completed_at).toLocaleString() : 'N/A';
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('viewOrderModal'));
                    modal.show();
                } else {
                    alert('Error loading order details: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading order details');
            });
    }
    
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>