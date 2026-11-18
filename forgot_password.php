<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$message = '';
$step = $_SESSION['fg_step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // STEP 1: Find Account & Send OTP
    if (isset($_POST['send_otp'])) {
        $search_input = trim($_POST['search_input']);

        if (!empty($search_input)) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$search_input, $search_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $otp = rand(100000, 999999);

                $_SESSION['fg_user_id'] = $user['id'];
                $_SESSION['fg_otp'] = $otp;
                $_SESSION['fg_step'] = 2;
                $step = 2;

                $message = "OTP Code generated successfully!";
            } else {
                $error = "No account found with that Email or Phone Number.";
            }
        } else {
            $error = "Please enter your Email or Phone Number.";
        }
    }

    // STEP 2: Verify OTP
    elseif (isset($_POST['verify_otp'])) {
        $entered_otp = trim($_POST['otp']);

        if (!empty($entered_otp) && isset($_SESSION['fg_otp'])) {
            if ($entered_otp == $_SESSION['fg_otp']) {
                $_SESSION['fg_step'] = 3;
                $step = 3;
                $message = "Account verified! Please set your new password.";
            } else {
                $error = "Invalid OTP code. Please try again.";
                $step = 2;
            }
        } else {
            $error = "Please enter the 6-digit OTP code.";
            $step = 2;
        }
    }

    // STEP 3: Save New Password
    elseif (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['fg_user_id'] ?? 0;

        if ($user_id > 0 && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

                if ($stmt->execute([$hashed_password, $user_id])) {
                    unset($_SESSION['fg_user_id'], $_SESSION['fg_otp'], $_SESSION['fg_step']);
                    $_SESSION['login_success_msg'] = "Password reset successfully! Please log in with your new password.";
                    header("Location: login.php");
                    exit();
                } else {
                    $error = "Failed to update password. Try again.";
                    $step = 3;
                }
            } else {
                $error = "Passwords do not match.";
                $step = 3;
            }
        } else {
            $error = "Please fill in both password fields.";
            $step = 3;
        }
    }
}

// Reset Forgot Password Session
if (isset($_GET['action']) && $_GET['action'] == 'reset') {
    unset($_SESSION['fg_user_id'], $_SESSION['fg_otp'], $_SESSION['fg_step']);
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

<div class="container" style="max-width: 420px;">
    <div class="card border-0 shadow-lg">
        <div class="card-body p-4">
            
            <div class="text-center mb-4">
                <h3 class="fw-bold text-primary">Expense Tracker</h3>
                <p class="text-muted small">Account Password Recovery</p>
            </div>

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

            <!-- STEP 1: Enter Email or Phone -->
            <?php if ($step == 1): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Registered Email or Phone Number</label>
                        <input type="text" name="search_input" class="form-control" placeholder="Email or Phone" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary w-100 fw-semibold">Get Verification OTP</button>
                </form>
            <?php endif; ?>

            <!-- STEP 2: Enter OTP -->
            <?php if ($step == 2): ?>
                <!-- Demo Banner displaying OTP -->
                <div class="alert alert-info text-center py-2 mb-3">
                    <small class="d-block text-uppercase fw-bold text-muted">Demo Verification Code</small>
                    <span class="fs-3 fw-bold text-primary tracking-wide"><?php echo htmlspecialchars($_SESSION['fg_otp'] ?? ''); ?></span>
                </div>

                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Enter 6-Digit OTP</label>
                        <input type="text" name="otp" class="form-control text-center fs-4 fw-bold" maxlength="6" placeholder="000000" required autofocus>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-success w-100 fw-semibold mb-2">Verify Account</button>
                </form>
                <div class="text-center">
                    <a href="forgot_password.php?action=reset" class="text-decoration-none small text-secondary">← Back</a>
                </div>
            <?php endif; ?>

            <!-- STEP 3: Reset Password -->
            <?php if ($step == 3): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary w-100 fw-semibold">Update Password</button>
                </form>
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