<?php
session_start();
require_once('../../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied.';
    header('Location: ../../index.php');
    exit();
}

// Check if the form was submitted via POST and technician_id is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['technician_id'])) {
    $technician_id = $_POST['technician_id'];

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // You might want to check for associated job orders before deleting
        // and either prevent deletion or reassign/handle those orders.
        // For now, we will proceed with deletion.

        // Delete the technician
        $delete_stmt = $pdo->prepare("DELETE FROM technicians WHERE id = ?");
        $delete_stmt->execute([$technician_id]);

        $_SESSION['success_message'] = 'Technician deleted successfully.';

    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request to delete technician.';
}

// Redirect back to the technicians page
header('Location: ../technicians.php');
exit();
?> 