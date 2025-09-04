<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the search query
$query = $_GET['query'] ?? '';

// Return empty array if query is too short
if (strlen(trim($query)) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Search for customers with names matching the query
    $sql = "SELECT DISTINCT customer_name FROM job_orders WHERE customer_name LIKE ? ORDER BY customer_name LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $query . '%']);
    $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($customers);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>