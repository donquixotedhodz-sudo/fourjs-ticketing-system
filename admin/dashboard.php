<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Handle AJAX requests for technician performance filter
if (isset($_GET['ajax']) && $_GET['ajax'] === 'technician_performance') {
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Generate periods based on filter type
        $periods = [];
        $whereClause = "";
        $params = [];
        
        if ($filter === 'weekly') {
            // Generate last 12 weeks
            for ($i = 11; $i >= 0; $i--) {
                $weekStart = date('Y-m-d', strtotime("-$i weeks"));
                $weekNumber = date('W', strtotime($weekStart));
                $year = date('Y', strtotime($weekStart));
                $periods[] = "Week $weekNumber, $year";
            }
            $whereClause = "WHERE jo.created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)";
        } elseif ($filter === 'yearly') {
            // Generate last 5 years
            for ($i = 4; $i >= 0; $i--) {
                $periods[] = (string)(date('Y') - $i);
            }
            $whereClause = "WHERE jo.created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)";
        } elseif ($filter === 'custom' && $start_date && $end_date) {
            $whereClause = "WHERE jo.created_at >= ? AND jo.created_at <= ?";
            $params = [$start_date, $end_date . ' 23:59:59'];
            
            // Generate daily periods for custom range
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = new DateInterval('P1D');
            $dateRange = new DatePeriod($start, $interval, $end->add($interval));
            
            foreach ($dateRange as $date) {
                $periods[] = $date->format('M j, Y');
            }
        } else {
            // Default monthly - last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $periods[] = date('M Y', strtotime("-$i months"));
            }
            $whereClause = "WHERE jo.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        }
        
        // Build SQL query based on filter
        if ($filter === 'weekly') {
            $sql = "
                SELECT 
                    CONCAT('Week ', WEEK(jo.created_at, 1), ', ', YEAR(jo.created_at)) as period,
                    t.name as technician_name,
                    COUNT(jo.id) as total_orders,
                    SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                FROM technicians t
                LEFT JOIN job_orders jo ON t.id = jo.assigned_technician_id
                $whereClause
                GROUP BY period, t.id, t.name
                ORDER BY t.name, period
            ";
        } elseif ($filter === 'yearly') {
            $sql = "
                SELECT 
                    CAST(YEAR(jo.created_at) AS CHAR) as period,
                    t.name as technician_name,
                    COUNT(jo.id) as total_orders,
                    SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                FROM technicians t
                LEFT JOIN job_orders jo ON t.id = jo.assigned_technician_id
                $whereClause
                GROUP BY period, t.id, t.name
                ORDER BY t.name, period
            ";
        } elseif ($filter === 'custom') {
            $sql = "
                SELECT 
                    DATE_FORMAT(jo.created_at, '%b %e, %Y') as period,
                    t.name as technician_name,
                    COUNT(jo.id) as total_orders,
                    SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                FROM technicians t
                LEFT JOIN job_orders jo ON t.id = jo.assigned_technician_id
                $whereClause
                GROUP BY period, t.id, t.name
                ORDER BY t.name, period
            ";
        } else {
            // Monthly
            $sql = "
                SELECT 
                    DATE_FORMAT(jo.created_at, '%b %Y') as period,
                    t.name as technician_name,
                    COUNT(jo.id) as total_orders,
                    SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                FROM technicians t
                LEFT JOIN job_orders jo ON t.id = jo.assigned_technician_id
                $whereClause
                GROUP BY period, t.id, t.name
                ORDER BY t.name, period
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all technicians for consistent data structure
        $techStmt = $pdo->prepare("SELECT id, name FROM technicians ORDER BY name");
        $techStmt->execute();
        $technicians = $techStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize data by technician and period
        $technicianData = [];
        foreach ($technicians as $tech) {
            $technicianData[$tech['name']] = [];
            foreach ($periods as $period) {
                $technicianData[$tech['name']][$period] = [
                    'total_orders' => 0,
                    'completed_orders' => 0
                ];
            }
        }
        
        // Fill in actual data
        foreach ($results as $row) {
            if (isset($technicianData[$row['technician_name']][$row['period']])) {
                $technicianData[$row['technician_name']][$row['period']] = [
                    'total_orders' => (int)$row['total_orders'],
                    'completed_orders' => (int)$row['completed_orders']
                ];
            }
        }
        
        // Format for chart.js
        $chartData = [
            'periods' => $periods,
            'technicians' => []
        ];
        
        foreach ($technicianData as $techName => $periodData) {
            $chartData['technicians'][] = [
                'name' => $techName,
                'total_orders' => array_values(array_column($periodData, 'total_orders')),
                'completed_orders' => array_values(array_column($periodData, 'completed_orders'))
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($chartData);
        exit();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

// Handle AJAX requests for orders overview filter
if (isset($_GET['ajax']) && $_GET['ajax'] === 'orders_overview') {
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $monthlyData = [];
        
        if ($filter === 'weekly') {
            // Generate last 12 weeks
            for ($i = 11; $i >= 0; $i--) {
                $weekStart = date('Y-m-d', strtotime("-$i weeks"));
                $weekEnd = date('Y-m-d', strtotime("-$i weeks +6 days"));
                $weekLabel = 'Week ' . date('W, Y', strtotime($weekStart));
                
                $monthlyData[] = [
                    'period' => $weekLabel,
                    'total_orders' => 0
                ];
            }
            
            $stmt = $pdo->query("
                SELECT 
                    CONCAT('Week ', WEEK(created_at, 1), ', ', YEAR(created_at)) as period,
                    COUNT(*) as total_orders
                FROM job_orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                GROUP BY WEEK(created_at, 1), YEAR(created_at)
                ORDER BY YEAR(created_at), WEEK(created_at, 1)
            ");
        } elseif ($filter === 'yearly') {
            // Generate last 5 years
            for ($i = 4; $i >= 0; $i--) {
                $year = date('Y', strtotime("-$i years"));
                $monthlyData[] = [
                    'period' => $year,
                    'total_orders' => 0
                ];
            }
            
            $stmt = $pdo->query("
                SELECT 
                    CAST(YEAR(created_at) AS CHAR) as period,
                    COUNT(*) as total_orders
                FROM job_orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
                GROUP BY YEAR(created_at)
                ORDER BY YEAR(created_at)
            ");
        } elseif ($filter === 'custom' && $startDate && $endDate) {
            // Custom date range - group by days, weeks, or months based on range
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $diff = $start->diff($end)->days;
            
            if ($diff <= 31) {
                // Daily grouping for ranges <= 31 days
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(created_at) as period,
                        COUNT(*) as total_orders
                    FROM job_orders 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY DATE(created_at)
                ");
                $stmt->execute([$startDate, $endDate]);
            } else {
                // Monthly grouping for longer ranges
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as period,
                        COUNT(*) as total_orders
                    FROM job_orders 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY DATE_FORMAT(created_at, '%Y-%m')
                ");
                $stmt->execute([$startDate, $endDate]);
            }
        } else {
            // Default monthly - Generate last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $monthDate = date('Y-m-01', strtotime("-$i months"));
                $monthName = date('M Y', strtotime($monthDate));
                
                $monthlyData[] = [
                    'period' => $monthName,
                    'total_orders' => 0
                ];
            }
            
            $stmt = $pdo->query("
                SELECT 
                    DATE_FORMAT(created_at, '%b %Y') as period,
                    COUNT(*) as total_orders
                FROM job_orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY DATE_FORMAT(created_at, '%Y-%m')
            ");
        }
        
        if ($filter !== 'custom') {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge database results with generated periods
            foreach ($results as $result) {
                foreach ($monthlyData as &$month) {
                    if ($month['period'] === $result['period']) {
                        $month['total_orders'] = (int)$result['total_orders'];
                        break;
                    }
                }
            }
        } else {
            $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        header('Content-Type: application/json');
        echo json_encode($monthlyData);
        exit();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get admin details
    $stmt = $pdo->prepare("SELECT name, profile_picture FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get counts for dashboard cards
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM job_orders
    ");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all technicians for the dropdown
    $stmt = $pdo->query("SELECT id, name FROM technicians ORDER BY name ASC");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly statistics for the chart
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            AVG(CASE 
                WHEN status = 'completed' 
                THEN TIMESTAMPDIFF(HOUR, created_at, completed_at)
                ELSE NULL 
            END) as avg_completion_time
        FROM job_orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $dbMonthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a complete 12-month array with zero values for missing months
    $monthlyStats = [];
    $currentDate = new DateTime();
    
    // Generate last 12 months
    for ($i = 11; $i >= 0; $i--) {
        $date = clone $currentDate;
        $date->sub(new DateInterval('P' . $i . 'M'));
        $monthKey = $date->format('Y-m');
        
        // Check if this month has data from database
        $found = false;
        foreach ($dbMonthlyStats as $dbStat) {
            if ($dbStat['month'] === $monthKey) {
                $monthlyStats[] = $dbStat;
                $found = true;
                break;
            }
        }
        
        // If no data found for this month, add zero values
        if (!$found) {
            $monthlyStats[] = [
                'month' => $monthKey,
                'total_orders' => 0,
                'completed_orders' => 0,
                'in_progress_orders' => 0,
                'pending_orders' => 0,
                'avg_completion_time' => null
            ];
        }
    }

    // Get technician performance statistics
    $stmt = $pdo->query("
        SELECT 
            t.name as technician_name,
            COUNT(jo.id) as total_orders,
            SUM(CASE WHEN jo.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            AVG(CASE 
                WHEN jo.status = 'completed' 
                THEN TIMESTAMPDIFF(HOUR, jo.created_at, jo.completed_at)
                ELSE NULL 
            END) as avg_completion_time
        FROM technicians t
        LEFT JOIN job_orders jo ON t.id = jo.assigned_technician_id
        WHERE jo.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY t.id, t.name
        ORDER BY completed_orders DESC
        LIMIT 5
    ");
    $technicianStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
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

            <div class="container-fluid">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-0">Dashboard</h4>
                        <p class="text-muted mb-0">Overview of job orders and system statistics</p>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); endif; ?>

                <!-- Dashboard Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card total-orders">
                            <div class="card-body text-white">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Total Orders</h5>
                                    <i class="fas fa-clipboard-list fa-2x"></i>
                                </div>
                                <h2 class="card-text mb-2"><?= $counts['total'] ?? 0 ?></h2>
                                <p class="card-text mb-0"><small>All time</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="card completed-orders">
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
                        <div class="card in-progress-orders">
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
                        <div class="card pending-orders">
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

                <!-- Charts -->
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="card">
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
                                <div class="chart-container" style="position: relative; height: 400px;">
                                    <canvas id="ordersChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="card-title mb-0">Technician Performance</h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="technicianFilterDropdown" data-bs-toggle="dropdown">
                                            Monthly
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item technician-filter" href="#" data-filter="weekly">Weekly</a></li>
                                            <li><a class="dropdown-item technician-filter" href="#" data-filter="monthly">Monthly</a></li>
                                            <li><a class="dropdown-item technician-filter" href="#" data-filter="yearly">Yearly</a></li>
                                            <li><a class="dropdown-item technician-filter" href="#" data-filter="custom">Custom Range</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="chart-container" style="position: relative; height: 400px;">
                                    <canvas id="technicianChart"></canvas>
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
    <!-- <script src="../js/dashboard.js"></script> -->

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

        // Custom date range functionality is now handled within each filter's event handler

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

        // Technician Performance Chart
        const techCtx = document.getElementById('technicianChart').getContext('2d');
        const techData = <?= json_encode($technicianStats) ?>;
        
        let technicianChart = new Chart(techCtx, {
            type: 'bar',
            data: {
                labels: techData.map(item => item.technician_name),
                datasets: [{
                    label: 'Total Orders',
                    data: techData.map(item => item.total_orders),
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

        // Technician Performance Filter Functionality
        document.querySelectorAll('.technician-filter').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const filter = this.getAttribute('data-filter');
                const dropdownButton = document.getElementById('technicianFilterDropdown');
                
                if (filter === 'custom') {
                    // Show custom date range modal
                    const modal = new bootstrap.Modal(document.getElementById('customDateModal'));
                    modal.show();
                    
                    // Update modal title and apply button for technician filter
                    document.getElementById('customDateModalLabel').textContent = 'Select Date Range for Technician Performance';
                    document.getElementById('applyCustomRange').onclick = function() {
                        const startDate = document.getElementById('startDate').value;
                        const endDate = document.getElementById('endDate').value;
                        
                        if (startDate && endDate) {
                            dropdownButton.textContent = 'Custom Range';
                            
                            // Fetch custom range data
                            fetch(`dashboard.php?ajax=technician_performance&filter=custom&start_date=${startDate}&end_date=${endDate}`)
                                .then(response => response.json())
                                .then(data => {
                                    updateTechnicianChart(data, 'custom');
                                    // Close modal
                                    bootstrap.Modal.getInstance(document.getElementById('customDateModal')).hide();
                                })
                                .catch(error => {
                                    console.error('Error fetching custom range technician data:', error);
                                });
                        } else {
                            alert('Please select both start and end dates.');
                        }
                    };
                } else {
                    // Update dropdown button text
                    dropdownButton.textContent = this.textContent;
                    
                    // Fetch new data via AJAX
                    fetch(`dashboard.php?ajax=technician_performance&filter=${filter}`)
                        .then(response => response.json())
                        .then(data => {
                            updateTechnicianChart(data, filter);
                        })
                        .catch(error => {
                            console.error('Error fetching technician data:', error);
                        });
                }
            });
        });
        
        // Function to update technician chart
        function updateTechnicianChart(data, filter) {
            // Calculate totals for each technician across all periods
            const technicianNames = [];
            const totalOrdersData = [];
            
            data.technicians.forEach(technician => {
                technicianNames.push(technician.name);
                
                // Sum up all periods for this technician
                const totalSum = technician.total_orders.reduce((sum, val) => sum + (val || 0), 0);
                
                totalOrdersData.push(totalSum);
            });
            
            // Update chart with technician names as labels
            technicianChart.data.labels = technicianNames;
            technicianChart.data.datasets[0].data = totalOrdersData;
            
            technicianChart.update();
        }
        

    </script>
</body>
</html>