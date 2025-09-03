<?php
require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Update admin password
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
    $stmt->execute([$admin_password]);
    echo "Admin password updated successfully.<br>";

    // Update technician password
    $tech_password = password_hash('tech123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE technicians SET password = ? WHERE username = 'tech1'");
    $stmt->execute([$tech_password]);
    echo "Technician password updated successfully.<br>";

    echo "<br>New login credentials:<br>";
    echo "Admin - Username: admin, Password: admin123<br>";
    echo "Technician - Username: tech1, Password: tech123<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 