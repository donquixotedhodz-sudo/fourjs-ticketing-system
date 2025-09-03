<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get order details with related information
    $stmt = $pdo->prepare("
        SELECT 
            jo.*,
            COALESCE(am.model_name, 'Not Specified') as model_name,
            COALESCE(am.brand, 'Not Specified') as brand,
            COALESCE(ap.part_name, NULL) as part_name,
            COALESCE(ap.part_code, NULL) as part_code,
            COALESCE(ap.part_category, NULL) as part_category,
            t.name as technician_name,
            t.phone as technician_phone,
            t.profile_picture as technician_profile
        FROM job_orders jo 
        LEFT JOIN aircon_models am ON jo.aircon_model_id = am.id AND jo.service_type = 'installation'
        LEFT JOIN ac_parts ap ON jo.part_id = ap.id AND jo.service_type = 'repair'
        LEFT JOIN technicians t ON jo.assigned_technician_id = t.id
        WHERE jo.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    // Return order details as JSON
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>