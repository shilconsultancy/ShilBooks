<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id == 0) {
    die("Invalid payment ID.");
}

// Fetch payment details
$sql = "SELECT p.*, c.name as customer_name, c.address as customer_address
        FROM payments p
        JOIN customers c ON p.customer_id = c.id
        WHERE p.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Payment not found.");
}

// Fetch linked invoices
$invoices_sql = "SELECT ip.amount_applied, i.invoice_number, i.invoice_date, i.total 
                 FROM invoice_payments ip
                 JOIN invoices i ON ip.invoice_id = i.id
                 WHERE ip.payment_id = :payment_id";
$invoices_stmt = $pdo->prepare($invoices_sql);
$invoices_stmt->execute(['payment_id' => $payment_id]);
$linked_invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($key, $default = '') => htmlspecialchars($settings_raw[$key] ?? $default);

$pageTitle = 'Print Payment Receipt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto my-8 bg-white p-10 shadow-lg">
        
        <header class="flex justify-between items-start pb-4 border-b">
            <div class="w-1/2 flex justify-left">
                <?php $logoPath = BASE_PATH . 'uploads/1/logo.png'; ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" class="h-20 w-auto">
            </div>
            <div class="w-1/2 text-right">
                <h2 class="text-4xl font-bold uppercase text-gray-800">Payment Receipt</h2>
                <p class="text-gray-500 mt-2">Ref #: <?php echo htmlspecialchars($payment['id']); ?></p>
            </div>
        </header>

        <section class="mt-8 flex justify-between">
            <div>
                <h4 class="font-semibold text-gray-500">Payment From</h4>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($payment['customer_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($payment['customer_address'])); ?></p>
            </div>
            <div class="text-right">
                 <div class="grid grid-cols-2 gap-x-4">
                    <p class="font-semibold text-gray-500">Payment Date:</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($payment['payment_date']))); ?></p>
                    <p class="font-semibold text-gray-500">Payment Method:</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars($payment['payment_method']); ?></p>
                </div>
            </div>
        </section>

        <section class="mt-10">
            <div class="text-center mb-6">
                <p class="text-gray-500">Amount Received</p>
                <p class="text-5xl font-bold text-green-600"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($payment['amount'], 2); ?></p>
            </div>
            
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Summary of Payment</h3>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount Applied</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($linked_invoices as $link): ?>
                    <tr>
                        <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($link['invoice_number']); ?></td>
                        <td class="px-6 py-4 text-gray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($link['invoice_date']))); ?></td>
                        <td class="px-6 py-4 text-right font-medium text-gray-800"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($link['amount_applied'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <footer class="text-center mt-12 pt-6 border-t text-gray-500 text-sm">
             <p><?php echo $s('company_name'); ?> | <?php echo $s('company_email'); ?> | <?php echo $s('company_phone'); ?></p>
             <p><?php echo $s('company_address'); ?></p>
        </footer>
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