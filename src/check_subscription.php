<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php';
require 'fetch_api_usage.php';

// Check if user ID is provided
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if (!$user_id) {
    echo "Please provide a user_id parameter.";
    exit;
}

// Fetch user details
$userStmt = $conn->prepare("SELECT id, name, email, requests_used, requests_total, subscription_type FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    echo "User not found.";
    exit;
}

$user = $userResult->fetch_assoc();

// Fetch subscriptions
$subsStmt = $conn->prepare("
    SELECT s.id, s.plan_id, sp.plan_name, s.created_at, s.starts_at, s.expires_at, s.is_active, sp.request_limit
    FROM subscriptions s
    JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = ?
    ORDER BY s.created_at ASC
");
$subsStmt->bind_param("i", $user_id);
$subsStmt->execute();
$subsResult = $subsStmt->get_result();

echo "<h1>Subscription Status for User: {$user['name']} (ID: {$user['id']})</h1>";
echo "<h2>User Details</h2>";
echo "<ul>";
echo "<li>Email: {$user['email']}</li>";
echo "<li>Requests Used: {$user['requests_used']}</li>";
echo "<li>Requests Total: {$user['requests_total']}</li>";
echo "<li>Subscription Type: {$user['subscription_type']}</li>";
echo "</ul>";

echo "<h2>Subscriptions</h2>";
if ($subsResult->num_rows === 0) {
    echo "<p>No subscriptions found for this user.</p>";
} else {
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Plan</th><th>Created</th><th>Starts</th><th>Expires</th><th>Status</th><th>Request Limit</th></tr>";
    
    while ($sub = $subsResult->fetch_assoc()) {
        $status = $sub['is_active'] ? 'Active' : 'Used';
        $statusColor = $sub['is_active'] ? 'green' : 'gray';
        
        echo "<tr>";
        echo "<td>{$sub['id']}</td>";
        echo "<td>{$sub['plan_name']}</td>";
        echo "<td>" . date('Y-m-d', strtotime($sub['created_at'])) . "</td>";
        echo "<td>" . date('Y-m-d', strtotime($sub['starts_at'])) . "</td>";
        echo "<td>" . ($sub['expires_at'] && $sub['expires_at'] != '0000-00-00 00:00:00' ? date('Y-m-d', strtotime($sub['expires_at'])) : 'Never') . "</td>";
        echo "<td style='color:{$statusColor}'>{$status}</td>";
        echo "<td>{$sub['request_limit']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<h2>Actions</h2>";
echo "<p><a href='update_subscriptions.php'>Update All Subscriptions</a></p>";
echo "<p><form method='post'><button type='submit' name='update'>Update This User's Subscription</button></form></p>";

// Process update request
if (isset($_POST['update'])) {
    echo "<h3>Update Results:</h3>";
    echo "<pre>";
    
    echo "Before update:\n";
    echo "Requests Used: {$user['requests_used']}\n";
    echo "Requests Total: {$user['requests_total']}\n\n";
    
    echo "Running updateSubscriptionStatus()...\n";
    $result = updateSubscriptionStatus($user_id);
    echo "Result: " . ($result ? "Success" : "Failed") . "\n\n";
    
    // Fetch updated user details
    $updatedStmt = $conn->prepare("SELECT requests_used, requests_total, subscription_type FROM users WHERE id = ?");
    $updatedStmt->bind_param("i", $user_id);
    $updatedStmt->execute();
    $updatedUser = $updatedStmt->get_result()->fetch_assoc();
    
    echo "After update:\n";
    echo "Requests Used: {$updatedUser['requests_used']}\n";
    echo "Requests Total: {$updatedUser['requests_total']}\n";
    echo "Subscription Type: {$updatedUser['subscription_type']}\n";
    
    echo "</pre>";
    
    echo "<p><a href='check_subscription.php?user_id={$user_id}'>Refresh Page</a></p>";
}
?> 