<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Filter ---
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// --- A/P Aging Calculations ---
try {
    // Fetch all unpaid expenses/bills created on or before the "as of" date
    // Note: This is a placeholder since we don't have a full vendor/bills system yet
    $sql = "SELECT
                'Expense' as type,
                e.id,
                ec.name as vendor_name,
                e.expense_date as due_date,
                e.amount as amount,
                e.description,
                DATEDIFF(:as_of_date, e.expense_date) as days_overdue
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.expense_date <= :as_of_date
            AND e.amount > 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['as_of_date' => $as_of_date]);
    $unpaid_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare buckets for categorizing bills
    $buckets = [
        'Current' => [],
        '1 - 30 Days' => [],
        '31 - 60 Days' => [],
        '61 - 90 Days' => [],
        '91+ Days' => [],
    ];
    $totals = array_fill_keys(array_keys($buckets), 0);
    $grand_total = 0;

    foreach ($unpaid_bills as $bill) {
        $days = $bill['days_overdue'];
        $amount = $bill['amount'];
        $grand_total += $amount;

        if ($days <= 0) {
            $buckets['Current'][] = $bill;
            $totals['Current'] += $amount;
        } elseif ($days >= 1 && $days <= 30) {
            $buckets['1 - 30 Days'][] = $bill;
            $totals['1 - 30 Days'] += $amount;
        } elseif ($days >= 31 && $days <= 60) {
            $buckets['31 - 60 Days'][] = $bill;
            $totals['31 - 60 Days'] += $amount;
        } elseif ($days >= 61 && $days <= 90) {
            $buckets['61 - 90 Days'][] = $bill;
            $totals['61 - 90 Days'] += $amount;
        } else {
            $buckets['91+ Days'][] = $bill;
            $totals['91+ Days'] += $amount;
        }
    }

} catch (Exception $e) {
    $unpaid_bills = [];
    $buckets = array_fill_keys(['Current', '1 - 30 Days', '31 - 60 Days', '61 - 90 Days', '91+ Days'], []);
    $totals = array_fill_keys(array_keys($buckets), 0);
    $grand_total = 0;
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/P Aging Report - <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?></title>
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
            <h1 class="text-xl font-bold text-gray-900 mb-2">Accounts Payable Aging Report</h1>
            <p class="text-gray-600">As of <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?></p>
        </div>

        <!-- Summary Table -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-3">A/P Aging Summary</h2>
            <table class="min-w-full">
                <thead>
                    <tr>
                        <?php foreach (array_keys($buckets) as $bucket_name): ?>
                        <th class="text-right"><?php echo $bucket_name; ?></th>
                        <?php endforeach; ?>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($totals as $total): ?>
                        <td class="text-right font-medium"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($total, 2); ?></td>
                        <?php endforeach; ?>
                        <td class="text-right font-bold"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Detail Tables -->
        <div class="space-y-6">
            <?php foreach ($buckets as $bucket_name => $bills): ?>
                <?php if (!empty($bills)): ?>
                <div class="print-break-inside-avoid">
                    <h3 class="text-base font-semibold mb-2 pb-1 border-b"><?php echo $bucket_name; ?></h3>
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Vendor/Category</th>
                                <th class="text-left">Description</th>
                                <th class="text-left">Date</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill['vendor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['description']); ?></td>
                                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($bill['due_date']))); ?></td>
                                    <td class="text-right"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($bill['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (empty($unpaid_bills)): ?>
        <div class="text-center py-8 text-gray-500">
            <p>No accounts payable found for this period.</p>
            <p class="text-sm mt-2">This report will show vendor bills and other payables as they are added to the system.</p>
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