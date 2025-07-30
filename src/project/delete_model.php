<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Set JSON header to ensure proper content type
header('Content-Type: application/json');

// Enhanced logging for debugging
error_log("Delete model request received");
error_log("POST data: " . json_encode($_POST));
error_log("Session user_id: " . ($_SESSION["user_id"] ?? 'not set'));

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION["user_id"];

// Check if model ID is provided
if (!isset($_POST["model_id"]) || !is_numeric($_POST["model_id"])) {
    echo json_encode(["success" => false, "error" => "Invalid model ID"]);
    exit;
}

$model_id = intval($_POST["model_id"]);
error_log("Processing delete for model ID: $model_id");

// Verify ownership of the model
$stmt = $conn->prepare("
    SELECT m.*, p.user_id, p.id as project_id, p.active_model_id 
    FROM models m
    JOIN projects p ON m.project_id = p.id
    WHERE m.id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $model_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Model not found or unauthorized. User ID: $user_id, Model ID: $model_id");
    echo json_encode(["success" => false, "error" => "Model not found or unauthorized"]);
    exit;
}

$model = $result->fetch_assoc();
$project_id = $model["project_id"];
$file_path = $model["file_path"];
$active_model_id = $model["active_model_id"];

// Debug the values to see what's happening
error_log("Model ID to delete: " . $model_id);
error_log("Active model ID from DB: " . $active_model_id);
error_log("File path: " . $file_path);

// Begin transaction
$conn->begin_transaction();

try {
    // If this model is the active model, set active_model_id to NULL
    if ($active_model_id == $model_id) {
        $update_stmt = $conn->prepare("UPDATE projects SET active_model_id = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $project_id);
        $update_stmt->execute();
        error_log("Updated project's active_model_id to NULL");
    }
    
    // Delete the model from the database
    $delete_stmt = $conn->prepare("DELETE FROM models WHERE id = ?");
    $delete_stmt->bind_param("i", $model_id);
    $delete_stmt->execute();
    error_log("Deleted model from database");
    
    // Commit the transaction
    $conn->commit();
    
    $full_file_path = __DIR__ . '/' . $file_path;
    
    // Also try without __DIR__ if the path is already absolute
    $alt_file_path = $file_path;
    
    // Log for debugging
    error_log("Attempting to delete file: " . $full_file_path);
    error_log("Alternative path: " . $alt_file_path);
    
    $file_deleted = false;
    
    // Try the first path
    if (file_exists($full_file_path)) {
        if (unlink($full_file_path)) {
            $file_deleted = true;
        }
    }
    
    // If first path failed, try the alternative path
    if (!$file_deleted && file_exists($alt_file_path)) {
        if (unlink($alt_file_path)) {
            $file_deleted = true;
        }
    }
    
    if ($file_deleted) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => true, "warning" => "Model deleted from database but file could not be deleted. Check file permissions."]);
    }
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    error_log("Exception during model deletion: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?> 