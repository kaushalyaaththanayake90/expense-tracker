<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$message = $_SESSION['login_success_msg'] ?? '';
unset($_SESSION['login_success_msg']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $login_input = trim($_POST['login_input']); // Email or Phone
    $password = $_POST['password'];

    if (!empty($login_input) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email/phone or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="container" style="max-width: 420px;">
    <div class="card border-0 shadow-lg">
        <div class="card-body p-4">
            
            <div class="text-center mb-4">
                <h3 class="fw-bold text-primary">Expense Tracker</h3>
                <p class="text-muted small">Sign in to your account</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
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

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email or Phone Number</label>
                    <input type="text" name="login_input" class="form-control" placeholder="Email / Phone" required>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label fw-semibold mb-0">Password</label>
                        <a href="forgot_password.php" class="text-decoration-none small text-primary">Forgot Password?</a>
                    </div>
                    <input type="password" name="password" class="form-control mt-1" placeholder="••••••••" required>
                </div>

                <button type="submit" name="login" class="btn btn-primary w-100 fw-semibold mb-3">Sign In</button>
            </form>

            <div class="text-center">
                <span class="text-muted small">Don't have an account?</span>
                <a href="register.php" class="text-decoration-none fw-semibold small">Register</a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>