<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get form data
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $customer_address = trim($_POST['customer_address']);
        $cleaning_service_id = (int)$_POST['cleaning_service_id'];
        $aircon_model_id = !empty($_POST['aircon_model_id']) ? (int)$_POST['aircon_model_id'] : null;
        $assigned_technician_id = !empty($_POST['assigned_technician_id']) ? (int)$_POST['assigned_technician_id'] : null;
        $base_price = (float)$_POST['base_price'];
        $additional_fee = (float)$_POST['additional_fee'];
        $discount = (float)$_POST['discount'];
        $total_price = (float)$_POST['price'];
        
        // Validate required fields
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || empty($cleaning_service_id)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Check if customer exists, if not create new customer
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ? AND phone = ?");
        $stmt->execute([$customer_name, $customer_phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            $customer_id = $customer['id'];
            // Update customer address if different
            $stmt = $pdo->prepare("UPDATE customers SET address = ? WHERE id = ?");
            $stmt->execute([$customer_address, $customer_id]);
        } else {
            // Create new customer
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$customer_name, $customer_phone, $customer_address]);
            $customer_id = $pdo->lastInsertId();
        }
        
        // Generate job order number
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM job_orders WHERE YEAR(created_at) = ?");
        $stmt->execute([$year]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $job_order_number = $year . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
        
        // Insert job order
        $stmt = $pdo->prepare("
            INSERT INTO job_orders (
                job_order_number, customer_id, customer_name, customer_phone, customer_address,
                service_type, cleaning_service_id, aircon_model_id, assigned_technician_id,
                status, base_price, additional_fee, discount, price, created_by
            ) VALUES (?, ?, ?, ?, ?, 'cleaning', ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $job_order_number,
            $customer_id,
            $customer_name,
            $customer_phone,
            $customer_address,
            $cleaning_service_id,
            $aircon_model_id,
            $assigned_technician_id,
            $base_price,
            $additional_fee,
            $discount,
            $total_price,
            $_SESSION['user_id']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = 'Cleaning service order created successfully! Job Order Number: ' . $job_order_number;
        header('Location: ../customer_orders.php?customer_id=' . $customer_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        $_SESSION['error'] = 'Error creating cleaning order: ' . $e->getMessage();
        header('Location: ../orders.php');
        exit();
    }
} else {
    header('Location: ../orders.php');
    exit();
}
?>