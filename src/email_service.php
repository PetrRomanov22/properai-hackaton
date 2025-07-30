<?php
// Email Service for ProperAI
require_once 'config.php';
require_once 'email_config.php';

// Include PHPMailer files (manual installation)
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';
require_once 'phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send welcome email to newly registered user
 * @param string $userEmail User's email address
 * @param string $userName User's name
 * @param int $userId User's ID
 * @return array Result with success status and message
 */
function sendWelcomeEmail($userEmail, $userName, $userId) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; // Hostinger SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_FROM_ADDRESS; // Your email address
        $mail->Password   = getEmailPassword(); // Your email password (you'll need to set this)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($userEmail, $userName);
        $mail->addReplyTo(EMAIL_SUPPORT_ADDRESS, 'ProperAI Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ProperAI - Get Started with Your Property Visualization';
        $mail->Body    = getWelcomeEmailTemplate($userName, $userId);
        $mail->AltBody = getWelcomeEmailTextVersion($userName, $userId);
        
        $mail->send();
        
        // Log successful email
        error_log("Welcome email sent successfully to: " . $userEmail);
        
        return [
            'success' => true,
            'message' => 'Welcome email sent successfully'
        ];
        
    } catch (Exception $e) {
        // Log error
        error_log("Welcome email failed for " . $userEmail . ": " . $mail->ErrorInfo);
        
        return [
            'success' => false,
            'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Get email password from environment or config
 * You'll need to set this up with your actual email password
 */
function getEmailPassword() {
    // Option 1: Environment variable (recommended for security)
    if (isset($_ENV['EMAIL_PASSWORD'])) {
        return $_ENV['EMAIL_PASSWORD'];
    }
    
    // Option 2: Use the EMAIL_PASSWORD constant from email_config.php
    if (defined('EMAIL_PASSWORD')) {
        return EMAIL_PASSWORD;
    }
    
    // Fallback - this should not happen if email_config.php is properly set up
    throw new Exception('Email password not configured. Please set EMAIL_PASSWORD in email_config.php');
}

/**
 * Generate HTML welcome email template
 */
function getWelcomeEmailTemplate($userName, $userId) {
    $loginUrl = 'https://properai.pro/login.php';
    $supportEmail = EMAIL_SUPPORT_ADDRESS;
    
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Welcome to properai</title>
        <style>
            body { font-family: 'Roboto', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 0; }
            .header { background: linear-gradient(135deg, #dbb0cbf6 0%, #9995b6 100%); color: white; padding: 30px; text-align: center; }
            .logo { max-width: 150px; height: auto; margin-bottom: 10px; }
            .content { padding: 30px; }
            .button { display: inline-block; background-color: #9995b6; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .button:hover { background-color: #86819e; }
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #666; border-top: 1px solid #ddd; }
            .highlight { background-color: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #9995b6; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to properai!</h1>
                <p>Advanced Property Visualization Platform</p>
            </div>
            
            <div class='content'>
                <h2>Hello " . htmlspecialchars($userName) . "!</h2>
                
                <p>Thank you for joining properai! We're excited to help you showcase your properties with cutting-edge 3D visualization technology.</p>
                
                <div class='highlight'>
                    <h3>🎉 Your account is ready!</h3>
                    <p>You've been automatically enrolled in our <strong>Free Plan</strong> which includes:</p>
                    <ul>
                        <li>Free access to 10 3D map requests for 6 months</li>
                        <li>Basic property visualization features</li>
                        <li>Photo and Video capturing</li>
                        <li>Customer support</li>
                    </ul>
                </div>
                
                <h3>What's Next?</h3>
                <ol>
                    <li><strong>Log in to your account</strong> - Start exploring your dashboard</li>
                    <li><strong>Upload your first property</strong> - Begin creating stunning visualizations</li>
                    <li><strong>Explore features</strong> - Discover all the tools available to you</li>
                </ol>
                
                <div style='text-align: center;'>
                    <a href='" . $loginUrl . "' class='button'>Access Your Account</a>
                </div>
                
                <h3>Need Help?</h3>
                <p>Our team is here to support you:</p>
                <ul>
                    <li>📧 Email us at <a href='mailto:" . $supportEmail . "'>" . $supportEmail . "</a></li>
                    <li>🌐 Visit our website: <a href='https://properai.pro/'>properai.pro</a></li>
                    <li>📚 Check out our getting started guide (coming soon!)</li>
                </ul>
                
                <p>We're thrilled to have you as part of the ProperAI community!</p>
                
                <p>Best regards,<br>
                <strong>The properai team</strong></p>
            </div>
            
            <div class='footer'>
                <p>ProperAI - Advanced Property Visualization Platform</p>
                <p>This email was sent to " . htmlspecialchars($userEmail) . "</p>
                <p>If you have any questions, please contact us at " . $supportEmail . "</p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Generate plain text version of welcome email
 */
function getWelcomeEmailTextVersion($userName, $userId) {
    $loginUrl = 'https://properai.pro/login.php';
    $supportEmail = EMAIL_SUPPORT_ADDRESS;
    
    return "
Welcome to properai!

Hello " . $userName . "!

Thank you for joining properai! We're excited to help you showcase your properties with cutting-edge 3D visualization technology.

YOUR ACCOUNT IS READY!
You've been automatically enrolled in our Free Plan which includes:
- Free access to 10 3D map requests for 6 months
- Basic property visualization features  
- Photo and Video capturing
- Customer support

WHAT'S NEXT?
1. Log in to your account - Start exploring your dashboard
2. Upload your first property - Begin creating stunning visualizations
3. Explore features - Discover all the tools available to you

Access your account: " . $loginUrl . "

NEED HELP?
Our team is here to support you:
- Email us at " . $supportEmail . "
- Visit our website: https://properai.pro/

We're thrilled to have you as part of the properai community!

Best regards,
The ProperAI Team

---
properai - Advanced Property Visualization Platform
This email was sent to " . $userEmail . "
If you have any questions, please contact us at " . $supportEmail . "
";
}
?> 