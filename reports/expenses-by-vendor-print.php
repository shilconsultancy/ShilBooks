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
    <title>Expenses by Vendor Report - <?php echo htmlspecialchars(date("F d, Y", strtotime($start_date))); ?> to <?php echo htmlspecialchars(date("F d, Y", strtotime($end_date))); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
        }
        .invoice-box {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            padding: 25mm 20mm;
            margin: 20px auto;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,.1);
            font-size: 14px;
            color: #333;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 120px;
            border-radius: 6px;
        }
        .invoice-details {
            text-align: right;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }
        h2 {
            margin-top: 0;
            font-size: 22px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .billing {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .billing .bill-from,
        .billing .bill-to {
            width: 48%;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table thead {
            background: #f0f0f0;
        }
        table th {
            padding: 10px;
            font-size: 13px;
            text-transform: uppercase;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        table td {
            padding: 10px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }
        table tr:last-child td {
            border-bottom: none;
        }

        /* Alignment */
        table th:nth-child(1),
        table td:nth-child(1) {
            text-align: left;
        }
        table th:nth-child(2),
        table th:nth-child(3),
        table th:nth-child(4),
        table td:nth-child(2),
        table td:nth-child(3),
        table td:nth-child(4) {
            text-align: right;
        }

        .totals {
            margin-top: 20px;
            width: 300px;
            float: right;
            border-collapse: collapse;
        }
        .totals td {
            padding: 8px;
            border-top: 1px solid #eee;
        }
        .totals .label {
            text-align: left;
            font-weight: bold;
            background: #f9f9f9;
        }
        .totals .value {
            text-align: right;
        }
        .payment-details {
            clear: both;
            margin-top: 50px;
            font-size: 13px;
            color: #555;
        }
        .footer {
            margin-top: 40px;
            font-size: 12px;
            text-align: center;
            color: #888;
        }
        @media print {
            body {
                background: #fff;
            }
            .invoice-box {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
            table thead {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .totals .label {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">

        <!-- Header -->
        <div class="top-bar">
            <div class="logo">
                <?php
                $logo_file = '';
                $logoPath = 'https://scontent.fcgp29-1.fna.fbcdn.net/v/t39.30808-6/375987847_7182796938414760_3494980128420191368_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFy-dv2v6XVMQseac9nN8ZaoECahBEJ8CSgQJqEEQnwJFm9iZAVgmwP-h7UF1HEorOemwno7VwmMe2HnXsljLX6&_nc_ohc=z6CQW_q9aAkQ7kNvwHYIwJY&_nc_oc=Adk-IbY31kKBN-OWCfWT_7JISUs271MdfpjpitHRaSpU5TQRkx-WR3WL7pwtMtBZrg8&_nc_zt=23&_nc_ht=scontent.fcgp29-1.fna&_nc_gid=oK0VdhqfSxssWS90xyEy9g&oh=00_AfemJ1g4PyPEvYavYYgPbRjzqBPwWlWZrsITnC2hG6Ue9w&oe=68E65F4A';
                try {
                    $logo_setting = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'");
                    $logo_setting->execute();
                    $logo_file = $logo_setting->fetchColumn();
                    $logoPath = $logo_file ? BASE_PATH . 'uploads/company/' . $logo_file : 'https://scontent.fcgp29-1.fna.fbcdn.net/v/t39.30808-6/375987847_7182796938414760_3494980128420191368_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFy-dv2v6XVMQseac9nN8ZaoECahBEJ8CSgQJqEEQnwJFm9iZAVgmwP-h7UF1HEorOemwno7VwmMe2HnXsljLX6&_nc_ohc=z6CQW_q9aAkQ7kNvwHYIwJY&_nc_oc=Adk-IbY31kKBN-OWCfWT_7JISUs271MdfpjpitHRaSpU5TQRkx-WR3WL7pwtMtBZrg8&_nc_zt=23&_nc_ht=scontent.fcgp29-1.fna&_nc_gid=oK0VdhqfSxssWS90xyEy9g&oh=00_AfemJ1g4PyPEvYavYYgPbRjzqBPwWlWZrsITnC2hG6Ue9w&oe=68E65F4A';
                } catch (Exception $e) {
                    // Use default logo path if database query fails
                }
                ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo">
            </div>
            <div class="invoice-details">
                <h2>Expenses by Vendor Report</h2>
                <strong>Period:</strong> <?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))); ?> - <?php echo htmlspecialchars(date("M d, Y", strtotime($end_date))); ?>
            </div>
        </div>

        <!-- Company Info -->
        <div class="billing">
            <div class="bill-from">
                <strong>Report Generated By:</strong><br>
                <?php echo $s('company_name', 'Your Company'); ?><br>
                <?php echo nl2br($s('company_address')); ?><br>
                Phone: <?php echo $s('company_phone'); ?><br>
                Email: <?php echo $s('company_email'); ?>
            </div>
            <div class="bill-to">
                <!-- Summary -->
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 5px; font-size: 12px; color: #666;"><strong>Total Expenses:</strong></td>
                            <td style="padding: 5px; text-align: right; font-size: 14px; font-weight: bold; color: #dc3545;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px; font-size: 12px; color: #666;"><strong>Transactions:</strong></td>
                            <td style="padding: 5px; text-align: right; font-size: 14px; font-weight: bold; color: #333;"><?php echo number_format($grand_transaction_count); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Summary Table -->
        <div style="margin-top: 30px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 5px;">Expense Summary</h3>
            <table style="width: 100%; margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Vendor/Category</th>
                        <th>Transactions</th>
                        <th>Total Amount</th>
                        <th>Avg Amount</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendor_expenses as $vendor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vendor['vendor_name']); ?></td>
                            <td><?php echo number_format($vendor['transaction_count']); ?></td>
                            <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['total_amount'], 2); ?></td>
                            <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['avg_amount'], 2); ?></td>
                            <td><?php echo $grand_total > 0 ? number_format(($vendor['total_amount'] / $grand_total) * 100, 1) : '0.0'; ?>%</td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Grand Total Row -->
                    <tr style="border-top: 2px solid #333;">
                        <td><strong>TOTAL</strong></td>
                        <td><strong><?php echo number_format($grand_transaction_count); ?></strong></td>
                        <td><strong><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></strong></td>
                        <td></td>
                        <td><strong>100.0%</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Detailed Transactions -->
        <div style="margin-top: 30px;">
            <?php foreach ($vendor_expenses as $vendor): ?>
                <?php
                $vendor_id = $vendor['vendor_name'];
                $transactions = $vendor_details[$vendor_id] ?? [];
                ?>
                <div style="margin-bottom: 30px;">
                    <h3 style="font-size: 14px; font-weight: bold; color: #333; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;"><?php echo htmlspecialchars($vendor['vendor_name']); ?> (<?php echo number_format($vendor['transaction_count']); ?> transactions, <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($vendor['total_amount'], 2); ?>)</h3>

                    <?php if (!empty($transactions)): ?>
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Notes</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($transaction['expense_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></td>
                                    <td style="text-align: right;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($transaction['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; color: #666; font-style: italic;">No transactions found for this vendor in the selected period.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($vendor_expenses)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>No expenses found for this period.</p>
            <p style="font-size: 12px; margin-top: 10px;">This report will show vendor expenses as they are added to the system.</p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?><br>
            <?php echo $s('company_address'); ?><br>
            Report generated on <?php echo date('F d, Y \a\t g:i A'); ?><br>
            This is a computer-generated expense report.
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>