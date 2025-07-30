<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Check if project ID was provided
if (!isset($_POST['project_id']) || empty($_POST['project_id'])) {
    echo "Error: No project specified";
    exit;
}

$project_id = $_POST['project_id'];

// Verify that this project belongs to the current user
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Error: Project not found or you don't have permission to delete it";
    exit;
}

// Delete the project's folder structure
$projectFolder = "project/users/$user_id/$project_id";
if (is_dir($projectFolder)) {
    // Recursive function to delete directory and its contents
    function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    deleteDirectory($projectFolder);
}

// Delete the project from the database
$stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);

if ($stmt->execute()) {
    header("Location: account.php?deleted=true");
    exit;
} else {
    echo "Database error: " . $stmt->error;
}
?> 