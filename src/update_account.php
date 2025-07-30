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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $subscription_type = $_POST["subscription_type"];
    $subscription_expired = $_POST["subscription_expired"] ? $_POST["subscription_expired"] : null;
    
    // Check if subscription_type and subscription_expired columns exist
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_type'");
    $subscription_type_exists = $result->num_rows > 0;
    
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_expired'");
    $subscription_expired_exists = $result->num_rows > 0;
    
    // Add columns if they don't exist
    if (!$subscription_type_exists) {
        $conn->query("ALTER TABLE users ADD COLUMN subscription_type VARCHAR(50) DEFAULT 'Free'");
    }
    
    if (!$subscription_expired_exists) {
        $conn->query("ALTER TABLE users ADD COLUMN subscription_expired DATE DEFAULT NULL");
    }
    
    // Update user information
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, subscription_type = ?, subscription_expired = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $subscription_type, $subscription_expired, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION["user_name"] = $name; // Update session with new name
        header("Location: account.php?updated=true");
        exit;
    } else {
        header("Location: account.php?error=true");
        exit;
    }
}
?> 