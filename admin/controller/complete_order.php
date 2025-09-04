<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get order ID from POST data
        $order_id = $_POST['order_id'] ?? null;

        if (!$order_id) {
            throw new Exception("Order ID is required");
        }

        // Update the order status to completed
        $stmt = $pdo->prepare("
            UPDATE job_orders 
            SET status = 'completed', 
                completed_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$order_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Job order has been marked as completed!";
            
            // Get customer_id for redirect
            $customer_stmt = $pdo->prepare("SELECT customer_id FROM job_orders WHERE id = ?");
            $customer_stmt->execute([$order_id]);
            $customer_data = $customer_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer_data && $customer_data['customer_id']) {
                header('Location: ../customer_orders.php?customer_id=' . $customer_data['customer_id']);
                exit();
            }
        } else {
            $_SESSION['error_message'] = "No changes were made to the job order.";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Invalid request method";
}

// Redirect back to orders page
header('Location: ../orders.php');
exit();