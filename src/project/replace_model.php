<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Add debugging headers
header('Content-Type: application/json');

// Log all incoming data for debugging
error_log("=== REPLACE MODEL DEBUG START ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("Session data: " . print_r($_SESSION ?? [], true));

session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    error_log("User not logged in");
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$model_id = $_POST["model_id"] ?? 0;
$project_id = $_POST["project_id"] ?? 0;

error_log("User ID: $user_id, Model ID: $model_id, Project ID: $project_id");

// Check if file was uploaded
if (!isset($_FILES["model_file"])) {
    error_log("No file uploaded - FILES array missing model_file");
    echo json_encode(["success" => false, "error" => "No file uploaded"]);
    exit;
}

// Check for upload errors
if ($_FILES["model_file"]["error"] !== UPLOAD_ERR_OK) {
    $error_message = "Upload error: " . $_FILES["model_file"]["error"];
    error_log($error_message);
    echo json_encode(["success" => false, "error" => $error_message]);
    exit;
}

// Validate that the model belongs to the user through project ownership
$stmt = $conn->prepare("
    SELECT m.*, p.user_id 
    FROM models m 
    JOIN projects p ON m.project_id = p.id 
    WHERE m.id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $model_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$old_model = $result->fetch_assoc();

if (!$old_model) {
    error_log("Model not found or access denied - Model ID: $model_id, User ID: $user_id");
    echo json_encode(["success" => false, "error" => "Model not found or access denied"]);
    exit;
}

error_log("Old model found: " . print_r($old_model, true));

// Store the old model data to preserve parameters
$old_file_path = $old_model['file_path'];
$old_model_id = $old_model['id'];

// Create upload directory if it doesn't exist
$upload_dir = "users/$user_id/$project_id/models/";
error_log("Upload directory: $upload_dir");

if (!is_dir($upload_dir)) {
    error_log("Directory doesn't exist, creating: $upload_dir");
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Failed to create directory: $upload_dir");
        echo json_encode(["success" => false, "error" => "Failed to create upload directory"]);
        exit;
    }
    error_log("Directory created successfully");
} else {
    error_log("Directory already exists");
}

// Generate new filename with timestamp
$original_filename = basename($_FILES["model_file"]["name"]);
$file_info = pathinfo($original_filename);
$timestamp = date('Y-m-d_H-i-s');
$filename = $file_info['filename'] . '_' . $timestamp . '.' . $file_info['extension'];
$filepath = $upload_dir . $filename;

// Get file format and size
$format = strtolower($file_info['extension'] ?? '');
$size_mb = $_FILES["model_file"]["size"] / (1024 * 1024); // Convert bytes to MB

// Ensure filename is unique (just in case)
$counter = 1;
$original_filepath = $filepath;
while (file_exists($filepath)) {
    $filename = $file_info['filename'] . '_' . $timestamp . '_' . $counter . '.' . $file_info['extension'];
    $filepath = $upload_dir . $filename;
    $counter++;
}

// Upload the new file
error_log("Attempting to upload file from: " . $_FILES["model_file"]["tmp_name"]);
error_log("To destination: $filepath");
error_log("File size: " . $_FILES["model_file"]["size"] . " bytes");

if (move_uploaded_file($_FILES["model_file"]["tmp_name"], $filepath)) {
    error_log("File upload successful");
    // Step 1: Create new model record with all parameters from old model
    $stmt = $conn->prepare("INSERT INTO models (
        project_id, name, file_path, format, size_mb,
        lat, lng, altitude_show, altitude_fact, 
        tilt, roll, scale, 
        marker_width, marker_length, marker_height, 
        uploaded_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    // Assign values to variables first (required for bind_param references)
    $lat = $old_model['lat'] ?? 0;
    $lng = $old_model['lng'] ?? 0;
    $altitude_show = $old_model['altitude_show'] ?? 0;
    $altitude_fact = $old_model['altitude_fact'] ?? 0;
    $tilt = $old_model['tilt'] ?? 0;
    $roll = $old_model['roll'] ?? 0;
    $scale = $old_model['scale'] ?? 1;
    $marker_width = $old_model['marker_width'] ?? 1;
    $marker_length = $old_model['marker_length'] ?? 1;
    $marker_height = $old_model['marker_height'] ?? 1;
    
    $stmt->bind_param(
        "isssddddddddddd", 
        $project_id, 
        $filename,
        $filepath,
        $format,
        $size_mb,
        $lat,
        $lng, 
        $altitude_show,
        $altitude_fact,
        $tilt,
        $roll,
        $scale,
        $marker_width,
        $marker_length,
        $marker_height
    );
    
    if ($stmt->execute()) {
        $new_model_id = $conn->insert_id;
        error_log("New model created successfully with ID: $new_model_id");
        
        // Step 2: Set the new model as active
        $stmt = $conn->prepare("UPDATE projects SET active_model_id = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $new_model_id, $project_id, $user_id);
        
        if ($stmt->execute()) {
            error_log("Active model updated successfully");
            // Step 3: Delete the old model record
            $stmt = $conn->prepare("DELETE FROM models WHERE id = ?");
            $stmt->bind_param("i", $old_model_id);
            
            if ($stmt->execute()) {
                error_log("Old model deleted successfully");
                
                // Step 4: Delete the old file
                if (file_exists($old_file_path)) {
                    if (unlink($old_file_path)) {
                        error_log("Old file deleted successfully: $old_file_path");
                    } else {
                        error_log("Failed to delete old file: $old_file_path");
                    }
                } else {
                    error_log("Old file not found: $old_file_path");
                }
                
                error_log("Model replacement completed successfully");
                echo json_encode([
                    "success" => true, 
                    "message" => "Model replaced successfully",
                    "new_model_id" => $new_model_id,
                    "new_file_path" => $filepath,
                    "new_filename" => $filename,
                    "old_model_id" => $old_model_id
                ]);
            } else {
                error_log("Failed to delete old model record: " . $conn->error);
                echo json_encode(["success" => false, "error" => "Failed to delete old model record: " . $conn->error]);
            }
        } else {
            error_log("Failed to set new model as active: " . $conn->error);
            echo json_encode(["success" => false, "error" => "Failed to set new model as active: " . $conn->error]);
        }
    } else {
        error_log("Failed to create new model record: " . $conn->error);
        // If database insert fails, remove the uploaded file
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        echo json_encode(["success" => false, "error" => "Failed to create new model record: " . $conn->error]);
    }
} else {
    error_log("Failed to upload file - move_uploaded_file returned false");
    error_log("Source: " . $_FILES["model_file"]["tmp_name"]);
    error_log("Destination: $filepath");
    error_log("Directory writable: " . (is_writable(dirname($filepath)) ? 'Yes' : 'No'));
    echo json_encode(["success" => false, "error" => "Failed to upload file"]);
}

error_log("=== REPLACE MODEL DEBUG END ===");
?> 