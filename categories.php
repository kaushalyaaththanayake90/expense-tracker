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

// Handle deleting user's custom category
if (isset($_GET['delete_cat'])) {
    $cat_id = intval($_GET['delete_cat']);
    
    // Ensure user can only delete their own custom category
    $chk = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $chk->execute([$cat_id, $user_id]);
    
    if ($chk->rowCount() > 0) {
        $del = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        if ($del->execute([$cat_id, $user_id])) {
            $message = "Category deleted successfully!";
        } else {
            $error = "Failed to delete category.";
        }
    } else {
        $error = "You can only delete your custom categories.";
    }
}

// Fetch custom categories created by this user
$stmt = $conn->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user_id]);
$custom_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="dashboard.php">Expense Tracker</a>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm fw-semibold">Dashboard</a>
            <a href="loans.php" class="btn btn-info btn-sm text-white fw-semibold">Loans Management</a>
            <a href="categories.php" class="btn btn-primary btn-sm fw-semibold">Manage Categories</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm fw-semibold">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">My Custom Categories</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category Name</th>
                            <th>Type</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($custom_categories) > 0): ?>
                            <?php foreach ($custom_categories as $cat): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $cat['type'] == 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($cat['type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="categories.php?delete_cat=<?php echo $cat['id']; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this category?');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-3 text-muted">No custom categories created yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>