<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Use PDO for database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch admin info
    $admin = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Handle Cleaning Service form submission
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_cleaning_service'])) {
        $service_name = trim($_POST['service_name'] ?? '');
        $service_description = trim($_POST['service_description'] ?? '');
        $service_type = trim($_POST['service_type'] ?? '');
        $base_price = trim($_POST['base_price'] ?? '');
        $unit_type = trim($_POST['unit_type'] ?? '');
        $aircon_type = trim($_POST['aircon_type'] ?? '');

        if ($service_name && $service_description && $service_type && is_numeric($base_price)) {
            $stmt = $pdo->prepare("INSERT INTO cleaning_services (service_name, service_description, service_type, base_price, unit_type, aircon_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$service_name, $service_description, $service_type, $base_price, $unit_type, $aircon_type]);
            $_SESSION['success_message'] = "Cleaning service added successfully!";
            header("Location: cleaning_services.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Please fill in all fields correctly.";
            header("Location: cleaning_services.php");
            exit;
        }
    }

    // Handle Edit Cleaning Service
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_cleaning_service'])) {
        $service_id = intval($_POST['service_id']);
        $service_name = trim($_POST['service_name'] ?? '');
        $service_description = trim($_POST['service_description'] ?? '');
        $service_type = trim($_POST['service_type'] ?? '');
        $base_price = trim($_POST['base_price'] ?? '');
        $unit_type = trim($_POST['unit_type'] ?? '');
        $aircon_type = trim($_POST['aircon_type'] ?? '');

        if ($service_name && $service_description && $service_type && is_numeric($base_price)) {
            $stmt = $pdo->prepare("UPDATE cleaning_services SET service_name = ?, service_description = ?, service_type = ?, base_price = ?, unit_type = ?, aircon_type = ? WHERE id = ?");
            $stmt->execute([$service_name, $service_description, $service_type, $base_price, $unit_type, $aircon_type, $service_id]);
            $_SESSION['success_message'] = "Cleaning service updated successfully!";
            header("Location: cleaning_services.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Please fill in all fields correctly.";
            header("Location: cleaning_services.php");
            exit;
        }
    }

    // Handle Delete Cleaning Service
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_cleaning_service'])) {
        $service_id = intval($_POST['service_id']);
        $stmt = $pdo->prepare("DELETE FROM cleaning_services WHERE id = ?");
        $stmt->execute([$service_id]);
        $_SESSION['success_message'] = "Cleaning service deleted successfully!";
        header("Location: cleaning_services.php");
        exit;
    }

    // Fetch all cleaning services
    $stmt = $pdo->query("SELECT * FROM cleaning_services ORDER BY id DESC");
    $cleaningServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <h3>Cleaning Services Management</h3>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Add Cleaning Service</h5>
            <form method="post" class="row g-3">
                <input type="hidden" name="add_cleaning_service" value="1">
                <div class="col-md-3">
                    <input type="text" name="service_name" class="form-control" placeholder="Service Name" required>
                </div>
                <div class="col-md-3">
                    <textarea name="service_description" class="form-control" placeholder="Service Description" rows="1" required></textarea>
                </div>
                <div class="col-md-2">
                    <select name="service_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="basic_cleaning">Basic Cleaning</option>
                        <option value="deep_cleaning">Deep Cleaning</option>
                        <option value="chemical_wash">Chemical Wash</option>
                        <option value="coil_cleaning">Coil Cleaning</option>
                        <option value="filter_cleaning">Filter Cleaning</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="base_price" class="form-control" placeholder="Base Price" step="0.01" min="0" required>
                </div>
                <div class="col-md-1">
                    <select name="unit_type" class="form-control" required>
                        <option value="per_unit">Per Unit</option>
                        <option value="per_hour">Per Hour</option>
                        <option value="per_service">Per Service</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
                <div class="col-md-12">
                    <select name="aircon_type" class="form-control" required>
                        <option value="all">All Types</option>
                        <option value="window">Window</option>
                        <option value="split">Split</option>
                        <option value="cassette">Cassette</option>
                        <option value="floor_standing">Floor Standing</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Existing Cleaning Services</h6>
            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service Name</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Base Price</th>
                            <th>Unit Type</th>
                            <th>Aircon Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cleaningServices)): ?>
                            <?php foreach ($cleaningServices as $service): ?>
                                <tr>
                                    <td><?= htmlspecialchars($service['id']) ?></td>
                                    <td><?= htmlspecialchars($service['service_name']) ?></td>
                                    <td><?= htmlspecialchars($service['service_description']) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $service['service_type']))) ?></td>
                                    <td><?= htmlspecialchars(number_format($service['base_price'], 2)) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $service['unit_type']))) ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $service['aircon_type']))) ?></td>
                                    <td>
                                        <!-- Edit Button (icon only) -->
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal_<?= $service['id'] ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Delete Button (icon only) -->
                                        <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete(<?= $service['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">No cleaning services found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
            <!-- Delete form (hidden, JS will submit this) -->
            <form id="deleteForm" method="post" style="display:none;">
                <input type="hidden" name="delete_cleaning_service" value="1">
                <input type="hidden" name="service_id" id="deleteServiceId">
            </form>
        </div>
    </div>
</div>

<!-- All Edit Modals (move outside of table for Bootstrap compatibility) -->
<?php if (count($cleaningServices)): ?>
    <?php foreach ($cleaningServices as $service): ?>
        <div class="modal fade" id="editModal_<?= $service['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel_<?= $service['id'] ?>" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content">
                    <input type="hidden" name="edit_cleaning_service" value="1">
                    <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel_<?= $service['id'] ?>">Edit Cleaning Service</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Service Name</label>
                            <input type="text" name="service_name" class="form-control" value="<?= htmlspecialchars($service['service_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Description</label>
                            <textarea name="service_description" class="form-control" rows="3" required><?= htmlspecialchars($service['service_description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Type</label>
                            <select name="service_type" class="form-control" required>
                                <option value="basic_cleaning" <?= $service['service_type'] === 'basic_cleaning' ? 'selected' : '' ?>>Basic Cleaning</option>
                                <option value="deep_cleaning" <?= $service['service_type'] === 'deep_cleaning' ? 'selected' : '' ?>>Deep Cleaning</option>
                                <option value="chemical_wash" <?= $service['service_type'] === 'chemical_wash' ? 'selected' : '' ?>>Chemical Wash</option>
                                <option value="coil_cleaning" <?= $service['service_type'] === 'coil_cleaning' ? 'selected' : '' ?>>Coil Cleaning</option>
                                <option value="filter_cleaning" <?= $service['service_type'] === 'filter_cleaning' ? 'selected' : '' ?>>Filter Cleaning</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Price</label>
                            <input type="number" name="base_price" class="form-control" value="<?= htmlspecialchars($service['base_price']) ?>" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit Type</label>
                            <select name="unit_type" class="form-control" required>
                                <option value="per_unit" <?= $service['unit_type'] === 'per_unit' ? 'selected' : '' ?>>Per Unit</option>
                                <option value="per_hour" <?= $service['unit_type'] === 'per_hour' ? 'selected' : '' ?>>Per Hour</option>
                                <option value="per_service" <?= $service['unit_type'] === 'per_service' ? 'selected' : '' ?>>Per Service</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aircon Type</label>
                            <select name="aircon_type" class="form-control" required>
                                <option value="all" <?= $service['aircon_type'] === 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="window" <?= $service['aircon_type'] === 'window' ? 'selected' : '' ?>>Window</option>
                                <option value="split" <?= $service['aircon_type'] === 'split' ? 'selected' : '' ?>>Split</option>
                                <option value="cassette" <?= $service['aircon_type'] === 'cassette' ? 'selected' : '' ?>>Cassette</option>
                                <option value="floor_standing" <?= $service['aircon_type'] === 'floor_standing' ? 'selected' : '' ?>>Floor Standing</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

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

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this service?')) {
                document.getElementById('deleteServiceId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
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
</body>
</html>