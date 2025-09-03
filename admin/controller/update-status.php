<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if order ID and status are provided
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
    header('Location: ' . $redirect);
    exit();
}

$allowed_statuses = ['in_progress', 'completed', 'cancelled'];
if (!in_array($_GET['status'], $allowed_statuses)) {
    $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
    header('Location: ' . $redirect);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the order exists and belongs to the technician (if technician)
    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM job_orders 
        WHERE id = ? 
        " . ($_SESSION['role'] === 'technician' ? "AND assigned_technician_id = ?" : "")
    );
    
    if ($_SESSION['role'] === 'technician') {
        $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    } else {
        $stmt->execute([$_GET['id']]);
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
        header('Location: ' . $redirect);
        exit();
    }

    // Validate status transition
    if ($_GET['status'] === 'completed' && $order['status'] !== 'in_progress') {
        $_SESSION['error'] = "Cannot mark order as completed. It must be in progress first.";
        $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
        header('Location: ' . $redirect);
        exit();
    }

    if ($_GET['status'] === 'in_progress' && $order['status'] !== 'pending') {
        $_SESSION['error'] = "Cannot start work. Order must be pending first.";
        $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
        header('Location: ' . $redirect);
        exit();
    }

    // Update the order status
    $stmt = $pdo->prepare("
        UPDATE job_orders 
        SET status = ?, 
            " . ($_GET['status'] === 'completed' ? "completed_at = NOW()," : "") . "
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_GET['status'], $_GET['id']]);

    $_SESSION['success'] = "Order status has been updated successfully.";
    $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
    header('Location: ' . $redirect);
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $redirect = (isset($_GET['customer_id'])) ? '../customer_orders.php?customer_id=' . (int)$_GET['customer_id'] : (($_SESSION['role'] === 'admin') ? '../orders.php' : '../technician/orders.php');
    header('Location: ' . $redirect);
    exit();
} 