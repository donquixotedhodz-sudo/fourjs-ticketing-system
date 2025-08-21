<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get technician ID from URL
$technician_id = $_GET['id'] ?? null;
if (!$technician_id) {
    header('Location: technicians.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get technician details
    $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = ?");
    $stmt->execute([$technician_id]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        header('Location: technicians.php');
        exit();
    }

    // Get all job orders for this technician
    $sql = "
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            COALESCE(am.brand, 'Not Specified') as brand,
            COALESCE(jo.service_type, 'Not Specified') as service_type
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        WHERE jo.assigned_technician_id = ?
        ORDER BY 
            CASE 
                WHEN jo.status = 'pending' THEN 1
                WHEN jo.status = 'in_progress' THEN 2
                WHEN jo.status = 'completed' THEN 3
                WHEN jo.status = 'cancelled' THEN 4
            END,
            jo.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$technician_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order statistics
    $stats = [
        'total' => count($orders),
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    foreach ($orders as $order) {
        $stats[$order['status']]++;
    }

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
                <!-- Pop-up Notifications -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-4" role="alert" style="z-index: 9999; min-width: 300px;">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-4" role="alert" style="z-index: 9999; min-width: 300px;">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Header with Back Button and Technician Info -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <img src="<?= !empty($technician['profile_picture']) ? '../' . htmlspecialchars($technician['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($technician['name']) . '&background=1a237e&color=fff' ?>" 
                             alt="<?= htmlspecialchars($technician['name']) ?>" 
                             class="rounded-circle me-3" 
                             style="width: 60px; height: 60px; object-fit: cover;">
                        <div>
                            <h3 class="mb-1">Orders for <?= htmlspecialchars($technician['name']) ?></h3>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user text-primary me-1"></i>@<?= htmlspecialchars($technician['username']) ?> 
                                <span class="mx-2">•</span>
                                <i class="fas fa-phone text-success me-1"></i><?= htmlspecialchars($technician['phone']) ?>
                            </p>
                        </div>
                    </div>
                    <a href="technicians.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Technicians
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card total-orders text-center">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Total Orders</h5>
                                    <i class="fas fa-clipboard-list fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $stats['total'] ?></h2>
                                <p class="card-text mb-0"><small>All orders</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card pending-orders text-center">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Pending</h5>
                                    <i class="fas fa-hourglass-half fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $stats['pending'] ?></h2>
                                <p class="card-text mb-0"><small>Awaiting action</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card in-progress-orders text-center">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">In Progress</h5>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $stats['in_progress'] ?></h2>
                                <p class="card-text mb-0"><small>Currently working</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card completed-orders text-center">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Completed</h5>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $stats['completed'] ?></h2>
                                <p class="card-text mb-0"><small>Successfully done</small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <h5 class="card-title mb-0">Technician Orders</h5>
                        </div>
                        <?php if (!$orders): ?>
                            <div class="alert alert-info">No orders found for this technician.</div>
                        <?php else: ?>
                            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ticket Number</th>
                                                <th>Customer</th>
                                                <th>Service Type</th>
                                                <th>Model</th>
                                                <th>Status</th>
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
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td>
                                                <span class="badge bg-info bg-opacity-10 text-info">
                                                    <?= ucfirst(htmlspecialchars($order['service_type'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($order['model_name'] ?? 'Not Specified') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'in_progress' ? 'primary' : ($order['status'] === 'completed' ? 'success' : 'secondary')) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                                </span>
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
                        <?php endif; ?>
                    </div>
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
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading order details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Handle view order modal
        document.addEventListener('DOMContentLoaded', function() {
            const viewOrderButtons = document.querySelectorAll('.view-order-btn');
            const orderDetailsContent = document.getElementById('orderDetailsContent');

            viewOrderButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    
                    // Show loading state
                    orderDetailsContent.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading order details...</p>
                        </div>
                    `;

                    // Fetch order details
                    fetch(`controller/get_order_details.php?id=${orderId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const order = data.order;
                                orderDetailsContent.innerHTML = `
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6 class="text-muted mb-2">Order Information</h6>
                                            <p><strong>Ticket Number:</strong> ${order.job_order_number}</p>
                                            <p><strong>Service Type:</strong> ${order.service_type || 'Not Specified'}</p>
                                            <p><strong>Status:</strong> 
                                                <span class="badge ${
                                                    order.status === 'pending' ? 'bg-warning text-dark' :
                                                    order.status === 'in_progress' ? 'bg-info text-white' :
                                                    order.status === 'completed' ? 'bg-success text-white' :
                                                    order.status === 'cancelled' ? 'bg-danger text-white' : 'bg-secondary text-white'
                                                }">
                                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1).replace('_', ' ')}
                                                </span>
                                            </p>
                                            <p><strong>Price:</strong> ₱${parseFloat(order.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                            <p><strong>Created:</strong> ${new Date(order.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-muted mb-2">Customer Information</h6>
                                            <p><strong>Name:</strong> ${order.customer_name}</p>
                                            <p><strong>Phone:</strong> ${order.customer_phone}</p>
                                            <p><strong>Address:</strong> ${order.customer_address}</p>
                                        </div>
                                        ${order.model_name && order.model_name !== 'Not Specified' ? `
                                        <div class="col-12">
                                            <h6 class="text-muted mb-2">Equipment Information</h6>
                                            <p><strong>Brand:</strong> ${order.brand || 'Not Specified'}</p>
                                            <p><strong>Model:</strong> ${order.model_name}</p>
                                        </div>
                                        ` : ''}
                                        ${order.description ? `
                                        <div class="col-12">
                                            <h6 class="text-muted mb-2">Description</h6>
                                            <p>${order.description}</p>
                                        </div>
                                        ` : ''}
                                    </div>
                                `;
                            } else {
                                orderDetailsContent.innerHTML = `
                                    <div class="text-center py-4">
                                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                        <h5 class="text-muted">Error Loading Order</h5>
                                        <p class="text-muted">${data.message || 'Unable to load order details.'}</p>
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            orderDetailsContent.innerHTML = `
                                <div class="text-center py-4">
                                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                                    <h5 class="text-muted">Connection Error</h5>
                                    <p class="text-muted">Unable to connect to the server. Please try again.</p>
                                </div>
                            `;
                        });
                });
            });
        });
    </script>
</body>
</html>