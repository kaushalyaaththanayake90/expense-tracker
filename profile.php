<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if (!empty($name) && !empty($email)) {
        $profile_image = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['profile_photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $new_filename = "user_" . $user_id . "_" . time() . "." . $ext;
                $target = "uploads/" . $new_filename;

                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
                    $profile_image = $new_filename;
                } else {
                    $error = "Failed to upload profile photo.";
                }
            } else {
                $error = "Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.";
            }
        }

        if (empty($error)) {
            if ($profile_image) {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, profile_image = ? WHERE id = ?");
                $stmt->execute([$name, $email, $profile_image, $user_id]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $user_id]);
            }

            $_SESSION['user_name'] = $name;
            $message = "Profile details updated successfully!";
        }
    } else {
        $error = "Name and Email are required.";
    }
}

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (!empty($current_pass) && !empty($new_pass) && !empty($confirm_pass)) {
        if ($new_pass === $confirm_pass) {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($current_pass, $user_data['password'])) {
                $hashed_new = password_hash($new_pass, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$hashed_new, $user_id]);
                $message = "Password changed successfully!";
            } else {
                $error = "Incorrect current password.";
            }
        } else {
            $error = "New passwords do not match.";
        }
    } else {
        $error = "All password fields are required.";
    }
}

// Handle Preferences Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $currency = trim($_POST['currency']);
    $monthly_budget = floatval($_POST['monthly_budget']);
    $theme = trim($_POST['theme'] ?? 'light');

    if (!empty($currency)) {
        $stmt = $conn->prepare("UPDATE users SET currency = ?, monthly_budget = ?, theme = ? WHERE id = ?");
        if ($stmt->execute([$currency, $monthly_budget, $theme, $user_id])) {
            $message = "Settings and Theme saved successfully!";
        } else {
            $error = "Failed to update settings.";
        }
    }
}

// Fetch Current User Details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$theme = $user['theme'] ?? 'light';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile & Settings - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="dashboard.php">Expense Tracker</a>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm fw-semibold">Dashboard</a>
            <a href="loans.php" class="btn btn-outline-light btn-sm fw-semibold">Loans Management</a>
            <a href="reports.php" class="btn btn-outline-light btn-sm fw-semibold">Reports</a>
            <span class="text-white-50 mx-1">|</span>
            <a href="profile.php" class="text-decoration-none text-warning me-2 d-inline-flex align-items-center gap-2">
                <?php if (!empty($user['profile_image']) && file_exists('uploads/' . $user['profile_image'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" class="rounded-circle" width="28" height="28" style="object-fit: cover;">
                <?php else: ?>
                    <span class="badge bg-secondary rounded-circle">👤</span>
                <?php endif; ?>
                <strong><?php echo htmlspecialchars($user['name']); ?></strong>
            </a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm fw-semibold">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Personal Details Form -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header fw-bold">Personal Profile Information</div>
                <div class="card-body">
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <?php if (!empty($user['profile_image']) && file_exists('uploads/' . $user['profile_image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" class="rounded-circle img-thumbnail mb-2" width="100" height="100" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px; font-size: 32px;">👤</div>
                            <?php endif; ?>
                            <div>
                                <label class="form-label small text-muted">Upload Profile Photo</label>
                                <input type="file" name="profile_photo" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary w-100 fw-semibold">Save Details</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Settings & Password Forms -->
        <div class="col-md-6">
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header fw-bold text-success">Application Preferences & Theme</div>
                <div class="card-body">
                    <form action="profile.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">System Appearance / Theme</label>
                            <select name="theme" class="form-select" required>
                                <option value="light" <?php echo ($theme == 'light') ? 'selected' : ''; ?>>☀️ Light Mode</option>
                                <option value="dark" <?php echo ($theme == 'dark') ? 'selected' : ''; ?>>🌙 Dark Mode</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Preferred Currency Symbol</label>
                            <select name="currency" class="form-select" required>
                                <option value="Rs." <?php echo ($user['currency'] == 'Rs.') ? 'selected' : ''; ?>>LKR - Rs.</option>
                                <option value="$" <?php echo ($user['currency'] == '$') ? 'selected' : ''; ?>>USD - $</option>
                                <option value="€" <?php echo ($user['currency'] == '€') ? 'selected' : ''; ?>>EUR - €</option>
                                <option value="£" <?php echo ($user['currency'] == '£') ? 'selected' : ''; ?>>GBP - £</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monthly Spending Budget Limit</label>
                            <input type="number" step="0.01" name="monthly_budget" class="form-control" value="<?php echo htmlspecialchars($user['monthly_budget']); ?>" placeholder="0.00">
                        </div>
                        <button type="submit" name="update_settings" class="btn btn-success w-100 fw-semibold">Save Preferences</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-danger">Security & Change Password</div>
                <div class="card-body">
                    <form action="profile.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-danger w-100 fw-semibold">Update Password</button>
                    </form>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>