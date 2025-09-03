<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get form data
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error_message'] = "All fields are required.";
            header('Location: ../profile.php');
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = "New passwords do not match.";
            header('Location: ../profile.php');
            exit();
        }

        if (strlen($new_password) < 6) {
            $_SESSION['error_message'] = "New password must be at least 6 characters long.";
            header('Location: ../profile.php');
            exit();
        }

        // Additional password strength validation
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $new_password)) {
            $_SESSION['error_message'] = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
            header('Location: ../profile.php');
            exit();
        }

        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($current_password, $admin['password'])) {
            $_SESSION['error_message'] = "Current password is incorrect.";
            header('Location: ../profile.php');
            exit();
        }

        // Check if new password is same as current password
        if (password_verify($new_password, $admin['password'])) {
            $_SESSION['error_message'] = "New password must be different from current password.";
            header('Location: ../profile.php');
            exit();
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);

        $_SESSION['success_message'] = "Password changed successfully.";
        header('Location: ../profile.php');
        exit();

    } catch (PDOException $e) {
        error_log("Password Change Error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while changing your password. Please try again.";
        header('Location: ../profile.php');
        exit();
    }
} else {
    header('Location: ../profile.php');
    exit();
}
?>