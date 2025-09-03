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

    // Handle Aircon Model form submission
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_aircon_model'])) {
        $brand = trim($_POST['brand'] ?? '');
        $model_name = trim($_POST['model_name'] ?? '');
        $price = trim($_POST['price'] ?? '');

        if ($brand && $model_name && is_numeric($price)) {
            $stmt = $pdo->prepare("INSERT INTO aircon_models (brand, model_name, price) VALUES (?, ?, ?)");
            $stmt->execute([$brand, $model_name, $price]);
            $_SESSION['success_message'] = "Aircon model added successfully!";
            header("Location: aircon_models.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Please fill in all fields correctly.";
            header("Location: aircon_models.php");
            exit;
        }
    }

    // Handle Edit Aircon Model
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_aircon_model'])) {
        $model_id = intval($_POST['model_id']);
        $brand = trim($_POST['brand'] ?? '');
        $model_name = trim($_POST['model_name'] ?? '');
        $price = trim($_POST['price'] ?? '');

        if ($brand && $model_name && is_numeric($price)) {
            $stmt = $pdo->prepare("UPDATE aircon_models SET brand = ?, model_name = ?, price = ? WHERE id = ?");
            $stmt->execute([$brand, $model_name, $price, $model_id]);
            $_SESSION['success_message'] = "Aircon model updated successfully!";
            header("Location: aircon_models.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Please fill in all fields correctly.";
            header("Location: aircon_models.php");
            exit;
        }
    }

    // Handle Delete Aircon Model
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_aircon_model'])) {
        $model_id = intval($_POST['model_id']);
        $stmt = $pdo->prepare("DELETE FROM aircon_models WHERE id = ?");
        $stmt->execute([$model_id]);
        $_SESSION['success_message'] = "Aircon model deleted successfully!";
        header("Location: aircon_models.php");
        exit;
    }

    // Fetch all aircon models
    $stmt = $pdo->query("SELECT * FROM aircon_models ORDER BY id DESC");
    $airconModels = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <h3>Aircon Models Management</h3>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Add Aircon Model</h5>
            <form method="post" class="row g-3">
                <input type="hidden" name="add_aircon_model" value="1">
                <div class="col-md-4">
                    <input type="text" name="brand" class="form-control" placeholder="Brand" required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="model_name" class="form-control" placeholder="Model Name" required>
                </div>
                <div class="col-md-3">
                    <input type="number" name="price" class="form-control" placeholder="Price" step="0.01" min="0" required>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Existing Aircon Models</h6>
            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Brand</th>
                            <th>Model Name</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($airconModels)): ?>
                            <?php foreach ($airconModels as $model): ?>
                                <tr>
                                    <td><?= htmlspecialchars($model['id']) ?></td>
                                    <td><?= htmlspecialchars($model['brand']) ?></td>
                                    <td><?= htmlspecialchars($model['model_name']) ?></td>
                                    <td><?= htmlspecialchars(number_format($model['price'], 2)) ?></td>
                                    <td>
                                        <!-- Edit Button (icon only) -->
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal_<?= $model['id'] ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Delete Button (icon only) -->
                                        <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete(<?= $model['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No aircon models found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
            <!-- Delete form (hidden, JS will submit this) -->
            <form id="deleteForm" method="post" style="display:none;">
                <input type="hidden" name="delete_aircon_model" value="1">
                <input type="hidden" name="model_id" id="deleteModelId">
            </form>
        </div>
    </div>
</div>

<!-- All Edit Modals (move outside of table for Bootstrap compatibility) -->
<?php if (count($airconModels)): ?>
    <?php foreach ($airconModels as $model): ?>
        <div class="modal fade" id="editModal_<?= $model['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel_<?= $model['id'] ?>" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content">
                    <input type="hidden" name="edit_aircon_model" value="1">
                    <input type="hidden" name="model_id" value="<?= $model['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel_<?= $model['id'] ?>">Edit Aircon Model</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($model['brand']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Model Name</label>
                            <input type="text" name="model_name" class="form-control" value="<?= htmlspecialchars($model['model_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" name="price" class="form-control" value="<?= htmlspecialchars($model['price']) ?>" step="0.01" min="0" required>
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
            if (confirm('Are you sure you want to delete this model?')) {
                document.getElementById('deleteModelId').value = id;
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