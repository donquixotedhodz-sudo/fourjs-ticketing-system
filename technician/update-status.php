<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a technician
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    header('Location: ../index.php');
    exit();
}

// Check if order ID and status are provided
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = "Invalid request.";
    header('Location: orders.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // First check if the order belongs to this technician
    $stmt = $pdo->prepare("SELECT id FROM job_orders WHERE id = ? AND assigned_technician_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = "Order not found or unauthorized.";
        header('Location: orders.php');
        exit();
    }

    // Validate status
    $allowed_statuses = ['in_progress', 'completed', 'cancelled'];
    if (!in_array($_GET['status'], $allowed_statuses)) {
        $_SESSION['error'] = "Invalid status.";
        header('Location: orders.php');
        exit();
    }

    // Update the order status
    $stmt = $pdo->prepare("
        UPDATE job_orders 
        SET status = ?, 
            updated_at = CURRENT_TIMESTAMP,
            completed_at = CASE 
                WHEN ? IN ('completed', 'cancelled') THEN CURRENT_TIMESTAMP 
                ELSE completed_at 
            END
        WHERE id = ? AND assigned_technician_id = ?
    ");
    $stmt->execute([$_GET['status'], $_GET['status'], $_GET['id'], $_SESSION['user_id']]);

    // Set success message based on status
    $status_messages = [
        'in_progress' => "Order has been started.",
        'completed' => "Order has been marked as completed.",
        'cancelled' => "Order has been cancelled and moved to archive."
    ];
    $_SESSION['success'] = $status_messages[$_GET['status']];

    // Redirect back to orders page
    header('Location: orders.php');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: orders.php');
    exit();
} 