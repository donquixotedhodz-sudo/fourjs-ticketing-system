<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


         // check technician table
        $stmt = $pdo->prepare("SELECT * FROM technicians WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'technician';
            session_write_close();
            
            echo json_encode([
                'success' => true, 
                'role' => 'technician', 
                'redirect' => 'technician/dashboard.php',
                'debug' => [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'role' => 'technician'
                ]
            ]);
            exit();
        }
        // checking admin table
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'admin';
            session_write_close();
            
            echo json_encode([
                'success' => true, 
                'role' => 'admin', 
                'redirect' => 'admin/dashboard.php',
                'debug' => [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'role' => 'admin'
                ]
            ]);
            exit();
        }

       

        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>