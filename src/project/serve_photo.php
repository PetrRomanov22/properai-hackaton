<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Check if user is authenticated
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    exit("Access denied");
}

$user_id = $_SESSION["user_id"];
$project_id = $_GET["project_id"] ?? 0;
$photo_name = $_GET["photo"] ?? '';

// Validate parameters
if (!$project_id || !$photo_name) {
    http_response_code(400);
    exit("Invalid parameters");
}

// Sanitize photo name to prevent directory traversal attacks
$photo_name = basename($photo_name);
if (empty($photo_name)) {
    http_response_code(400);
    exit("Invalid photo name");
}

// Verify user owns the project
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    exit("Access denied");
}

// Build photo path
$photo_path = "users/{$user_id}/{$project_id}/images/{$photo_name}";

// Check if file exists and is a valid image
if (!file_exists($photo_path)) {
    http_response_code(404);
    exit("Photo not found");
}

// Verify it's an image file
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$file_extension = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(403);
    exit("Invalid file type");
}

// Get file info
$file_info = getimagesize($photo_path);
if ($file_info === false) {
    http_response_code(403);
    exit("Invalid image file");
}

// Set appropriate headers
$mime_type = $file_info['mime'];
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($photo_path));

// Optional: Set cache headers for better performance
header('Cache-Control: private, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($photo_path)) . ' GMT');

// Output the image
readfile($photo_path);
exit;
?> 