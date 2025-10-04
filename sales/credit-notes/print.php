<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php"); exit;
}

$cn_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cn_id == 0) { header("location: index.php"); exit; }

// Fetch details
$sql = "SELECT cn.*, c.name as customer_name, c.address as customer_address, i.invoice_number 
        FROM credit_notes cn 
        JOIN customers c ON cn.customer_id = c.id 
        JOIN invoices i ON cn.invoice_id = i.id 
        WHERE cn.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $cn_id]);
$credit_note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$credit_note) { header("location: index.php"); exit; }

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function($key, $default = '') { return htmlspecialchars($settings_raw[$key] ?? $default); };

$pageTitle = 'Print Credit Note ' . htmlspecialchars($credit_note['credit_note_number']);
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
                $logo_setting = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'");
                $logo_setting->execute();
                $logo_file = $logo_setting->fetchColumn();
                $logoPath = $logo_file ? BASE_PATH . 'uploads/company/' . $logo_file : 'https://scontent.fcgp29-1.fna.fbcdn.net/v/t39.30808-6/375987847_7182796938414760_3494980128420191368_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFy-dv2v6XVMQseac9nN8ZaoECahBEJ8CSgQJqEEQnwJFm9iZAVgmwP-h7UF1HEorOemwno7VwmMe2HnXsljLX6&_nc_ohc=z6CQW_q9aAkQ7kNvwHYIwJY&_nc_oc=Adk-IbY31kKBN-OWCfWT_7JISUs271MdfpjpitHRaSpU5TQRkx-WR3WL7pwtMtBZrg8&_nc_zt=23&_nc_ht=scontent.fcgp29-1.fna&_nc_gid=oK0VdhqfSxssWS90xyEy9g&oh=00_AfemJ1g4PyPEvYavYYgPbRjzqBPwWlWZrsITnC2hG6Ue9w&oe=68E65F4A';
                ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo">
            </div>
            <div class="invoice-details">
                <h2>Credit Note</h2>
                <strong>No:</strong> <?php echo htmlspecialchars($credit_note['credit_note_number']); ?><br>
                <strong>Date:</strong> <?php echo date("d/m/Y", strtotime($credit_note['credit_note_date'])); ?><br>
                <strong>Ref Invoice:</strong> <?php echo htmlspecialchars($credit_note['invoice_number']); ?>
            </div>
        </div>

        <!-- Bill From / To -->
        <div class="billing">
            <div class="bill-from">
                <strong>Bill From:</strong><br>
                <?php echo $s('company_name', 'Your Company'); ?><br>
                <?php echo nl2br($s('company_address')); ?><br>
                Phone: <?php echo $s('company_phone'); ?><br>
                Email: <?php echo $s('company_email'); ?>
            </div>
            <div class="bill-to">
                <strong>Credit For:</strong><br>
                <?php echo htmlspecialchars($credit_note['customer_name']); ?><br>
                <?php echo nl2br(htmlspecialchars($credit_note['customer_address'])); ?>
            </div>
        </div>

        <!-- Credit Amount -->
        <table class="totals">
            <tr>
                <td class="label" style="font-size: 16px;">Credit Amount</td>
                <td class="value" style="font-size: 16px;"><strong><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($credit_note['amount'], 2); ?></strong></td>
            </tr>
        </table>

        <?php if (!empty($credit_note['notes'])): ?>
        <div class="payment-details">
            <strong>Notes:</strong><br>
            <?php echo nl2br(htmlspecialchars($credit_note['notes'])); ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            Thank you for your business! <br>
            This is a computer-generated credit note.
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