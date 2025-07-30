<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require 'db.php';

use Google\Cloud\Monitoring\V3\MetricServiceClient;
use Google\Cloud\Monitoring\V3\TimeInterval;
use Google\Cloud\Monitoring\V3\Aggregation;
use Google\Protobuf\Timestamp;
use Google\Protobuf\Duration;
use Google\Cloud\Monitoring\V3\ListTimeSeriesRequest\TimeSeriesView;

/**
 * Fetch API usage data for a given API key
 * 
 * @param string $apiKey API key to check
 * @param int $userId User ID to check subscription start date
 * @return int Number of requests made using the API key
 */
function fetchApiUsage($apiKey, $userId = null) {
    try {
        // Validate API key
        if (empty($apiKey)) {
            return 0;
        }
        
        // Initialize Google Cloud client with service account
        $serviceAccountFile = 'keys/google-service-account.json';
        
        // Check if service account file exists
        if (!file_exists($serviceAccountFile)) {
            throw new Exception("Service account file not found.");
        }
        
        // Create the client
        $client = new MetricServiceClient([
            'credentials' => $serviceAccountFile
        ]);
        
        $projectName = $client->projectName('google-cloud-project');
        
        // Build filter based on dashboard's PromQL query
        $filter = "metric.type=\"maps.googleapis.com/service/v2/request_count\" AND resource.type=\"maps.googleapis.com/Api\"";
        
        // Add credential filter if API key is specified
        if (!empty($apiKey)) {
            $filter .= " AND resource.label.credential_id=\"apikey:$apiKey\"";
        }
        
        // Get the start time from the user's first subscription
        $startTimestamp = null;
        if ($userId) {
            global $conn;
            $stmt = $conn->prepare("SELECT MIN(created_at) as first_subscription FROM subscriptions WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (!empty($row['first_subscription'])) {
                    $startTimestamp = strtotime($row['first_subscription']);
                }
            }
        }
        
        // Default to 30 days if no subscription found
        if (!$startTimestamp) {
            $startTimestamp = time() - (30 * 24 * 3600);
        }
        
        // Set up time interval from first subscription to now
        $endTime = new Timestamp();
        $endTime->setSeconds(time());
        
        $startTime = new Timestamp();
        $startTime->setSeconds($startTimestamp);
        
        $interval = new TimeInterval([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
        
        // Setup alignment period as 1 day to match the increase() function behavior
        $alignmentPeriodSeconds = 86400; // 24 hours in seconds
        $alignmentPeriod = new Duration();
        $alignmentPeriod->setSeconds($alignmentPeriodSeconds);
        
        // Setup aggregation to match the PromQL query's sum(increase()) pattern
        $aggregation = new Aggregation([
            'alignment_period' => $alignmentPeriod,
            'per_series_aligner' => Aggregation\Aligner::ALIGN_RATE, // Rate of change (similar to increase())
            'cross_series_reducer' => Aggregation\Reducer::REDUCE_SUM, // Sum across series (like sum())
            'group_by_fields' => ['resource.label.credential_id'], // Group by credential_id
        ]);
        
        // Execute the query
        $response = $client->listTimeSeries(
            $projectName, 
            $filter, 
            $interval, 
            TimeSeriesView::FULL,
            ['aggregation' => $aggregation]
        );
        
        // Process results
        $totalRequests = 0;
        
        foreach ($response->iterateAllElements() as $timeSeries) {
            $points = $timeSeries->getPoints();
            if (!empty($points)) {
                foreach ($points as $point) {
                    // We get rate per second, multiply by alignment period (not the entire timeframe)
                    $totalRequests += $point->getValue()->getDoubleValue() * $alignmentPeriodSeconds;
                }
            }
        }
        
        return round($totalRequests); // Round to nearest integer
        
    } catch (Exception $e) {
        // Log error and return 0
        error_log("Error fetching API usage: " . $e->getMessage());
        return 0;
    }
}

/**
 * Updates the requests_used field for a user based on their API key usage
 * 
 * @param int $userId The user ID to update
 * @return bool Whether the update was successful
 */
function updateUserApiUsage($userId) {
    global $conn;
    
    error_log("Starting updateUserApiUsage for user ID: $userId");
    
    // Check if the user has an API key
    $stmt = $conn->prepare("SELECT google_maps_api_key FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("User $userId not found in database");
        return false; // User not found
    }
    
    $user = $result->fetch_assoc();
    $apiKey = $user['google_maps_api_key'];
    
    if (empty($apiKey)) {
        error_log("No API key found for user $userId");
        return false; // No API key found
    }
    
    error_log("Found API key for user $userId: $apiKey");
    
    // Fetch the API usage count
    $requestCount = fetchApiUsage($apiKey, $userId);
    
    // Make sure the requests_used column exists
    ensureRequestsColumns();
    
    // Update the user's requests_used field
    $updateStmt = $conn->prepare("UPDATE users SET requests_used = ? WHERE id = ?");
    $updateStmt->bind_param("ii", $requestCount, $userId);
    $result = $updateStmt->execute();
    
    if ($result) {
        error_log("Successfully updated requests_used for user $userId to $requestCount");
        
        // Now update subscription status and request limits
        updateSubscriptionStatus($userId);
    } else {
        error_log("Failed to update requests_used for user $userId: " . $conn->error);
    }
    
    return $result;
}

/**
 * Updates subscription status based on API usage and calculates request limits
 *
 * @param int $userId The user ID to update
 * @return bool Whether the update was successful
 */
function updateSubscriptionStatus($userId) {
    global $conn;
    
    error_log("Starting updateSubscriptionStatus for user ID: $userId");
    
    // Get user's current requests_used
    $userStmt = $conn->prepare("SELECT requests_used FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        error_log("User $userId not found in database");
        return false;
    }
    
    $userData = $userResult->fetch_assoc();
    $requestsUsed = (int)$userData['requests_used'];
    $initialRequestsUsed = $requestsUsed;
    
    error_log("Initial requests used for user $userId: $requestsUsed");
    
    // Get all subscriptions for this user in chronological order (oldest first)
    $subStmt = $conn->prepare("
        SELECT s.id, s.plan_id, s.is_active, s.created_at, s.starts_at, s.expires_at, sp.request_limit, sp.plan_name
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ?
        ORDER BY s.created_at ASC
    ");
    
    $subStmt->bind_param("i", $userId);
    $subStmt->execute();
    $subscriptions = $subStmt->get_result();
    
    $totalRequestLimit = 0;
    $activeSubscriptions = [];
    $inactiveSubscriptions = [];
    $subscriptionData = [];
    
    // First pass: Calculate total request limit and collect subscriptions
    while ($sub = $subscriptions->fetch_assoc()) {
        $subscriptionData[] = $sub;
        $totalRequestLimit += (int)$sub['request_limit'];
        
        if ($sub['is_active']) {
            $activeSubscriptions[] = $sub['id'];
        } else {
            $inactiveSubscriptions[] = $sub['id'];
        }
    }
    
    error_log("Total request limit before processing: $totalRequestLimit");
    error_log("Initial active subscriptions: " . implode(", ", $activeSubscriptions));
    error_log("Initial inactive subscriptions: " . implode(", ", $inactiveSubscriptions));
    
    // Calculate total requests limit from inactive subscriptions
    $inactiveLimit = 0;
    foreach ($subscriptionData as $sub) {
        if (!$sub['is_active']) {
            $inactiveLimit += (int)$sub['request_limit'];
        }
    }
    
    // Process only active subscriptions in chronological order
    $remainingRequests = $requestsUsed;
    
    // Only deduct inactive subscriptions' limits from remaining requests once
    $remainingRequests = max(0, $remainingRequests - $inactiveLimit);
    
    foreach ($subscriptionData as $sub) {
        $subId = $sub['id'];
        $requestLimit = (int)$sub['request_limit'];
        
        // Skip subscriptions that are already inactive
        if (!$sub['is_active']) {
            continue;
        }
        
        if ($remainingRequests > $requestLimit) {
            // Mark as inactive since all requests have been used
            $updateSubStmt = $conn->prepare("UPDATE subscriptions SET is_active = 0 WHERE id = ?");
            $updateSubStmt->bind_param("i", $subId);
            $updateSubStmt->execute();
            
            $remainingRequests -= $requestLimit;
            $inactiveLimit += $requestLimit;
            
            error_log("Marking subscription $subId as inactive. Remaining requests: $remainingRequests");
        } else {
            // This subscription can handle remaining requests, keep it active
            error_log("Subscription $subId remains active with $requestLimit limit to handle $remainingRequests remaining requests");
            break;
        }
    }
    
    // Calculate and update requests_total
    $activeLimit = $totalRequestLimit - $inactiveLimit;
    
    // Ensure users always have at least the free tier limit (10 requests) 
    // even when they have no active subscriptions, so they can still purchase new packs
    if ($activeLimit <= 0) {
        $activeLimit = 10; // Default free tier limit
        error_log("User $userId has no active subscriptions, setting requests_total to default free tier: $activeLimit");
    }
    
    error_log("Total request limit: $totalRequestLimit");
    error_log("Inactive subscription limit: $inactiveLimit");
    error_log("Final active limit: $activeLimit");
    
    // Update the user's requests_total
    $updateTotalStmt = $conn->prepare("UPDATE users SET requests_total = ? WHERE id = ?");
    $updateTotalStmt->bind_param("ii", $activeLimit, $userId);
    $result = $updateTotalStmt->execute();
    
    if ($result) {
        error_log("Successfully updated requests_total for user $userId to $activeLimit");
    } else {
        error_log("Failed to update requests_total for user $userId: " . $conn->error);
    }
    
    // Calculate and update requests_used_shown (requests_used minus inactive subscription limits)
    $requestsUsedShown = max(0, $requestsUsed - $inactiveLimit);

    // If user has no active subscriptions, show their actual usage against the free tier
    if ($totalRequestLimit - $inactiveLimit <= 0) {
        $requestsUsedShown = min($requestsUsed, 10); // Cap at free tier limit for display
        error_log("User $userId has no active subscriptions, showing usage against free tier: $requestsUsedShown");
    }

    error_log("Calculated requests_used_shown for user $userId: $requestsUsedShown (requests_used: $requestsUsed, inactive_limit: $inactiveLimit)");
    
    $updateShownStmt = $conn->prepare("UPDATE users SET requests_used_shown = ? WHERE id = ?");
    $updateShownStmt->bind_param("ii", $requestsUsedShown, $userId);
    $resultShown = $updateShownStmt->execute();
    
    if ($resultShown) {
        error_log("Successfully updated requests_used_shown for user $userId to $requestsUsedShown");
    } else {
        error_log("Failed to update requests_used_shown for user $userId: " . $conn->error);
    }
    
    // Update the user's subscription type based on active subscriptions
    $typeStmt = $conn->prepare("
        SELECT sp.plan_name 
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ? AND s.is_active = 1
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    
    $typeStmt->bind_param("i", $userId);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    
    if ($typeResult->num_rows > 0) {
        $planData = $typeResult->fetch_assoc();
        $subscriptionType = $planData['plan_name'];
        
        $updateTypeStmt = $conn->prepare("UPDATE users SET subscription_type = ? WHERE id = ?");
        $updateTypeStmt->bind_param("si", $subscriptionType, $userId);
        $updateTypeStmt->execute();
        
        error_log("Updated subscription type for user $userId to $subscriptionType");
    } else {
        // No active subscriptions, set to Free
        $updateTypeStmt = $conn->prepare("UPDATE users SET subscription_type = 'Free' WHERE id = ?");
        $updateTypeStmt->bind_param("i", $userId);
        $updateTypeStmt->execute();
        
        error_log("User $userId has no active subscriptions, setting subscription type to Free");
    }
    
    return true;
}

/**
 * Ensures that the necessary columns for tracking API requests exist
 */
function ensureRequestsColumns() {
    global $conn;
    
    // Check for requests_used column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'requests_used'");
    if ($result->num_rows === 0) {
        error_log("Adding requests_used column to users table");
        $conn->query("ALTER TABLE users ADD COLUMN requests_used INT DEFAULT 0");
    }
    
    // Check for requests_used_shown column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'requests_used_shown'");
    if ($result->num_rows === 0) {
        error_log("Adding requests_used_shown column to users table");
        $conn->query("ALTER TABLE users ADD COLUMN requests_used_shown INT DEFAULT 0");
    }
    
    // Check for requests_total column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'requests_total'");
    if ($result->num_rows === 0) {
        error_log("Adding requests_total column to users table");
        $conn->query("ALTER TABLE users ADD COLUMN requests_total INT DEFAULT 10");
    }
    
    // Check for google_maps_api_key column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'google_maps_api_key'");
    if ($result->num_rows === 0) {
        error_log("Adding google_maps_api_key column to users table");
        $conn->query("ALTER TABLE users ADD COLUMN google_maps_api_key VARCHAR(255) DEFAULT NULL");
        $conn->query("CREATE INDEX idx_google_maps_api_key ON users(google_maps_api_key)");
    }
}

// This file can be run directly to update all users
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "Starting API usage update using Maps API metric\n";
    
    // Update all users with API keys
    $result = $conn->query("SELECT id FROM users WHERE google_maps_api_key IS NOT NULL");
    $updateCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        if (updateUserApiUsage($row['id'])) {
            $updateCount++;
        }
    }
    
    echo "API usage updated for $updateCount users using Maps API metrics.";
} 