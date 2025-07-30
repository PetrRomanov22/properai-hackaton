<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

// Check if user is logged in - SECURITY: Only accept session data
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["user_name"])) {
    header("Location: login.php");
    exit;
}

// Get username from session only - SECURITY: Never from URL parameters
$username = $_SESSION["user_name"];
$user_id = $_SESSION["user_id"]; // Store user_id for potential future use
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - properai</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #fff;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 100vh;
        }

        .welcome-card {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .welcome-header {
            padding: 15px;
            background: linear-gradient(135deg, #dbb0cbf6 0%, #9995b6 100%);
            color: white;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }

        .logo-container {
            margin-bottom: 10px;
            margin-top: 10px;
        }

        .logo {
            max-width: 160px;
            height: auto;
        }

        .logo-container a {
            display: inline-block;
            text-decoration: none;
            transition: transform 0.2s;
            margin: 0;
        }

        .logo-container a:hover {
            transform: scale(1.05);
            text-decoration: none;
        }

        .welcome-message {
            margin-bottom: 30px;
            color: #555;
            line-height: 1.6;
        }

        .welcome-message h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .welcome-message p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .username {
            color: #9995b6;
            font-weight: 600;
        }

        .service-info {
            text-align: center;
            margin-bottom: 25px;
            color: #555;
            line-height: 1.5;
            font-size: 14px;
        }

        .service-info a {
            display: inline;
            margin-top: 0;
            font-weight: 500;
            color: #9995b6;
            text-decoration: none;
        }

        .service-info a:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            background-color: #9995b6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            background-color: #86819e;
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        .btn-secondary {
            background-color: transparent;
            color: #9995b6;
            border: 2px solid #9995b6;
        }

        .btn-secondary:hover {
            background-color: #9995b6;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-card">
            <div class="welcome-header">
                <div class="logo-container">
                    <a href="default.php">
                        <img src="images/logo.png" alt="ProperAI Logo" class="logo">
                    </a>
                </div>
            </div>
            
            <div class="welcome-message">
                <h2>Welcome to properai!</h2>
                <p>Hi <span class="username"><?php echo htmlspecialchars($username); ?></span>,</p>
                <p>Thank you for registering with us. We're excited to have you on board!</p>
                <p>Start exploring our features and services tailored just for you.</p>
            </div>
            
            <div class="service-info">
                <p>properai is an advanced property visualization platform. Showcase your property with real world 3D maps.</p>
                <p>Learn more about our services at <a href="https://properai.pro/" target="_blank">properai.pro</a></p>
            </div>
            
            <div class="action-buttons">
                <a href="account.php" class="btn">Go to My Account</a>
                <a href="default.php" class="btn btn-secondary">Explore Platform</a>
            </div>
        </div>
    </div>
</body>
</html>