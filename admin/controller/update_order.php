<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Find customer_id from order if not provided
        $customer_id = $_POST['customer_id'] ?? null;
        if (!$customer_id && !empty($_POST['order_id'])) {
            $stmt = $pdo->prepare("SELECT customer_id FROM job_orders WHERE id = ?");
            $stmt->execute([$_POST['order_id']]);
            $customer_id = $stmt->fetchColumn();
        }

        // Update customer info if possible
        if ($customer_id) {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([
                $_POST['customer_name'],
                $_POST['customer_phone'],
                $_POST['customer_address'],
                $customer_id
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE job_orders SET
                aircon_model_id = :aircon_model_id,
                assigned_technician_id = :assigned_technician_id,
                base_price = :base_price,
                additional_fee = :additional_fee,
                discount = :discount,
                price = :price,
                status = :status
            WHERE id = :order_id
        ");
        $stmt->bindValue(':aircon_model_id', $_POST['aircon_model_id'] ?: null);
        $stmt->bindValue(':assigned_technician_id', $_POST['assigned_technician_id'] ?: null);
        $stmt->bindValue(':base_price', $_POST['base_price'] ?? 0);
        $stmt->bindValue(':additional_fee', $_POST['additional_fee'] ?? 0);
        $stmt->bindValue(':discount', $_POST['discount'] ?? 0);
        $stmt->bindValue(':price', $_POST['price'] ?? 0);
        $stmt->bindValue(':status', $_POST['status']);
        $stmt->bindValue(':order_id', $_POST['order_id']);
        $stmt->execute();

        $_SESSION['success'] = "Order updated successfully.";
        // Try to redirect to the customer_orders page if possible
        if ($customer_id) {
            header('Location: ../customer_orders.php?customer_id=' . (int)$customer_id);
        } else {
            header('Location: ../orders.php');
        }
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating order: " . $e->getMessage();
        if (!empty($customer_id)) {
            header('Location: ../customer_orders.php?customer_id=' . (int)$customer_id);
        } else {
            header('Location: ../orders.php');
        }
        exit();
    }
} else {
    header('Location: ../orders.php');
    exit();
}
