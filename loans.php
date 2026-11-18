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
$theme = $user['theme'] ?? 'light';

$message = '';
$error = '';

// Handle Adding a New Loan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_loan'])) {
    $lender_name = trim($_POST['lender_name']);
    $total_amount = floatval($_POST['total_amount']);
    $loan_type = $_POST['loan_type'] ?? 'borrowed';
    $loan_date = $_POST['loan_date'];
    $due_date = $_POST['due_date'] ?: null;

    if (!empty($lender_name) && $total_amount > 0 && !empty($loan_date)) {
        $stmt = $conn->prepare("INSERT INTO loans (user_id, lender_name, total_amount, loan_type, loan_date, due_date) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $lender_name, $total_amount, $loan_type, $loan_date, $due_date])) {
            $message = "Loan record added successfully!";
        } else {
            $error = "Failed to add loan record.";
        }
    } else {
        $error = "Please fill in all required fields properly.";
    }
}

// Handle Recording Payment / Settlement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $loan_id = intval($_POST['loan_id']);
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = $_POST['payment_date'];
    $note = trim($_POST['note']);

    if ($loan_id > 0 && $amount_paid > 0 && !empty($payment_date)) {
        $stmt = $conn->prepare("SELECT total_amount, paid_amount FROM loans WHERE id = ? AND user_id = ?");
        $stmt->execute([$loan_id, $user_id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($loan) {
            $new_paid = $loan['paid_amount'] + $amount_paid;
            $status = ($new_paid >= $loan['total_amount']) ? 'paid' : 'active';

            $pay_stmt = $conn->prepare("INSERT INTO loan_payments (loan_id, amount_paid, payment_date, note) VALUES (?, ?, ?, ?)");
            $pay_stmt->execute([$loan_id, $amount_paid, $payment_date, $note]);

            $update_stmt = $conn->prepare("UPDATE loans SET paid_amount = ?, status = ? WHERE id = ?");
            $update_stmt->execute([$new_paid, $status, $loan_id]);

            $message = "Payment recorded successfully!";
        } else {
            $error = "Invalid loan record selected.";
        }
    } else {
        $error = "Please enter valid payment details.";
    }
}

// Fetch Active Loans
$loans_stmt = $conn->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY status ASC, loan_date DESC");
$loans_stmt->execute([$user_id]);
$loans = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals
$total_borrowed_due = 0;
$total_lent_due = 0;

foreach ($loans as $l) {
    if ($l['status'] == 'active') {
        $rem = $l['total_amount'] - $l['paid_amount'];
        if (($l['loan_type'] ?? 'borrowed') === 'lent') {
            $total_lent_due += $rem;
        } else {
            $total_borrowed_due += $rem;
        }
    }
}

// Fetch Loan Payments History
$history_stmt = $conn->prepare("
    SELECT p.*, l.lender_name, l.loan_type 
    FROM loan_payments p 
    JOIN loans l ON p.loan_id = l.id 
    WHERE l.user_id = ? 
    ORDER BY p.payment_date DESC, p.id DESC
");
$history_stmt->execute([$user_id]);
$payment_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="dashboard.php">Expense Tracker</a>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm fw-semibold">Dashboard</a>
            <a href="loans.php" class="btn btn-info btn-sm text-white fw-semibold">Loans Management</a>
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

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-1 small">Total Money I Borrowed</h6>
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_borrowed_due, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-1 small">Total Money I Lent</h6>
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_lent_due, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- Add Loan Form -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold">Add New Loan</div>
                <div class="card-body">
                    <form action="loans.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Loan Type</label>
                            <select name="loan_type" class="form-select" required>
                                <option value="borrowed">Borrowed (I Took Money)</option>
                                <option value="lent">Lent (I Gave Money)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Person / Bank Name</label>
                            <input type="text" name="lender_name" class="form-control" placeholder="e.g. Kasun, Bank, Nimal" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Amount (<?php echo htmlspecialchars($currency); ?>)</label>
                            <input type="number" step="0.01" name="total_amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="loan_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date (Optional)</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <button type="submit" name="add_loan" class="btn btn-danger w-100 fw-semibold">Save Loan</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Record Payment Form -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold">Record Payment</div>
                <div class="card-body">
                    <form action="loans.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select Active Loan</label>
                            <select name="loan_id" class="form-select" required>
                                <option value="">Select Loan</option>
                                <?php foreach ($loans as $l): ?>
                                    <?php if ($l['status'] == 'active'): ?>
                                        <?php $l_type = ($l['loan_type'] ?? 'borrowed') === 'lent' ? 'Lent to' : 'Borrowed from'; ?>
                                        <option value="<?php echo $l['id']; ?>">
                                            [<?php echo $l_type; ?>] <?php echo htmlspecialchars($l['lender_name']); ?> (Due: <?php echo htmlspecialchars($currency); ?> <?php echo number_format($l['total_amount'] - $l['paid_amount'], 2); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount Paid (<?php echo htmlspecialchars($currency); ?>)</label>
                            <input type="number" step="0.01" name="amount_paid" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note (Optional)</label>
                            <input type="text" name="note" class="form-control" placeholder="e.g. 1st installment">
                        </div>
                        <button type="submit" name="add_payment" class="btn btn-success w-100 fw-semibold">Record Payment</button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- Active Loans Progress Section -->
    <div class="card border-0 shadow-sm my-4">
        <div class="card-header fw-bold">My Loans Overview</div>
        <div class="card-body">
            <?php if (count($loans) > 0): ?>
                <?php foreach ($loans as $l): ?>
                    <?php 
                        $remaining = $l['total_amount'] - $l['paid_amount'];
                        $percentage = ($l['total_amount'] > 0) ? round(($l['paid_amount'] / $l['total_amount']) * 100) : 0;
                        $is_overdue = ($l['status'] == 'active' && !empty($l['due_date']) && strtotime($l['due_date']) < strtotime(date('Y-m-d')));
                        $is_lent = (($l['loan_type'] ?? 'borrowed') === 'lent');
                    ?>
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="fw-bold mb-0">
                                <?php echo htmlspecialchars($l['lender_name']); ?>
                                <?php if ($is_lent): ?>
                                    <span class="badge bg-primary ms-1">Lent</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-1">Borrowed</span>
                                <?php endif; ?>

                                <?php if ($l['status'] == 'paid'): ?>
                                    <span class="badge bg-success ms-1">Settled</span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="badge bg-danger ms-1">OVERDUE</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-1">Active</span>
                                <?php endif; ?>
                            </h6>
                            <small class="text-muted">
                                Total: <?php echo htmlspecialchars($currency); ?> <?php echo number_format($l['total_amount'], 2); ?> | 
                                Balance: <strong class="<?php echo $is_lent ? 'text-primary' : 'text-danger'; ?>"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($remaining, 2); ?></strong>
                                <?php if (!empty($l['due_date'])): ?>
                                    | <span class="fw-bold">Due: <?php echo htmlspecialchars($l['due_date']); ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar <?php echo $is_lent ? 'bg-primary' : 'bg-success'; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $percentage; ?>% Settled
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center mb-0">No loan records found.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>