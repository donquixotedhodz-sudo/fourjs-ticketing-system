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
    
    // Get active orders for this customer (excluding completed and cancelled)
    $stmt = $pdo->prepare("
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            COALESCE(am.brand, 'Not Specified') as brand,
            t.name as technician_name,
            t.profile_picture as technician_profile,
            t2.name as secondary_technician_name,
            t2.profile_picture as secondary_technician_profile
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
        LEFT JOIN technicians t2 ON jo.secondary_technician_id = t2.id
        $where_clause
        AND jo.status NOT IN ('completed', 'cancelled')
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
            
            <?php if (!$orders): ?>
                <div class="alert alert-info">No orders found for this customer.</div>
            <?php else: ?>
                <!-- Search and Bulk Actions -->
                <div class="mb-3">
                    <div class="row align-items-end">
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
                        <div class="col-md-4">
                            <?php if (!empty($search_ticket)): ?>
                            <div class="text-center">
                                <span class="text-muted">Showing results for: <strong><?= htmlspecialchars($search_ticket) ?></strong></span>
                                <a href="?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-outline-secondary ms-2">Clear</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-info" id="bulkPrintBtn" onclick="printSelectedOrders()" disabled>
                                    <i class="fas fa-print me-2"></i>Print Selected Orders
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAllHeader">
                                    </th>
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
                                        <input type="checkbox" class="form-check-input order-checkbox" value="<?= $order['id'] ?>">
                                    </td>
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
                                            $techName = $order['technician_name'] ?? '';
                                            $techProfile = $order['technician_profile'] ?? '';
                                            ?>
                                            <div class="d-flex align-items-center mb-1">
                                                <img src="<?= !empty($techProfile) ? '../' . htmlspecialchars($techProfile) : 'https://ui-avatars.com/api/?name=' . urlencode($techName) . '&background=1a237e&color=fff' ?>" 
                                                     alt="<?= htmlspecialchars($techName) ?>" 
                                                     class="rounded-circle me-2" 
                                                     width="24" height="24"
                                                     style="object-fit: cover;">
                                                <div>
                                                    <div class="fw-medium"><?= htmlspecialchars($techName) ?></div>
                                                    <small class="text-muted">Primary</small>
                                                </div>
                                            </div>
                                            <?php if (!empty($order['secondary_technician_id']) && !empty($order['secondary_technician_name'])): ?>
                                                <?php
                                                $secondaryTechName = $order['secondary_technician_name'];
                                                $secondaryTechProfile = $order['secondary_technician_profile'] ?? '';
                                                ?>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($secondaryTechProfile) ? '../' . htmlspecialchars($secondaryTechProfile) : 'https://ui-avatars.com/api/?name=' . urlencode($secondaryTechName) . '&background=28a745&color=fff' ?>" 
                                                         alt="<?= htmlspecialchars($secondaryTechName) ?>" 
                                                         class="rounded-circle me-2" 
                                                         width="24" height="24"
                                                         style="object-fit: cover;">
                                                    <div>
                                                        <div class="fw-medium"><?= htmlspecialchars($secondaryTechName) ?></div>
                                                        <small class="text-muted">Secondary</small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
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
                                            <button 
                                                class="btn btn-sm btn-info print-order-btn" 
                                                onclick="printOrder(<?= $order['id'] ?>)"
                                                title="Print Receipt">
                                                <i class="fas fa-print text-white"></i>
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
                        <strong>Bulk Installation Orders:</strong> Create multiple installation orders for the same customer.
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
                            <label class="form-label">Assign Technician <span class="text-danger">*</span></label>
                            <select class="form-select" name="assigned_technician_id" required>
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secondary Technician <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="secondary_technician_id">
                                <option value="">Select Secondary Technician</option>
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
                    <button type="submit" class="btn btn-primary">Create Orders</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Repair Order Modal -->
<div class="modal fade" id="bulkRepairModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Multiple Repair Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkRepairForm" action="controller/process_bulk_repair.php" method="POST">
                <input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>">
                <div class="modal-body">
                    <div class="alert alert-warning bulk-order-alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Bulk Repair Orders:</strong> Create multiple repair orders for the same customer.
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
                            <label class="form-label">Assign Technician <span class="text-danger">*</span></label>
                            <select class="form-select" name="assigned_technician_id" required>
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secondary Technician <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="secondary_technician_id">
                                <option value="">Select Secondary Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Repair Orders Container -->
                        <div class="col-12">
                            <label class="form-label">Repair Orders</label>
                            <div id="repair-orders-container">
                                <!-- Repair Order 1 -->
                                <div class="repair-order-item border rounded p-3 mb-3" data-order="1">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">AC Part</label>
                                            <select class="form-select ac-part-select" name="part_id[]" required>
                                                <option value="">Select AC Part</option>
                                                <?php foreach ($acParts as $part): ?>
                                                <option value="<?= $part['id'] ?>" data-price="<?= $part['unit_price'] ?>"><?= htmlspecialchars($part['part_name'] . ' - ' . $part['part_code']) ?> (₱<?= number_format($part['unit_price'], 2) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Aircon Model</label>
                                            <select class="form-select aircon-model-select" name="aircon_model_id[]" required>
                                                <option value="">Select Model</option>
                                                <?php foreach ($airconModels as $model): ?>
                                                <option value="<?= $model['id'] ?>" data-price="<?= $model['price'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Base Price (₱)</label>
                                            <input type="number" class="form-control repair-base-price-input" name="base_price[]" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-warning btn-sm" id="addRepairOrderBtn">
                                <i class="fas fa-plus me-2"></i>Add Another Repair Order
                            </button>
                        </div>

                        <!-- Pricing Summary -->
                        <div class="col-md-3">
                            <label class="form-label">Total Additional Fee (₱)</label>
                            <input type="number" class="form-control" name="total_additional_fee" id="repair_bulk_additional_fee" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Total Discount (₱)</label>
                            <input type="number" class="form-control" name="discount" id="repair_bulk_discount" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Price (₱)</label>
                            <input type="number" class="form-control" name="price" id="repair_bulk_total_price" readonly>
                        </div>
                    </div>
                    
                    <!-- Price Summary -->
                    <div class="price-summary">
                        <h6><i class="fas fa-calculator me-2"></i>Price Summary</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Total Base Price:</small>
                                <div class="fw-bold" id="repair_summary_base_price">₱0.00</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total Additional Fees:</small>
                                <div class="fw-bold" id="repair_summary_additional_fee">₱0.00</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total Discount:</small>
                                <div class="fw-bold text-danger" id="repair_summary_discount">₱0.00</div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <small class="text-muted">Total Price:</small>
                                <div class="fw-bold text-success fs-5" id="repair_summary_total">₱0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Create Repair Orders</button>
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
                            <label class="form-label">Assign Technician <span class="text-danger">*</span></label>
                            <select class="form-select" name="assigned_technician_id" required>
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secondary Technician <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="secondary_technician_id">
                                <option value="">Select Secondary Technician</option>
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
                            <label class="form-label">Assign Technician <span class="text-danger">*</span></label>
                            <select class="form-select" name="assigned_technician_id" id="edit_assigned_technician_id" required>
                                <option value="">Select Technician</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secondary Technician <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" name="secondary_technician_id" id="edit_secondary_technician_id">
                                <option value="">Select Secondary Technician</option>
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
    
    #bulkRepairModal .modal-body {
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
                // For repair, show bulk repair modal
                const bulkRepairModal = document.getElementById('bulkRepairModal');
                const bulkRepairModalInstance = new bootstrap.Modal(bulkRepairModal);
                bulkRepairModalInstance.show();
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

    // Bulk repair order functionality
    let repairOrderCounter = 1;
    document.getElementById('addRepairOrderBtn').addEventListener('click', function() {
        repairOrderCounter++;
        const repairOrdersContainer = document.getElementById('repair-orders-container');
        const newRepairOrder = document.createElement('div');
        newRepairOrder.className = 'repair-order-item border rounded p-3 mb-3';
        newRepairOrder.setAttribute('data-order', repairOrderCounter);
        
        newRepairOrder.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">AC Part</label>
                    <select class="form-select ac-part-select" name="part_id[]" required>
                        <option value="">Select AC Part</option>
                        <?php foreach ($acParts as $part): ?>
                        <option value="<?= $part['id'] ?>" data-price="<?= $part['unit_price'] ?>"><?= htmlspecialchars($part['part_name'] . ' - ' . $part['part_code']) ?> (₱<?= number_format($part['unit_price'], 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Aircon Model</label>
                    <select class="form-select aircon-model-select" name="aircon_model_id[]" required>
                        <option value="">Select Model</option>
                        <?php foreach ($airconModels as $model): ?>
                        <option value="<?= $model['id'] ?>" data-price="<?= $model['price'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Base Price (₱)</label>
                    <input type="number" class="form-control repair-base-price-input" name="base_price[]" readonly>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-repair-order-btn">
                <i class="fas fa-trash me-1"></i>Remove Order
            </button>
        `;
        
        repairOrdersContainer.appendChild(newRepairOrder);
        
        // Add event listeners to new repair order
        const newAcPartSelect = newRepairOrder.querySelector('.ac-part-select');
        const newAirconModelSelect = newRepairOrder.querySelector('.aircon-model-select');
        const newRepairBasePriceInput = newRepairOrder.querySelector('.repair-base-price-input');
        const removeRepairBtn = newRepairOrder.querySelector('.remove-repair-order-btn');
        
        newAcPartSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const price = selected.getAttribute('data-price');
            newRepairBasePriceInput.value = price ? parseFloat(price).toFixed(2) : '0.00';
            calculateRepairBulkTotal();
        });
        
        newAirconModelSelect.addEventListener('change', calculateRepairBulkTotal);
        
        removeRepairBtn.addEventListener('click', function() {
            newRepairOrder.remove();
            calculateRepairBulkTotal();
        });
    });

    // Bulk repair order price calculation
    function calculateRepairBulkTotal() {
        const repairBasePriceInputs = document.querySelectorAll('.repair-base-price-input');
        const repairDiscountInput = document.getElementById('repair_bulk_discount');
        const repairTotalPriceInput = document.getElementById('repair_bulk_total_price');
        const repairBulkAdditionalFeeInput = document.getElementById('repair_bulk_additional_fee');
        
        let totalBasePrice = 0;
        
        repairBasePriceInputs.forEach(input => {
            totalBasePrice += parseFloat(input.value) || 0;
        });
        
        const discount = parseFloat(repairDiscountInput.value) || 0;
        const additionalFee = parseFloat(repairBulkAdditionalFeeInput.value) || 0;
        const total = totalBasePrice + additionalFee - discount;
        
        repairTotalPriceInput.value = total.toFixed(2);
        
        // Update summary
        document.getElementById('repair_summary_base_price').textContent = '₱' + totalBasePrice.toFixed(2);
        document.getElementById('repair_summary_additional_fee').textContent = '₱' + additionalFee.toFixed(2);
        document.getElementById('repair_summary_discount').textContent = '₱' + discount.toFixed(2);
        document.getElementById('repair_summary_total').textContent = '₱' + total.toFixed(2);
    }

    // Add event listeners for bulk repair order price calculation
    document.querySelectorAll('.ac-part-select').forEach(select => {
        select.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const price = selected.getAttribute('data-price');
            const repairBasePriceInput = this.closest('.repair-order-item').querySelector('.repair-base-price-input');
            repairBasePriceInput.value = price ? parseFloat(price).toFixed(2) : '0.00';
            calculateRepairBulkTotal();
        });
    });

    document.getElementById('repair_bulk_discount').addEventListener('input', calculateRepairBulkTotal);
    document.getElementById('repair_bulk_additional_fee').addEventListener('input', calculateRepairBulkTotal);

    // Handle bulk repair form submission with AJAX
    document.getElementById('bulkRepairForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Orders...';
        
        fetch('controller/process_bulk_repair.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showAlert('success', data.message);
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('bulkRepairModal'));
                modal.hide();
                
                // Reset form
                form.reset();
                
                // Refresh the page to update the orders table
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                // Show error message
                showAlert('danger', data.message || 'An error occurred while creating repair orders.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while creating repair orders.');
        })
        .finally(() => {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});

// Function to show alert messages
function showAlert(type, message) {
    // Create or get the notification container
    let notificationContainer = document.getElementById('notification-container');
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.id = 'notification-container';
        notificationContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            width: 100%;
        `;
        document.body.appendChild(notificationContainer);
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show mb-3`;
    alertDiv.style.cssText = `
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: none;
        border-radius: 8px;
    `;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert alert at the top of the notification container
    notificationContainer.insertBefore(alertDiv, notificationContainer.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

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

// Print Order Function
function printOrder(orderId) {
    // Open a new window for printing
    const printWindow = window.open('', '_blank', 'width=600,height=800');
    
    // Fetch order details via AJAX
    fetch(`controller/get_order_details.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load order details');
            }
            const order = data.order;
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Order Receipt - ${order.id}</title>
                    <style>
                        @page {
                            size: A5;
                            margin: 10mm;
                        }
                        body {
                            font-family: Arial, sans-serif;
                            font-size: 12px;
                            line-height: 1.4;
                            margin: 0;
                            padding: 0;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 2px solid #333;
                            padding-bottom: 10px;
                            margin-bottom: 15px;
                        }
                        .company-name {
                            font-size: 18px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        }
                        .receipt-title {
                            font-size: 14px;
                            font-weight: bold;
                            margin-top: 10px;
                        }
                        .order-info {
                            margin-bottom: 15px;
                        }
                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 5px;
                        }
                        .label {
                            font-weight: bold;
                        }
                        .customer-section {
                            border-top: 1px solid #ddd;
                            padding-top: 10px;
                            margin-bottom: 15px;
                        }
                        .service-section {
                            border-top: 1px solid #ddd;
                            padding-top: 10px;
                            margin-bottom: 15px;
                        }
                        .total-section {
                            border-top: 2px solid #333;
                            padding-top: 10px;
                            font-weight: bold;
                            font-size: 14px;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 20px;
                            font-size: 10px;
                            color: #666;
                        }
                        @media print {
                            body { print-color-adjust: exact; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                         <div style="display: flex; align-items: center; margin-bottom: 10px; padding-left: 20px;">
                             <img src="images/logo.png" alt="FourJs Logo" style="height: 80px; width: auto; margin-right: 15px;">
                             <div style="flex: 1; text-align: center; margin-right: 95px;">
                                 <div class="company-name">FourJs Aircon Services</div>
                                 <div>Professional Aircon Installation & Repair</div>
                                 <div class="receipt-title" style="margin-top: 5px; font-size: 18px; font-weight: bold;">SERVICE ORDER RECEIPT</div>
                             </div>
                         </div>
                     </div>
                    
                    <div class="order-info">
                        <div class="info-row">
                             <span class="label">Ticket Number:</span>
                             <span>${order.job_order_number || '#' + order.id}</span>
                         </div>
                        <div class="info-row">
                            <span class="label">Date:</span>
                            <span>${new Date(order.created_at).toLocaleDateString()}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span>${order.status.charAt(0).toUpperCase() + order.status.slice(1).replace('_', ' ')}</span>
                        </div>
                    </div>
                    
                    <div class="customer-section">
                         <div class="label" style="margin-bottom: 8px;">Customer Information:</div>
                         <div class="info-row">
                             <span class="label">Name:</span>
                             <span>${order.customer_name || 'N/A'}</span>
                         </div>
                         <div class="info-row">
                             <span class="label">Phone:</span>
                             <span>${order.customer_phone || 'N/A'}</span>
                         </div>
                         <div class="info-row">
                             <span class="label">Address:</span>
                             <span>${order.customer_address || 'N/A'}</span>
                         </div>
                     </div>
                    
                    <div class="service-section">
                        <div class="label" style="margin-bottom: 8px;">Service Details:</div>
                        <div class="info-row">
                            <span class="label">Service Type:</span>
                            <span>${order.service_type.charAt(0).toUpperCase() + order.service_type.slice(1)}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Model/Part:</span>
                            <span>${order.model_name || 'Not Specified'}</span>
                        </div>
                        ${order.technician_name ? `
                        <div class="info-row">
                            <span class="label">Technician:</span>
                            <span>${order.technician_name}</span>
                        </div>
                        ` : ''}
                        ${order.description ? `
                        <div class="info-row">
                            <span class="label">Description:</span>
                            <span>${order.description}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div class="total-section">
                        <div class="info-row">
                            <span>Total Amount:</span>
                            <span>₱${parseFloat(order.price).toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>Thank you for choosing FourJs Aircon Services!</p>
                        <p>For inquiries, please contact us.</p>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading order details for printing. Please try again.');
            printWindow.close();
        });
}

// Bulk Print Functions
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('bulkPrintBtn').disabled = count === 0;
}

function printSelectedOrders() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const orderIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (orderIds.length === 0) {
        alert('Please select at least one order to print.');
        return;
    }
    
    // Open a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=1000');
    
    // Fetch all selected order details
    Promise.all(orderIds.map(id => 
        fetch(`controller/get_order_details.php?id=${id}`)
            .then(response => response.json())
    ))
    .then(responses => {
        const orders = responses.filter(data => data.success).map(data => data.order);
        
        if (orders.length === 0) {
            throw new Error('No valid orders found');
        }
        
        // Calculate total amount
        const totalAmount = orders.reduce((sum, order) => sum + parseFloat(order.price), 0);
        
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Bulk Order Receipt</title>
                <style>
                    @page {
                        size: A4;
                        margin: 15mm;
                    }
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 12px;
                        line-height: 1.4;
                        margin: 0;
                        padding: 0;
                    }
                    .header {
                        text-align: center;
                        border-bottom: 2px solid #333;
                        padding-bottom: 15px;
                        margin-bottom: 20px;
                    }
                    .company-name {
                        font-size: 20px;
                        font-weight: bold;
                        margin-bottom: 5px;
                    }
                    .receipt-title {
                        font-size: 16px;
                        font-weight: bold;
                        margin-top: 10px;
                    }
                    .order-item {
                        border: 1px solid #ddd;
                        margin-bottom: 15px;
                        padding: 15px;
                        page-break-inside: avoid;
                    }
                    .order-header {
                        background-color: #f8f9fa;
                        padding: 8px;
                        margin: -15px -15px 10px -15px;
                        font-weight: bold;
                        border-bottom: 1px solid #ddd;
                    }
                    .info-row {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 5px;
                    }
                    .label {
                        font-weight: bold;
                        min-width: 120px;
                    }
                    .total-section {
                        border-top: 2px solid #333;
                        padding-top: 15px;
                        margin-top: 20px;
                        font-weight: bold;
                        font-size: 16px;
                        text-align: right;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 30px;
                        font-size: 10px;
                        color: #666;
                    }
                    @media print {
                        body { print-color-adjust: exact; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                     <div style="display: flex; align-items: center; margin-bottom: 10px; padding-left: 20px;">
                          <img src="images/logo.png" alt="FourJs Logo" style="height: 80px; width: auto; margin-right: 15px;">
                         <div style="flex: 1; text-align: center; margin-right: 95px;">
                             <div class="company-name">FourJs Aircon Services</div>
                             <div>Professional Aircon Installation & Repair</div>
                             <div class="receipt-title" style="margin-top: 5px; font-size: 18px; font-weight: bold;">BULK ORDER RECEIPT</div>
                         </div>
                     </div>
                 </div>
                
                <div style="margin-bottom: 20px;">
                    <div class="info-row">
                        <span class="label">Customer:</span>
                        <span>${orders[0].customer_name}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date:</span>
                        <span>${new Date().toLocaleDateString()}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Orders:</span>
                        <span>${orders.length}</span>
                    </div>
                </div>
                
                ${orders.map((order, index) => `
                    <div class="order-item">
                        <div class="order-header">
                            Order ${index + 1} - Ticket: ${order.job_order_number || '#' + order.id}
                        </div>
                        <div class="info-row">
                            <span class="label">Service Type:</span>
                            <span>${order.service_type.charAt(0).toUpperCase() + order.service_type.slice(1)}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Model/Part:</span>
                            <span>${order.model_name || 'Not Specified'}</span>
                        </div>
                        ${order.technician_name ? `
                        <div class="info-row">
                            <span class="label">Technician:</span>
                            <span>${order.technician_name}</span>
                        </div>
                        ` : ''}
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span>${order.status.charAt(0).toUpperCase() + order.status.slice(1).replace('_', ' ')}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Amount:</span>
                            <span>₱${parseFloat(order.price).toFixed(2)}</span>
                        </div>
                    </div>
                `).join('')}
                
                <div class="total-section">
                    <div>Total Amount: ₱${totalAmount.toFixed(2)}</div>
                </div>
                
                <div class="footer">
                    <p>Thank you for choosing FourJs Aircon Services!</p>
                    <p>For inquiries, please contact us.</p>
                </div>
            </body>
            </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load then print
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading order details for printing. Please try again.');
        printWindow.close();
    });
}

// Event listeners for checkboxes
document.addEventListener('DOMContentLoaded', function() {
    // Individual checkbox change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('order-checkbox')) {
            updateSelectedCount();
        }
    });
});
</script>

</div>

</body>
</html>