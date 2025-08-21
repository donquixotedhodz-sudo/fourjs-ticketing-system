<?php
require_once 'includes/header.php';
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    header('Location: orders.php');
    exit();
}
$customer_id = (int)$_GET['customer_id'];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Get customer info
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        header('Location: orders.php');
        exit();
    }
    // Get search parameter
    $search_ticket = isset($_GET['search_ticket']) ? trim($_GET['search_ticket']) : '';
    
    // Build the WHERE clause
    $where_clause = "WHERE jo.customer_id = ?";
    $params = [$customer_id];
    
    if (!empty($search_ticket)) {
        $where_clause .= " AND jo.job_order_number LIKE ?";
        $params[] = '%' . $search_ticket . '%';
    }
    
    // Get all orders for this customer with aircon model information
    $stmt = $pdo->prepare("
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            COALESCE(am.brand, 'Not Specified') as brand,
            t.name as technician_name
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
        $where_clause
        ORDER BY jo.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch technicians and aircon models for dropdowns
    $stmt = $pdo->query("SELECT id, name FROM technicians");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT id, model_name, brand, price FROM aircon_models");
    $airconModels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get AC parts for dropdown (for repair orders)
    $stmt = $pdo->query("SELECT id, part_name, part_code, part_category, unit_price FROM ac_parts ORDER BY part_name");
    $acParts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>


<div class="wrapper">
    <?php require_once 'includes/sidebar.php'; ?>

    <!-- HEADER FROM orders.php -->
    <div id="content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ms-auto d-flex align-items-center">
                    <div class="dropdown">
                        <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php
                            // Try to get admin info for profile dropdown
                            $admin = null;
                            if (isset($_SESSION['user_id'])) {
                                $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                            }
                            ?>
                            <img src="<?= !empty($admin['profile_picture']) ? '../' . htmlspecialchars($admin['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['name'] ?? 'Admin') . '&background=1a237e&color=fff' ?>" 
                                 alt="Admin" 
                                 class="rounded-circle me-2" 
                                 width="32" 
                                 height="32"
                                 style="object-fit: cover; border: 2px solid #4A90E2;">
                            <span class="me-3">Welcome, <?= htmlspecialchars($admin['name'] ?? 'Admin') ?></span>
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
    <!-- Pop-up Notifications (moved above header) -->
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
    
    <h3>Orders for <?= htmlspecialchars($customer['name']) ?></h3>
    
    <div class="mb-4">
        <p class="text-muted mb-0">
            <i class="fas fa-phone text-primary me-1"></i><?= htmlspecialchars($customer['phone']) ?> <br>
            <i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($customer['address']) ?>
        </p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Customer Orders</h5>
                <div class="d-flex gap-2">
                    <a href="orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Customers</a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#orderTypeModal">
                        <i class="fas fa-plus me-2"></i>Add Job Order
                    </button>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="mb-3">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label class="form-label mb-2">Search Ticket</label>
                        <form method="GET" action="" class="d-flex">
                            <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       name="search_ticket" 
                                       placeholder="Enter ticket number" 
                                       value="<?= htmlspecialchars($search_ticket) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php if (!empty($search_ticket)): ?>
                    <div class="col-md-4">
                        <div class="mt-4">
                            <span class="text-muted">Showing results for: <strong><?= htmlspecialchars($search_ticket) ?></strong></span>
                            <a href="?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-outline-secondary ms-2">Clear</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$orders): ?>
                <div class="alert alert-info">No orders found for this customer.</div>
            <?php else: ?>
                <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Ticket Number</th>
                                    <th>Service Type</th>
                                    <th>Model</th>
                                    <th>Technician</th>
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
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info">
                                            <?= ucfirst(htmlspecialchars($order['service_type'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($order['model_name'] ?? 'Not Specified') ?></td>
                                    <td>
                                        <?php if (!empty($order['assigned_technician_id'])): ?>
                                            <?php
                                            // Fetch technician name and profile if not already available
                                            $techName = '';
                                            $techProfile = '';
                                            if (!isset($order['technician_name']) || !isset($order['technician_profile'])) {
                                                $stmt = $pdo->prepare("SELECT name, profile_picture FROM technicians WHERE id = ?");
                                                $stmt->execute([$order['assigned_technician_id']]);
                                                $tech = $stmt->fetch(PDO::FETCH_ASSOC);
                                                $techName = $tech['name'] ?? '';
                                                $techProfile = $tech['profile_picture'] ?? '';
                                            } else {
                                                $techName = $order['technician_name'];
                                                $techProfile = $order['technician_profile'];
                                            }
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($techProfile) ? '../' . htmlspecialchars($techProfile) : 'https://ui-avatars.com/api/?name=' . urlencode($techName) . '&background=1a237e&color=fff' ?>" 
                                                     alt="<?= htmlspecialchars($techName) ?>" 
                                                     class="rounded-circle me-2" 
                                                     width="24" height="24"
                                                     style="object-fit: cover;">
                                                <?= htmlspecialchars($techName) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>
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
                                            <?php if ($order['status'] === 'pending'): ?>
                                            <a href="controller/update-status.php?status=in_progress&id=<?= $order['id'] ?>&customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Accept Order">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($order['status'] === 'in_progress'): ?>
                                            <a href="controller/update-status.php?status=completed&id=<?= $order['id'] ?>&customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Mark as Completed">
                                                <i class="fas fa-check-double"></i>
                                            </a>
                                            <?php endif; ?>
                                            <button 
                                                class="btn btn-sm btn-warning edit-order-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editOrderModal"
                                                data-order='<?= json_encode($order) ?>'
                                                title="Edit Order">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="controller/update-status.php?status=cancelled&id=<?= $order['id'] ?>&customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Cancel Order" onclick="return confirm('Are you sure you want to cancel this order?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
<!-- Service Type Selection Modal -->
<div class="modal fade" id="orderTypeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-list me-2 text-primary"></i>
                    Select Service Type
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100 order-type-card border-0 shadow-sm" data-service-type="installation">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-tools fa-3x text-success"></i>
                                </div>
                                <h5 class="card-title mb-3">Installation</h5>
                                <p class="card-text text-muted">Complete aircon unit installation service with professional setup</p>
                                <div class="mt-auto">
                                    <span class="badge bg-success-subtle text-success">Setup</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 order-type-card border-0 shadow-sm" data-service-type="repair">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="fas fa-wrench fa-3x text-warning"></i>
                                </div>
                                <h5 class="card-title mb-3">Repair</h5>
                                <p class="card-text text-muted">Maintenance and repair services for existing aircon units</p>
                                <div class="mt-auto">
                                    <span class="badge bg-warning-subtle text-warning">Maintenance</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Installation Order Modal -->
<div class="modal fade" id="bulkOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Multiple Installation Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="controller/process_bulk_order.php" method="POST">
                <input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>">
                <div class="modal-body">
                    <div class="alert alert-info bulk-order-alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Bulk Installation Orders:</strong> Create multiple installation orders for the same customer. You can also create a single order using the "Create Single Order" button.
                    </div>
                    <div class="row g-3">
                        <!-- Customer Information (pre-filled and readonly) -->
                        <div class="col-md-6">
                            <label class="form-label">Customer Name</label>
                            <input type="text" class="form-control" name="customer_name" value="<?= htmlspecialchars($customer['name']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="customer_phone" value="<?= htmlspecialchars($customer['phone']) ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="customer_address" rows="2" readonly><?= htmlspecialchars($customer['address']) ?></textarea>
                        </div>

                        <!-- Order Details -->
                        <div class="col-md-6">
                            <label class="form-label">Assign Technician</label>
                            <select class="form-select" name="assigned_technician_id" required>
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Removed Due Date field here -->

                        <!-- Orders Container -->
                        <div class="col-12">
                            <label class="form-label">Orders</label>
                            <div id="orders-container">
                                <!-- Order 1 -->
                                <div class="order-item border rounded p-3 mb-3" data-order="1">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Aircon Model</label>
                                            <select class="form-select aircon-model-select" name="aircon_model_id[]" required>
                                                <option value="">Select Model</option>
                                                <?php foreach ($airconModels as $model): ?>
                                                <option value="<?= $model['id'] ?>" data-price="<?= $model['price'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Base Price (₱)</label>
                                            <input type="number" class="form-control base-price-input" name="base_price[]" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Additional Fee (₱)</label>
                                            <input type="number" class="form-control additional-fee-input" name="additional_fee[]" value="0" min="0" step="0.01">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addOrderBtn">
                                <i class="fas fa-plus me-2"></i>Add Another Order
                            </button>
                        </div>

                        <!-- Pricing Summary -->
                        <div class="col-md-6">
                            <label class="form-label">Total Discount (₱)</label>
                            <input type="number" class="form-control" name="discount" id="bulk_discount" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Price (₱)</label>
                            <input type="number" class="form-control" name="price" id="bulk_total_price" readonly>
                        </div>
                    </div>
                    
                    <!-- Price Summary -->
                    <div class="price-summary">
                        <h6><i class="fas fa-calculator me-2"></i>Price Summary</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Total Base Price:</small>
                                <div class="fw-bold" id="summary_base_price">₱0.00</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total Additional Fees:</small>
                                <div class="fw-bold" id="summary_additional_fee">₱0.00</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total Discount:</small>
                                <div class="fw-bold text-danger" id="summary_discount">₱0.00</div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <small class="text-muted">Total Price:</small>
                                <div class="fw-bold text-success fs-5" id="summary_total">₱0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" id="createSingleOrderBtn">Create Single Order</button>
                    <button type="submit" class="btn btn-primary">Create Orders</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Job Order Modal -->
<div class="modal fade" id="addJobOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Job Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="controller/process_order.php" method="POST">
                <input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Customer Information (pre-filled and readonly) -->
                        <div class="col-md-6">
                            <label class="form-label">Customer Name</label>
                            <input type="text" class="form-control" name="customer_name" value="<?= htmlspecialchars($customer['name']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="customer_phone" value="<?= htmlspecialchars($customer['phone']) ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="customer_address" rows="2" readonly><?= htmlspecialchars($customer['address']) ?></textarea>
                        </div>
                        <!-- Service Information -->
                        <div class="col-md-6">
                            <label class="form-label">Service Type</label>
                            <input type="text" class="form-control" id="display_service_type" readonly>
                            <input type="hidden" name="service_type" id="selected_service_type">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" id="modal_aircon_model_label">Aircon Model</label>
                            <select class="form-select" name="aircon_model_id" id="modal_aircon_model_id">
                                <option value="">Select Model</option>
                                <?php foreach ($airconModels as $model): ?>
                                <option value="<?= $model['id'] ?>" data-price="<?= $model['price'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Assignment Information -->
                        <div class="col-md-6">
                            <label class="form-label">Assign Technician</label>
                            <select class="form-select" name="assigned_technician_id">
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Removed Due Date field here -->
                        <!-- Price -->
                        <div class="col-md-4">
                            <label class="form-label">Base Price (₱)</label>
                            <input type="number" class="form-control" name="base_price" id="modal_base_price" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Additional Fee (₱)</label>
                            <input type="number" class="form-control" name="additional_fee" id="modal_additional_fee" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Discount (₱)</label>
                            <input type="number" class="form-control" name="discount" id="modal_discount" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Price (₱)</label>
                            <input type="number" class="form-control" name="price" id="modal_total_price" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Job Order</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Order Modal -->
<div class="modal fade" id="editOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editOrderForm" action="controller/update_order.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="edit_order_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name</label>
                            <input type="text" class="form-control" name="customer_name" id="edit_customer_name" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="customer_phone" id="edit_customer_phone" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="customer_address" id="edit_customer_address" rows="2" readonly></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Type</label>
                            <input type="text" class="form-control" name="service_type" id="edit_service_type" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" id="edit_modal_aircon_model_label">Aircon Model</label>
                            <select class="form-select" name="aircon_model_id" id="edit_aircon_model_id">
                                <option value="">Select Model</option>
                                <?php foreach ($airconModels as $model): ?>
                                <option value="<?= $model['id'] ?>" data-price="<?= $model['price'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign Technician</label>
                            <select class="form-select" name="assigned_technician_id" id="edit_assigned_technician_id">
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Base Price (₱)</label>
                            <input type="number" class="form-control" name="base_price" id="edit_base_price" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Additional Fee (₱)</label>
                            <input type="number" class="form-control" name="additional_fee" id="edit_additional_fee" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Discount (₱)</label>
                            <input type="number" class="form-control" name="discount" id="edit_discount" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Price (₱)</label>
                            <input type="number" class="form-control" name="price" id="edit_total_price" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<!-- <script src="../js/dashboard.js"></script> -->
<style>
    .order-type-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .order-type-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .bulk-order-alert {
        border-left: 4px solid #17a2b8;
        background-color: #f8f9fa;
    }
    
    .bulk-order-alert i {
        color: #17a2b8;
    }
    
    #bulkOrderModal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .price-summary {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
    }
    
    .price-summary h6 {
        color: #495057;
        margin-bottom: 10px;
    }
</style>
<script>
// Service type selection and modal handling
document.addEventListener('DOMContentLoaded', function() {
    const orderTypeCards = document.querySelectorAll('.order-type-card');
    const orderTypeModal = document.getElementById('orderTypeModal');
    const addJobOrderModal = document.getElementById('addJobOrderModal');
    const bulkOrderModal = document.getElementById('bulkOrderModal');

    orderTypeCards.forEach(card => {
        card.addEventListener('click', function() {
            const serviceType = this.getAttribute('data-service-type');
            
            // Close the order type modal
            const orderTypeModalInstance = bootstrap.Modal.getInstance(orderTypeModal);
            orderTypeModalInstance.hide();

            if (serviceType === 'installation') {
                // For installation, show bulk order modal
                const bulkOrderModalInstance = new bootstrap.Modal(bulkOrderModal);
                bulkOrderModalInstance.show();
            } else if (serviceType === 'repair') {
                // For repair, show single order form
                document.getElementById('selected_service_type').value = serviceType;
                document.getElementById('display_service_type').value = 'Repair';
                
                const addJobOrderModalInstance = new bootstrap.Modal(addJobOrderModal);
                addJobOrderModalInstance.show();
            }
        });
    });

    // Add hover effect to order type cards
    orderTypeCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.cursor = 'pointer';
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });

    // Handle "Create Single Order" button in bulk modal
    document.getElementById('createSingleOrderBtn').addEventListener('click', function() {
        // Close bulk order modal
        const bulkOrderModalInstance = bootstrap.Modal.getInstance(bulkOrderModal);
        bulkOrderModalInstance.hide();

        // Set service type to installation for single order
        document.getElementById('selected_service_type').value = 'installation';
        document.getElementById('display_service_type').value = 'Installation';

        // Show the regular job order form
        const addJobOrderModalInstance = new bootstrap.Modal(addJobOrderModal);
        addJobOrderModalInstance.show();
    });

    // Bulk order functionality
    let orderCounter = 1;
    document.getElementById('addOrderBtn').addEventListener('click', function() {
        orderCounter++;
        const ordersContainer = document.getElementById('orders-container');
        const newOrder = document.createElement('div');
        newOrder.className = 'order-item border rounded p-3 mb-3';
        newOrder.setAttribute('data-order', orderCounter);
        
        newOrder.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Aircon Model</label>
                    <select class="form-select aircon-model-select" name="aircon_model_id[]" required>
                        <option value="">Select Model</option>
                        <?php foreach ($airconModels as $model): ?>
                        <option value="<?= $model['id'] ?>" data-price="<?= $model['price'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Base Price (₱)</label>
                    <input type="number" class="form-control base-price-input" name="base_price[]" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Additional Fee (₱)</label>
                    <input type="number" class="form-control additional-fee-input" name="additional_fee[]" value="0" min="0" step="0.01">
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-order-btn">
                <i class="fas fa-trash me-1"></i>Remove Order
            </button>
        `;
        
        ordersContainer.appendChild(newOrder);
        
        // Add event listeners to new order
        const newAirconSelect = newOrder.querySelector('.aircon-model-select');
        const newBasePriceInput = newOrder.querySelector('.base-price-input');
        const newAdditionalFeeInput = newOrder.querySelector('.additional-fee-input');
        const removeBtn = newOrder.querySelector('.remove-order-btn');
        
        newAirconSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const price = selected.getAttribute('data-price');
            newBasePriceInput.value = price ? parseFloat(price).toFixed(2) : '0.00';
            calculateBulkTotal();
        });
        
        newAdditionalFeeInput.addEventListener('input', calculateBulkTotal);
        
        removeBtn.addEventListener('click', function() {
            newOrder.remove();
            calculateBulkTotal();
        });
    });

    // Bulk order price calculation
    function calculateBulkTotal() {
        const basePriceInputs = document.querySelectorAll('.base-price-input');
        const additionalFeeInputs = document.querySelectorAll('.additional-fee-input');
        const discountInput = document.getElementById('bulk_discount');
        const totalPriceInput = document.getElementById('bulk_total_price');
        
        let totalBasePrice = 0;
        let totalAdditionalFee = 0;
        
        basePriceInputs.forEach(input => {
            totalBasePrice += parseFloat(input.value) || 0;
        });
        
        additionalFeeInputs.forEach(input => {
            totalAdditionalFee += parseFloat(input.value) || 0;
        });
        
        const discount = parseFloat(discountInput.value) || 0;
        const total = totalBasePrice + totalAdditionalFee - discount;
        
        totalPriceInput.value = total.toFixed(2);
        
        // Update summary
        document.getElementById('summary_base_price').textContent = '₱' + totalBasePrice.toFixed(2);
        document.getElementById('summary_additional_fee').textContent = '₱' + totalAdditionalFee.toFixed(2);
        document.getElementById('summary_discount').textContent = '₱' + discount.toFixed(2);
        document.getElementById('summary_total').textContent = '₱' + total.toFixed(2);
    }

    // Add event listeners for bulk order price calculation
    document.querySelectorAll('.aircon-model-select').forEach(select => {
        select.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const price = selected.getAttribute('data-price');
            const basePriceInput = this.closest('.order-item').querySelector('.base-price-input');
            basePriceInput.value = price ? parseFloat(price).toFixed(2) : '0.00';
            calculateBulkTotal();
        });
    });

    document.querySelectorAll('.additional-fee-input').forEach(input => {
        input.addEventListener('input', calculateBulkTotal);
    });

    document.getElementById('bulk_discount').addEventListener('input', calculateBulkTotal);
});

// Price calculation for Add Job Order Modal
(function() {
    const airconModelSelect = document.getElementById('modal_aircon_model_id');
    const basePriceInput = document.getElementById('modal_base_price');
    const additionalFeeInput = document.getElementById('modal_additional_fee');
    const discountInput = document.getElementById('modal_discount');
    const totalPriceInput = document.getElementById('modal_total_price');
    if (airconModelSelect && basePriceInput && additionalFeeInput && discountInput && totalPriceInput) {
        function calculateTotal() {
            const basePrice = parseFloat(basePriceInput.value) || 0;
            const additionalFee = parseFloat(additionalFeeInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            const total = basePrice + additionalFee - discount;
            totalPriceInput.value = total.toFixed(2);
        }
        airconModelSelect.addEventListener('change', function() {
            const selected = airconModelSelect.options[airconModelSelect.selectedIndex];
            const price = selected.getAttribute('data-price');
            basePriceInput.value = price ? parseFloat(price).toFixed(2) : '0.00';
            calculateTotal();
        });
        additionalFeeInput.addEventListener('input', calculateTotal);
        discountInput.addEventListener('input', calculateTotal);
    }
})();
// Auto-dismiss alerts after 3 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alert = document.querySelector('.alert-dismissible');
            if (alert) {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 3000);
    });

// --- Populate Edit Order Modal with Customer Info and Service Type ---
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-order-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var order = this.getAttribute('data-order');
            if (!order) return;
            try {
                var orderData = JSON.parse(order);
                // Set order ID (CRITICAL FIX)
                document.getElementById('edit_order_id').value = orderData.id || '';
                // Set customer info fields
                document.getElementById('edit_customer_name').value = orderData.customer_name || '';
                document.getElementById('edit_customer_phone').value = orderData.customer_phone || '';
                document.getElementById('edit_customer_address').value = orderData.customer_address || '';
                // Set service type (make editable)
                document.getElementById('edit_service_type').value = orderData.service_type || '';
                // Set aircon model
                var airconModelSelect = document.getElementById('edit_aircon_model_id');
                if (orderData.aircon_model_id) {
                    airconModelSelect.value = orderData.aircon_model_id;
                    // Trigger change event to update price
                    var event = new Event('change');
                    airconModelSelect.dispatchEvent(event);
                } else {
                    airconModelSelect.value = '';
                }
                // Set base price, additional fee, discount, total price
                document.getElementById('edit_base_price').value = orderData.base_price || '';
                document.getElementById('edit_additional_fee').value = orderData.additional_fee || '';
                document.getElementById('edit_discount').value = orderData.discount || '';
                document.getElementById('edit_total_price').value = orderData.price || '';
                // Set technician
                document.getElementById('edit_assigned_technician_id').value = orderData.assigned_technician_id || '';
                // Set status
                document.getElementById('edit_status').value = orderData.status || 'pending';
            } catch (e) {
                // Fallback: clear fields
                document.getElementById('edit_order_id').value = '';
                document.getElementById('edit_customer_name').value = '';
                document.getElementById('edit_customer_phone').value = '';
                document.getElementById('edit_customer_address').value = '';
                document.getElementById('edit_service_type').value = '';
                document.getElementById('edit_aircon_model_id').value = '';
                document.getElementById('edit_base_price').value = '';
                document.getElementById('edit_additional_fee').value = '';
                document.getElementById('edit_discount').value = '';
                document.getElementById('edit_total_price').value = '';
                document.getElementById('edit_assigned_technician_id').value = '';
                document.getElementById('edit_status').value = 'pending';
            }
        });
    });
    // --- Show base price when aircon model is selected in Edit Modal ---
    var editAirconModelSelect = document.getElementById('edit_aircon_model_id');
    var editBasePriceInput = document.getElementById('edit_base_price');
    if (editAirconModelSelect && editBasePriceInput) {
        editAirconModelSelect.addEventListener('change', function() {
            var selected = this.options[this.selectedIndex];
            var price = selected.getAttribute('data-price');
            editBasePriceInput.value = price ? parseFloat(price).toFixed(2) : '0.00';
            // Optionally, recalculate total price
            var additionalFee = parseFloat(document.getElementById('edit_additional_fee').value) || 0;
            var discount = parseFloat(document.getElementById('edit_discount').value) || 0;
            var total = (parseFloat(editBasePriceInput.value) || 0) + additionalFee - discount;
            document.getElementById('edit_total_price').value = total.toFixed(2);
        });
    }
    // Also recalculate total price when additional fee or discount changes
    var editAdditionalFeeInput = document.getElementById('edit_additional_fee');
    var editDiscountInput = document.getElementById('edit_discount');
    if (editAdditionalFeeInput && editDiscountInput && editBasePriceInput) {
        function recalcEditTotal() {
            var base = parseFloat(editBasePriceInput.value) || 0;
            var add = parseFloat(editAdditionalFeeInput.value) || 0;
            var disc = parseFloat(editDiscountInput.value) || 0;
            var total = base + add - disc;
            document.getElementById('edit_total_price').value = total.toFixed(2);
        }
        editAdditionalFeeInput.addEventListener('input', recalcEditTotal);
        editDiscountInput.addEventListener('input', recalcEditTotal);
    }
    
    // --- View Order Modal Population ---
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

    // DYNAMIC LABEL CHANGE FOR AIRCON MODEL/AC PARTS
    function updateAirconModelLabel(serviceType, labelId, selectId) {
        const label = document.getElementById(labelId);
        const select = document.getElementById(selectId);
        
         // Clear existing options except the first one
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        if (serviceType === 'repair') {
            label.textContent = 'AC Parts';
            select.querySelector('option[value=""]').textContent = 'Select Parts';
            
            // Populate with AC parts
            const acParts = <?= json_encode($acParts) ?>;
            acParts.forEach(part => {
                const option = document.createElement('option');
                option.value = part.id;
                option.textContent = `${part.part_name} - ${part.part_code || 'N/A'} (₱${parseFloat(part.unit_price).toFixed(2)})`;
                option.setAttribute('data-price', part.unit_price);
                select.appendChild(option);
            });
        } else {
            label.textContent = 'Aircon Model';
            select.querySelector('option[value=""]').textContent = 'Select Model';
            
            // Populate with aircon models
            const airconModels = <?= json_encode($airconModels) ?>;
            airconModels.forEach(model => {
                const option = document.createElement('option');
                option.value = model.id;
                option.textContent = `${model.brand} - ${model.model_name}`;
                option.setAttribute('data-price', model.price);
                select.appendChild(option);
            });
        }
    }

    // Listen for service type changes in modals
    const displayServiceType = document.getElementById('display_service_type');
    if (displayServiceType) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    updateAirconModelLabel(displayServiceType.value.toLowerCase(), 'modal_aircon_model_label', 'modal_aircon_model_id');
                }
            });
        });
        observer.observe(displayServiceType, { attributes: true, attributeFilter: ['value'] });
        
        // Also listen for input events
        displayServiceType.addEventListener('input', function() {
            updateAirconModelLabel(this.value.toLowerCase(), 'modal_aircon_model_label', 'modal_aircon_model_id');
        });
    }

    // For the edit modal - listen to edit_service_type changes
    const editServiceType = document.getElementById('edit_service_type');
    if (editServiceType) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    updateAirconModelLabel(editServiceType.value.toLowerCase(), 'edit_modal_aircon_model_label', 'edit_aircon_model_id');
                }
            });
        });
        observer.observe(editServiceType, { attributes: true, attributeFilter: ['value'] });
        
        // Also listen for input events
        editServiceType.addEventListener('input', function() {
            updateAirconModelLabel(this.value.toLowerCase(), 'edit_modal_aircon_model_label', 'edit_aircon_model_id');
        });
    }

    // Initial check when modals are opened
    document.getElementById('addJobOrderModal').addEventListener('shown.bs.modal', function() {
        const serviceType = document.getElementById('display_service_type').value;
        if (serviceType) {
            updateAirconModelLabel(serviceType.toLowerCase(), 'modal_aircon_model_label', 'modal_aircon_model_id');
        }
    });

    document.getElementById('editOrderModal').addEventListener('shown.bs.modal', function() {
        const serviceType = document.getElementById('edit_service_type').value;
        if (serviceType) {
            updateAirconModelLabel(serviceType.toLowerCase(), 'edit_modal_aircon_model_label', 'edit_aircon_model_id');
        }
    });
});
</script>

</div>

</body>
</html>