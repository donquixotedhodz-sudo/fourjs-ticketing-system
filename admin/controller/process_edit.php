<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../orders.php');
    exit();
}

// Validate required fields
$required_fields = ['order_id', 'customer_name', 'customer_phone', 'customer_address', 'service_type', 'due_date', 'price', 'status'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $_SESSION['error'] = "All required fields must be filled out.";
        header('Location: ../edit-order.php?id=' . $_POST['order_id']);
        exit();
    }
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the order exists
    $stmt = $pdo->prepare("SELECT id FROM job_orders WHERE id = ?");
    $stmt->execute([$_POST['order_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Order not found.";
        header('Location: ../orders.php');
        exit();
    }

    // Handle aircon_model_id vs part_id based on service_type
    $service_type = $_POST['service_type'];
    $aircon_model_id = null;
    $part_id = null;
    
    if ($service_type === 'repair') {
        // For repair orders, the aircon_model_id field contains the part ID
        $part_id = !empty($_POST['aircon_model_id']) ? $_POST['aircon_model_id'] : null;
    } else {
        // For installation/survey orders, use aircon_model_id normally
        $aircon_model_id = !empty($_POST['aircon_model_id']) ? $_POST['aircon_model_id'] : null;
    }

    // Update the order
    $stmt = $pdo->prepare("
        UPDATE job_orders 
        SET 
            customer_name = ?,
            customer_phone = ?,
            customer_address = ?,
            service_type = ?,
            aircon_model_id = ?,
            part_id = ?,
            assigned_technician_id = ?,
            due_date = ?,
            price = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['customer_name'],
        $_POST['customer_phone'],
        $_POST['customer_address'],
        $service_type,
        $aircon_model_id,
        $part_id,
        !empty($_POST['assigned_technician_id']) ? $_POST['assigned_technician_id'] : null,
        $_POST['due_date'],
        $_POST['price'],
        $_POST['status'],
        $_POST['order_id']
    ]);

    $_SESSION['success'] = "Order has been updated successfully.";
    header('Location: ../orders.php');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: ../edit-order.php?id=' . $_POST['order_id']);
    exit();
}