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
        $assigned_technician_id = !empty($_POST['assigned_technician_id']) ? (int)$_POST['assigned_technician_id'] : null;
        $cleaning_service_ids = $_POST['cleaning_service_id'];
        $aircon_model_ids = $_POST['aircon_model_id'] ?? [];
        $base_prices = $_POST['base_price'];
        $total_additional_fee = (float)$_POST['total_additional_fee'];
        $total_discount = (float)$_POST['discount'];
        $total_price = (float)$_POST['price'];
        
        // Validate required fields
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || empty($cleaning_service_ids)) {
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
        
        $created_orders = [];
        
        // Create individual cleaning orders
        for ($i = 0; $i < count($cleaning_service_ids); $i++) {
            $cleaning_service_id = (int)$cleaning_service_ids[$i];
            $aircon_model_id = !empty($aircon_model_ids[$i]) ? (int)$aircon_model_ids[$i] : null;
            $base_price = (float)$base_prices[$i];
            
            // Calculate proportional additional fee and discount
            $total_base_price = array_sum($base_prices);
            $proportional_additional_fee = $total_base_price > 0 ? ($base_price / $total_base_price) * $total_additional_fee : 0;
            $order_subtotal = $base_price + $proportional_additional_fee;
            $total_subtotal = $total_base_price + $total_additional_fee;
            $proportional_discount = $total_subtotal > 0 ? ($order_subtotal / $total_subtotal) * $total_discount : 0;
            $order_total = $order_subtotal - $proportional_discount;
            
            // Generate job order number
            $job_order_number = 'JO-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
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
                $proportional_additional_fee,
                $proportional_discount,
                $order_total,
                $_SESSION['user_id']
            ]);
            
            $created_orders[] = $job_order_number;
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = 'Bulk cleaning orders created successfully! Job Order Numbers: ' . implode(', ', $created_orders);
        header('Location: ../orders.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        $_SESSION['error'] = 'Error creating bulk cleaning orders: ' . $e->getMessage();
        header('Location: ../orders.php');
        exit();
    }
} else {
    header('Location: ../orders.php');
    exit();
}
?>