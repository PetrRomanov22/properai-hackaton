<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php';
require 'fetch_api_usage.php';

echo "<pre>";
echo "Starting subscription status update...\n";

// Get all users
$result = $conn->query("SELECT id, name, email, requests_used, requests_total FROM users");
$updateCount = 0;

while ($user = $result->fetch_assoc()) {
    echo "Processing user ID: {$user['id']} ({$user['name']})\n";
    
    // Update subscription status
    if (updateSubscriptionStatus($user['id'])) {
        $updateCount++;
        
        // Get updated user data
        $updatedStmt = $conn->prepare("SELECT requests_used, requests_total FROM users WHERE id = ?");
        $updatedStmt->bind_param("i", $user['id']);
        $updatedStmt->execute();
        $updatedResult = $updatedStmt->get_result();
        $updatedUser = $updatedResult->fetch_assoc();
        
        // Display before/after
        echo "  - Before: {$user['requests_used']}/{$user['requests_total']} requests\n";
        echo "  - After:  {$updatedUser['requests_used']}/{$updatedUser['requests_total']} requests\n";
        echo "  - Done\n";
    } else {
        echo "  - Failed to update\n";
    }
}

echo "\nSubscription status updated for $updateCount users.\n";
echo "</pre>";
?> 