<?php
/**
 * Mango Number - Authentication & Email Verification Handler
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Mailer.php';

// Verify CSRF token for security
$action = $_GET['action'] ?? '';
if (!in_array($action, ['check-status', 'get-user-purchases'])) {
    $headers = getallheaders();
    $csrf_token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'CSRF verification failed. Please refresh the page and try again.']);
        exit;
    }
}

$db = get_db_connection();

if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please check your MySQL server status.']);
    exit;
}

// -------------------------------------------------------------
// ACTION: Check Account Deletion Status (Rapid Session Purger)
// -------------------------------------------------------------
if ($action === 'check-status') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['logged_in' => false]);
        exit;
    }
    
    // Force logout for non-admins if website usage is disabled (Maintenance lockdown)
    if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
        if (get_system_setting('allow_website_usage', '1') === '0') {
            $_SESSION['website_maintenance_flag'] = true;
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
            unset($_SESSION['role']);
            echo json_encode(['logged_in' => false, 'maintenance' => true]);
            exit;
        }
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || $user['status'] === 'deleted') {
        // User has been deleted! Clear session immediately and set temporary redirect flag
        $_SESSION['account_deleted_flag'] = true;
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['role']);
        echo json_encode(['logged_in' => false, 'deleted' => true]);
        exit;
    }
    
    echo json_encode(['logged_in' => true]);
    exit;
}

// -------------------------------------------------------------
// ACTION: Poll User Purchase Statuses (Real-time Order Updates)
// -------------------------------------------------------------
if ($action === 'get-user-purchases') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id, status, virtual_number_provided, otp_provided FROM purchases WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
    $purchases = $stmt->fetchAll();
    echo json_encode(['success' => true, 'purchases' => $purchases]);
    exit;
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

try {
    // -------------------------------------------------------------
    // ACTION: Send OTP for Signup
    // -------------------------------------------------------------
    if ($action === 'send-signup-otp') {
        // Verify if signups are allowed
        if (get_system_setting('allow_signups', '1') === '0') {
            echo json_encode([
                'success' => false, 
                'error' => 'Registration/Sign-ups are temporarily disabled by the administrator. If you need a new number, please contact the owner on Telegram: @nu9rl.'
            ]);
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        
        if (!validate_email($email)) {
            echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
            exit;
        }

        // Check if email already registered
        $stmt = $db->prepare("SELECT id, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();
        if ($existing_user && $existing_user['status'] === 'active') {
            echo json_encode(['success' => false, 'error' => 'This email address is already registered. Please login.']);
            exit;
        }

        // Flood Prevention Check: Max 1 OTP per minute
        $stmt = $db->prepare("SELECT count(*) FROM email_otps WHERE email = ? AND purpose = 'signup' AND created_at >= NOW() - INTERVAL 1 MINUTE");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Please wait 60 seconds before requesting another OTP code.']);
            exit;
        }

        // Flood Prevention Check: Max 5 OTPs per 10 minutes
        $stmt = $db->prepare("SELECT count(*) FROM email_otps WHERE email = ? AND purpose = 'signup' AND created_at >= NOW() - INTERVAL 10 MINUTE");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() >= 5) {
            echo json_encode(['success' => false, 'error' => 'Maximum of 5 OTP codes allowed in 10 minutes. Please try again later.']);
            exit;
        }

        // Generate secure random 6-digit numeric OTP code
        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed_otp = password_hash($otp_code, PASSWORD_DEFAULT);
        
        // Set 10 minutes expiry
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save hashed OTP to database
        $stmt = $db->prepare("INSERT INTO email_otps (email, otp, purpose, expires_at, is_used) VALUES (?, ?, 'signup', ?, 0)");
        $stmt->execute([$email, $hashed_otp, $expires_at]);
        
        // Compile email details
        $subject = "Your Mango Numbers OTP Code";
        $body = "
        <div style='background-color: #fffdf9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; padding: 40px 20px; text-align: center; color: #1A1208;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border: 1px solid #FFE8C8; border-radius: 24px; padding: 44px 34px; box-shadow: 0 16px 40px rgba(0,0,0,0.04);'>
                <div style='margin-bottom: 30px;'>
                    <span style='font-size: 26px; font-weight: 900; color: #1A1208; letter-spacing: -0.5px;'>Mango <span style='color: #D97706;'>Number</span></span>
                </div>
                
                <h2 style='font-size: 22px; font-weight: 800; color: #1A1208; margin-bottom: 12px; letter-spacing: -0.3px;'>Verify Your Email</h2>
                <p style='font-size: 14.5px; color: rgba(26,18,8,0.65); line-height: 1.6; margin-bottom: 32px;'>Thank you for choosing Mango Numbers. Use the secure 6-digit OTP code below to complete your registration request.</p>
                
                <div style='background: linear-gradient(135deg, #FFF9F2 0%, #FFEEDC 100%); border: 1.5px dashed #FF8C00; border-radius: 16px; padding: 20px; margin-bottom: 32px; display: inline-block; min-width: 200px;'>
                    <span style='font-size: 32px; font-weight: 900; letter-spacing: 6px; color: #D97706; font-family: monospace;'>{$otp_code}</span>
                </div>
                
                <p style='font-size: 13px; color: rgba(26,18,8,0.45); font-weight: 600; margin-bottom: 24px;'>This OTP code is valid for exactly <strong>10 minutes</strong> and can be used only once.</p>
                
                <div style='border-top: 1px solid #FFE8C8; padding-top: 24px; font-size: 12px; color: rgba(26,18,8,0.4); line-height: 1.5;'>
                    If you did not request this verification code, please disregard this message safely.<br><br>
                    &copy; " . date('Y') . " Mango Numbers. Premium SMS Verification Services.
                </div>
            </div>
        </div>";
                 
        // Send email via SMTP socket client
        Mailer::send($email, $subject, $body);
        
        echo json_encode(['success' => true, 'message' => 'OTP verification code sent successfully to your email.']);
    }

    // -------------------------------------------------------------
    // ACTION: Verify Signup OTP
    // -------------------------------------------------------------
    elseif ($action === 'verify-signup-otp') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($otp) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'All signup fields are required.']);
            exit;
        }

        if ($password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
            exit;
        }

        // Fetch the most recent unused OTP code for the email
        $stmt = $db->prepare("SELECT * FROM email_otps WHERE email = ? AND purpose = 'signup' AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $record = $stmt->fetch();

        if (!$record) {
            echo json_encode(['success' => false, 'error' => 'No OTP request found. Please request a new OTP.']);
            exit;
        }

        if (time() > strtotime($record['expires_at'])) {
            echo json_encode(['success' => false, 'error' => 'OTP expired. Please resend OTP.']);
            exit;
        }

        if ($record['attempts'] >= 5) {
            echo json_encode(['success' => false, 'error' => 'Maximum attempts exceeded. Please request a new OTP.']);
            exit;
        }

        // Verify OTP value against database hash
        if (password_verify($otp, $record['otp'])) {
            // Mark OTP as used
            $stmt = $db->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
            $stmt->execute([$record['id']]);

            // Register user account in database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Double check username uniqueness
            $username = $email;
            $stmt = $db->prepare("SELECT id, status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $existing_user = $stmt->fetch();
            if ($existing_user) {
                if ($existing_user['status'] === 'active') {
                    echo json_encode(['success' => false, 'error' => 'Username/Email is already registered.']);
                    exit;
                } else {
                    // Remove old deleted record first to avoid key conflicts and create fresh active account
                    $del_stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $del_stmt->execute([$existing_user['id']]);
                }
            }

            $mobile = trim($_POST['mobile'] ?? '');
            $stmt = $db->prepare("INSERT INTO users (name, email, username, mobile, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->execute([$name, $email, $username, $mobile, $hashed_password]);
            $user_id = $db->lastInsertId();

            // Regenerate session ID to prevent Session Fixation
            session_regenerate_id(true);

            // Set login sessions
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';

            echo json_encode(['success' => true, 'message' => 'Email verified successfully! Creating account...']);
        } else {
            // Increment attempts count
            $stmt = $db->prepare("UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$record['id']]);
            
            $remaining = 4 - $record['attempts'];
            if ($remaining <= 0) {
                echo json_encode(['success' => false, 'error' => 'Maximum attempts exceeded. Please request a new OTP.']);
            } else {
                echo json_encode(['success' => false, 'error' => "Invalid OTP. You have {$remaining} attempts remaining."]);
            }
        }
    }

    // -------------------------------------------------------------
    // ACTION: Send Forgot Password OTP
    // -------------------------------------------------------------
    elseif ($action === 'send-forgot-password-otp') {
        $email = trim($_POST['email'] ?? '');

        if (!validate_email($email)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
            exit;
        }

        // Verify if user is registered in the database
        $stmt = $db->prepare("SELECT id, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();
        if (!$existing_user || $existing_user['status'] === 'deleted') {
            echo json_encode(['success' => false, 'error' => 'No active account registered with this email address.']);
            exit;
        }

        // Flood Prevention Check: Max 1 OTP per minute
        $stmt = $db->prepare("SELECT count(*) FROM email_otps WHERE email = ? AND purpose = 'forgot_password' AND created_at >= NOW() - INTERVAL 1 MINUTE");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Please wait 60 seconds before requesting another OTP code.']);
            exit;
        }

        // Flood Prevention Check: Max 5 OTPs per 10 minutes
        $stmt = $db->prepare("SELECT count(*) FROM email_otps WHERE email = ? AND purpose = 'forgot_password' AND created_at >= NOW() - INTERVAL 10 MINUTE");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() >= 5) {
            echo json_encode(['success' => false, 'error' => 'Maximum of 5 OTP codes allowed in 10 minutes. Please try again later.']);
            exit;
        }

        // Generate secure random 6-digit numeric OTP code
        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed_otp = password_hash($otp_code, PASSWORD_DEFAULT);
        
        // Set 10 minutes expiry
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save hashed OTP to database
        $stmt = $db->prepare("INSERT INTO email_otps (email, otp, purpose, expires_at, is_used) VALUES (?, ?, 'forgot_password', ?, 0)");
        $stmt->execute([$email, $hashed_otp, $expires_at]);
        
        // Compile email details
        $subject = "Your Mango Numbers OTP Code";
        $body = "
        <div style='background-color: #fffdf9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; padding: 40px 20px; text-align: center; color: #1A1208;'>
            <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; border: 1px solid #FFE8C8; border-radius: 24px; padding: 44px 34px; box-shadow: 0 16px 40px rgba(0,0,0,0.04);'>
                <div style='margin-bottom: 30px;'>
                    <span style='font-size: 26px; font-weight: 900; color: #1A1208; letter-spacing: -0.5px;'>Mango <span style='color: #D97706;'>Number</span></span>
                </div>
                
                <h2 style='font-size: 22px; font-weight: 800; color: #1A1208; margin-bottom: 12px; letter-spacing: -0.3px;'>Reset Your Password</h2>
                <p style='font-size: 14.5px; color: rgba(26,18,8,0.65); line-height: 1.6; margin-bottom: 32px;'>We received a request to reset your password. Use the secure 6-digit OTP code below to verify your identity.</p>
                
                <div style='background: linear-gradient(135deg, #FFF9F2 0%, #FFEEDC 100%); border: 1.5px dashed #FF8C00; border-radius: 16px; padding: 20px; margin-bottom: 32px; display: inline-block; min-width: 200px;'>
                    <span style='font-size: 32px; font-weight: 900; letter-spacing: 6px; color: #D97706; font-family: monospace;'>{$otp_code}</span>
                </div>
                
                <p style='font-size: 13px; color: rgba(26,18,8,0.45); font-weight: 600; margin-bottom: 24px;'>This OTP code is valid for exactly <strong>10 minutes</strong> and can be used only once.</p>
                
                <div style='border-top: 1px solid #FFE8C8; padding-top: 24px; font-size: 12px; color: rgba(26,18,8,0.4); line-height: 1.5;'>
                    If you did not request this password reset code, please disregard this message safely.<br><br>
                    &copy; " . date('Y') . " Mango Numbers. Premium SMS Verification Services.
                </div>
            </div>
        </div>";
                 
        // Send email via SMTP socket client
        Mailer::send($email, $subject, $body);
        
        echo json_encode(['success' => true, 'message' => 'Password reset OTP code sent successfully to your email.']);
    }

    // -------------------------------------------------------------
    // ACTION: Verify Forgot Password OTP
    // -------------------------------------------------------------
    elseif ($action === 'verify-forgot-password-otp') {
        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');

        if (empty($email) || empty($otp)) {
            echo json_encode(['success' => false, 'error' => 'Email and OTP inputs are required.']);
            exit;
        }

        // Fetch the most recent unused OTP code for this email
        $stmt = $db->prepare("SELECT * FROM email_otps WHERE email = ? AND purpose = 'forgot_password' AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $record = $stmt->fetch();

        if (!$record) {
            echo json_encode(['success' => false, 'error' => 'No OTP request found. Please request a new OTP.']);
            exit;
        }

        if (time() > strtotime($record['expires_at'])) {
            echo json_encode(['success' => false, 'error' => 'OTP expired. Please resend OTP.']);
            exit;
        }

        if ($record['attempts'] >= 5) {
            echo json_encode(['success' => false, 'error' => 'Maximum attempts exceeded. Please request a new OTP.']);
            exit;
        }

        // Verify OTP value against database hash
        if (password_verify($otp, $record['otp'])) {
            // Mark OTP as used
            $stmt = $db->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
            $stmt->execute([$record['id']]);

            // Store reset state in session temporarily
            $_SESSION['reset_email'] = $email;

            echo json_encode(['success' => true, 'message' => 'OTP verified successfully! Set your new password.']);
        } else {
            // Increment attempts count
            $stmt = $db->prepare("UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$record['id']]);
            
            $remaining = 4 - $record['attempts'];
            if ($remaining <= 0) {
                echo json_encode(['success' => false, 'error' => 'Maximum attempts exceeded. Please request a new OTP.']);
            } else {
                echo json_encode(['success' => false, 'error' => "Invalid OTP. You have {$remaining} attempts remaining."]);
            }
        }
    }

    // -------------------------------------------------------------
    // ACTION: Reset Password
    // -------------------------------------------------------------
    elseif ($action === 'reset-password') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';

        if (empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Session expired or invalid reset request. Please restart forgot password flow.']);
            exit;
        }

        if (empty($password) || $password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
            exit;
        }

        // Update the password in database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);
        
        // Clear temp reset state from session & regenerate session ID
        unset($_SESSION['reset_email']);
        session_regenerate_id(true);

        echo json_encode(['success' => true, 'message' => 'Password reset successfully! Please login.']);
    }

    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action endpoint.']);
    }

} catch (PDOException $e) {
    // Handle database connection or missing table issues gracefully
    if (strpos($e->getMessage(), "Table") !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode([
            'success' => false, 
            'error' => 'Database tables missing. If you are the administrator, please visit http://localhost/mango-number/db_init.php in your browser to run the database setup first!'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Mailer or server error: ' . $e->getMessage()]);
}
