<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get form data
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $customer_address = trim($_POST['customer_address']);
        $aircon_model = trim($_POST['aircon_model']);
        $aircon_type = trim($_POST['aircon_type']);
        $service_type = trim($_POST['service_type']);
        $technician_id = trim($_POST['technician_id']);
        $notes = trim($_POST['notes']);

        // Validate input
        $errors = [];

        if (empty($customer_name)) {
            $errors[] = "Customer name is required";
        }

        if (empty($customer_phone)) {
            $errors[] = "Customer phone is required";
        }

        if (empty($customer_address)) {
            $errors[] = "Customer address is required";
        }

        if (empty($aircon_model)) {
            $errors[] = "Aircon model is required";
        }

        if (empty($aircon_type)) {
            $errors[] = "Aircon type is required";
        }

        if (empty($service_type)) {
            $errors[] = "Service type is required";
        }

        if (empty($technician_id)) {
            $errors[] = "Technician assignment is required";
        }

        // If no errors, proceed with insertion
        if (empty($errors)) {
            // Start transaction
            $pdo->beginTransaction();

            try {
                // Insert the new job order
                $stmt = $pdo->prepare("
                    INSERT INTO job_orders (
                        customer_name, 
                        customer_phone, 
                        customer_address, 
                        aircon_model, 
                        aircon_type, 
                        service_type, 
                        assigned_technician_id,
                        notes,
                        status,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");
                
                $stmt->execute([
                    $customer_name,
                    $customer_phone,
                    $customer_address,
                    $aircon_model,
                    $aircon_type,
                    $service_type,
                    $technician_id,
                    $notes
                ]);

                // Get the new job order ID
                $job_order_id = $pdo->lastInsertId();

                // Create a notification for the assigned technician
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        technician_id,
                        job_order_id,
                        message,
                        is_read,
                        created_at
                    ) VALUES (?, ?, ?, 0, NOW())
                ");

                $notification_message = "New job order #" . $job_order_id . " has been assigned to you.";
                $stmt->execute([$technician_id, $job_order_id, $notification_message]);

                // Commit transaction
                $pdo->commit();

                // Redirect back to dashboard with success message
                $_SESSION['success_message'] = "Job order added successfully!";
                header('Location: ../dashboard.php');
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // Redirect back with errors
            $_SESSION['error_message'] = implode("<br>", $errors);
            header('Location: ../dashboard.php');
            exit();
        }

    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header('Location: ../dashboard.php');
        exit();
    }
} else {
    // If not POST request, redirect to dashboard
    header('Location: ../dashboard.php');
    exit();
} 