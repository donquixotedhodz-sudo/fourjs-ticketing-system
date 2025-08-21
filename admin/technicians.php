<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all technicians with their order counts
    $stmt = $pdo->query("
        SELECT 
            t.*,
            COUNT(DISTINCT jo.id) as total_orders,
            SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN jo.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_orders,
            SUM(CASE WHEN jo.status = 'pending' THEN 1 ELSE 0 END) as pending_orders
        FROM technicians t
        LEFT JOIN job_orders jo ON t.id = jo.assigned_technician_id
        GROUP BY t.id
        ORDER BY t.name ASC
    ");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <!-- Dark Mode Toggle -->
                        <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                            <i class="fas fa-sun" id="themeIcon"></i>
                            <span id="themeText">Light</span>
                        </button>
                        
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3>Technicians</h3>
            <p class="text-muted mb-0">Manage technician accounts and assignments</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTechnicianModal">
            <i class="fas fa-plus me-2"></i>Add Technician
        </button>
    </div>

                <!-- Alert Messages -->
                <?php 
                if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])): 
                    $success_message = $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php 
                if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])): 
                    $error_message = $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Technicians Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Technician</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Orders</th>
                                        <th>Performance</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($technicians as $tech): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($tech['profile_picture']) ? '../' . htmlspecialchars($tech['profile_picture']) : 'https://ui-avatars.com/api/?name=' . urlencode($tech['name']) . '&background=1a237e&color=fff' ?>" 
                                                     alt="<?= htmlspecialchars($tech['name']) ?>" 
                                                     class="rounded-circle me-3"
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($tech['name']) ?></h6>
                                                    <small class="text-muted">@<?= htmlspecialchars($tech['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-muted">
                                                    <i class="fas fa-phone me-2"></i><?= htmlspecialchars($tech['phone']) ?>
                                                </span>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-2"></i>Joined <?= date('M d, Y', strtotime($tech['created_at'])) ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge text-success"></span>
                                            <span class="text-success">Active</span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-3">
                                                <div class="text-center">
                                                    <h6 class="mb-0"><?= $tech['total_orders'] ?></h6>
                                                    <small class="text-muted">Total</small>
                                                </div>
                                                <div class="text-center">
                                                    <h6 class="mb-0 text-success"><?= $tech['completed_orders'] ?></h6>
                                                    <small class="text-muted">Completed</small>
                                                </div>
                                                <div class="text-center">
                                                    <h6 class="mb-0 text-primary"><?= $tech['in_progress_orders'] ?></h6>
                                                    <small class="text-muted">In Progress</small>
                                                </div>
                                                <div class="text-center">
                                                    <h6 class="mb-0 text-warning"><?= $tech['pending_orders'] ?></h6>
                                                    <small class="text-muted">Pending</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $completion_rate = $tech['total_orders'] > 0 
                                                ? round(($tech['completed_orders'] / $tech['total_orders']) * 100) 
                                                : 0;
                                            $progress_color = $completion_rate >= 70 ? 'bg-success' : ($completion_rate >= 40 ? 'bg-warning' : 'bg-danger');
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar <?= $progress_color ?>" role="progressbar" 
                                                         style="width: <?= $completion_rate ?>%"></div>
                                                </div>
                                                <span class="ms-2"><?= $completion_rate ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="technician-orders.php?id=<?= $tech['id'] ?>" 
                                                   class="btn btn-sm btn-light" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Orders">
                                                    <i class="fas fa-clipboard-list text-primary"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-light edit-technician-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editTechnicianModal"
                                                        data-id="<?= $tech['id'] ?>"
                                                        data-name="<?= htmlspecialchars($tech['name']) ?>"
                                                        data-username="<?= htmlspecialchars($tech['username']) ?>"
                                                        data-phone="<?= htmlspecialchars($tech['phone']) ?>"
                                                        title="Edit Technician">
                                                    <i class="fas fa-edit text-warning"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-light delete-technician-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteTechnicianModal"
                                                        data-id="<?= $tech['id'] ?>"
                                                        data-name="<?= htmlspecialchars($tech['name']) ?>"
                                                        title="Delete Technician">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </div>
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
    </div>

    <!-- Add Technician Modal -->
    <div class="modal fade" id="addTechnicianModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Technician</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTechnicianForm" action="controller/add_technician.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" class="form-control" name="profile_picture" accept="image/*">
                        </div>
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Technician</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Technician Modal -->
    <div class="modal fade" id="editTechnicianModal" tabindex="-1" aria-labelledby="editTechnicianModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTechnicianModalLabel">Edit Technician</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editTechnicianForm" action="controller/edit_technician.php" method="POST">
                        <input type="hidden" name="technician_id" id="edit_technician_id">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" id="edit_technician_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit_technician_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" id="edit_technician_phone" required>
                        </div>
                        <!-- Password fields are typically handled separately for security -->
                        <!-- Or you can add them here if needed, but require current password -->
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Technician Modal -->
    <div class="modal fade" id="deleteTechnicianModal" tabindex="-1" aria-labelledby="deleteTechnicianModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTechnicianModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete technician <strong id="delete_technician_name_placeholder"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                    <form id="deleteTechnicianForm" action="controller/delete_technician.php" method="POST">
                        <input type="hidden" name="technician_id" id="delete_technician_id">
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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

        document.addEventListener('DOMContentLoaded', function() {
            const editModal = document.getElementById('editTechnicianModal');
            const deleteModal = document.getElementById('deleteTechnicianModal');

            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const technicianId = button.getAttribute('data-id');
                const technicianName = button.getAttribute('data-name');
                const technicianUsername = button.getAttribute('data-username');
                const technicianPhone = button.getAttribute('data-phone');

                // Populate the modal's form fields
                editModal.querySelector('#edit_technician_id').value = technicianId;
                editModal.querySelector('#edit_technician_name').value = technicianName;
                editModal.querySelector('#edit_technician_username').value = technicianUsername;
                editModal.querySelector('#edit_technician_phone').value = technicianPhone;
            });

            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const technicianId = button.getAttribute('data-id');
                const technicianName = button.getAttribute('data-name');

                // Populate the modal content
                deleteModal.querySelector('#delete_technician_id').value = technicianId;
                deleteModal.querySelector('#delete_technician_name_placeholder').textContent = technicianName;
            });
        });
    </script>

    <style>
        @media print {
            /* Hide screen elements */
            .navbar, .sidebar, .btn, .card-header, .modal, .d-flex.gap-2 {
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
            
            /* Hide profile images in print */
            .table img {
                display: none !important;
            }
            
            /* Hide action column in print */
            .table th:last-child,
            .table td:last-child {
                display: none !important;
            }
            
            /* Hide progress bars in print */
            .progress {
                display: none !important;
            }
            
            /* Show completion rate as text */
            .progress + span {
                display: inline-block !important;
                font-weight: bold !important;
                color: #2c3e50 !important;
            }
            
            /* Technician information styling */
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
    
    <!-- Dark Mode JS -->
    <script src="../js/dark-mode.js"></script>
</body>
</html>