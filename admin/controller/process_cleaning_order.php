<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $customer_address = trim($_POST['customer_address']);
        $cleaning_service_id = intval($_POST['cleaning_service_id']);
        $technician_id = intval($_POST['technician_id']);
        $additional_fee = floatval($_POST['additional_fee'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $total_price = floatval($_POST['total_price']);
        
        // Validate required fields
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || 
            $cleaning_service_id <= 0 || $technician_id <= 0) {
            throw new Exception('All required fields must be filled.');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if customer exists
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE phone = ?");
        $stmt->execute([$customer_phone]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            $customer_id = $customer['customer_id'];
            
            // Update existing customer
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, address = ? WHERE customer_id = ?");
            $stmt->execute([$customer_name, $customer_address, $customer_id]);
        } else {
            // Create new customer
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$customer_name, $customer_phone, $customer_address]);
            $customer_id = $pdo->lastInsertId();
        }
        
        // Get cleaning service details
        $stmt = $pdo->prepare("SELECT service_name, base_price FROM cleaning_services WHERE service_id = ?");
        $stmt->execute([$cleaning_service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            throw new Exception('Invalid cleaning service selected.');
        }
        
        // Create job order
        $stmt = $pdo->prepare("
            INSERT INTO job_orders (
                customer_id, 
                technician_id, 
                service_type, 
                cleaning_service_id,
                service_fee, 
                additional_fee, 
                discount, 
                total_price, 
                status, 
                order_date
            ) VALUES (?, ?, 'cleaning', ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $customer_id,
            $technician_id,
            $cleaning_service_id,
            $service['base_price'],
            $additional_fee,
            $discount,
            $total_price
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect with success message
        header('Location: ../orders.php?success=Cleaning order created successfully!');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        // Redirect with error message
        header('Location: ../orders.php?error=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: ../orders.php');
    exit();
}
?>