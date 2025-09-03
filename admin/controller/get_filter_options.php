<?php
session_start();
require_once '../../config/database.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get filter type from request
$filter_type = $_GET['filter_type'] ?? '';

header('Content-Type: application/json');

switch ($filter_type) {
    case 'customer':
        // Check if search parameter is provided
        $search = $_GET['search'] ?? '';
        
        if (!empty($search)) {
            // Search for customers matching the query
            $stmt = $pdo->prepare("SELECT DISTINCT customer_name FROM job_orders WHERE customer_name IS NOT NULL AND customer_name != '' AND customer_name LIKE ? ORDER BY customer_name ASC LIMIT 10");
            $stmt->execute(['%' . $search . '%']);
            $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Get all unique customer names (for initial load or when no search)
            $stmt = $pdo->query("SELECT DISTINCT customer_name FROM job_orders WHERE customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name ASC");
            $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        echo json_encode(['options' => $customers]);
        break;
        
    case 'service_type':
        // Get unique service types
        $stmt = $pdo->query("SELECT DISTINCT service_type FROM job_orders WHERE service_type IS NOT NULL AND service_type != '' ORDER BY service_type ASC");
        $service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['options' => $service_types]);
        break;
        
    case 'technician':
        // Get technicians
        $stmt = $pdo->query("SELECT id, name FROM technicians ORDER BY name ASC");
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $options = [];
        foreach ($technicians as $tech) {
            $options[] = ['value' => $tech['id'], 'label' => $tech['name']];
        }
        echo json_encode(['options' => $options]);
        break;
        
    case 'date':
        // Return predefined date options
        $date_options = [
            ['value' => 'day', 'label' => 'Today'],
            ['value' => 'week', 'label' => 'This Week'],
            ['value' => 'month', 'label' => 'This Month'],
            ['value' => 'year', 'label' => 'This Year'],
            ['value' => 'custom', 'label' => 'Custom Range']
        ];
        echo json_encode(['options' => $date_options]);
        break;
        
    default:
        echo json_encode(['options' => []]);
        break;
}
?>