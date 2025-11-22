<?php
/**
 * Email Helper for Account Creation
 * Uses PHPMailer to send credentials to new users
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer (assuming it's installed via composer or manually)
require_once __DIR__ . '/../vendor/autoload.php'; // If using composer

function getGmailAccount() {
    static $gmail_accounts = [
        [
            'username' => 'dwayne.hrtb@gmail.com',
            'password' => 'fdnkqiuhvmbrmzes',
            'name' => 'NU Lipa Academic System'
        ],
        [
            'username' => 'denz.hrtb@gmail.com',
            'password' => 'gjtonlgekeguxvgc',
            'name' => 'NU Lipa Academic System'
        ],
        [
            'username' => 'deewayne4217@gmail.com',
            'password' => 'nbtiqholkgbgbrmf',
            'name' => 'NU Lipa Academic System'
        ]
    ];
    
    // Round-robin selection (cycles through accounts)
    static $current_index = 0;
    $account = $gmail_accounts[$current_index];
    $current_index = ($current_index + 1) % count($gmail_accounts);
    
    return $account;
}

// Keep these for backwards compatibility
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
/**
 * Generate a random secure password
 */
function generateRandomPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*';
    
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    $all = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    return str_shuffle($password);
}

/**
 * Send account creation email to new user
 * 
 * @param string $recipientEmail User's email address
 * @param string $recipientName User's full name
 * @param string $userId Employee ID or Student ID
 * @param string $temporaryPassword Generated password
 * @param string $role 'faculty', 'admin', or 'student'
 * @return array ['success' => bool, 'message' => string]
 */
function sendAccountCreationEmail($recipientEmail, $recipientName, $userId, $temporaryPassword, $role) {
    $mail = new PHPMailer(true);
    
   try {
    //  GET ROTATING GMAIL ACCOUNT
    $gmail = getGmailAccount();
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail['username'];
    $mail->Password   = $gmail['password'];
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    
    // Recipients
    $mail->setFrom($gmail['username'], $gmail['name']);
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->addReplyTo($gmail['username'], $gmail['name']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your NU Lipa Academic System Account';
        
        // Determine role display and login URL
        $roleDisplay = ucfirst($role);
        $loginUrl = 'http://localhost/automation_system/auth/login.php'; // UPDATE THIS WITH YOUR ACTUAL URL
        
        // Email body with NU Blue and Gold branding
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #1a1a1a;
                    background-color: #f5f5f5;
                }
                .email-wrapper {
                    max-width: 650px;
                    margin: 40px auto;
                    background-color: #ffffff;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .header {
                    background: linear-gradient(135deg, #003082 0%, #001a4d 100%);
                    padding: 40px 30px;
                    text-align: center;
                    border-bottom: 5px solid #D4AF37;
                }
                .header h1 {
                    color: #ffffff;
                    font-size: 28px;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                }
                .header p {
                    color: #D4AF37;
                    font-size: 14px;
                    font-weight: 500;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                }
                .content {
                    padding: 40px 35px;
                }
                .greeting {
                    font-size: 20px;
                    color: #003082;
                    margin-bottom: 20px;
                    font-weight: 600;
                }
                .intro-text {
                    color: #4a4a4a;
                    margin-bottom: 30px;
                    font-size: 15px;
                }
                .credentials-section {
                    background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%);
                    border: 2px solid #D4AF37;
                    border-radius: 8px;
                    padding: 30px;
                    margin: 30px 0;
                }
                .credentials-title {
                    color: #003082;
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    text-align: center;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .credential-item {
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #e0e0e0;
                }
                .credential-item:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                    padding-bottom: 0;
                }
                .credential-label {
                    font-weight: 600;
                    color: #003082;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                }
                .credential-value {
                    font-family: 'Courier New', Courier, monospace;
                    background: #ffffff;
                    padding: 12px 16px;
                    border-radius: 6px;
                    display: block;
                    color: #1a1a1a;
                    font-size: 16px;
                    font-weight: 600;
                    border: 2px solid #003082;
                    letter-spacing: 1px;
                }
                .security-notice {
                    background: linear-gradient(to right, #fff8e1 0%, #fffaed 100%);
                    border-left: 5px solid #D4AF37;
                    padding: 20px;
                    margin: 30px 0;
                    border-radius: 4px;
                }
                .security-notice-title {
                    color: #b8860b;
                    font-weight: 700;
                    font-size: 15px;
                    margin-bottom: 8px;
                }
                .security-notice-text {
                    color: #5a5a5a;
                    font-size: 14px;
                }
                .button-container {
                    text-align: center;
                    margin: 35px 0;
                }
                .login-button {
                    display: inline-block;
                    padding: 16px 45px;
                    background: linear-gradient(135deg, #003082 0%, #001a4d 100%);
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                    font-size: 16px;
                    letter-spacing: 0.5px;
                    box-shadow: 0 4px 6px rgba(0, 48, 130, 0.3);
                    transition: all 0.3s ease;
                    border: 2px solid #D4AF37;
                }
                .instructions-section {
                    background: #f8f9fa;
                    padding: 25px;
                    border-radius: 8px;
                    margin: 30px 0;
                }
                .section-title {
                    color: #003082;
                    font-size: 17px;
                    font-weight: 600;
                    margin-bottom: 15px;
                }
                .instructions-list {
                    list-style-position: inside;
                    color: #4a4a4a;
                }
                .instructions-list li {
                    margin-bottom: 12px;
                    padding-left: 10px;
                    font-size: 14px;
                }
                .instructions-list li strong {
                    color: #003082;
                }
                .requirements-list {
                    list-style-position: inside;
                    color: #4a4a4a;
                }
                .requirements-list li {
                    margin-bottom: 10px;
                    padding-left: 10px;
                    font-size: 14px;
                }
                .divider {
                    height: 2px;
                    background: linear-gradient(to right, #003082 0%, #D4AF37 50%, #003082 100%);
                    margin: 30px 0;
                }
                .footer {
                    background: #003082;
                    color: #ffffff;
                    text-align: center;
                    padding: 30px;
                    font-size: 13px;
                }
                .footer p {
                    margin-bottom: 10px;
                }
                .footer-note {
                    color: #D4AF37;
                    font-size: 12px;
                    margin-top: 15px;
                }
                .contact-info {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin-top: 25px;
                    text-align: center;
                    border-top: 3px solid #D4AF37;
                }
                .contact-info p {
                    color: #4a4a4a;
                    font-size: 14px;
                    margin-bottom: 5px;
                }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <!-- Header -->
                <div class='header'>
                    <h1>National University Lipa</h1>
                    <p>Academic Management System</p>
                </div>
                
                <!-- Main Content -->
                <div class='content'>
                    <div class='greeting'>Dear {$recipientName},</div>
                    
                    <p class='intro-text'>
                        Welcome to the National University Lipa Academic Management System. Your account has been successfully created and is now ready for activation.
                    </p>
                    
                    <!-- Credentials Box -->
                    <div class='credentials-section'>
                        <div class='credentials-title'>Account Credentials</div>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>Account Role</div>
                            <div class='credential-value'>{$roleDisplay}</div>
                        </div>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>" . ($role === 'student' ? 'Student ID' : 'Employee ID') . "</div>
                            <div class='credential-value'>{$userId}</div>
                        </div>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>Temporary Password</div>
                            <div class='credential-value'>{$temporaryPassword}</div>
                        </div>
                    </div>
                    
                    <!-- Security Notice -->
                    <div class='security-notice'>
                        <div class='security-notice-title'>IMPORTANT SECURITY NOTICE</div>
                        <div class='security-notice-text'>
                            This is a temporary password that must be changed upon your first login. For your security, you will be required to create a new password immediately after signing in.
                        </div>
                    </div>
                    
                    <!-- Login Button -->
                    <div class='button-container'>
                        <a href='{$loginUrl}' class='login-button'>ACCESS YOUR ACCOUNT</a>
                    </div>
                    
                    <div class='divider'></div>
                    
                    <!-- Login Instructions -->
                    <div class='instructions-section'>
                        <div class='section-title'>Getting Started</div>
                        <ol class='instructions-list'>
                            <li>Click the <strong>ACCESS YOUR ACCOUNT</strong> button above</li>
                            <li>Select your account role: <strong>{$roleDisplay}</strong></li>
                            <li>Enter your " . ($role === 'student' ? 'Student ID' : 'Employee ID') . " and temporary password</li>
                            <li>Follow the prompts to create your new secure password</li>
                            <li>Complete your profile information as required</li>
                        </ol>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class='instructions-section'>
                        <div class='section-title'>Password Requirements</div>
                        <ul class='requirements-list'>
                            <li>Minimum of 8 characters in length</li>
                            <li>At least one uppercase letter (A-Z)</li>
                            <li>At least one lowercase letter (a-z)</li>
                            <li>At least one number (0-9)</li>
                            <li>Recommended: Include special characters for added security</li>
                        </ul>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class='contact-info'>
                        <p><strong>Need Assistance?</strong></p>
                        <p>If you encounter any issues or did not request this account, please contact the IT Department or your system administrator immediately.</p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class='footer'>
                    <p><strong>National University Lipa</strong></p>
                    <p>Academic Management System</p>
                    <div class='footer-note'>
                        <p>&copy; " . date('Y') . " National University. All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text alternative
        $mail->AltBody = "NATIONAL UNIVERSITY LIPA\n" .
                        "Academic Management System\n\n" .
                        "Dear {$recipientName},\n\n" .
                        "Welcome to the National University Lipa Academic Management System.\n\n" .
                        "YOUR ACCOUNT CREDENTIALS\n" .
                        "========================\n" .
                        "Role: {$roleDisplay}\n" .
                        ($role === 'student' ? 'Student ID: ' : 'Employee ID: ') . "{$userId}\n" .
                        "Temporary Password: {$temporaryPassword}\n\n" .
                        "IMPORTANT SECURITY NOTICE\n" .
                        "This is a temporary password that must be changed upon your first login.\n\n" .
                        "GETTING STARTED\n" .
                        "1. Visit: {$loginUrl}\n" .
                        "2. Select your role: {$roleDisplay}\n" .
                        "3. Enter your credentials\n" .
                        "4. Create a new secure password\n\n" .
                        "PASSWORD REQUIREMENTS\n" .
                        "- Minimum 8 characters\n" .
                        "- At least one uppercase letter\n" .
                        "- At least one lowercase letter\n" .
                        "- At least one number\n\n" .
                        "For assistance, please contact your system administrator.\n\n" .
                        "© " . date('Y') . " National University. All rights reserved.";
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Account creation email sent successfully to ' . $recipientEmail
        ];
        
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return [
            'success' => false,
            'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Test email configuration
 * Use this to verify your email settings are correct
 */
function testEmailConfiguration() {
    $testEmail = 'test@example.com'; // Change this to your test email
    $result = sendAccountCreationEmail(
        $testEmail,
        'Test User',
        'TEST-001',
        'TestPass123!',
        'faculty'
    );
    
    return $result;
}
/**
 * Send account creation email specifically for students
 * Wrapper function that calls the main sendAccountCreationEmail with student role
 * 
 * @param string $recipientEmail Student's email address
 * @param string $recipientName Student's full name
 * @param string $studentId Student ID
 * @param string $temporaryPassword Generated password
 * @return array ['success' => bool, 'message' => string]
 */
function sendStudentAccountCreationEmail($recipientEmail, $recipientName, $studentId, $temporaryPassword) {
    return sendAccountCreationEmail($recipientEmail, $recipientName, $studentId, $temporaryPassword, 'student');
}

/**
 * Resend credentials to an existing student
 * 
 * @param string $recipientEmail Student's email address
 * @param string $recipientName Student's full name
 * @param string $studentId Student ID
 * @param string $newPassword New temporary password
 * @return array ['success' => bool, 'message' => string]
 */
function resendStudentCredentials($recipientEmail, $recipientName, $studentId, $newPassword) {
    $mail = new PHPMailer(true);
    
    try {
    //  GET ROTATING GMAIL ACCOUNT
    $gmail = getGmailAccount();
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail['username'];
    $mail->Password   = $gmail['password'];
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    
    // Recipients
    $mail->setFrom($gmail['username'], $gmail['name']);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your NU Lipa Account Credentials - Resent';
        
        $loginUrl = 'http://localhost/automation_system/auth/login.php';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #1a1a1a; background-color: #f5f5f5; }
                .email-wrapper { max-width: 650px; margin: 40px auto; background: #ffffff; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                .header { background: linear-gradient(135deg, #003082 0%, #001a4d 100%); padding: 40px 30px; text-align: center; border-bottom: 5px solid #D4AF37; }
                .header h1 { color: #ffffff; font-size: 28px; margin-bottom: 8px; }
                .header p { color: #D4AF37; font-size: 14px; letter-spacing: 1px; text-transform: uppercase; }
                .content { padding: 40px 35px; }
                .greeting { font-size: 20px; color: #003082; margin-bottom: 20px; font-weight: 600; }
                .credentials-section { background: #f8f9fa; border: 2px solid #D4AF37; border-radius: 8px; padding: 30px; margin: 30px 0; }
                .credentials-title { color: #003082; font-size: 18px; font-weight: 600; margin-bottom: 20px; text-align: center; }
                .credential-item { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
                .credential-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
                .credential-label { font-weight: 600; color: #003082; font-size: 13px; text-transform: uppercase; margin-bottom: 8px; }
                .credential-value { font-family: 'Courier New', monospace; background: #ffffff; padding: 12px 16px; border-radius: 6px; color: #1a1a1a; font-size: 16px; font-weight: 600; border: 2px solid #003082; letter-spacing: 1px; }
                .security-notice { background: #fff8e1; border-left: 5px solid #D4AF37; padding: 20px; margin: 30px 0; border-radius: 4px; }
                .security-notice-title { color: #b8860b; font-weight: 700; font-size: 15px; margin-bottom: 8px; }
                .button-container { text-align: center; margin: 35px 0; }
                .login-button { display: inline-block; padding: 16px 45px; background: linear-gradient(135deg, #003082 0%, #001a4d 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; border: 2px solid #D4AF37; }
                .footer { background: #003082; color: #ffffff; text-align: center; padding: 30px; font-size: 13px; }
                .footer-note { color: #D4AF37; font-size: 12px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='email-wrapper'>
                <div class='header'>
                    <h1>National University Lipa</h1>
                    <p>Academic Management System</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>Dear {$recipientName},</div>
                    
                    <p>Your login credentials have been resent as requested. Please use the information below to access your account.</p>
                    
                    <div class='credentials-section'>
                        <div class='credentials-title'>Your Login Credentials</div>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>Student ID</div>
                            <div class='credential-value'>{$studentId}</div>
                        </div>
                        
                        <div class='credential-item'>
                            <div class='credential-label'>New Temporary Password</div>
                            <div class='credential-value'>{$newPassword}</div>
                        </div>
                    </div>
                    
                    <div class='security-notice'>
                        <div class='security-notice-title'>⚠️ SECURITY REMINDER</div>
                        <div>This is a new temporary password. You will be required to change it upon your first login for security purposes.</div>
                    </div>
                    
                    <div class='button-container'>
                        <a href='{$loginUrl}' class='login-button'>LOGIN NOW</a>
                    </div>
                    
                    <p style='color: #666; font-size: 14px; margin-top: 30px;'>
                        If you did not request this email, please contact the IT Department immediately.
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>National University Lipa</strong></p>
                    <div class='footer-note'>
                        <p>&copy; " . date('Y') . " National University. All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "NATIONAL UNIVERSITY LIPA\n" .
                        "Academic Management System\n\n" .
                        "Dear {$recipientName},\n\n" .
                        "Your credentials have been resent.\n\n" .
                        "Student ID: {$studentId}\n" .
                        "New Temporary Password: {$newPassword}\n\n" .
                        "Please login at: {$loginUrl}\n\n" .
                        "You will be required to change this password upon first login.\n\n" .
                        "© " . date('Y') . " National University. All rights reserved.";
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Credentials resent successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Email resend failed: {$mail->ErrorInfo}");
        return [
            'success' => false,
            'message' => 'Failed to resend email: ' . $mail->ErrorInfo
        ];
    }
}
?>