<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

$term = $_GET['term'] ?? '';
if (empty($term)) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("SELECT id, name, phone, address FROM customers WHERE name LIKE ? LIMIT 10");
    $stmt->execute(['%' . $term . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode([]);
} 