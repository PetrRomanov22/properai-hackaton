<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';
require 'fetch_api_usage.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$name = $_SESSION["user_name"];

// Update API usage data for this user - Add debugging
$debug_result = updateUserApiUsage($user_id);
error_log("API Usage update result for user $user_id: " . ($debug_result ? "Success" : "Failed"));

// Fetch all projects for this user
$stmt = $conn->prepare("SELECT id, name, description, address, country, city, lat, lng, altitude, camera_range, camera_tilt, created_at 
                        FROM projects WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch user details including subscription info and Google Maps API key
$user_stmt = $conn->prepare("SELECT name, email, subscription_type, subscription_expired, requests_used, requests_used_shown, requests_total, google_maps_api_key FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Fetch Google Maps API key
$GOOGLE_MAPS_API_KEY = $user_data['google_maps_api_key'] ?? '';

// Safeguard: Ensure user data has proper default values to prevent button unavailability
if (!isset($user_data['requests_total']) || $user_data['requests_total'] === null || $user_data['requests_total'] < 0) {
    $user_data['requests_total'] = 10; // Default free tier
}
if (!isset($user_data['requests_used_shown']) || $user_data['requests_used_shown'] === null || $user_data['requests_used_shown'] < 0) {
    $user_data['requests_used_shown'] = 0;
}
if (!isset($user_data['requests_used']) || $user_data['requests_used'] === null || $user_data['requests_used'] < 0) {
    $user_data['requests_used'] = 0;
}

// Check if user has active subscriptions
$active_sub_stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM subscriptions WHERE user_id = ? AND is_active = 1");
$active_sub_stmt->bind_param("i", $user_id);
$active_sub_stmt->execute();
$active_sub_result = $active_sub_stmt->get_result();
$active_sub_data = $active_sub_result->fetch_assoc();
$has_active_subscription = $active_sub_data['active_count'] > 0;

// Determine if buttons should be disabled
$api_limit_reached = isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) && $user_data['requests_used_shown'] >= $user_data['requests_total'];
$buttons_disabled = $api_limit_reached && !$has_active_subscription;

// Fetch available subscription plans (only those marked as available)
$plans_stmt = $conn->prepare("SELECT plan_name, price, request_limit, features FROM subscription_plans WHERE is_available = 1");
$plans_stmt->execute();
$plans_result = $plans_stmt->get_result();
$subscription_plans = [];
while ($plan = $plans_result->fetch_assoc()) {
    $subscription_plans[] = $plan;
}

// Fetch subscription history for this user
$history_stmt = $conn->prepare("SELECT s.created_at, sp.plan_name, s.plan_id, s.starts_at, s.expires_at, s.is_active, sp.request_limit
                               FROM subscriptions s
                               LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
                               WHERE s.user_id = ? 
                               ORDER BY s.created_at DESC");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$subscription_history = [];
while ($history = $history_result->fetch_assoc()) {
    $subscription_history[] = $history;
}

// If subscription fields don't exist yet, use default values
$subscription_type = isset($user_data['subscription_type']) ? $user_data['subscription_type'] : 'Free';
$subscription_expired = isset($user_data['subscription_expired']) ? $user_data['subscription_expired'] : null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Personal Account</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Roboto&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="project/project_map.css">
    <style>
        .account-container {
            width: 100%;
            max-width: 100%;
            padding: 0;
            margin: 0 auto;
        }
        
        .project-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            background-color: #fff;
        }
        
        .project-table th, .project-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
        }
        
        .project-table th {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        
        .project-table tr:hover {
            background-color: #f8f9fa;
        }
        
        #projectForm {
            display: none;
            position: fixed;
            top: 50%;
            left: calc(50% + 125px); /* Adjust for sidebar width */
            transform: translate(-50%, -50%);
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 100;
            max-width: 700px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        #projectForm label {
            display: block;
            margin: 8px 0 4px;
            font-family: 'Roboto', sans-serif;
        }

        #projectForm input {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 5px;
            margin-bottom: 10px;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-family: 'Roboto', sans-serif;
        }
        
        #projectForm input:focus {
            border-color: #6a5acd;
            box-shadow: 0 0 5px rgba(106, 90, 205, 0.3);
            outline: none;
        }

        #projectForm button {
            padding: 8px 15px;
            margin-right: 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Montserrat', sans-serif;
        }
        
        #projectForm button[type="submit"] {
            background-color: #6a5acd;
            color: white;
            border: none;
        }
        
        #projectForm button[type="submit"]:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }
        
        #projectForm button[type="button"] {
            background-color: #f0f0f0;
            color: #000;
            border: 1px solid #ddd;
        }
        
        #projectForm button[type="button"]:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .create-btn {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Montserrat', sans-serif;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .create-btn:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }
        
        .create-btn:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            transform: none;
        }
        
        .create-btn:disabled:hover {
            background-color: #cccccc;
            transform: none;
        }
        
        .edit-btn {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .edit-btn:hover {
            background-color: #5a4abf;
        }
        
        .edit-btn:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
        }
        
        .edit-btn:disabled:hover {
            background-color: #cccccc;
        }
        
        .delete-btn {
            background-color: #E81770;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .delete-btn:hover {
            background-color: #d21566;
        }
        
        .delete-btn:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
        }
        
        .delete-btn:disabled:hover {
            background-color: #cccccc;
        }
        
        .manage-btn {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Montserrat', sans-serif;
            margin-left: 10px;
            white-space: nowrap;
        }
        
        .manage-btn:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }
        
        .manage-btn:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            transform: none;
        }
        
        .manage-btn:disabled:hover {
            background-color: #cccccc;
            transform: none;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border-left: 5px solid #28a745;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 300px;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border-left: 5px solid #dc3545;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 300px;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        
        .header {
            padding: 20px;
            background: linear-gradient(135deg, #dbb0cbf6 0%, #9995b6 100%);
            color: white;
            border-right: 1px solid #dee2e6;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 10;
            justify-content: space-between;
        }
        
        .header h1 {
            margin: 0 0 30px 0;
            font-size: 24px;
        }
        
        .header-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }
        
        .header-btn {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Montserrat', sans-serif;
            width: 100%;
            text-align: center;
        }
        
        .header-btn:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }
        
        .header-btn.active {
            background-color: #5a4abf;
            font-weight: bold;
        }
        
        .logout-link {
            color: white;
            text-decoration: none;
            font-weight: 400;
            background-color: #E81770;
            padding: 6px 10px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: inline-block;
            text-align: center;
            font-size: 12px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 50px;
        }
        
        .logout-link:hover {
            background-color: #d21566;
            transform: translateY(-2px);
        }

        /* Account details form styles */
        .account-details {
            display: none;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-top: 20px;
            width: 100%;
            max-width: 800px;
        }

        .account-details h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-family: 'Montserrat', sans-serif;
        }

        .account-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            width: 100%;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Roboto', sans-serif;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #6a5acd;
            box-shadow: 0 0 5px rgba(106, 90, 205, 0.3);
            outline: none;
        }

        .save-btn {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Montserrat', sans-serif;
            margin-top: 10px;
        }

        .save-btn:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }

        /* Container layout for the two views */
        .content-container {
            display: flex;
            flex-direction: column;
            margin-left: 270px;
            width: calc(100% - 270px);
            padding: 20px;
            min-height: 100vh;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }
        
        .project-content {
            width: 100%;
        }
        
        .project-details {
            width: 100%;
        }
        
        /* Project header with create button and requests counter */
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        
        
        .usage-info {
            color: #333;
        }
        
        .usage-info strong {
            color: #6a5acd;
        }
        
        .usage-info .requests-limit {
            color: #E81770;
            font-weight: 700;
        }
        
        /* Manage subscription button */
        .manage-btn {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Montserrat', sans-serif;
            margin-left: 10px;
            white-space: nowrap;
        }
        
        .manage-btn:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }

        /* Subscription Container Styles */
        .subscription-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .subscription-content {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 25px;
            position: relative;
        }

        .close-subscription {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .close-subscription:hover {
            color: #E81770;
        }

        .subscription-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .subscription-header h2 {
            margin-top: 0;
            color: #333;
        }

        .usage-info {
            background-color: #f5f5f5;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            min-width: 500px;
        }

        .usage-progress {
            flex-grow: 2;
            margin: 0 20px;
            height: 15px;
            background-color: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            /*border: 2px solid #999;*/
            min-width: 200px;
        }

        .usage-bar {
            height: 100%;
            background: linear-gradient(90deg, #6a5acd, #9764c7);
            border-radius: 6px;
            transition: width 0.3s ease;
            min-width: 3px;
        }

        .purchase-history {
            margin-bottom: 20px;
        }

        .history-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-header:hover {
            background-color: #f0f0f0;
        }

        .history-content {
            display: none;
            padding: 0 10px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th, .history-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .status-active, .status-completed, .status-successful {
            color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        
        .status-pending {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        
        .status-failed, .status-cancelled, .status-expired {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }

        .subscription-plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .plan-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 350px;
            justify-content: space-between;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .plan-header {
            margin-bottom: 20px;
        }
        
        .plan-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .plan-price {
            font-size: 24px;
            font-weight: 700;
            color: #6a5acd;
            margin-bottom: 15px;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            text-align: left;
            flex-grow: 1;
            min-height: 150px;
        }

        .plan-features li {
            padding: 5px 0;
            position: relative;
            padding-left: 25px;
        }

        .plan-features li:before {
            content: "✓";
            color: #6a5acd;
            position: absolute;
            left: 0;
        }

        .plan-button {
            background-color: #6a5acd;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .plan-button:hover {
            background-color: #5a4abf;
            transform: translateY(-2px);
        }
        
        .plan-footer {
            padding-top: 10px;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="account-container">
        <div class="header">
            <div class="header-top">
                <h1>Welcome, <?= htmlspecialchars($name) ?>!</h1>
                <div class="header-buttons">
                    <button class="header-btn active" id="overview-btn">Overview</button>
                    <button class="header-btn" id="account-btn">Account Details</button>
                </div>
            </div>
            <div class="header-bottom">
                <a href="logout.php" class="logout-link">Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'true'): ?>
        <div class="success-message" id="deleted-notification">
            Project has been successfully deleted.
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated']) && $_GET['updated'] == 'true'): ?>
        <div class="success-message" id="success-notification">
            Your account details have been updated successfully.
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'true'): ?>
        <div class="error-message" id="error-notification">
            There was an error updating your account. Please try again.
        </div>
        <?php endif; ?>

        <div class="content-container">
            <div class="project-content" id="overview-section">
                <div class="project-details">
                    <h2>Your Projects</h2>
                    <div class="project-header">
                        <button class="create-btn" onclick="<?= $buttons_disabled ? '' : 'window.location.href=\'project_create_wizard.php\'' ?>" <?= $buttons_disabled ? 'disabled title="3D Map request limit reached. Please upgrade your subscription to continue creating projects."' : '' ?>>Create New Project</button>
                        <?php if ($buttons_disabled): ?>
                        <div class="disabled-warning" style="background-color: #fff3f5; border-left: 3px solid #E81770; padding: 8px 12px; margin-left: 10px; color: #721c24; font-size: 12px; border-radius: 4px;">
                            <strong>⚠️ Create/Edit Disabled:</strong> API limit reached. Please upgrade your subscription to continue creating and editing projects.
                        </div>
                        <?php endif; ?>
                        <div class="usage-info">
                            <div>
                                <strong>Current usage of 3D map requests:</strong> 
                                <span>Used: <strong class="<?= (isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) && $user_data['requests_used_shown'] >= $user_data['requests_total']) ? 'requests-limit' : '' ?>">
                                    <?= isset($user_data['requests_used_shown']) ? $user_data['requests_used_shown'] : 0 ?>
                                </strong> | Total: <strong>
                                    <?= isset($user_data['requests_total']) ? $user_data['requests_total'] : 10 ?>
                                </strong>
                                <?php if (isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) && $user_data['requests_used_shown'] >= $user_data['requests_total']): ?>
                                <span style="color: #E81770; margin-left: 5px;">(Limit reached)</span>
                                <?php endif; ?>
                                </span>
                            </div>
                            <div class="usage-progress">
                                <div class="usage-bar" style="width: <?= isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) ? min(100, ($user_data['requests_used_shown'] / $user_data['requests_total']) * 100) : 0 ?>%;"></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span><strong><?= isset($user_data['requests_total']) && isset($user_data['requests_used_shown']) ? max(0, $user_data['requests_total'] - $user_data['requests_used_shown']) : 10 ?></strong> remaining</span>
                                <button type="button" class="manage-btn" id="header-manage-subscription" style="font-size: 12px;">Upgrade</button>
                            </div>
                        </div>
                    </div>

                    <table class="project-table">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Address</th>
                            <th>Country</th>
                            <th>City</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row["name"]) ?></td>
                                <td><?= htmlspecialchars($row["description"]) ?></td>
                                <td><?= htmlspecialchars($row["address"]) ?></td>
                                <td><?= htmlspecialchars($row["country"]) ?></td>
                                <td><?= htmlspecialchars($row["city"]) ?></td>
                                <td><?= htmlspecialchars($row["created_at"]) ?></td>
                                <td>
                                    <form action="project/project.php" method="GET" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $row["id"] ?>">
                                        <button type="submit" class="edit-btn" <?= $buttons_disabled ? 'disabled title="3D Map request limit reached. Please upgrade your subscription to continue editing projects."' : '' ?>>Edit</button>
                                    </form>
                                    <form action="delete_project.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this project? This cannot be undone.');">
                                        <input type="hidden" name="project_id" value="<?= $row["id"] ?>">
                                        <button type="submit" class="delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>

            <div class="account-details" id="account-section">
                <h2>Account Details</h2>
                
                <?php if (isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) && $user_data['requests_used_shown'] >= $user_data['requests_total']): ?>
                <div class="usage-warning" style="background-color: #fff3f5; border-left: 3px solid #E81770; padding: 12px; margin-bottom: 20px; color: #721c24;">
                    <strong>⚠️ 3D Map Request Limit Reached:</strong> You have used all available 3D Map requests for your current subscription. 
                    Please upgrade your plan below to continue using the API services.
                </div>
                <?php endif; ?>
                
                <form class="account-form" action="update_account.php" method="POST">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user_data['name'] ?? $name) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_type">Subscription Type:</label>
                        <div style="display: flex; align-items: center;">
                            <input type="text" id="subscription_type" name="subscription_type" value="<?= htmlspecialchars($subscription_type) ?>" readonly style="background-color: #f0f0f0; color: #666; width: 70%;">
                            <button type="button" class="manage-btn" id="manage-subscription-btn">Manage</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subscription_expired">Subscription Expires:</label>
                        <input type="text" id="subscription_expired" name="subscription_expired" 
                               value="<?= htmlspecialchars($subscription_expired ?? 'N/A') ?>" readonly style="background-color: #f0f0f0; color: #666;">
                    </div>
                    
                    <button type="submit" class="save-btn">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="subscription-container" id="subscription-modal">
        <div class="subscription-content">
            <span class="close-subscription">&times;</span>
            
            <div class="subscription-header">
                <h2>Subscription Management</h2>
                <p>Manage your subscription plan and view usage details</p>
            </div>
            
            <div class="usage-info">
                <div>
                    <strong>Current usage of 3D map requests:</strong> 
                    <span>Used: <?= isset($user_data['requests_used_shown']) ? $user_data['requests_used_shown'] : 0 ?> | Total: <?= isset($user_data['requests_total']) ? $user_data['requests_total'] : 10 ?></span>
                </div>
                <div class="usage-progress">
                    <div class="usage-bar" style="width: <?= isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) ? min(100, ($user_data['requests_used_shown'] / $user_data['requests_total']) * 100) : 0 ?>%;"></div>
                </div>
                <div>
                    <strong><?= isset($user_data['requests_total']) && isset($user_data['requests_used_shown']) ? max(0, $user_data['requests_total'] - $user_data['requests_used_shown']) : 10 ?></strong> remaining
                </div>
            </div>
            
            <?php if (isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) && $user_data['requests_used_shown'] >= $user_data['requests_total']): ?>
            <div class="usage-warning" style="background-color: #fff3f5; border-left: 3px solid #E81770; padding: 12px; margin-bottom: 20px; color: #721c24;">
                <strong>⚠️ 3D Map Request Limit Reached:</strong> You have used all available 3D Map requests for your current subscription. 
                Please upgrade your plan to continue using the API services.
            </div>
            <?php endif; ?>
            
            <div class="purchase-history">
                <div class="history-header" id="history-toggle">
                    <h3>Purchase History</h3>
                    <span>▼</span>
                </div>
                <div class="history-content" id="history-content">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Start Date</th>
                                <th>Plan</th>
                                <th>Request Amount</th>
                                <th>Expires</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscription_history)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No subscription history available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscription_history as $history): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($history['starts_at'])) ?></td>
                                        <td><?= htmlspecialchars($history['plan_name']) ?></td>
                                        <td><?= $history['request_limit'] > 0 ? number_format($history['request_limit']) : 'Unlimited' ?></td>
                                        <td><?= $history['expires_at'] && $history['expires_at'] != '0000-00-00 00:00:00' ? date('M d, Y', strtotime($history['expires_at'])) : 'Never' ?></td>
                                        <td><span class="status-<?= $history['is_active'] ? 'active' : 'expired' ?>"><?= $history['is_active'] ? 'Active' : 'Used' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <h3>Available Packs</h3>
            <div class="subscription-plans">
                <?php if (empty($subscription_plans)): ?>
                <!-- Fallback plans if none are found in database -->
                <div class="plan-card">
                    <div class="plan-header">
                        <div class="plan-name">Free</div>
                        <div class="plan-price">$0</div>
                    </div>
                    <ul class="plan-features">
                        <li>10 3D Map requests per month</li>
                        <li>Standard support</li>
                        <li>Limited features</li>
                    </ul>
                    <div class="plan-footer">
                        <a href="subscription.php?plan=free" class="plan-button">Buy Pack</a>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($subscription_plans as $plan): ?>
                        <?php 
                            $features = json_decode($plan['features'], true);
                            $buttonText = "Buy Pack";
                            $price = floatval($plan['price']) == 0 ? '$0' : '$' . number_format($plan['price'], 2);
                        ?>
                        <div class="plan-card">
                            <div class="plan-header">
                                <div class="plan-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
                                <div class="plan-price"><?= $price ?></div>
                            </div>
                            <ul class="plan-features">
                                <?php if ($plan['request_limit'] > 0): ?>
                                    <li>3D Map requests: <?= number_format($plan['request_limit']) ?></li>
                                <?php else: ?>
                                    <li>Unlimited 3D Map requests</li>
                                <?php endif; ?>
                                
                                <?php 
                                    // Check if features array is valid and has actual content
                                    $has_valid_features = false;
                                    if (is_array($features)) {
                                        foreach ($features as $key => $feature) {
                                            if (!empty($feature) || (!is_numeric($key) && !empty($key))) {
                                                $has_valid_features = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // If no valid features, create default ones based on plan name
                                    if (!$has_valid_features) {
                                        $default_features = [];
                                        
                                        // Set default features based on plan name
                                        $plan_name_lower = strtolower($plan['plan_name']);
                                        
                                        // Use a single default feature when no features are found
                                        $default_features = ['Full access to API'];
                                        
                                        // Display the default features
                                        foreach ($default_features as $feature) {
                                            echo "<li>" . htmlspecialchars($feature) . "</li>";
                                        }
                                    } else {
                                        // Display the features from the database
                                        foreach ($features as $key => $feature) {
                                            if (is_numeric($key) && empty($feature)) {
                                                continue;
                                            } elseif (is_numeric($key) && !empty($feature)) {
                                                // This is a regular feature with numeric index
                                                echo "<li>" . htmlspecialchars($feature) . "</li>";
                                            } else {
                                                // This is a key-value feature
                                                echo "<li>" . htmlspecialchars($key) . ": " . htmlspecialchars($feature) . "</li>";
                                            }
                                        }
                                    }
                                ?>
                            </ul>
                            <div class="plan-footer">
                                <a href="subscription.php?plan=<?= urlencode(strtolower($plan['plan_name'])) ?>" class="plan-button"><?= $buttonText ?></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="projectForm" style="display: none;">
        <form action="create_project.php" method="POST">
            <h3>Create New Project</h3>
            <label>Project Name: <input name="name" required></label>
            <label>Description: <input name="description"></label>
            <label>Address: <input name="address" id="project-address" required></label>
            <div id="project-gmap" style="width:100%;height:220px;margin-bottom:10px;"></div>
            <label>Country: <input name="country"></label>
            <label>City: <input name="city"></label>
            <label>lat: <input name="lat" type="number" step="any"></label>
            <label>lng: <input name="lng" type="number" step="any"></label>
            <input type="hidden" name="altitude" value="50">
            <input type="hidden" name="camera_range" value="250">
            <input type="hidden" name="camera_tilt" value="45">
            <button type="submit">Save Project</button>
            <button type="button" onclick="document.getElementById('projectForm').style.display='none'">Cancel</button>
        </form>
    </div>

    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($GOOGLE_MAPS_API_KEY); ?>&libraries=places&language=en&region=US"></script>
    <script>
        // Check if we need to show the account section based on URL params
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('updated') || urlParams.has('error')) {
            document.getElementById('overview-section').style.display = 'none';
            document.getElementById('account-section').style.display = 'block';
            document.getElementById('account-btn').classList.add('active');
            document.getElementById('overview-btn').classList.remove('active');
        }
        
        // Function to toggle account section
        function showAccountSection() {
            document.getElementById('overview-section').style.display = 'none';
            document.getElementById('account-section').style.display = 'block';
            document.getElementById('account-btn').classList.add('active');
            document.getElementById('overview-btn').classList.remove('active');
        }
        
        function showOverviewSection() {
            document.getElementById('overview-section').style.display = 'block';
            document.getElementById('account-section').style.display = 'none';
            document.getElementById('overview-btn').classList.add('active');
            document.getElementById('account-btn').classList.remove('active');
        }
        
        // Toggle between Overview and Account Details sections
        document.getElementById('overview-btn').addEventListener('click', showOverviewSection);
        document.getElementById('account-btn').addEventListener('click', showAccountSection);
        
        // Manage subscription button click handler
        document.getElementById('manage-subscription-btn').addEventListener('click', function() {
            document.getElementById('subscription-modal').style.display = 'flex';
        });
        
        // Header upgrade button click handler
        document.getElementById('header-manage-subscription').addEventListener('click', function() {
            document.getElementById('subscription-modal').style.display = 'flex';
        });
        
        // Close subscription modal when clicking the X
        document.querySelector('.close-subscription').addEventListener('click', function() {
            document.getElementById('subscription-modal').style.display = 'none';
        });
        
        // Close subscription modal when clicking outside the content
        document.getElementById('subscription-modal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.style.display = 'none';
            }
        });
        
        // Toggle purchase history
        document.getElementById('history-toggle').addEventListener('click', function() {
            const historyContent = document.getElementById('history-content');
            const arrowSpan = this.querySelector('span');
            
            if (historyContent.style.display === 'block') {
                historyContent.style.display = 'none';
                arrowSpan.textContent = '▼';
            } else {
                historyContent.style.display = 'block';
                arrowSpan.textContent = '▲';
            }
        });
        
        // Function to auto-hide notifications
        function setupAutoHideNotification(elementId) {
            var notification = document.getElementById(elementId);
            if (notification) {
                // Fade in
                setTimeout(function() {
                    notification.style.opacity = '1';
                }, 100);
                
                // Fade out after delay
                setTimeout(function() {
                    notification.style.opacity = '0';
                    
                    // Remove from DOM after transition completes
                    setTimeout(function() {
                        notification.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        }
        
        // Setup all notifications
        setupAutoHideNotification('success-notification');
        setupAutoHideNotification('error-notification');
        setupAutoHideNotification('deleted-notification');

        // Check if limit is reached and show account section if needed
        <?php if (isset($user_data['requests_used_shown']) && isset($user_data['requests_total']) && $user_data['requests_used_shown'] >= $user_data['requests_total']): ?>
        showAccountSection();
        <?php endif; ?>

        // Google Maps for Create Project Form
        let projectMap, projectMarker, projectGeocoder, projectAutocomplete;
        const defaultProjectLocation = { lat: 41.3851, lng: 2.1734 };
        function initProjectMap() {
            const mapDiv = document.getElementById('project-gmap');
            if (!mapDiv) return;
            projectMap = new google.maps.Map(mapDiv, {
                center: defaultProjectLocation,
                zoom: 14
            });
            projectGeocoder = new google.maps.Geocoder();
            // Draggable marker
            projectMarker = new google.maps.Marker({
                position: defaultProjectLocation,
                map: projectMap,
                draggable: true
            });
            // Update lat/lng fields on drag
            projectMarker.addListener('dragend', function() {
                const pos = projectMarker.getPosition();
                updateProjectPosition(pos);
                reverseProjectGeocode(pos);
            });
            // On map click, move marker
            projectMap.addListener('click', function(event) {
                projectMarker.setPosition(event.latLng);
                updateProjectPosition(event.latLng);
                reverseProjectGeocode(event.latLng);
            });
            // Autocomplete for address
            const input = document.getElementById('project-address');
            if (input) {
                projectAutocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
                projectAutocomplete.bindTo('bounds', projectMap);
                projectAutocomplete.addListener('place_changed', function() {
                    const place = projectAutocomplete.getPlace();
                    if (!place.geometry) return;
                    if (place.geometry.viewport) {
                        projectMap.fitBounds(place.geometry.viewport);
                    } else {
                        projectMap.setCenter(place.geometry.location);
                        projectMap.setZoom(17);
                    }
                    projectMarker.setPosition(place.geometry.location);
                    updateProjectPosition(place.geometry.location);
                    fillCityCountry(place);
                });
            }
        }
        function updateProjectPosition(latlng) {
            document.querySelector('input[name="lat"]').value = latlng.lat();
            document.querySelector('input[name="lng"]').value = latlng.lng();
        }
        function reverseProjectGeocode(latlng) {
            projectGeocoder.geocode({ 'location': latlng }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    document.getElementById('project-address').value = results[0].formatted_address;
                    fillCityCountry(results[0]);
                }
            });
        }
        // Helper to extract city and country from geocode result
        function fillCityCountry(geoResult) {
            let city = '', country = '';
            if (geoResult.address_components) {
                geoResult.address_components.forEach(function(component) {
                    if (component.types.includes('locality')) {
                        city = component.long_name;
                    }
                    if (component.types.includes('country')) {
                        country = component.long_name;
                    }
                });
            }
            if (city) document.querySelector('input[name="city"]').value = city;
            if (country) document.querySelector('input[name="country"]').value = country;
        }
        // Only initialize when form is shown
        document.querySelector('.create-btn').addEventListener('click', function() {
            // Check if button is disabled
            if (this.disabled) {
                return false;
            }
            setTimeout(initProjectMap, 200); // Wait for form to display
        });
        
        // Prevent disabled create and edit buttons from triggering actions
        document.addEventListener('click', function(event) {
            if (event.target.disabled && (event.target.classList.contains('create-btn') || event.target.classList.contains('edit-btn'))) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }
        });
    </script>
</body>
</html>
