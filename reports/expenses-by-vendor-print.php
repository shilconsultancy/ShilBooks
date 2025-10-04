<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Filter ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- Expenses by Vendor Calculations ---
try {
    // Fetch expenses grouped by vendor/category for the date range
    $sql = "SELECT
                ec.name as vendor_name,
                COUNT(e.id) as transaction_count,
                SUM(e.amount) as total_amount,
                AVG(e.amount) as avg_amount,
                MIN(e.expense_date) as first_expense_date,
                MAX(e.expense_date) as last_expense_date
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.expense_date BETWEEN :start_date AND :end_date
            GROUP BY ec.id, ec.name
            ORDER BY total_amount DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    $vendor_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate grand totals
    $grand_total = 0;
    $grand_transaction_count = 0;

    foreach ($vendor_expenses as $vendor) {
        $grand_total += $vendor['total_amount'];
        $grand_transaction_count += $vendor['transaction_count'];
    }

    // Fetch detailed transactions for each vendor
    $vendor_details = [];
    foreach ($vendor_expenses as $vendor) {
        $vendor_id = $vendor['vendor_name']; // Using name as key since we don't have vendor_id

        $detail_sql = "SELECT
                        e.expense_date,
                        e.description,
                        e.amount,
                        e.notes
                    FROM expenses e
                    JOIN expense_categories ec ON e.category_id = ec.id
                    WHERE ec.name = :vendor_name
                    AND e.expense_date BETWEEN :start_date AND :end_date
                    ORDER BY e.expense_date DESC";

        $detail_stmt = $pdo->prepare($detail_sql);
        $detail_stmt->execute([
            'vendor_name' => $vendor['vendor_name'],
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        $vendor_details[$vendor_id] = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $vendor_expenses = [];
    $vendor_details = [];
    $grand_total = 0;
    $grand_transaction_count = 0;
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses by Vendor Report - <?php echo htmlspecialchars(date("F d, Y", strtotime($start_date))); ?> to <?php echo htmlspecialchars(date("F d, Y", strtotime($end_date))); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .print-break-inside-avoid { break-inside: avoid; }
        }
        body { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 8px; border: 1px solid #ddd; }
        th { background-color: #f5f5f5; font-weight: bold; }
    </style>
</head>
<body class="bg-white">
    <div class="max-w-4xl mx-auto p-6">
        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="text-center mb-6 pb-4 border-b-2 border-gray-300">
            <h1 class="text-xl font-bold text-gray-900 mb-2">Expenses by Vendor Report</h1>
            <p class="text-gray-600"><?php echo htmlspecialchars(date("F d, Y", strtotime($start_date))); ?> - <?php echo htmlspecialchars(date("F d, Y", strtotime($end_date))); ?></p>
        </div>

        <!-- Summary Table -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-3">Summary</h2>
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="text-left">Vendor/Category</th>
                        <th class="text-right">Transactions</th>
                        <th class="text-right">Total Amount</th>
                        <th class="text-right">Avg Amount</th>
                        <th class="text-right">% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendor_expenses as $vendor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vendor['vendor_name']); ?></td>
                            <td class="text-right"><?php echo number_format($vendor['transaction_count']); ?></td>
                            <td class="text-right font-medium"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['total_amount'], 2); ?></td>
                            <td class="text-right"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['avg_amount'], 2); ?></td>
                            <td class="text-right"><?php echo $grand_total > 0 ? number_format(($vendor['total_amount'] / $grand_total) * 100, 1) : '0.0'; ?>%</td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Grand Total Row -->
                    <tr class="border-t-2">
                        <td class="font-bold">TOTAL</td>
                        <td class="text-right font-bold"><?php echo number_format($grand_transaction_count); ?></td>
                        <td class="text-right font-bold"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                        <td class="text-right font-bold"></td>
                        <td class="text-right font-bold">100.0%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Detailed Transactions -->
        <div class="space-y-6">
            <?php foreach ($vendor_expenses as $vendor): ?>
                <?php
                $vendor_id = $vendor['vendor_name'];
                $transactions = $vendor_details[$vendor_id] ?? [];
                ?>
                <div class="print-break-inside-avoid">
                    <h3 class="text-base font-semibold mb-2 pb-1 border-b">
                        <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                        <span class="text-sm font-normal ml-2">
                            (<?php echo number_format($vendor['transaction_count']); ?> transactions, <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['total_amount'], 2); ?>)
                        </span>
                    </h3>

                    <?php if (!empty($transactions)): ?>
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Date</th>
                                <th class="text-left">Description</th>
                                <th class="text-left">Notes</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($transaction['expense_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></td>
                                    <td class="text-right"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($transaction['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-sm italic">No transactions found for this vendor in the selected period.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($vendor_expenses)): ?>
        <div class="text-center py-8 text-gray-500">
            <p>No expenses found for this period.</p>
            <p class="text-sm mt-2">This report will show vendor expenses as they are added to the system.</p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-8 pt-4 border-t text-center text-gray-500 text-xs">
            <p>Report generated on <?php echo date('F d, Y \a\t g:i A'); ?></p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>