<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION["user_id"];
$project_id = $_POST["project_id"] ?? 0;
$filename = $_POST["filename"] ?? '';

if (empty($filename) || $project_id == 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid parameters']));
}

// Validate project belongs to user
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result->fetch_assoc()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied']));
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$file_path = "users/$user_id/$project_id/videos/$filename";

$success = true;
$messages = [];

// Delete from database if table exists and record exists
$check_table = $conn->query("SHOW TABLES LIKE 'project_videos'");
if ($check_table->num_rows > 0) {
    $stmt = $conn->prepare("DELETE FROM project_videos WHERE project_id = ? AND filename = ?");
    $stmt->bind_param("is", $project_id, $filename);
    if (!$stmt->execute()) {
        $success = false;
        $messages[] = "Failed to delete from database: " . $stmt->error;
    } else {
        $messages[] = "Deleted from database";
    }
}

// Delete file from filesystem
if (file_exists($file_path)) {
    if (unlink($file_path)) {
        $messages[] = "Deleted from file system";
    } else {
        $success = false;
        $messages[] = "Failed to delete file from system";
    }
} else {
    $messages[] = "File not found in system";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'message' => implode('. ', $messages)
]);
?> 