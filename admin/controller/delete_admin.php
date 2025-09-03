<?php
session_start();
require_once('../../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied.';
    header('Location: ../../index.php');
    exit();
}

// Check if the form was submitted via POST and admin_id is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id']) && isset($_POST['delete_admin'])) {
    $admin_id = $_POST['admin_id'];
    
    // Prevent deletion of the current logged-in admin
    if ($admin_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot delete your own admin account.';
        header('Location: ../settings.php');
        exit();
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if this is the only admin account
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
        $count_stmt->execute();
        $admin_count = $count_stmt->fetchColumn();
        
        if ($admin_count <= 1) {
            $_SESSION['error_message'] = 'Cannot delete the last admin account.';
            header('Location: ../settings.php');
            exit();
        }

        // Get admin info before deletion (for profile picture cleanup)
        $admin_stmt = $pdo->prepare("SELECT profile_picture FROM admins WHERE id = ?");
        $admin_stmt->execute([$admin_id]);
        $admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);

        // Delete the admin
        $delete_stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $delete_stmt->execute([$admin_id]);

        // Delete profile picture file if it exists
        if ($admin_data && !empty($admin_data['profile_picture']) && file_exists('../../' . $admin_data['profile_picture'])) {
            unlink('../../' . $admin_data['profile_picture']);
        }

        $_SESSION['success_message'] = 'Admin deleted successfully.';

    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request to delete admin.';
}

// Redirect back to the settings page
header('Location: ../settings.php');
exit();
?>