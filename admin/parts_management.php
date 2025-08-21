<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$part_category = $_GET['part_category'] ?? '';

// Fetch AC parts
$parts_query = "SELECT * FROM ac_parts";
if ($part_category) {
    $parts_query .= " WHERE part_category = :part_category";
}
$parts_query .= " ORDER BY part_name";
$parts_stmt = $pdo->prepare($parts_query);
if ($part_category) {
    $parts_stmt->bindParam(':part_category', $part_category);
}
$parts_stmt->execute();
$parts = $parts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Only working with AC parts now

require_once 'includes/header.php';
?>
<style>
    .badge-category {
        font-size: 0.75em;
    }
    .price-highlight {
        font-weight: bold;
        color: #28a745;
    }
    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .filter-section {
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: all 0.3s ease;
    }
    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        background-color: #fff;
        border-bottom: 3px solid #4A90E2;
        color: #4A90E2;
    }
    .tab-content {
        background-color: #fff;
        border-radius: 0 0 0.5rem 0.5rem;
    }
</style>
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
    <h3>Parts Management</h3>
    <p class="text-muted mb-4">Manage air conditioning repair estimates, cleaning services, and parts pricing</p>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Add New AC Part</h5>
            <form id="addPartFormDirect" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="part_name" class="form-control" placeholder="Part Name" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="part_code" class="form-control" placeholder="Part Code">
                </div>
                <div class="col-md-2">
                    <select name="part_category" class="form-select" required>
                        <option value="">Category</option>
                        <option value="compressor">Compressor</option>
                        <option value="condenser">Condenser</option>
                        <option value="evaporator">Evaporator</option>
                        <option value="filter">Filter</option>
                        <option value="capacitor">Capacitor</option>
                        <option value="thermostat">Thermostat</option>
                        <option value="fan_motor">Fan Motor</option>
                        <option value="refrigerant">Refrigerant</option>
                        <option value="electrical">Electrical</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="unit_price" class="form-control" placeholder="Unit Price" step="0.01" min="0" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="labor_cost" class="form-control" placeholder="Labor Cost" step="0.01" min="0">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter Options</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <select name="part_category" class="form-select">
                        <option value="">All Parts</option>
                        <option value="compressor" <?= $part_category == 'compressor' ? 'selected' : '' ?>>Compressor</option>
                        <option value="condenser" <?= $part_category == 'condenser' ? 'selected' : '' ?>>Condenser</option>
                        <option value="evaporator" <?= $part_category == 'evaporator' ? 'selected' : '' ?>>Evaporator</option>
                        <option value="filter" <?= $part_category == 'filter' ? 'selected' : '' ?>>Filter</option>
                        <option value="capacitor" <?= $part_category == 'capacitor' ? 'selected' : '' ?>>Capacitor</option>
                        <option value="thermostat" <?= $part_category == 'thermostat' ? 'selected' : '' ?>>Thermostat</option>
                        <option value="fan_motor" <?= $part_category == 'fan_motor' ? 'selected' : '' ?>>Fan Motor</option>
                        <option value="refrigerant" <?= $part_category == 'refrigerant' ? 'selected' : '' ?>>Refrigerant</option>
                        <option value="electrical" <?= $part_category == 'electrical' ? 'selected' : '' ?>>Electrical</option>
                        <option value="other" <?= $part_category == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="parts_management.php" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">AC Parts & Prices (<?= count($parts) ?>)</h6>
            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Part Name</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Unit Price</th>
                                <th>Labor Cost</th>
                                <th>Total Cost</th>
                                <th>Warranty</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($parts)): ?>
                                <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td><?= htmlspecialchars($part['part_name']) ?></td>
                                    <td><?= htmlspecialchars($part['part_code']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= str_replace('_', ' ', ucfirst($part['part_category'])) ?>
                                        </span>
                                    </td>
                                    <td>₱<?= number_format($part['unit_price'], 2) ?></td>
                                    <td>₱<?= number_format($part['labor_cost'], 2) ?></td>
                                    <td><strong>₱<?= number_format($part['unit_price'] + $part['labor_cost'], 2) ?></strong></td>
                                    <td><?= $part['warranty_months'] ?> months</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="editPart(<?= $part['id'] ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deletePart(<?= $part['id'] ?>, '<?= htmlspecialchars($part['part_name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">No parts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>

    <!-- Add Part Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New AC Part</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addPartForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Part Name</label>
                                <input type="text" class="form-control" name="part_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Part Code</label>
                                <input type="text" class="form-control" name="part_code">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="part_category" required>
                                    <option value="">Select Category</option>
                                    <option value="compressor">Compressor</option>
                                    <option value="condenser">Condenser</option>
                                    <option value="evaporator">Evaporator</option>
                                    <option value="filter">Filter</option>
                                    <option value="capacitor">Capacitor</option>
                                    <option value="thermostat">Thermostat</option>
                                    <option value="fan_motor">Fan Motor</option>
                                    <option value="refrigerant">Refrigerant</option>
                                    <option value="electrical">Electrical</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Price (₱)</label>
                                <input type="number" class="form-control" name="unit_price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                 <label class="form-label">Labor Cost (₱)</label>
                                 <input type="number" class="form-control" name="labor_cost" step="0.01" min="0" value="0">
                             </div>
                             <div class="col-md-6">
                                 <label class="form-label">Warranty (months)</label>
                                 <input type="number" class="form-control" name="warranty_months" min="0" value="12">
                             </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Part</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit Part Modal -->
    <div class="modal fade" id="editPartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Part</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editPartForm">
                    <div class="modal-body">
                        <input type="hidden" id="partId" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Part Name</label>
                                <input type="text" class="form-control" id="partName" name="part_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Part Code</label>
                                <input type="text" class="form-control" id="partCode" name="part_code" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="partCategory" name="part_category" required>
                                    <option value="compressor">Compressor</option>
                                    <option value="condenser">Condenser</option>
                                    <option value="evaporator">Evaporator</option>
                                    <option value="filter">Filter</option>
                                    <option value="electrical">Electrical</option>
                                    <option value="refrigerant">Refrigerant</option>
                                    <option value="accessories">Accessories</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Price (₱)</label>
                                <input type="number" class="form-control" id="partPrice" name="unit_price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Labor Cost (₱)</label>
                                <input type="number" class="form-control" id="partLabor" name="labor_cost" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Warranty (months)</label>
                                <input type="number" class="form-control" id="partWarranty" name="warranty_months" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Part</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../../js/dashboard.js"></script>
    <script>
        // Store data for easy access
        const partsData = <?= json_encode($parts) ?>;

        function editPart(id) {
            const part = partsData.find(p => p.id == id);
            if (!part) return;

            // Populate modal fields
            document.getElementById('partId').value = part.id;
            document.getElementById('partName').value = part.part_name;
            document.getElementById('partCode').value = part.part_code;
            document.getElementById('partCategory').value = part.part_category;
            document.getElementById('partPrice').value = part.unit_price;
            document.getElementById('partLabor').value = part.labor_cost;
            document.getElementById('partWarranty').value = part.warranty_months;

            // Show modal
            new bootstrap.Modal(document.getElementById('editPartModal')).show();
        }

        // Handle edit form submission
        document.getElementById('editPartForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update');
            formData.append('type', 'part');
            
            fetch('controller/parts_management_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating part');
                console.error('Error:', error);
            });
        });

        // Handle direct add form submission
        document.getElementById('addPartFormDirect').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'create');
            formData.append('type', 'part');
            formData.append('warranty_months', '12'); // Default warranty
            
            fetch('controller/parts_management_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset form and reload page
                    this.reset();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error adding part');
                console.error('Error:', error);
            });
        });

        // Handle modal add form submission (keep for backward compatibility)
        document.getElementById('addPartForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'create');
            formData.append('type', 'part');
            
            fetch('controller/parts_management_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and reload page
                    bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error adding part');
                console.error('Error:', error);
            });
        });

        // Delete function
        function deletePart(id, name) {
            if (confirm(`Are you sure you want to delete the part "${name}"?`)) {
                fetch('controller/parts_management_controller.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&type=part&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting part');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>