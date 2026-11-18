<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Set HTTP headers to force CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=expense_report_' . date('Y-m-d') . '.csv');

// Open the output stream
$output = fopen('php://output', 'w');

// Output column headers
fputcsv($output, ['ID', 'Date', 'Type', 'Category', 'Amount (Rs)', 'Description']);

// Fetch user transactions
$stmt = $conn->prepare("
    SELECT t.id, t.transaction_date, t.type, c.name AS category_name, t.amount, t.description
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$user_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['transaction_date'],
        ucfirst($row['type']),
        $row['category_name'],
        $row['amount'],
        $row['description']
    ]);
}

fclose($output);
exit();
?>