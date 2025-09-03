<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../customer_orders.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Validate required fields
    $required_fields = ['customer_id', 'customer_name', 'customer_phone', 'customer_address', 'assigned_technician_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $_SESSION['error'] = "Missing required field: " . ucfirst(str_replace('_', ' ', $field));
            header('Location: ../customer_orders.php?customer_id=' . $_POST['customer_id']);
            exit();
        }
    }

    // Validate arrays
    if (!isset($_POST['aircon_model_id']) || !is_array($_POST['aircon_model_id'])) {
        $_SESSION['error'] = "No aircon models selected";
        header('Location: ../customer_orders.php?customer_id=' . $_POST['customer_id']);
        exit();
    }

    $aircon_model_ids = $_POST['aircon_model_id'];
    $base_prices = $_POST['base_price'] ?? [];
    $additional_fees = $_POST['additional_fee'] ?? [];
    $discount = floatval($_POST['discount'] ?? 0);

    // Validate arrays have same length
    if (count($aircon_model_ids) !== count($base_prices) || count($aircon_model_ids) !== count($additional_fees)) {
        $_SESSION['error'] = "Invalid data structure";
        header('Location: ../customer_orders.php?customer_id=' . $_POST['customer_id']);
        exit();
    }

    // Start transaction
    $pdo->beginTransaction();

    $customer_id = (int)$_POST['customer_id'];
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_address = trim($_POST['customer_address']);
    $assigned_technician_id = (int)$_POST['assigned_technician_id'];
    $service_type = 'installation';

    // Check if customer exists, if not create them
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $existing_customer = $stmt->fetch();

    if (!$existing_customer) {
        // Create new customer
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
        $stmt->execute([$customer_name, $customer_phone, $customer_address]);
        $customer_id = $pdo->lastInsertId();
    } else {
        // Update existing customer info
        $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$customer_name, $customer_phone, $customer_address, $customer_id]);
    }

    $created_orders = [];
    $total_price = 0;

    // Process each order
    for ($i = 0; $i < count($aircon_model_ids); $i++) {
        $aircon_model_id = (int)$aircon_model_ids[$i];
        $base_price = floatval($base_prices[$i] ?? 0);
        $additional_fee = floatval($additional_fees[$i] ?? 0);
        
        // Skip if no aircon model selected
        if (empty($aircon_model_id)) {
            continue;
        }

        // Calculate individual order price
        $order_price = $base_price + $additional_fee;
        $total_price += $order_price;

        // Generate job order number
        $job_order_number = 'JO-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Insert job order
        $stmt = $pdo->prepare("
            INSERT INTO job_orders (
                job_order_number, customer_id, customer_name, customer_phone, customer_address,
                service_type, aircon_model_id, assigned_technician_id,
                base_price, additional_fee, price, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $job_order_number,
            $customer_id,
            $customer_name,
            $customer_phone,
            $customer_address,
            $service_type,
            $aircon_model_id,
            $assigned_technician_id,
            $base_price,
            $additional_fee,
            $order_price
        ]);

        $order_id = $pdo->lastInsertId();
        $created_orders[] = [
            'id' => $order_id,
            'job_order_number' => $job_order_number,
            'price' => $order_price
        ];
    }

    // Apply discount if any
    if ($discount > 0 && count($created_orders) > 0) {
        // Distribute discount equally among orders
        $discount_per_order = $discount / count($created_orders);
        
        foreach ($created_orders as $order) {
            $final_price = max(0, $order['price'] - $discount_per_order);
            
            $stmt = $pdo->prepare("UPDATE job_orders SET price = ?, discount = ? WHERE id = ?");
            $stmt->execute([$final_price, $discount_per_order, $order['id']]);
        }
    }

    // Commit transaction
    $pdo->commit();

    // Success message
    $order_count = count($created_orders);
    $total_final_price = $total_price - $discount;
    
    $_SESSION['success'] = "Successfully created $order_count installation order(s) for $customer_name. Total: ₱" . number_format($total_final_price, 2);

    // Redirect back to customer orders
    header('Location: ../customer_orders.php?customer_id=' . $customer_id);
    exit();

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: ../customer_orders.php?customer_id=' . ($_POST['customer_id'] ?? ''));
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: ../customer_orders.php?customer_id=' . ($_POST['customer_id'] ?? ''));
    exit();
}
?>
