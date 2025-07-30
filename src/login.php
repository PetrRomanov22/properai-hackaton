<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];
    $stmt = $conn->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $name, $password_hash);
        $stmt->fetch();
        if (password_verify($password, $password_hash)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["user_name"] = $name;
            header("Location: account.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ProperAI</title>
    
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

        .login-form {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            padding: 15px;
            background: linear-gradient(135deg, #dbb0cbf6 0%, #9995b6 100%);
            color: white;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 20px -30px;
            text-align: center;
        }

        .logo-container {
            text-align: center;
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

        .service-info {
            text-align: center;
            margin-bottom: 25px;
            color: #555;
            line-height: 1.5;
        }

        .service-info a {
            display: inline;
            margin-top: 0;
            font-weight: 500;
        }

        input[type="email"], 
        input[type="password"] {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 12px;
            margin-top: 5px;
            margin-bottom: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-family: 'Roboto', sans-serif;
            box-sizing: border-box;
        }

        input[type="email"]:focus, 
        input[type="password"]:focus {
            border-color: #9995b6;
            box-shadow: 0 0 0 3px rgba(153, 149, 182, 0.2);
            outline: none;
        }

        button {
            background-color: #9995b6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            width: 100%;
        }

        button:hover {
            background-color: #86819e;
            transform: translateY(-2px);
        }

        a {
            color: #9995b6;
            text-decoration: none;
            transition: color 0.3s;
            display: block;
            text-align: center;
            margin-top: 15px;
        }

        a:hover {
            color: #7a769c;
            text-decoration: underline;
        }

        label {
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            color: #444;
        }

        .error-message {
            color: #E81770;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
        }

        .google-btn {
            background-color: #4285f4;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s, transform 0.2s;
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
            margin: 0 0 15px 0;
        }

        .google-btn:hover {
            background-color: #3367d6;
            transform: translateY(-2px);
            text-decoration: none;
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            color: #666;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #ddd;
            z-index: 1;
        }

        .divider span {
            background-color: #f8f9fa;
            padding: 0 15px;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <div class="login-header">
                <div class="logo-container">
                    <a href="default.php">
                        <img src="images/logo.png" alt="ProperAI Logo" class="logo">
                    </a>
                </div>
            </div>
            
            <div class="service-info">
                <p>Welcome to properai - advanced property visualization platform. Showcase your property with real world 3d map.</p>
                <p>Learn more about our services at <a href="https://properai.info/" target="_blank">properai.info</a></p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required>
                
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
                
                <button type="submit">Login</button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <a href="google_login.php" class="google-btn">
                <svg style="margin-right: 8px;" width="18" height="18" viewBox="0 0 24 24">
                    <path fill="white" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="white" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="white" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="white" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Login with Google
            </a>
            
            <a href="register.php">Don't have an account? Register</a>
        </div>
    </div>
</body>
</html>