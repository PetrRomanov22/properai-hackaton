<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit("Unauthorized");
}

$user_id = $_SESSION["user_id"];
$model_id = $_GET["model_id"] ?? 0;

if ($model_id == 0) {
    http_response_code(400);
    exit("Invalid model ID");
}

// Validate model belongs to user through project ownership
$stmt = $conn->prepare("
    SELECT m.file_path, m.name, p.user_id
    FROM models m 
    JOIN projects p ON m.project_id = p.id 
    WHERE m.id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $model_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$model = $result->fetch_assoc();
if (!$model) {
    http_response_code(403);
    exit("Access denied");
}

$file_path = $model['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    exit("Model file not found");
}

// Get file info
$file_size = filesize($file_path);
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// Set appropriate MIME type based on file extension
$mime_types = [
    'gltf' => 'model/gltf+json',
    'glb' => 'model/gltf-binary',
    'obj' => 'model/obj',
    'dae' => 'model/vnd.collada+xml',
    'fbx' => 'application/octet-stream',
    '3ds' => 'application/octet-stream',
    'ply' => 'application/octet-stream',
    'stl' => 'application/octet-stream'
];

$content_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Set headers
header("Content-Type: $content_type");
header("Content-Length: $file_size");
header("Content-Disposition: inline; filename=\"" . basename($file_path) . "\"");
header("Accept-Ranges: bytes");

// Add CORS headers for browser compatibility
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle range requests for large model files
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $ranges = explode('=', $range);
    $offsets = explode('-', $ranges[1]);
    $offset_start = $offsets[0];
    $offset_end = ($offsets[1] == '') ? $file_size - 1 : $offsets[1];
    
    $new_length = $offset_end - $offset_start + 1;
    
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $offset_start-$offset_end/$file_size");
    header("Content-Length: $new_length");
    
    $file = fopen($file_path, 'r');
    fseek($file, $offset_start);
    $buffer = 1024 * 8;
    while (!feof($file) && ($p = ftell($file)) <= $offset_end) {
        if ($p + $buffer > $offset_end) {
            $buffer = $offset_end - $p + 1;
        }
        set_time_limit(0);
        echo fread($file, $buffer);
        flush();
    }
    fclose($file);
} else {
    // Serve entire file
    readfile($file_path);
}
?> 