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
$project_id = $_GET["project_id"] ?? 0;
$filename = $_GET["filename"] ?? '';
$download = isset($_GET["download"]) && $_GET["download"] == '1';

if (empty($filename) || $project_id == 0) {
    http_response_code(400);
    exit("Invalid parameters");
}

// Validate project belongs to user
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result->fetch_assoc()) {
    http_response_code(403);
    exit("Access denied");
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$file_path = "users/$user_id/$project_id/videos/$filename";

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    exit("File not found");
}

// Get file info
$file_size = filesize($file_path);
$file_type = mime_content_type($file_path);

// Determine if this is a video or image file and set appropriate MIME type
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$is_video = in_array($extension, ['webm', 'mp4', 'mov', 'avi']);
$is_image = in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif']);

if (!$file_type || (!$is_video && !$is_image)) {
    // Set MIME type based on extension
    switch($extension) {
        // Video types
        case 'webm':
            $file_type = 'video/webm';
            break;
        case 'mp4':
            $file_type = 'video/mp4';
            break;
        case 'mov':
            $file_type = 'video/quicktime';
            break;
        case 'avi':
            $file_type = 'video/x-msvideo';
            break;
        // Image types
        case 'png':
            $file_type = 'image/png';
            break;
        case 'jpg':
        case 'jpeg':
            $file_type = 'image/jpeg';
            break;
        case 'webp':
            $file_type = 'image/webp';
            break;
        case 'gif':
            $file_type = 'image/gif';
            break;
        default:
            $file_type = 'application/octet-stream';
            break;
    }
}

// Set common headers for video streaming
header("Content-Type: $file_type");
header("Accept-Ranges: bytes");
header("Cache-Control: public, max-age=3600"); // Cache for 1 hour
header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime($file_path)) . ' GMT');
header("ETag: \"" . md5($file_path . filemtime($file_path)) . "\"");

// Handle conditional requests (304 Not Modified)
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . md5($file_path . filemtime($file_path)) . '"') {
    http_response_code(304);
    exit();
}

// Set download headers if requested
if ($download) {
    header("Content-Disposition: attachment; filename=\"$filename\"");
} else {
    header("Content-Disposition: inline; filename=\"$filename\"");
}

// Handle range requests for video streaming (only for videos, not images)
if ($is_video && isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    
    // Parse range header
    if (!preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
        http_response_code(416); // Range Not Satisfiable
        header("Content-Range: bytes */$file_size");
        exit("Invalid range format");
    }
    
    $offset_start = $matches[1] !== '' ? (int)$matches[1] : 0;
    $offset_end = $matches[2] !== '' ? (int)$matches[2] : $file_size - 1;
    
    // Validate range
    if ($offset_start < 0 || $offset_end >= $file_size || $offset_start > $offset_end) {
        http_response_code(416); // Range Not Satisfiable
        header("Content-Range: bytes */$file_size");
        exit("Range not satisfiable");
    }
    
    $new_length = $offset_end - $offset_start + 1;
    
    // Set partial content headers
    http_response_code(206); // Partial Content
    header("Content-Range: bytes $offset_start-$offset_end/$file_size");
    header("Content-Length: $new_length");
    
    // Open file and seek to start position
    $file = fopen($file_path, 'rb');
    if (!$file) {
        http_response_code(500);
        exit("Unable to open file");
    }
    
    fseek($file, $offset_start);
    
    // Stream the requested range in chunks
    $buffer_size = 8192; // 8KB chunks for smooth streaming
    $bytes_remaining = $new_length;
    
    while ($bytes_remaining > 0 && !feof($file)) {
        $chunk_size = min($buffer_size, $bytes_remaining);
        $data = fread($file, $chunk_size);
        
        if ($data === false) {
            break;
        }
        
        echo $data;
        
        $bytes_remaining -= strlen($data);
        
        // Flush output to prevent memory issues
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
        
        // Prevent timeout
        set_time_limit(0);
    }
    
    fclose($file);
} else {
    // Serve entire file
    header("Content-Length: $file_size");
    
    // Use readfile for full file serving - it's optimized for this
    if (!readfile($file_path)) {
        http_response_code(500);
        exit("Unable to read file");
    }
}
?> 