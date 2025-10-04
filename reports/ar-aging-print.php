<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ".BASE_PATH."index.php");
    exit;
}

$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// --- A/R Aging Calculations ---
try {
    $sql = "SELECT i.id, i.invoice_number, i.due_date, (i.total - i.amount_paid) as balance_due, c.name as customer_name, DATEDIFF(:as_of_date, i.due_date) as days_overdue
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            WHERE i.status IN ('sent', 'overdue') AND i.invoice_date <= :as_of_date";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['as_of_date' => $as_of_date]);
    $unpaid_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $unpaid_invoices = [];
}

$buckets = ['Current' => [], '1 - 30 Days' => [], '31 - 60 Days' => [], '61 - 90 Days' => [], '91+ Days' => []];
$totals = array_fill_keys(array_keys($buckets), 0);
$grand_total = 0;

foreach ($unpaid_invoices as $invoice) {
    $days = $invoice['days_overdue'];
    $balance = $invoice['balance_due'];
    $grand_total += $balance;

    if ($days <= 0) { $buckets['Current'][] = $invoice; $totals['Current'] += $balance; } 
    elseif ($days <= 30) { $buckets['1 - 30 Days'][] = $invoice; $totals['1 - 30 Days'] += $balance; } 
    elseif ($days <= 60) { $buckets['31 - 60 Days'][] = $invoice; $totals['31 - 60 Days'] += $balance; } 
    elseif ($days <= 90) { $buckets['61 - 90 Days'][] = $invoice; $totals['61 - 90 Days'] += $balance; } 
    else { $buckets['91+ Days'][] = $invoice; $totals['91+ Days'] += $balance; }
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

$pageTitle = 'Print A/R Aging Report';
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
                <h2>A/R Aging Summary</h2>
                <strong>As of:</strong> <?php echo htmlspecialchars(date("F d, Y", strtotime($as_of_date))); ?>
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
                            <td style="padding: 5px; font-size: 12px; color: #666;"><strong>Total Outstanding:</strong></td>
                            <td style="padding: 5px; text-align: right; font-size: 14px; font-weight: bold; color: #dc3545;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Aging Summary Table -->
        <div style="margin-top: 30px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 5px;">Aging Summary</h3>
            <table style="width: 100%; margin-bottom: 30px;">
                <thead>
                    <tr>
                        <?php foreach (array_keys($buckets) as $bucket_name): ?>
                        <th style="text-align: right;"><?php echo $bucket_name; ?></th>
                        <?php endforeach; ?>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($totals as $total): ?>
                        <td style="text-align: right;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($total, 2); ?></td>
                        <?php endforeach; ?>
                        <td style="text-align: right; font-weight: bold;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Detailed Breakdown -->
        <div style="margin-top: 30px;">
            <?php foreach ($buckets as $bucket_name => $invoices): ?>
                <?php if (!empty($invoices)): ?>
                <div style="margin-bottom: 30px;">
                    <h3 style="font-size: 14px; font-weight: bold; color: #333; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;"><?php echo $bucket_name; ?> - <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totals[$bucket_name], 2); ?></h3>
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Invoice #</th>
                                <th>Due Date</th>
                                <th>Balance Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($invoice['due_date']))); ?></td>
                                    <td style="text-align: right;"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['balance_due'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?><br>
            <?php echo $s('company_address'); ?><br>
            This is a computer-generated aging report.
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