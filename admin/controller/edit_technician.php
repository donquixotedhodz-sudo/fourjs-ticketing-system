<?php
session_start();
require_once('../../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied.';
    header('Location: ../../index.php');
    exit();
}

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $technician_id = $_POST['technician_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // Basic validation
    if (empty($technician_id) || empty($name) || empty($username) || empty($phone)) {
        $_SESSION['error_message'] = 'All fields are required to edit a technician.';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if the username already exists for another technician
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM technicians WHERE username = ? AND id != ?");
            $stmt->execute([$username, $technician_id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = 'Username already exists for another technician.';
            } else {
                // Update technician details
                $update_stmt = $pdo->prepare("UPDATE technicians SET name = ?, username = ?, phone = ? WHERE id = ?");
                $update_stmt->execute([$name, $username, $phone, $technician_id]);

                $_SESSION['success_message'] = 'Technician updated successfully.';
            }

        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Redirect back to the technicians page
header('Location: ../technicians.php');
exit();
?> 