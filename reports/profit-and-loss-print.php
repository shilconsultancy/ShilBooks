<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

// --- Handle Date Range ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- Report Data Fetching ---
try {
    $paid_invoices_total_stmt = $pdo->prepare("SELECT SUM(total) FROM invoices WHERE status = 'paid' AND invoice_date BETWEEN ? AND ?");
    $paid_invoices_total_stmt->execute([$start_date, $end_date]);
    $grossRevenue = $paid_invoices_total_stmt->fetchColumn() ?? 0;
    
    // Note: Sales receipts feature has been removed from this project

    $credit_notes_stmt = $pdo->prepare("SELECT SUM(amount) FROM credit_notes WHERE credit_note_date BETWEEN ? AND ?");
    $credit_notes_stmt->execute([$start_date, $end_date]);
    $totalCredits = $credit_notes_stmt->fetchColumn() ?? 0;
    $netRevenue = $grossRevenue - $totalCredits;

    $expenses_sql = "SELECT ec.name as category_name, SUM(e.amount) as total_amount
                     FROM expenses e
                     JOIN expense_categories ec ON e.category_id = ec.id
                     WHERE e.expense_date BETWEEN ? AND ?
                     GROUP BY e.category_id
                     ORDER BY ec.name ASC";
    $expenses_stmt = $pdo->prepare($expenses_sql);
    $expenses_stmt->execute([$start_date, $end_date]);
    $expensesByCategory = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalExpenses = array_sum(array_column($expensesByCategory, 'total_amount'));
    $netProfit = $netRevenue - $totalExpenses;
} catch (Exception $e) {
    // Handle database errors gracefully
    $grossRevenue = 0;
    $totalCredits = 0;
    $netRevenue = 0;
    $expensesByCategory = [];
    $totalExpenses = 0;
    $netProfit = 0;
}

// Fetch company settings
try {
    $settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings_raw = [];
}
$s = function($key, $default = '') use ($settings_raw) { return htmlspecialchars($settings_raw[$key] ?? $default); };

$pageTitle = 'Print Profit & Loss';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
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
                <h2>Profit & Loss Statement</h2>
                <strong>Period:</strong> <?php echo htmlspecialchars(date("M d, Y", strtotime($start_date))) . " - " . htmlspecialchars(date("M d, Y", strtotime($end_date))); ?>
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
                            <td style="padding: 5px; font-size: 12px; color: #666;"><strong>Net Profit/Loss:</strong></td>
                            <td style="padding: 5px; text-align: right; font-size: 16px; font-weight: bold; color: <?php echo $netProfit >= 0 ? '#28a745' : '#dc3545'; ?>;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netProfit, 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Revenue Section -->
        <div style="margin-top: 30px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 5px;">Revenue</h3>
            <table style="width: 100%; margin-bottom: 20px;">
                <tr>
                    <td>Gross Sales (Invoices)</td>
                    <td style="text-align: right;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grossRevenue, 2); ?></td>
                </tr>
                <tr>
                    <td>Less: Returns & Allowances (Credit Notes)</td>
                    <td style="text-align: right;">(<?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalCredits, 2); ?>)</td>
                </tr>
            </table>
            <table class="totals">
                <tr>
                    <td class="label">Net Revenue</td>
                    <td class="value"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netRevenue, 2); ?></td>
                </tr>
            </table>
        </div>

        <!-- Expenses Section -->
        <div style="margin-top: 40px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 5px;">Expenses</h3>
            <?php if(empty($expensesByCategory)): ?>
                <p style="text-align: center; color: #666; font-style: italic;">No expenses recorded for this period.</p>
            <?php else: ?>
                <table style="width: 100%; margin-bottom: 20px;">
                    <?php foreach($expensesByCategory as $expense): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                        <td style="text-align: right;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($expense['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            <table class="totals">
                <tr>
                    <td class="label">Total Expenses</td>
                    <td class="value"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalExpenses, 2); ?></td>
                </tr>
            </table>
        </div>

        <!-- Net Profit/Loss Section -->
        <div style="margin-top: 40px; padding-top: 20px; border-top: 3px solid #333;">
            <table class="totals">
                <tr>
                    <td class="label" style="font-size: 16px; background: <?php echo $netProfit >= 0 ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $netProfit >= 0 ? '#155724' : '#721c24'; ?>;">Net Profit / (Loss)</td>
                    <td class="value" style="font-size: 16px; font-weight: bold; color: <?php echo $netProfit >= 0 ? '#28a745' : '#dc3545'; ?>;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($netProfit, 2); ?></td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?><br>
            <?php echo $s('company_address'); ?><br>
            This is a computer-generated financial report.
        </div>
    </div>

    <div class="fixed bottom-5 right-5 no-print">
        <button onclick="window.print()" class="p-4 bg-black text-white rounded-full shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
             <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-printer"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        </button>
    </div>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>