<?php
session_start();
require_once('../../config/database.php');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get the action and type from POST data
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';

    // Handle different actions
    switch ($action) {
        case 'create':
            handleCreate($pdo, $type);
            break;
        case 'update':
            handleUpdate($pdo, $type);
            break;
        case 'delete':
            handleDelete($pdo, $type);
            break;
        case 'get':
            handleGet($pdo, $type);
            break;
        case 'get_categories':
            handleGetCategories($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Function to handle creating new items
function handleCreate($pdo, $type) {
    if ($type === 'part') {
        $part_name = $_POST['part_name'] ?? '';
        $part_code = $_POST['part_code'] ?? '';
        $category_id = $_POST['category_id'] ?? '';
        $unit_price = $_POST['unit_price'] ?? 0;
        $labor_cost = $_POST['labor_cost'] ?? 0;
        $warranty_months = $_POST['warranty_months'] ?? 12;

        // Validation
        if (empty($part_name) || empty($category_id) || $unit_price < 0) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            return;
        }

        // Check if part code already exists
        if (!empty($part_code)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ac_parts WHERE part_code = ?");
            $stmt->execute([$part_code]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Part code already exists.']);
                return;
            }
        }

        // Insert new part
        $stmt = $pdo->prepare("
            INSERT INTO ac_parts (part_name, part_code, category_id, unit_price, labor_cost, warranty_months) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $part_name, 
            $part_code, 
            $category_id, 
            $unit_price, 
            $labor_cost, 
            $warranty_months
        ]);

        echo json_encode(['success' => true, 'message' => 'Part added successfully.']);
    } elseif ($type === 'category') {
        $category_name = $_POST['category_name'] ?? '';
        $category_description = $_POST['category_description'] ?? '';

        // Validation
        if (empty($category_name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']);
            return;
        }

        // Check if category already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM part_categories WHERE category_name = ?");
        $stmt->execute([$category_name]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Category already exists.']);
            return;
        }

        // Insert new category
        $stmt = $pdo->prepare("
            INSERT INTO part_categories (category_name, category_description) 
            VALUES (?, ?)
        ");
        
        $stmt->execute([$category_name, $category_description]);

        echo json_encode(['success' => true, 'message' => 'Category added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type for creation.']);
    }
}

// Function to handle updating items
function handleUpdate($pdo, $type) {
    if ($type === 'part') {
        $id = $_POST['id'] ?? 0;
        $part_name = $_POST['part_name'] ?? '';
        $part_code = $_POST['part_code'] ?? '';
        $category_id = $_POST['category_id'] ?? '';
        $unit_price = $_POST['unit_price'] ?? 0;
        $labor_cost = $_POST['labor_cost'] ?? 0;
        $warranty_months = $_POST['warranty_months'] ?? 12;

        // Validation
        if (empty($id) || empty($part_name) || empty($category_id) || $unit_price < 0) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            return;
        }

        // Check if part exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ac_parts WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Part not found.']);
            return;
        }

        // Check if part code already exists for another part
        if (!empty($part_code)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ac_parts WHERE part_code = ? AND id != ?");
            $stmt->execute([$part_code, $id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Part code already exists for another part.']);
                return;
            }
        }

        // Update part
        $stmt = $pdo->prepare("
            UPDATE ac_parts 
            SET part_name = ?, part_code = ?, category_id = ?, unit_price = ?, labor_cost = ?, 
                warranty_months = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $part_name, 
            $part_code, 
            $category_id, 
            $unit_price, 
            $labor_cost, 
            $warranty_months,
            $id
        ]);

        echo json_encode(['success' => true, 'message' => 'Part updated successfully.']);
    } elseif ($type === 'category') {
        $id = $_POST['category_id'] ?? 0;
        $category_name = $_POST['category_name'] ?? '';
        $category_description = $_POST['category_description'] ?? '';
        $is_active = $_POST['is_active'] ?? 1;

        // Validation
        if (empty($id) || empty($category_name)) {
            echo json_encode(['success' => false, 'message' => 'Category ID and name are required.']);
            return;
        }

        // Check if category exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM part_categories WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Category not found.']);
            return;
        }

        // Check if category name already exists for another category
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM part_categories WHERE category_name = ? AND id != ?");
        $stmt->execute([$category_name, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Category name already exists.']);
            return;
        }

        // Update category
        $stmt = $pdo->prepare("
            UPDATE part_categories 
            SET category_name = ?, category_description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([$category_name, $category_description, $is_active, $id]);

        echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type for update.']);
    }
}

// Function to handle deleting items
function handleDelete($pdo, $type) {
    if ($type === 'part') {
        $id = $_POST['id'] ?? 0;

        // Validation
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Part ID is required.']);
            return;
        }

        // Check if part exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ac_parts WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Part not found.']);
            return;
        }

        // Delete part
        $stmt = $pdo->prepare("DELETE FROM ac_parts WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Part deleted successfully.']);
    } elseif ($type === 'category') {
        $id = $_POST['category_id'] ?? 0;

        // Validation
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Category ID is required.']);
            return;
        }

        // Check if category exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM part_categories WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Category not found.']);
            return;
        }

        // Check if category is being used by any parts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ac_parts WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete category. It is being used by existing parts.']);
            return;
        }

        // Delete category
        $stmt = $pdo->prepare("DELETE FROM part_categories WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Category deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type for deletion.']);
    }
}

// Function to handle getting items
function handleGet($pdo, $type) {
    if ($type === 'part') {
        $id = $_POST['id'] ?? $_GET['id'] ?? 0;

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Part ID is required.']);
            return;
        }

        // Get part details
        $stmt = $pdo->prepare("SELECT * FROM ac_parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($part) {
            echo json_encode(['success' => true, 'data' => $part]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Part not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type for retrieval.']);
    }
}

// Function to get all categories
function handleGetCategories($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM part_categories WHERE is_active = 1 ORDER BY category_name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $categories]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching categories: ' . $e->getMessage()]);
    }
}
?>