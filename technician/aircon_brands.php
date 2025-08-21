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

    // Handle search functionality
    $search_brand = isset($_GET['search_brand']) ? trim($_GET['search_brand']) : '';
    $search_model = isset($_GET['search_model']) ? trim($_GET['search_model']) : '';
    
    // Build the query with search filters
    $sql = "SELECT id, brand, model_name, price FROM aircon_models WHERE 1=1";
    $params = [];
    
    if (!empty($search_brand)) {
        $sql .= " AND brand LIKE ?";
        $params[] = '%' . $search_brand . '%';
    }
    
    if (!empty($search_model)) {
        $sql .= " AND model_name LIKE ?";
        $params[] = '%' . $search_model . '%';
    }
    
    $sql .= " ORDER BY brand ASC, model_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get technician details for sidebar/header
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM technicians WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include header
require_once 'includes/header.php';
?>
<body>
    <div class="wrapper">
        <?php require_once 'includes/sidebar.php'; ?>
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
                <h3 class="mb-3">Aircon Models</h3>
                <p class="text-muted mb-4">Browse and search through all available aircon models in the system.</p>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search_brand" class="form-label">Search by Brand</label>
                                <input type="text" class="form-control" id="search_brand" name="search_brand" 
                                       value="<?= htmlspecialchars($_GET['search_brand'] ?? '') ?>" placeholder="Enter brand name...">
                            </div>
                            <div class="col-md-4">
                                <label for="search_model" class="form-label">Search by Model</label>
                                <input type="text" class="form-control" id="search_model" name="search_model" 
                                       value="<?= htmlspecialchars($_GET['search_model'] ?? '') ?>" placeholder="Enter model name...">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                                <?php if (!empty($_GET['search_brand']) || !empty($_GET['search_model'])): ?>
                                    <a href="aircon_brands.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear Filter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (empty($models)): ?>
                            <p class="text-muted">No aircon models found.</p>
                        <?php else: ?>
                            <div class="table-wrapper" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Brand</th>
                                            <th>Model Name</th>
                                            <th>Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($models as $model): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($model['id']) ?></td>
                                                <td><?= htmlspecialchars($model['brand']) ?></td>
                                                <td><?= htmlspecialchars($model['model_name']) ?></td>
                                                <td>₱<?= number_format($model['price'], 2) ?></td>
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
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sidebar toggle functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarCollapse');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            
            if (sidebarToggle && sidebar && content) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('active');
                    content.classList.toggle('expanded');
                });
            }
        });
    </script>
</body>
</html>