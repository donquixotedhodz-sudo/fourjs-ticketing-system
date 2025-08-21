<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($_GET['ajax'] === 'orders_overview') {
            $filter = $_GET['filter'] ?? 'monthly';
            $technician_id = $_SESSION['user_id'];
            
            switch ($filter) {
                case 'weekly':
                    $stmt = $pdo->prepare("
                        WITH RECURSIVE weeks AS (
                            SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 WEEK), '%Y-%u') as period,
                                   DATE_SUB(NOW(), INTERVAL 11 WEEK) as week_start
                            UNION ALL
                            SELECT DATE_FORMAT(DATE_ADD(week_start, INTERVAL 1 WEEK), '%Y-%u'),
                                   DATE_ADD(week_start, INTERVAL 1 WEEK)
                            FROM weeks
                            WHERE week_start < DATE_SUB(NOW(), INTERVAL 1 WEEK)
                        )
                        SELECT 
                            CONCAT('Week ', WEEK(w.week_start)) as period,
                            COALESCE(COUNT(jo.id), 0) as total_orders
                        FROM weeks w
                        LEFT JOIN job_orders jo ON YEARWEEK(jo.created_at) = REPLACE(w.period, '-', '')
                            AND jo.assigned_technician_id = ?
                        GROUP BY w.period, w.week_start
                        ORDER BY w.week_start ASC
                    ");
                    break;
                    
                case 'yearly':
                    $stmt = $pdo->prepare("
                        WITH RECURSIVE years AS (
                            SELECT YEAR(DATE_SUB(NOW(), INTERVAL 4 YEAR)) as year_num
                            UNION ALL
                            SELECT year_num + 1
                            FROM years
                            WHERE year_num < YEAR(NOW())
                        )
                        SELECT 
                            CAST(y.year_num AS CHAR) as period,
                            COALESCE(COUNT(jo.id), 0) as total_orders
                        FROM years y
                        LEFT JOIN job_orders jo ON YEAR(jo.created_at) = y.year_num
                            AND jo.assigned_technician_id = ?
                        GROUP BY y.year_num
                        ORDER BY y.year_num ASC
                    ");
                    break;
                    
                case 'custom':
                    $start_date = $_GET['start_date'] ?? date('Y-m-01');
                    $end_date = $_GET['end_date'] ?? date('Y-m-t');
                    $stmt = $pdo->prepare("
                        SELECT 
                            DATE_FORMAT(created_at, '%Y-%m') as period,
                            COUNT(*) as total_orders
                        FROM job_orders 
                        WHERE assigned_technician_id = ?
                        AND DATE(created_at) BETWEEN ? AND ?
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY period ASC
                    ");
                    $stmt->execute([$technician_id, $start_date, $end_date]);
                    break;
                    
                default: // monthly
                    $stmt = $pdo->prepare("
                        WITH RECURSIVE months AS (
                            SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m') as month
                            UNION ALL
                            SELECT DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d'), INTERVAL 1 MONTH), '%Y-%m')
                            FROM months
                            WHERE month < DATE_FORMAT(NOW(), '%Y-%m')
                        )
                        SELECT 
                            DATE_FORMAT(STR_TO_DATE(CONCAT(m.month, '-01'), '%Y-%m-%d'), '%b %Y') as period,
                            COALESCE(COUNT(jo.id), 0) as total_orders
                        FROM months m
                        LEFT JOIN job_orders jo ON DATE_FORMAT(jo.created_at, '%Y-%m') = m.month 
                            AND jo.assigned_technician_id = ?
                        GROUP BY m.month
                        ORDER BY m.month ASC
                    ");
                    break;
            }
            
            if ($filter !== 'custom') {
                $stmt->execute([$technician_id]);
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit();
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}



try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get technician's assigned orders (non-completed)
    $stmt = $pdo->prepare("
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name 
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        WHERE jo.assigned_technician_id = ? 
        AND jo.status != 'completed'
        ORDER BY jo.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $assignedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get completed orders
    $stmt = $pdo->prepare("
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name 
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id 
        WHERE jo.assigned_technician_id = ? 
        AND jo.status = 'completed'
        ORDER BY jo.completed_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts for dashboard cards
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM job_orders 
        WHERE assigned_technician_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get monthly statistics for the chart (all 12 months)
    $stmt = $pdo->prepare("
        WITH RECURSIVE months AS (
            SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m') as month
            UNION ALL
            SELECT DATE_FORMAT(DATE_ADD(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d'), INTERVAL 1 MONTH), '%Y-%m')
            FROM months
            WHERE month < DATE_FORMAT(NOW(), '%Y-%m')
        )
        SELECT 
            m.month,
            COALESCE(COUNT(jo.id), 0) as total_orders,
            COALESCE(SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_orders,
            COALESCE(SUM(CASE WHEN jo.status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress_orders,
            COALESCE(SUM(CASE WHEN jo.status = 'pending' THEN 1 ELSE 0 END), 0) as pending_orders,
            COALESCE(SUM(CASE WHEN jo.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_orders,
            COALESCE(AVG(CASE 
                WHEN jo.status = 'completed' 
                THEN TIMESTAMPDIFF(HOUR, jo.created_at, jo.completed_at)
                ELSE NULL 
            END), 0) as avg_completion_time
        FROM months m
        LEFT JOIN job_orders jo ON DATE_FORMAT(jo.created_at, '%Y-%m') = m.month 
            AND jo.assigned_technician_id = ?
        GROUP BY m.month
        ORDER BY m.month ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);



    // Fetch technician details for header
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM technicians WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
    $technician = ['name' => $_SESSION['username'] ?? 'Technician', 'profile_picture' => ''];
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
            <!-- Top Navigation -->
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

            <div class="container-fluid py-4">
                <!-- Dashboard Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0">Dashboard</h4>
                        <p class="text-muted mb-0">Overview of your job orders and performance</p>
                    </div>
                </div>

                <!-- Dashboard Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card total-orders h-100">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Total Orders</h5>
                                    <i class="fas fa-clipboard-list fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $counts['total'] ?? 0 ?></h2>
                                <p class="card-text mb-0"><small>Assigned to you</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card completed-orders h-100">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Completed</h5>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $counts['completed'] ?? 0 ?></h2>
                                <p class="card-text mb-0"><small>Successfully done</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card in-progress-orders h-100">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">In Progress</h5>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $counts['in_progress'] ?? 0 ?></h2>
                                <p class="card-text mb-0"><small>Currently working</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card pending-orders h-100">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Pending</h5>
                                    <i class="fas fa-hourglass-half fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $counts['pending'] ?? 0 ?></h2>
                                <p class="card-text mb-0"><small>Awaiting action</small></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row g-4">
                    <div class="col-12 col-lg-12">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="card-title mb-0">Orders Overview</h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="ordersFilterDropdown" data-bs-toggle="dropdown">
                                            Monthly
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item orders-filter" href="#" data-filter="weekly">Weekly</a></li>
                                            <li><a class="dropdown-item orders-filter" href="#" data-filter="monthly">Monthly</a></li>
                                            <li><a class="dropdown-item orders-filter" href="#" data-filter="yearly">Yearly</a></li>
                                            <li><a class="dropdown-item orders-filter" href="#" data-filter="custom">Custom Range</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="ordersChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Date Range Modal -->
    <div class="modal fade" id="customDateModal" tabindex="-1" aria-labelledby="customDateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customDateModalLabel">Select Custom Date Range</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="startDate" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>
                        <div class="col-md-6">
                            <label for="endDate" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyCustomRange">Apply Range</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../js/dashboard.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Common chart options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        };

        // Orders Overview Chart
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const monthlyData = <?= json_encode($monthlyStats) ?>;
        
        let ordersChart = new Chart(ordersCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Total Orders',
                    data: monthlyData.map(item => item.total_orders),
                    backgroundColor: 'rgba(74, 144, 226, 0.7)',
                    borderColor: '#4A90E2',
                    borderWidth: 1
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.03)'
                        },
                        ticks: {
                            padding: 10,
                            stepSize: 1
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                }
            }
        });

        // Orders Overview Filter Functionality
        document.querySelectorAll('.orders-filter').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const filter = this.getAttribute('data-filter');
                const dropdownButton = document.getElementById('ordersFilterDropdown');
                
                if (filter === 'custom') {
                    // Show custom date range modal
                    const modal = new bootstrap.Modal(document.getElementById('customDateModal'));
                    document.getElementById('customDateModalLabel').textContent = 'Select Date Range for Orders Overview';
                    
                    // Set up the apply button for orders overview
                    document.getElementById('applyCustomRange').onclick = function() {
                        const startDate = document.getElementById('startDate').value;
                        const endDate = document.getElementById('endDate').value;
                        
                        if (startDate && endDate) {
                            dropdownButton.textContent = 'Custom Range';
                            
                            // Fetch custom range data
                            fetch(`dashboard.php?ajax=orders_overview&filter=custom&start_date=${startDate}&end_date=${endDate}`)
                                .then(response => response.json())
                                .then(data => {
                                    updateOrdersChart(data, 'custom');
                                    // Close modal
                                    bootstrap.Modal.getInstance(document.getElementById('customDateModal')).hide();
                                })
                                .catch(error => {
                                    console.error('Error fetching custom range orders data:', error);
                                });
                        } else {
                            alert('Please select both start and end dates.');
                        }
                    };
                    
                    modal.show();
                } else {
                    // Update dropdown button text
                    dropdownButton.textContent = this.textContent;
                    
                    // Fetch new data via AJAX
                    fetch(`dashboard.php?ajax=orders_overview&filter=${filter}`)
                        .then(response => response.json())
                        .then(data => {
                            updateOrdersChart(data, filter);
                        })
                        .catch(error => {
                            console.error('Error fetching orders data:', error);
                        });
                }
            });
        });

        // Function to update orders chart
        function updateOrdersChart(data, filter) {
            let labels = [];
            
            if (filter === 'weekly') {
                labels = data.map(item => item.period);
            } else if (filter === 'yearly') {
                labels = data.map(item => item.period);
            } else if (filter === 'custom') {
                labels = data.map(item => item.period);
            } else {
                // Monthly - format the labels
                labels = data.map(item => item.period);
            }
            
            ordersChart.data.labels = labels;
            ordersChart.data.datasets[0].data = data.map(item => item.total_orders);
            ordersChart.update();
        }

        // Sidebar toggle functionality is now handled by sidebar.js


    </script>
</body>
</html>