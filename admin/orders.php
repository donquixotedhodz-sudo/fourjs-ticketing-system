<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get filter parameters from the request
$search_customer = $_GET['search_customer'] ?? '';
// Remove other filters

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all ongoing job orders (pending and in_progress)
    $sql = "
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            COALESCE(jo.service_type, 'Not Specified') as service_type,
            t.name as technician_name,
            t.profile_picture as technician_profile
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
        WHERE jo.status IN ('pending', 'in_progress')
    ";

    $params = [];

    if (!empty($search_customer)) {
        $sql .= " AND jo.customer_name LIKE ?";
        $params[] = '%' . $search_customer . '%';
    }

    $sql .= "
        ORDER BY 
            CASE 
                WHEN jo.status = 'pending' THEN 1
                WHEN jo.status = 'in_progress' THEN 2
            END,
            jo.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get technicians for dropdown
    $stmt = $pdo->query("SELECT id, name FROM technicians");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get aircon models for dropdown
    $stmt = $pdo->query("SELECT id, model_name, brand, price FROM aircon_models");
    $airconModels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get AC parts for dropdown (for repair orders)
    $stmt = $pdo->query("SELECT id, part_name, part_code, part_category, unit_price FROM ac_parts ORDER BY part_name");
    $acParts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all customers with at least one job order (grouped)
    $sql = "
        SELECT 
            c.id as customer_id,
            c.name as customer_name,
            c.phone as customer_phone,
            c.address as customer_address,
            COUNT(jo.id) as order_count
        FROM customers c
        LEFT JOIN job_orders jo ON jo.customer_id = c.id
    ";
    $params = [];
    if (!empty($search_customer)) {
        $sql .= " WHERE c.name LIKE ? ";
        $params[] = '%' . $search_customer . '%';
    }
    $sql .= " GROUP BY c.id
        HAVING order_count > 0
        ORDER BY c.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customerOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <h3>Orders</h3>
    
    <div class="mb-4">
        <p class="text-muted mb-0">Manage and track all job orders</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Job Orders</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJobOrderModal">
                     <i class="fas fa-plus me-2"></i>Add Survey Order
                 </button>
            </div>

            <!-- Search and Filter Form -->
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="search_customer" class="form-label">Search Customer</label>
                    <input type="text" class="form-control" id="search_customer" name="search_customer" value="<?= htmlspecialchars($search_customer) ?>" placeholder="Enter customer name">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="orders.php" class="btn btn-outline-secondary w-100">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Order Count</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerOrders as $customer): ?>
                        <tr>
                            <td>
                                <span class="fw-medium">
                                    <?= htmlspecialchars($customer['customer_name']) ?>
                                </span>
                            </td>
                            <td><i class="fas fa-phone text-primary me-1"></i><?= htmlspecialchars($customer['customer_phone']) ?></td>
                            <td><i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($customer['customer_address']) ?></td>
                            <td><?= (int)$customer['order_count'] ?></td>
                            <td class="text-center">
                                <a href="customer_orders.php?customer_id=<?= (int)$customer['customer_id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-list"></i> View Orders
                                </a>
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



    <!-- Add Job Order Modal -->
    <div class="modal fade" id="addJobOrderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Survey Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="controller/process_order.php" method="POST">
                    <input type="hidden" name="service_type" id="selected_service_type" required>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Customer Information -->
                            <div class="col-md-6">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control" name="customer_name" id="customer_name_autocomplete" autocomplete="off" required>
                                <div id="customer_suggestions" class="list-group position-absolute w-100" style="z-index: 1000;"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="customer_phone" id="customer_phone" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="customer_address" id="customer_address" rows="2" required></textarea>
                            </div>

                            <!-- Service Information -->
                            <div class="col-md-6">
                                <label class="form-label">Service Type</label>
                                <input type="text" class="form-control" id="display_service_type" readonly>
                            </div>
                            <div class="col-md-6" id="aircon_model_section">
                                <label class="form-label" id="aircon_model_label">Aircon Model <small class="text-muted">(Optional for Survey)</small></label>
                                <select class="form-select" name="aircon_model_id" id="aircon_model_select">
                                    <option value="">Select Model</option>
                                    <?php foreach ($airconModels as $model): ?>
                                    <option value="<?= $model['id'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
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

                            <!-- Price Section -->
                            <div class="col-md-4">
                                <label class="form-label">Base Price (₱)</label>
                                <input type="number" class="form-control" name="base_price" id="base_price" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Additional Fee (₱)</label>
                                <input type="number" class="form-control" name="additional_fee" id="additional_fee" value="0" min="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discount (₱)</label>
                                <input type="number" class="form-control" name="discount" id="discount" value="0" min="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Price (₱)</label>
                                <input type="number" class="form-control" name="price" id="total_price" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Survey Order</button>
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
                                <label class="form-label" id="edit_aircon_model_label">Aircon Model</label>
                                <select class="form-select" name="aircon_model_id" id="edit_aircon_model_id">
                                    <option value="">Select Model</option>
                                    <?php foreach ($airconModels as $model): ?>
                                    <option value="<?= $model['id'] ?>"><?= htmlspecialchars($model['brand'] . ' - ' . $model['model_name']) ?></option>
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

    <!-- Complete Order Modal -->
    <div class="modal fade" id="completeOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Job Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="controller/complete_order.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="complete_order_id">
                        <p>Are you sure you want to mark this job order as completed?</p>
                        <div class="alert alert-info">
                            <strong>Order #:</strong> <span id="complete_order_number"></span><br>
                            <strong>Customer:</strong> <span id="complete_customer_name"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark as Completed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <!-- <script src="../js/dashboard.js"></script> -->
    <style>
        
    </style>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Handle survey order form initialization
        document.addEventListener('DOMContentLoaded', function() {
            const addJobOrderModal = document.getElementById('addJobOrderModal');

            // When the add survey order modal opens, automatically set service type to survey
            addJobOrderModal.addEventListener('show.bs.modal', function() {
                // Set the service type to survey
                document.getElementById('selected_service_type').value = 'survey';
                document.getElementById('display_service_type').value = 'Survey';
                
                // Handle form field behavior for survey
                handleServiceTypeFields('survey');
            });



            // Function to handle form fields based on service type
            function handleServiceTypeFields(serviceType) {
                const airconModelSelect = document.querySelector('select[name="aircon_model_id"]');
                const basePriceInput = document.getElementById('base_price');
                const totalPriceInput = document.getElementById('total_price');

                // For survey, aircon model is optional and price is set to default survey fee
                    airconModelSelect.required = false;
                    basePriceInput.value = '500.00'; // Default survey fee
                    totalPriceInput.value = '500.00';
                calculateSingleTotalPrice();
            }

            // Store aircon model prices
            const airconPrices = <?php 
                $prices = [];
                foreach ($airconModels as $model) {
                    $prices[$model['id']] = $model['price'];
                }
                echo json_encode($prices);
            ?>;

            // Handle price calculations for single order
            const airconModelSelect = document.querySelector('select[name="aircon_model_id"]');
            const basePriceInput = document.getElementById('base_price');
            const additionalFeeInput = document.getElementById('additional_fee');
            const discountInput = document.getElementById('discount');
            const totalPriceInput = document.getElementById('total_price');

            // Function to calculate total price for single order
            function calculateSingleTotalPrice() {
                const basePrice = parseFloat(basePriceInput.value) || 0;
                const additionalFee = parseFloat(additionalFeeInput.value) || 0;
                const discount = parseFloat(discountInput.value) || 0;
                
                const total = basePrice + additionalFee - discount;
                totalPriceInput.value = total.toFixed(2);
            }

            // Make calculateSingleTotalPrice available globally
            window.calculateSingleTotalPrice = calculateSingleTotalPrice;

            // Update base price when aircon model is selected
            airconModelSelect.addEventListener('change', function() {
                const selectedModelId = this.value;
                const serviceType = document.getElementById('selected_service_type').value;

                // For survey, keep the default survey fee regardless of aircon model selection
                if (serviceType === 'survey') {
                    basePriceInput.value = '500.00'; // Keep survey fee
                }
                calculateSingleTotalPrice();
            });

            // Update total price when additional fee or discount changes
            additionalFeeInput.addEventListener('input', calculateSingleTotalPrice);
            discountInput.addEventListener('input', calculateSingleTotalPrice);


        });

        // CUSTOMER AUTOCOMPLETE
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('customer_name_autocomplete');
            const phoneInput = document.getElementById('customer_phone');
            const addressInput = document.getElementById('customer_address');
            const suggestionsBox = document.getElementById('customer_suggestions');
            let selectedCustomerId = null;

            nameInput.addEventListener('input', function() {
                const term = this.value.trim();
                selectedCustomerId = null;
                if (term.length < 2) {
                    suggestionsBox.innerHTML = '';
                    suggestionsBox.style.display = 'none';
                    phoneInput.value = '';
                    addressInput.value = '';
                    phoneInput.readOnly = false;
                    addressInput.readOnly = false;
                    return;
                }
                fetch('controller/search_customers.php?term=' + encodeURIComponent(term))
                    .then(res => res.json())
                    .then(data => {
                        suggestionsBox.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(customer => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action';
                                item.textContent = customer.name + (customer.phone ? ' (' + customer.phone + ')' : '');
                                item.addEventListener('click', function() {
                                    nameInput.value = customer.name;
                                    phoneInput.value = customer.phone || '';
                                    addressInput.value = customer.address || '';
                                    phoneInput.readOnly = !!customer.phone;
                                    addressInput.readOnly = !!customer.address;
                                    selectedCustomerId = customer.id;
                                    suggestionsBox.innerHTML = '';
                                    suggestionsBox.style.display = 'none';
                                });
                                suggestionsBox.appendChild(item);
                            });
                            suggestionsBox.style.display = 'block';
                        } else {
                            suggestionsBox.style.display = 'none';
                        }
                    });
            });
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!suggestionsBox.contains(e.target) && e.target !== nameInput) {
                    suggestionsBox.innerHTML = '';
                    suggestionsBox.style.display = 'none';
                }
            });
            // Allow manual entry for new customers
            nameInput.addEventListener('blur', function() {
                setTimeout(() => {
                    if (!selectedCustomerId) {
                        phoneInput.value = '';
                        addressInput.value = '';
                        phoneInput.readOnly = false;
                        addressInput.readOnly = false;
                    }
                }, 200);
            });
        });

        // CREATE ANOTHER ORDER BUTTON
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.create-another-order-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('customer_name_autocomplete').value = btn.getAttribute('data-customer-name');
                    document.getElementById('customer_phone').value = btn.getAttribute('data-customer-phone');
                    document.getElementById('customer_address').value = btn.getAttribute('data-customer-address');
                    document.getElementById('customer_phone').readOnly = !!btn.getAttribute('data-customer-phone');
                    document.getElementById('customer_address').readOnly = !!btn.getAttribute('data-customer-address');
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
                if (labelId === 'aircon_model_label') {
                    label.innerHTML = 'AC Parts <small class="text-muted">(Optional for Survey)</small>';
                } else {
                    label.textContent = 'AC Parts';
                }
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
                if (labelId === 'aircon_model_label') {
                    label.innerHTML = 'Aircon Model <small class="text-muted">(Optional for Survey)</small>';
                } else {
                    label.textContent = 'Aircon Model';
                }
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

        // Listen for service type changes in add modal
        document.addEventListener('DOMContentLoaded', function() {
            // For the main add order form - listen to display_service_type changes
            const displayServiceType = document.getElementById('display_service_type');
            if (displayServiceType) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            updateAirconModelLabel(displayServiceType.value, 'aircon_model_label', 'aircon_model_select');
                        }
                    });
                });
                observer.observe(displayServiceType, { attributes: true, attributeFilter: ['value'] });
                
                // Also listen for input events
                displayServiceType.addEventListener('input', function() {
                    updateAirconModelLabel(this.value, 'aircon_model_label', 'aircon_model_select');
                });
            }

            // For the edit modal - listen to edit_service_type changes
            const editServiceType = document.getElementById('edit_service_type');
            if (editServiceType) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            updateAirconModelLabel(editServiceType.value, 'edit_aircon_model_label', 'edit_aircon_model_id');
                        }
                    });
                });
                observer.observe(editServiceType, { attributes: true, attributeFilter: ['value'] });
                
                // Also listen for input events
                editServiceType.addEventListener('input', function() {
                    updateAirconModelLabel(this.value, 'edit_aircon_model_label', 'edit_aircon_model_id');
                });
            }
        });
    </script>
</body>
</html>