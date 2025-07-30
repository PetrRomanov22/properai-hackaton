<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "User not authenticated"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$project_id = $_POST["project_id"] ?? 0;

// Validate project belongs to user
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Project not found or access denied"]);
    exit;
}

// Handle video upload
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["video"])) {
    $uploadedFile = $_FILES["video"];
    
    // Check for upload errors
    if ($uploadedFile["error"] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Upload error: " . $uploadedFile["error"]]);
        exit;
    }
    
    // Validate file type
    $allowedTypes = ["video/webm", "video/mp4"];
    $fileType = $uploadedFile["type"];
    
    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid file type. Only WebM and MP4 files are allowed."]);
        exit;
    }
    
    // Create directory structure
    $baseDir = "users/$user_id/$project_id/videos";
    if (!file_exists($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to create directory"]);
            exit;
        }
    }
    
    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $originalName = pathinfo($uploadedFile["name"], PATHINFO_FILENAME);
    $extension = pathinfo($uploadedFile["name"], PATHINFO_EXTENSION);
    $filename = "{$originalName}_{$timestamp}.{$extension}";
    $filePath = "$baseDir/$filename";
    
    // Move uploaded file
    if (move_uploaded_file($uploadedFile["tmp_name"], $filePath)) {
        // Save video record to database (optional - you might want to track videos)
        $stmt = $conn->prepare("INSERT INTO project_videos (project_id, user_id, filename, file_path, file_size, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $fileSize = filesize($filePath);
        $stmt->bind_param("iissi", $project_id, $user_id, $filename, $filePath, $fileSize);
        
        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Video uploaded successfully",
                "filename" => $filename,
                "path" => $filePath
            ]);
        } else {
            // File was uploaded but database record failed - that's still a success
            echo json_encode([
                "success" => true,
                "message" => "Video uploaded successfully (database record failed)",
                "filename" => $filename,
                "path" => $filePath
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to save video file"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No video file provided"]);
}
?> 