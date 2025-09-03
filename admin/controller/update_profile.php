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
        // Enable error logging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', '../../logs/php-error.log');

        // Set PDO connection options
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        // Create logs directory if it doesn't exist
        if (!file_exists('../../logs')) {
            mkdir('../../logs', 0777, true);
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = '../../uploads/profile_pictures';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Log connection attempt
        error_log("Attempting database connection to " . DB_HOST . " with database " . DB_NAME);

        // Attempt database connection
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            error_log("Database connection successful");
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Could not connect to the database. Please check your database configuration.");
        }

        // Get form data
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $current_picture = $_POST['current_picture'] ?? '';

        // Handle file upload
        $profile_picture = $current_picture;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error_message'] = "Invalid file type. Please upload a JPG, PNG, or GIF image.";
                header('Location: ../profile.php');
                exit();
            }

            if ($file['size'] > $max_size) {
                $_SESSION['error_message'] = "File is too large. Maximum size is 5MB.";
                header('Location: ../profile.php');
                exit();
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('profile_') . '.' . $extension;
            $filepath = $upload_dir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old profile picture if it exists
                if (!empty($current_picture) && file_exists('../../' . $current_picture)) {
                    unlink('../../' . $current_picture);
                }
                $profile_picture = 'uploads/profile_pictures/' . $filename;
            } else {
                error_log("Failed to move uploaded file to: " . $filepath);
                $_SESSION['error_message'] = "Failed to upload profile picture. Please try again.";
                header('Location: ../profile.php');
                exit();
            }
        }

        // Log the received data
        error_log("Profile Update - Received data: " . json_encode([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'user_id' => $_SESSION['user_id']
        ]));

        // Validate input
        if (empty($name) || empty($username) || empty($email) || empty($phone)) {
            $_SESSION['error_message'] = "All fields are required.";
            header('Location: ../profile.php');
            exit();
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = "Invalid email format.";
            header('Location: ../profile.php');
            exit();
        }

        // Validate phone format (basic validation)
        if (!preg_match('/^[0-9+\-\s()]{10,}$/', $phone)) {
            $_SESSION['error_message'] = "Invalid phone number format.";
            header('Location: ../profile.php');
            exit();
        }

        // Check if username is already taken by another admin
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['error_message'] = "Username is already taken.";
            header('Location: ../profile.php');
            exit();
        }

        // Check if email is already taken by another admin
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['error_message'] = "Email is already taken.";
            header('Location: ../profile.php');
            exit();
        }

        // First, check if the admin exists
        $check_stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
        $check_stmt->execute([$_SESSION['user_id']]);
        if ($check_stmt->rowCount() === 0) {
            error_log("Profile Update Error: Admin not found with ID " . $_SESSION['user_id']);
            $_SESSION['error_message'] = "Admin account not found.";
            header('Location: ../profile.php');
            exit();
        }

        // Update admin profile
        $update_stmt = $pdo->prepare("
            UPDATE admins 
            SET name = ?, 
                username = ?, 
                email = ?, 
                phone = ?, 
                profile_picture = ?,
                updated_at = NOW() 
            WHERE id = ?
        ");

        try {
            $result = $update_stmt->execute([$name, $username, $email, $phone, $profile_picture, $_SESSION['user_id']]);
            
            if ($result) {
                // Update session username
                $_SESSION['username'] = $username;
                $_SESSION['success_message'] = "Profile updated successfully.";
                error_log("Profile Update Success: Admin ID " . $_SESSION['user_id']);
            } else {
                error_log("Profile Update Error: Update failed for Admin ID " . $_SESSION['user_id']);
                $_SESSION['error_message'] = "Failed to update profile. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Profile Update Error (SQL): " . $e->getMessage());
            $_SESSION['error_message'] = "Database error occurred. Please try again.";
        }

        header('Location: ../profile.php');
        exit();

    } catch (PDOException $e) {
        error_log("Profile Update Error (Connection): " . $e->getMessage());
        $_SESSION['error_message'] = "Database connection error. Please try again.";
        header('Location: ../profile.php');
        exit();
    } catch (Exception $e) {
        error_log("Profile Update Error (General): " . $e->getMessage());
        $_SESSION['error_message'] = "An unexpected error occurred. Please try again.";
        header('Location: ../profile.php');
        exit();
    }
} else {
    header('Location: ../profile.php');
    exit();
}