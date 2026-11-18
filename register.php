<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';
$step = $_SESSION['reset_step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // STEP 1: Email Verification & OTP Generation
    if (isset($_POST['send_otp'])) {
        $email = trim($_POST['email']);
        
        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate 6-digit OTP Code
                $otp = rand(100000, 999999);
                
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_step'] = 2;
                $step = 2;

                $message = "OTP Code sent! (Demo OTP: " . $otp . ")";
            } else {
                $error = "No account found with that email address.";
            }
        } else {
            $error = "Please enter your registered email address.";
        }
    } 
    
    // STEP 2: Verify OTP
    elseif (isset($_POST['verify_otp'])) {
        $entered_otp = trim($_POST['otp']);
        
        if (!empty($entered_otp) && isset($_SESSION['reset_otp'])) {
            if ($entered_otp == $_SESSION['reset_otp']) {
                $_SESSION['otp_verified'] = true;
                $_SESSION['reset_step'] = 3;
                $step = 3;
                $message = "OTP Verified successfully! Set your new password.";
            } else {
                $error = "Invalid OTP Code. Please try again.";
                $step = 2;
            }
        } else {
            $error = "Please enter the 6-digit OTP code.";
            $step = 2;
        }
    }

    // STEP 3: Reset Password
    elseif (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $email = $_SESSION['reset_email'] ?? '';

        if (!empty($email) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                
                if ($stmt->execute([$hashed_password, $email])) {
                    // Clear Session Reset Data
                    unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_step'], $_SESSION['otp_verified']);
                    $message = "Password reset successfully! You can now log in.";
                    $step = 4;
                } else {
                    $error = "Failed to reset password. Please try again.";
                    $step = 3;
                }
            } else {
                $error = "New passwords do not match.";
                $step = 3;
            }
        } else {
            $error = "Please fill in all password fields.";
            $step = 3;
        }
    }
}

// Reset button handler to start over
if (isset($_GET['action']) && $_GET['action'] == 'reset') {
    unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_step'], $_SESSION['otp_verified']);
    header("Location: forgot_password.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="container" style="max-width: 450px;">
    <div class="card border-0 shadow-lg">
        <div class="card-body p-4">
            
            <div class="text-center mb-4">
                <h3 class="fw-bold text-primary">Expense Tracker</h3>
                <p class="text-muted small">Account Password Recovery</p>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- STEP 1: Enter Email -->
            <?php if ($step == 1): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Registered Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="example@domain.com" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary w-100 fw-semibold">Send OTP Code</button>
                </form>
            <?php endif; ?>

            <!-- STEP 2: Verify OTP -->
            <?php if ($step == 2): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enter 6-Digit OTP Code</label>
                        <input type="text" name="otp" class="form-control text-center fs-4 fw-bold letter-spacing" maxlength="6" placeholder="000000" required>
                        <div class="form-text text-center mt-2">Enter the verification code generated above.</div>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-success w-100 fw-semibold">Verify OTP</button>
                </form>
                <div class="text-center mt-3">
                    <a href="forgot_password.php?action=reset" class="text-decoration-none small text-secondary">Change Email</a>
                </div>
            <?php endif; ?>

            <!-- STEP 3: Reset Password -->
            <?php if ($step == 3): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary w-100 fw-semibold">Update Password</button>
                </form>
            <?php endif; ?>

            <!-- STEP 4: Success -->
            <?php if ($step == 4): ?>
                <div class="text-center my-3">
                    <a href="login.php" class="btn btn-success w-100 fw-semibold">Go to Login</a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none small">← Back to Login</a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>