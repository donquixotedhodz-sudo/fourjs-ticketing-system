<?php
session_start();
require_once('../../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied.';
    header('Location: ../../index.php');
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate form data
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($name) || empty($username) || empty($email) || empty($phone) || empty($password)) {
        $_SESSION['error_message'] = 'All fields are required.';
    } else if ($password !== $confirm_password) {
        $_SESSION['error_message'] = 'Password and confirm password do not match.';
    } else if (strlen($password) < 8) {
        $_SESSION['error_message'] = 'Password must be at least 8 characters long.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Invalid email format.';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = 'Username already exists.';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error_message'] = 'Email already exists.';
                } else {
                    // Handle profile picture upload
                    $profile_picture = null;
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES['profile_picture'];
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $max_size = 2 * 1024 * 1024; // 2MB

                        // Validate file type
                        if (!in_array($file['type'], $allowed_types)) {
                            throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
                        }

                        // Validate file size
                        if ($file['size'] > $max_size) {
                            throw new Exception("File size exceeds 2MB limit.");
                        }

                        // Generate unique filename
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = uniqid('admin_') . '.' . $extension;
                        $upload_dir = '../../uploads/profile_pictures/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $filepath = $upload_dir . $filename;
                        
                        // Move uploaded file
                        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                            throw new Exception("Failed to upload profile picture.");
                        }

                        $profile_picture = 'uploads/profile_pictures/' . $filename;
                    }

                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new admin into the database
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO admins (name, username, email, phone, password, profile_picture, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insert_stmt->execute([
                        $name,
                        $username,
                        $email,
                        $phone,
                        $hashed_password,
                        $profile_picture
                    ]);

                    $_SESSION['success_message'] = 'New admin created successfully.';
                }
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
}

// Redirect back to the settings page
header('Location: ../settings.php');
exit();
?>