<?php
// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? 'your-google-client-id');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'your-google-client-secret');
define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? 'https://properai.pro/google_callback.php');

// Google reCAPTCHA Configuration
define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? 'your-recaptcha-site-key');
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? 'your-recaptcha-secret-key');

// Email Configuration
define('EMAIL_FROM_ADDRESS', $_ENV['EMAIL_FROM_ADDRESS'] ?? 'info@properai.pro');
define('EMAIL_FROM_NAME', $_ENV['EMAIL_FROM_NAME'] ?? 'properai team');
define('EMAIL_SUPPORT_ADDRESS', $_ENV['EMAIL_SUPPORT_ADDRESS'] ?? 'info@properai.pro');
?> 