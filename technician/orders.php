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

    // Handle search functionality
    $search_ticket = isset($_GET['search_ticket']) ? trim($_GET['search_ticket']) : '';
    
    // Get only ongoing orders (pending and in_progress) with optional search
    $sql = "
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name 
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        WHERE jo.assigned_technician_id = ? 
        AND jo.status IN ('pending', 'in_progress')
        AND jo.status != 'cancelled'";
    
    $params = [$_SESSION['user_id']];
    
    if (!empty($search_ticket)) {
        $sql .= " AND jo.job_order_number LIKE ?";
        $params[] = '%' . $search_ticket . '%';
    }
    
    $sql .= " ORDER BY 
            CASE 
                WHEN jo.status = 'pending' THEN 1
                WHEN jo.status = 'in_progress' THEN 2
            END,
            jo.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}
// Include header
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
                <h3 class="mb-3">Ongoing Orders</h3>
                <p class="text-muted mb-4">Manage your pending and in-progress job orders.</p>

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

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search_ticket" class="form-label">Search by Ticket Number</label>
                                <input type="text" class="form-control" id="search_ticket" name="search_ticket" 
                                       value="<?= htmlspecialchars($search_ticket) ?>" placeholder="Enter ticket number...">
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <?php if (!empty($search_ticket)): ?>
                                    <a href="orders.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <p class="text-muted">No ongoing orders found.</p>
                        <?php else: ?>
                            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Service Type</th>
                                        <th>Model</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
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
                                                    <span class="badge bg-<?= $order['status'] === 'pending' ? 'warning' : 'info' ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
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
                                                        <?php if ($order['status'] === 'pending'): ?>
                                                            <a href="update-status.php?id=<?= $order['id'] ?>&status=in_progress" 
                                                               class="btn btn-sm btn-success" 
                                                               data-bs-toggle="tooltip" 
                                                               title="Start Work">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="update-status.php?id=<?= $order['id'] ?>&status=cancelled" 
                                                               class="btn btn-sm btn-danger" 
                                                               data-bs-toggle="tooltip" 
                                                               title="Cancel Order"
                                                               onclick="return confirm('Are you sure you want to cancel this order?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php elseif ($order['status'] === 'in_progress'): ?>
                                                            <a href="update-status.php?id=<?= $order['id'] ?>&status=completed" 
                                                               class="btn btn-sm btn-primary" 
                                                               data-bs-toggle="tooltip" 
                                                               title="Mark as Completed">
                                                                <i class="fas fa-check-double"></i>
                                                            </a>
                                                            <a href="update-status.php?id=<?= $order['id'] ?>&status=cancelled" 
                                                               class="btn btn-sm btn-danger" 
                                                               data-bs-toggle="tooltip" 
                                                               title="Cancel Order"
                                                               onclick="return confirm('Are you sure you want to cancel this order?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../js/dashboard.js"></script>
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
                                    <div class="mb-2">
                                        <label class="form-label text-muted">Aircon Model</label>
                                        <p class="mb-0" id="view_aircon_model">-</p>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        // View Order Modal Population
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.view-order-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var orderId = this.getAttribute('data-order-id');
                    if (!orderId) return;
                    
                    // Set order ID for print button
                    document.getElementById('viewOrderModal').dataset.orderId = orderId;
                    
                    // Fetch order details via AJAX
                    fetch('../admin/controller/get_order_details.php?id=' + orderId)
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
                                document.getElementById('view_aircon_model').textContent = order.model_name || 'Not Specified';
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

        // Sidebar toggle functionality is now handled by sidebar.js
    </script>
</body>
</html>