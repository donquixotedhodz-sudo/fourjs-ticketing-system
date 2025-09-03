<?php
session_start();
require_once '../config/database.php';

// Store the session data before destroying it
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Get admin's full name from database
$adminName = 'User';
if ($username && $role === 'admin') {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT name FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        if ($result) {
            $adminName = $result['name'];
        }
    } catch (PDOException $e) {
        // If there's an error, just use the username
        $adminName = $username;
    }
}

// Clear all session data
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Job Order System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1a237e;
            --light-blue: #f8f9fa;
            --hover-blue: #283593;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: var(--primary-blue);
        }

        .logout-container {
            text-align: center;
            position: relative;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .aircon-icon {
            font-size: 4rem;
            color: var(--primary-blue);
            margin-bottom: 1rem;
            animation: glowPulse 3s ease-in-out infinite alternate;
        }

        .goodbye-text {
            margin-top: 20px;
            color: var(--primary-blue);
            font-weight: 500;
            font-size: 1.5rem;
            text-align: center;
            animation: fadeIn 0.5s ease forwards;
        }

        .message {
            margin-top: 10px;
            color: var(--primary-blue);
            font-weight: 500;
            font-size: 1.1rem;
            text-align: center;
            opacity: 0.8;
        }

        .redirect-text {
            color: #999;
            font-size: 0.9rem;
            opacity: 0;
            animation: fadeIn 0.5s ease forwards 0.6s;
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .snowflake-loader {
            width: 80px;
            height: 80px;
            position: relative;
            margin: 0 auto 1rem;
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
         .loading-text {
            margin-top: 20px;
            color: var(--primary-blue);
            font-weight: 500;
            font-size: 1.1rem;
         }
    </style>
</head>
<body>
    <div class="snowflake-loader">
        <i class="fas fa-snowflake"></i>
    </div>
    <div class="goodbye-text">Goodbye, <?= htmlspecialchars($adminName) ?>!</div>
    <div class="loading-text">Thank you for using the Job Order System</div>
    <div class="progress-container">
        <div class="progress-bar"></div>
    </div>

    <script>
        // Animate progress bar
        const progressBar = document.querySelector('.progress-bar');
        let progress = 0;
        const interval = setInterval(() => {
            progress += 2;
            progressBar.style.width = progress + '%';
            if (progress >= 100) {
                clearInterval(interval);
                // Redirect after animation completes
                setTimeout(() => {
                    window.location.href = '../index.php';
                }, 500);
            }
        }, 60); // Complete in 3 seconds (100 / 2 * 60ms = 3000ms)
    </script>
</body>
</html>