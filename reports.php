<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch User Details for Navbar
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$user_name = $user['name'];
$currency = $user['currency'] ?? 'Rs.';
$theme = $user['theme'] ?? 'light';

// Filter Parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$type = $_GET['type'] ?? 'all';
$category_id = intval($_GET['category_id'] ?? 0);

// Build SQL Query
$sql = "SELECT t.*, c.name AS category_name 
        FROM transactions t 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?";

$params = [$user_id, $start_date, $end_date];

if ($type !== 'all') {
    $sql .= " AND t.type = ?";
    $params[] = $type;
}

if ($category_id > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $category_id;
}

$sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$report_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$total_income = 0;
$total_expense = 0;

foreach ($report_transactions as $row) {
    if ($row['type'] === 'income') {
        $total_income += $row['amount'];
    } else {
        $total_expense += $row['amount'];
    }
}
$net_filtered_balance = $total_income - $total_expense;

// Categories
$cat_stmt = $conn->prepare("SELECT * FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY name ASC");
$cat_stmt->execute([$user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Expense Tracker</title>
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
            <a href="reports.php" class="btn btn-primary btn-sm fw-semibold">Reports</a>
            <span class="text-white-50 mx-1">|</span>
            <a href="profile.php" class="text-decoration-none text-warning me-2 d-inline-flex align-items-center gap-2">
                <?php if (!empty($user['profile_image']) && file_exists('uploads/' . $user['profile_image'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" class="rounded-circle" width="28" height="28" style="object-fit: cover;">
                <?php else: ?>
                    <span class="badge bg-secondary rounded-circle">👤</span>
                <?php endif; ?>
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
            </a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm fw-semibold">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">

    <!-- Filter Form Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-bold">Filter Transactions & Custom Reports</div>
        <div class="card-body">
            <form action="reports.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income Only</option>
                        <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expense Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary fw-semibold px-4">Apply Filter</button>
                    <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-1 small">Filtered Income</h6>
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_income, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-1 small">Filtered Expense</h6>
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_expense, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-1 small">Period Net Balance</h6>
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($net_filtered_balance, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span>Filtered Transactions List (<?php echo count($report_transactions); ?> records found)</span>
            <a href="export.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&type=<?php echo urlencode($type); ?>" class="btn btn-sm btn-outline-success fw-semibold">Export Filtered CSV</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($report_transactions) > 0): ?>
                            <?php foreach ($report_transactions as $tx): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tx['transaction_date']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tx['category_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($tx['description'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $tx['type'] == 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($tx['type']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold <?php echo $tx['type'] == 'income' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ($tx['type'] == 'income' ? '+' : '-') . ' ' . htmlspecialchars($currency) . ' ' . number_format($tx['amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No transactions matching your criteria.</td>
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