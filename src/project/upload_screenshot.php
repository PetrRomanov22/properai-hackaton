<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Increase upload limits for image files
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', 60); // 1 minute
ini_set('max_input_time', 60);

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

// Handle screenshot upload
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["screenshot"])) {
    $uploadedFile = $_FILES["screenshot"];
    
    // Check for upload errors
    if ($uploadedFile["error"] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $errorMsg = $errorMessages[$uploadedFile["error"]] ?? "Unknown upload error: " . $uploadedFile["error"];
        http_response_code(400);
        echo json_encode(["success" => false, "error" => $errorMsg]);
        exit;
    }
    
    // Check file size (additional check) - 50MB limit for screenshots
    if ($uploadedFile["size"] > 50 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(["success" => false, "error" => "File too large (max 50MB)"]);
        exit;
    }
    
    // Validate file type - be more flexible with MIME types
    $allowedTypes = ["image/png", "image/jpeg", "image/jpg", "image/webp", "application/octet-stream"];
    $fileType = $uploadedFile["type"];
    
    // Also check file extension as a backup
    $extension = strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));
    $allowedExtensions = ["png", "jpg", "jpeg", "webp"];
    
    if (!in_array($fileType, $allowedTypes) && !in_array($extension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "error" => "Invalid file type. Only PNG, JPG, JPEG, and WebP files are allowed. Detected: $fileType, Extension: $extension"
        ]);
        exit;
    }
    
    // Create directory structure - save screenshots in the same videos directory
    $baseDir = "users/$user_id/$project_id/videos";
    if (!file_exists($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to create directory: $baseDir"]);
            exit;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($baseDir)) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Directory is not writable: $baseDir"]);
        exit;
    }
    
    // Generate unique filename - use the original extension if valid, otherwise default to png
    $timestamp = date('Y-m-d_H-i-s');
    $originalName = pathinfo($uploadedFile["name"], PATHINFO_FILENAME);
    $extension = in_array($extension, $allowedExtensions) ? $extension : 'png';
    $filename = "{$originalName}_{$timestamp}.{$extension}";
    $filePath = "$baseDir/$filename";
    
    // Ensure filename is unique
    $counter = 1;
    while (file_exists($filePath)) {
        $filename = "{$originalName}_{$timestamp}_{$counter}.{$extension}";
        $filePath = "$baseDir/$filename";
        $counter++;
    }
    
    // Move uploaded file
    if (move_uploaded_file($uploadedFile["tmp_name"], $filePath)) {
        // Verify file was actually written
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "File upload failed - file not found or empty after move"]);
            exit;
        }
        
        $fileSize = filesize($filePath);
        
        // Try to save screenshot record to database (optional - using same table as videos for simplicity)
        try {
            // Check if project_videos table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'project_videos'");
            if ($check_table->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO project_videos (project_id, user_id, filename, file_path, file_size, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iissi", $project_id, $user_id, $filename, $filePath, $fileSize);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        "success" => true,
                        "message" => "Screenshot uploaded successfully",
                        "filename" => $filename,
                        "path" => $filePath,
                        "size" => $fileSize
                    ]);
                } else {
                    // File was uploaded but database record failed - that's still a success
                    echo json_encode([
                        "success" => true,
                        "message" => "Screenshot uploaded successfully (database record failed)",
                        "filename" => $filename,
                        "path" => $filePath,
                        "size" => $fileSize,
                        "db_error" => $conn->error
                    ]);
                }
            } else {
                // No database table, just return success
                echo json_encode([
                    "success" => true,
                    "message" => "Screenshot uploaded successfully",
                    "filename" => $filename,
                    "path" => $filePath,
                    "size" => $fileSize
                ]);
            }
        } catch (Exception $e) {
            // File uploaded successfully but database failed
            echo json_encode([
                "success" => true,
                "message" => "Screenshot uploaded successfully (database error: " . $e->getMessage() . ")",
                "filename" => $filename,
                "path" => $filePath,
                "size" => $fileSize
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false, 
            "error" => "Failed to save screenshot file. Check server permissions and disk space."
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false, 
        "error" => "No screenshot file provided",
        "debug" => [
            "POST" => !empty($_POST),
            "FILES" => !empty($_FILES),
            "screenshot_file" => isset($_FILES["screenshot"])
        ]
    ]);
}
?> 