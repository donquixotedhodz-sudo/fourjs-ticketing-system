<?php
session_start();
require_once 'config/database.php';

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: technician/dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['check_credentials'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $user = null;
            $userRole = null;

            // First check admin table
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'admin';
                header('Location: admin/dashboard.php');
                exit();
            }

            // If not found in admin table, check technician table
            $stmt = $pdo->prepare("SELECT * FROM technicians WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'technician';
                header('Location: technician/dashboard.php');
                exit();
            }

            $error = 'Invalid username or password';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Job Order System</title>
    <!-- Google Fonts -->
    <link rel="icon" href="images/logo-favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --primary-blue: #1a237e;
            --light-blue:#f8f9fa;
            --hover-blue: #283593;
        }
        
        body {
            background: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        @keyframes glowPulse {
            0% {
                opacity: 0.5;
                transform: scale(1);
            }
            100% {
                opacity: 0.8;
                transform: scale(1.1);
            }
        }
        
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(26, 35, 126, 0.2), 
                        0 0 0 1px rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header i {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }
        

        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating > .form-control {
            padding: 1rem 0.75rem;
        }
        
        .form-floating > label {
            padding: 1rem 0.75rem;
        }
        
        .btn-login {
            background-color: var(--primary-blue);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background-color: var(--hover-blue);
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .form-floating {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            z-index: 10;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #495057;
        }

        .password-toggle:focus {
            outline: none;
        }

        .password-field {
            padding-right: 35px !important;
        }

        .password-toggle i {
            font-size: 14px;
        }

        /* Add loading screen styles */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .loading-screen.active {
            display: flex;
            opacity: 1;
        }

        .loading-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        .snowflake-loader {
            width: 80px;
            height: 80px;
            position: relative;
        }

        .snowflake-loader::before,
        .snowflake-loader::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            border: 3px solid var(--light-blue);
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }

        .snowflake-loader::before {
            animation: snowflake-pulse 2s ease-in-out infinite;
        }

        .snowflake-loader::after {
            animation: snowflake-pulse 2s ease-in-out infinite 1s;
        }

        .snowflake-loader i {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--primary-blue);
            font-size: 48px;
            animation: snowflake-beat 2s ease-in-out infinite;
        }

        .loading-text {
            margin-top: 20px;
            color: var(--primary-blue);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .progress-container {
            width: 200px;
            height: 4px;
            background: var(--light-blue);
            border-radius: 2px;
            margin-top: 15px;
            overflow: hidden;
        }

        .progress-bar {
            width: 0%;
            height: 100%;
            background: var(--primary-blue);
            border-radius: 2px;
            transition: width 0.1s linear;
        }

        @keyframes snowflake-beat {
            0% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.2); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes snowflake-pulse {
            0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.5; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Add loading screen HTML -->
    <div class="loading-screen">
        <div class="snowflake-loader">
            <i class="fas fa-snowflake"></i>
        </div>
        <div class="loading-text">Loading Dashboard...</div>
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
    </div>

        <div class="login-card">
            <div class="login-header">
                <div class="logo-circle">
                    <img src="images/logo.png" alt="Logo" class="logo-image">
                </div>
                <h2>Job Order System</h2>
                <p class="text-muted">Please login to continue</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username">Username</label>
                </div>

                <div class="form-floating">
                    <input type="password" class="form-control password-field" id="password" name="password" placeholder=" " required>
                    <label for="password">Password</label>
                    <button type="button" class="password-toggle" style="display: none;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
        </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle');
            const loadingScreen = document.querySelector('.loading-screen');
            const loginForm = document.querySelector('form');
            const progressBar = document.querySelector('.progress-bar');
            const loginButton = document.querySelector('.btn-login');

            // Ensure login button is clickable
            loginButton.style.cursor = 'pointer';
            loginButton.style.pointerEvents = 'auto';

            // Password toggle functionality
            passwordInput.addEventListener('input', function() {
                toggleButton.style.display = this.value.length > 0 ? 'flex' : 'none';
            });

            toggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Add form submit handler for loading screen
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;

                if (username && password) {
                    // Create form data
                    const formData = new FormData();
                    formData.append('username', username);
                    formData.append('password', password);
                    formData.append('check_credentials', 'true');

                    // Check credentials first
                    fetch('validation/check_credentials.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadingScreen.classList.add('active');
                            
                            // Reset progress bar
                            progressBar.style.width = '0%';
                            
                            // Animate progress bar over 2 seconds
                            let progress = 0;
                            const interval = setInterval(() => {
                                progress += 2;
                                progressBar.style.width = progress + '%';
                                
                                if (progress >= 100) {
                                    clearInterval(interval);
                                    // Add a small delay to ensure session is properly established
                                    setTimeout(() => {
                                        window.location.href = data.redirect;
                                    }, 100);
                                }
                            }, 40); // 40ms * 50 = 2000ms (2 seconds)
                        } else {
                            // Show error message
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger';
                            errorDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + data.message;
                            
                            // Remove any existing error messages
                            const existingError = document.querySelector('.alert');
                            if (existingError) {
                                existingError.remove();
                            }
                            
                            // Insert new error message after the login header
                            const loginHeader = document.querySelector('.login-header');
                            loginHeader.parentNode.insertBefore(errorDiv, loginHeader.nextSibling);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            });
        });
    </script>
</body>
</html>