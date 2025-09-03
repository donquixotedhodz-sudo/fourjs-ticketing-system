<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $customer_address = trim($_POST['customer_address']);
        
        // Validate required fields
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
            $_SESSION['error'] = 'All fields are required.';
            header('Location: ../orders.php');
            exit();
        }
        
        // Check if customer already exists
        $check_sql = "SELECT id FROM customers WHERE phone = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$customer_phone]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Customer with this phone number already exists.';
            header('Location: ../orders.php');
            exit();
        }
        
        // Insert new customer
        $sql = "INSERT INTO customers (name, phone, address, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer_name, $customer_phone, $customer_address]);
        
        $_SESSION['success'] = 'Customer added successfully!';
        header('Location: ../orders.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: ../orders.php');
        exit();
    }
} else {
    header('Location: ../orders.php');
    exit();
}
?>