<?php
session_start();
require_once('../../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $admin_id = intval($_POST['admin_id']);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        // Validate required fields
        if (empty($name) || empty($username) || empty($email) || empty($phone)) {
            $_SESSION['error_message'] = 'Please fill in all required fields.';
            header('Location: ../settings.php');
            exit;
        }
        
        // Check if username or email already exists (excluding current admin)
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $admin_id]);
        if ($stmt->fetch()) {
            $_SESSION['error_message'] = 'Username or email already exists.';
            header('Location: ../settings.php');
            exit;
        }
        
        // Handle profile picture upload
        $profile_picture_path = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/profile_pictures/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['profile_picture']['name']);
            $extension = strtolower($file_info['extension']);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($extension, $allowed_extensions)) {
                $_SESSION['error_message'] = 'Invalid file type. Please upload JPG, PNG, or GIF files only.';
                header('Location: ../settings.php');
                exit;
            }
            
            // Check file size (2MB limit)
            if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
                $_SESSION['error_message'] = 'File size exceeds 2MB limit.';
                header('Location: ../settings.php');
                exit;
            }
            
            $filename = 'admin_' . $admin_id . '_' . uniqid() . '.' . $extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                $profile_picture_path = 'uploads/profile_pictures/' . $filename;
                
                // Delete old profile picture if exists
                $stmt = $pdo->prepare("SELECT profile_picture FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $old_admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($old_admin && !empty($old_admin['profile_picture']) && file_exists('../../' . $old_admin['profile_picture'])) {
                    unlink('../../' . $old_admin['profile_picture']);
                }
            } else {
                $_SESSION['error_message'] = 'Failed to upload profile picture.';
                header('Location: ../settings.php');
                exit;
            }
        }
        
        // Prepare update query
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if ($profile_picture_path) {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, phone = ?, password = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $phone, $hashed_password, $profile_picture_path, $admin_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $phone, $hashed_password, $admin_id]);
            }
        } else {
            // Update without changing password
            if ($profile_picture_path) {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $phone, $profile_picture_path, $admin_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $phone, $admin_id]);
            }
        }
        
        $_SESSION['success_message'] = 'Admin updated successfully!';
        header('Location: ../settings.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        header('Location: ../settings.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header('Location: ../settings.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../settings.php');
    exit;
}
?>