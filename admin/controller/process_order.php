<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Generate job order number (format: JO-YYYYMMDD-XXXX)
        $job_order_number = 'JO-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // CUSTOMER HANDLING
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $customer_address = trim($_POST['customer_address']);
        
        // If customer_id is provided (from customer_orders.php), use it
        if (!empty($_POST['customer_id'])) {
            $customer_id = (int)$_POST['customer_id'];
            
            // Update customer info
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$customer_name, $customer_phone, $customer_address, $customer_id]);
        } else {
            // Try to find existing customer by name and phone
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ? AND phone = ? LIMIT 1");
            $stmt->execute([$customer_name, $customer_phone]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                $customer_id = $customer['id'];
                // Update customer address if it has changed
                $stmt = $pdo->prepare("UPDATE customers SET address = ? WHERE id = ?");
                $stmt->execute([$customer_address, $customer_id]);
            } else {
                // Insert new customer
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
                $stmt->execute([$customer_name, $customer_phone, $customer_address]);
                $customer_id = $pdo->lastInsertId();
            }
        }

        // Prepare the SQL statement
        $stmt = $pdo->prepare("
            INSERT INTO job_orders (
                job_order_number,
                customer_name,
                customer_address,
                customer_phone,
                service_type,
                aircon_model_id,
                part_id,
                assigned_technician_id,
                status,
                price,
                base_price,
                additional_fee,
                discount,
                created_by,
                customer_id
            ) VALUES (
                :job_order_number,
                :customer_name,
                :customer_address,
                :customer_phone,
                :service_type,
                :aircon_model_id,
                :part_id,
                :assigned_technician_id,
                :status,
                :price,
                :base_price,
                :additional_fee,
                :discount,
                :created_by,
                :customer_id
            )
        ");

        // Handle aircon_model_id vs part_id based on service_type
        $service_type = $_POST['service_type'];
        $aircon_model_id = null;
        $part_id = null;
        
        if ($service_type === 'repair') {
            // For repair orders, the aircon_model_id field contains the part ID
            $part_id = $_POST['aircon_model_id'] ?: null;
        } else {
            // For installation/survey orders, use aircon_model_id normally
            $aircon_model_id = $_POST['aircon_model_id'] ?: null;
        }

        // Bind parameters using bindValue instead of bindParam
        $stmt->bindValue(':job_order_number', $job_order_number);
        $stmt->bindValue(':customer_name', $customer_name);
        $stmt->bindValue(':customer_address', $customer_address);
        $stmt->bindValue(':customer_phone', $customer_phone);
        $stmt->bindValue(':service_type', $service_type);
        $stmt->bindValue(':aircon_model_id', $aircon_model_id);
        $stmt->bindValue(':part_id', $part_id);
        $stmt->bindValue(':assigned_technician_id', $_POST['assigned_technician_id'] ?: null);
        $stmt->bindValue(':status', 'pending');
        $stmt->bindValue(':price', $_POST['price']);
        $stmt->bindValue(':base_price', $_POST['base_price'] ?? 0);
        $stmt->bindValue(':additional_fee', $_POST['additional_fee'] ?? 0);
        $stmt->bindValue(':discount', $_POST['discount'] ?? 0);
        $stmt->bindValue(':created_by', $_SESSION['user_id']);
        $stmt->bindValue(':customer_id', $customer_id);

        // Execute the statement
        $stmt->execute();

        // Redirect back to orders page with success message
        $_SESSION['success'] = "Job order #$job_order_number has been created successfully.";
        if (!empty($_POST['customer_id'])) {
            header('Location: ../customer_orders.php?customer_id=' . (int)$_POST['customer_id']);
            exit();
        }
        header('Location: ../orders.php');
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating job order: " . $e->getMessage();
        if (!empty($_POST['customer_id'])) {
            header('Location: ../customer_orders.php?customer_id=' . (int)$_POST['customer_id']);
            exit();
        }
        header('Location: ../orders.php');
        exit();
    }
} else {
    // If not POST request, redirect to orders page
    header('Location: ../orders.php');
    exit();
}