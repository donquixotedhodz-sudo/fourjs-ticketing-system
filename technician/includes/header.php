<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - Job Order System</title>
    <!-- Favicon -->
    <link rel="icon" href="../images/logo-favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .technician-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }
        .technician-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .technician-item img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
        }
        .technician-item .name {
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* New responsive styles */
        .card {
            height: 100%;
            margin-bottom: 1rem;
        }
        .card-body {
            padding: 1.25rem;
        }
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        @media (max-width: 768px) {
            .chart-container {
                height: 200px;
            }
            .card-body {
                padding: 1rem;
            }
            .card-title {
                font-size: 1rem;
            }
            .card-text h2 {
                font-size: 1.5rem;
            }
        }
        @media (max-width: 576px) {
            .chart-container {
                height: 180px;
            }
        }
        .dropdown-menu {
            border-radius: 10px;
            padding: 0.5rem;
        }
        
        .dropdown-item {
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .dropdown-item.text-danger:hover {
            background-color: #dc3545;
            color: white !important;
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        .dropdown-divider {
            margin: 0.5rem 0;
            opacity: 0.1;
        }
        
        #userDropdown {
            transition: all 0.2s ease;
        }
        
        #userDropdown:hover {
            opacity: 0.8;
        }
        
        #userDropdown img {
            border: 2px solid #4A90E2;
            transition: all 0.2s ease;
        }
        
        #userDropdown:hover img {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- Rest of the file remains unchanged -->
</body>
</html>