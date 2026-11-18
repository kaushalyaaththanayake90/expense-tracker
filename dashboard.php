<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Current User Details
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$user_name = $user['name'];
$currency = $user['currency'] ?? 'Rs.';
$monthly_budget = floatval($user['monthly_budget'] ?? 0);
$theme = $user['theme'] ?? 'light';

$message = '';
$error = '';

$default_system_categories = [
    'food & drinks', 'bills & utilities', 'transport', 
    'rent & boarding', 'pocket money / allowance', 'salary', 'others'
];

// Handle Deleting Custom Category
if (isset($_GET['delete_category'])) {
    $del_cat_id = intval($_GET['delete_category']);
    $stmt_cat = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt_cat->execute([$del_cat_id]);
    $cat_to_del = $stmt_cat->fetch(PDO::FETCH_ASSOC);

    if ($cat_to_del) {
        $cat_name_lower = strtolower(trim($cat_to_del['name']));
        if (!in_array($cat_name_lower, $default_system_categories)) {
            $del_stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            if ($del_stmt->execute([$del_cat_id])) {
                $message = "Category '" . htmlspecialchars($cat_to_del['name']) . "' deleted successfully!";
            }
        } else {
            $error = "System default categories cannot be deleted.";
        }
    }
}

// Handle Adding Transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_transaction'])) {
    $type = $_POST['type'];
    $category_id = intval($_POST['category_id']);
    $custom_category = trim($_POST['custom_category'] ?? '');
    $amount = floatval($_POST['amount']);
    $transaction_date = $_POST['transaction_date'];
    $description = trim($_POST['description']);

    if (!empty($type) && $category_id > 0 && $amount > 0 && !empty($transaction_date)) {
        $cat_check = $conn->prepare("SELECT name FROM categories WHERE id = ?");
        $cat_check->execute([$category_id]);
        $cat_res = $cat_check->fetch(PDO::FETCH_ASSOC);

        if ($cat_res && strtolower($cat_res['name']) === 'others' && !empty($custom_category)) {
            $find_cat = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND type = ? AND (user_id IS NULL OR user_id = ?)");
            $find_cat->execute([$custom_category, $type, $user_id]);
            $existing_cat = $find_cat->fetch(PDO::FETCH_ASSOC);

            if ($existing_cat) {
                $category_id = $existing_cat['id'];
            } else {
                $ins_cat = $conn->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, ?, ?)");
                $ins_cat->execute([$custom_category, $type, $user_id]);
                $category_id = $conn->lastInsertId();
            }
        }

        $stmt = $conn->prepare("INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $category_id, $type, $amount, $transaction_date, $description])) {
            $message = "Transaction added successfully!";
        }
    }
}

// Handle Deleting Transaction
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$delete_id, $user_id])) {
        $message = "Transaction deleted successfully!";
    }
}

// Fetch Current Month Total Expenses
$current_month_exp_stmt = $conn->prepare("
    SELECT SUM(amount) AS monthly_total 
    FROM transactions 
    WHERE user_id = ? AND type = 'expense' 
    AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(transaction_date) = YEAR(CURRENT_DATE())
");
$current_month_exp_stmt->execute([$user_id]);
$current_month_expense = $current_month_exp_stmt->fetch(PDO::FETCH_ASSOC)['monthly_total'] ?? 0;

// Fetch Active Loans with Due Dates
$due_loans_stmt = $conn->prepare("
    SELECT * FROM loans 
    WHERE user_id = ? AND status = 'active' AND due_date IS NOT NULL AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY due_date ASC
");
$due_loans_stmt->execute([$user_id]);
$due_loans = $due_loans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Overall Transaction Totals
$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'income'");
$stmt->execute([$user_id]);
$total_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'expense'");
$stmt->execute([$user_id]);
$total_expense = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$net_balance = $total_income - $total_expense;

// Fetch Loan Totals
$loans_summary_stmt = $conn->prepare("
    SELECT loan_type, SUM(total_amount - paid_amount) AS total_due 
    FROM loans 
    WHERE user_id = ? AND status = 'active' 
    GROUP BY loan_type
");
$loans_summary_stmt->execute([$user_id]);
$loan_data = $loans_summary_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total_borrowed_due = $loan_data['borrowed'] ?? 0;
$total_lent_due = $loan_data['lent'] ?? 0;

$overview_labels = ['Total Expense', 'Remaining Balance'];
$overview_values = [$total_expense, max(0, $net_balance)];

// Fetch Categories
$categories_stmt = $conn->prepare("SELECT * FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY name ASC");
$categories_stmt->execute([$user_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Transactions
$stmt = $conn->prepare("
    SELECT t.*, c.name AS category_name 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id = ? 
    ORDER BY t.transaction_date DESC, t.id DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Expense Data for Chart
$exp_stmt = $conn->prepare("
    SELECT c.name AS category_name, SUM(t.amount) AS total_amount 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id = ? AND t.type = 'expense' 
    GROUP BY t.category_id
");
$exp_stmt->execute([$user_id]);
$exp_data = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);

$exp_labels = array_column($exp_data, 'category_name');
$exp_values = array_column($exp_data, 'total_amount');
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="dashboard.php">Expense Tracker</a>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-primary btn-sm fw-semibold">Dashboard</a>
            <a href="loans.php" class="btn btn-outline-light btn-sm fw-semibold">Loans Management</a>
            <a href="reports.php" class="btn btn-outline-light btn-sm fw-semibold">Reports</a>
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

    <!-- Monthly Budget Exceeded Alert -->
    <?php if ($monthly_budget > 0 && $current_month_expense >= $monthly_budget): ?>
        <div class="alert alert-danger shadow-sm border-danger d-flex align-items-center mb-3" role="alert">
            <div class="fs-4 me-3">⚠️</div>
            <div>
                <strong class="d-block">Monthly Budget Threshold Exceeded!</strong>
                Your expenses for this month (<?php echo htmlspecialchars($currency) . ' ' . number_format($current_month_expense, 2); ?>) have crossed your set budget limit of <?php echo htmlspecialchars($currency) . ' ' . number_format($monthly_budget, 2); ?>.
            </div>
        </div>
    <?php endif; ?>

    <!-- Loan Reminders -->
    <?php if (count($due_loans) > 0): ?>
        <?php foreach ($due_loans as $d_loan): ?>
            <div class="alert alert-warning shadow-sm border-warning d-flex align-items-center mb-3" role="alert">
                <div class="fs-4 me-3">🔔</div>
                <div>
                    <strong>Loan Repayment Reminder:</strong>
                    Loan from <strong><?php echo htmlspecialchars($d_loan['lender_name']); ?></strong> (Due Amount: <?php echo htmlspecialchars($currency) . ' ' . number_format($d_loan['total_amount'] - $d_loan['paid_amount'], 2); ?>) is due on <strong><?php echo htmlspecialchars($d_loan['due_date']); ?></strong>. 
                    <a href="loans.php" class="alert-link ms-2">Pay Now</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards Row 1: Finances -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-2">Total Income</h6>
                    <h2 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_income, 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-2">Total Expense</h6>
                    <h2 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_expense, 2); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm <?php echo $net_balance >= 0 ? 'bg-primary' : 'bg-warning text-dark'; ?> text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-2">Net Balance</h6>
                    <h2 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($net_balance, 2); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row 2: Loans Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-dark text-white border-start border-danger border-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase mb-1 small text-white-50">Total Money I Borrowed</h6>
                        <h3 class="fw-bold mb-0 text-danger"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_borrowed_due, 2); ?></h3>
                    </div>
                    <a href="loans.php" class="btn btn-outline-light btn-sm">Manage Loans</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-dark text-white border-start border-info border-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase mb-1 small text-white-50">Total Money I Lent</h6>
                        <h3 class="fw-bold mb-0 text-info"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_lent_due, 2); ?></h3>
                    </div>
                    <a href="loans.php" class="btn btn-outline-light btn-sm">Manage Loans</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="row g-4">

        <!-- Form -->
        <div class="col-lg-4 col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header fw-bold">Add New Transaction</div>
                <div class="card-body">
                    <form action="dashboard.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Transaction Type</label>
                            <select name="type" id="transactionType" class="form-select" required onchange="filterCategories()">
                                <option value="expense">Expense</option>
                                <option value="income">Income</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">Category</label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                    ⚙️ Manage Categories
                                </button>
                            </div>
                            <select name="category_id" id="categorySelect" class="form-select" required onchange="checkOthersCategory()">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" data-type="<?php echo $cat['type']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="customCategoryDiv" style="display: none;">
                            <label class="form-label text-primary fw-bold">Specify Custom Category</label>
                            <input type="text" name="custom_category" id="customCategoryInput" class="form-control" placeholder="e.g. Medical, Gym, Gift">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amount (<?php echo htmlspecialchars($currency); ?>)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. Lunch at canteen">
                        </div>
                        <button type="submit" name="add_transaction" class="btn btn-primary w-100 fw-semibold">Add Transaction</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="col-lg-8 col-md-7">
            <div class="row g-3 h-100">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header fw-bold text-primary">Financial Balance Overview</div>
                        <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 280px; max-height: 340px;">
                            <?php if ($total_income > 0 || $total_expense > 0): ?>
                                <canvas id="overviewChart"></canvas>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No financial data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header fw-bold text-danger">Expenses Category Breakdown</div>
                        <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 280px; max-height: 340px;">
                            <?php if (!empty($exp_labels)): ?>
                                <canvas id="expenseCategoryChart"></canvas>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No expense records available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Transactions Table -->
    <div class="card border-0 shadow-sm my-4">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span>Recent Transactions</span>
            <a href="reports.php" class="btn btn-sm btn-outline-primary fw-semibold">View All & Export Reports</a>
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
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_transactions) > 0): ?>
                            <?php foreach ($recent_transactions as $tx): ?>
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
                                    <td class="text-end">
                                        <a href="dashboard.php?delete=<?php echo $tx['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No transactions recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Manage Categories Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Search & Manage Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="categorySearchInput" class="form-control" placeholder="🔍 Search categories..." onkeyup="searchCategoriesModal()">
                </div>
                <ul class="list-group">
                    <?php foreach ($categories as $c): ?>
                        <?php $is_system = in_array(strtolower(trim($c['name'])), $default_system_categories); ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center category-modal-item">
                            <div>
                                <span class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></span>
                                <span class="badge bg-light text-dark border ms-1"><?php echo ucfirst($c['type']); ?></span>
                            </div>
                            <?php if (!$is_system): ?>
                                <a href="dashboard.php?delete_category=<?php echo $c['id']; ?>" class="btn btn-outline-danger btn-sm py-0 px-2 fw-semibold" onclick="return confirm('Delete category?');">Delete</a>
                            <?php else: ?>
                                <span class="badge bg-secondary">System Default</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const activeCurrency = <?php echo json_encode($currency); ?>;

function filterCategories() {
    const selectedType = document.getElementById('transactionType').value;
    const categoryOptions = document.querySelectorAll('#categorySelect option');
    categoryOptions.forEach(option => {
        const optionType = option.getAttribute('data-type');
        if (!optionType || optionType === selectedType) {
            option.style.display = "block";
        } else {
            option.style.display = "none";
        }
    });
    document.getElementById('categorySelect').value = "";
    checkOthersCategory();
}

function checkOthersCategory() {
    const select = document.getElementById('categorySelect');
    const selectedOption = select.options[select.selectedIndex];
    const customDiv = document.getElementById('customCategoryDiv');
    const customInput = document.getElementById('customCategoryInput');

    if (selectedOption && selectedOption.text.trim().toLowerCase() === 'others') {
        customDiv.style.display = 'block';
        customInput.required = true;
    } else {
        customDiv.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

function searchCategoriesModal() {
    let input = document.getElementById('categorySearchInput').value.toLowerCase();
    let items = document.querySelectorAll('.category-modal-item');
    items.forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(input) ? 'flex' : 'none';
    });
}

function getChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) { label += ': '; }
                        let value = context.raw || 0;
                        let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                        return label + activeCurrency + ' ' + value.toLocaleString() + ' (' + percentage + ')';
                    }
                }
            }
        }
    };
}

const overviewLabels = <?php echo json_encode($overview_labels); ?>;
const overviewValues = <?php echo json_encode($overview_values); ?>;
if ((overviewValues[0] > 0 || overviewValues[1] > 0) && document.getElementById('overviewChart')) {
    new Chart(document.getElementById('overviewChart').getContext('2d'), {
        type: 'doughnut',
        data: { labels: overviewLabels, datasets: [{ data: overviewValues, backgroundColor: ['#DC3545', '#198754'] }] },
        options: getChartOptions()
    });
}

const expLabels = <?php echo json_encode($exp_labels); ?>;
const expValues = <?php echo json_encode($exp_values); ?>;
if (expLabels.length > 0 && document.getElementById('expenseCategoryChart')) {
    new Chart(document.getElementById('expenseCategoryChart').getContext('2d'), {
        type: 'doughnut',
        data: { labels: expLabels, datasets: [{ data: expValues, backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'] }] },
        options: getChartOptions()
    });
}

document.addEventListener('DOMContentLoaded', () => { filterCategories(); });
</script>
</body>
</html>