<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php"); exit;
}

$userId = $_SESSION['user_id'];
$cn_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cn_id == 0) { header("location: index.php"); exit; }

// Fetch details
$sql = "SELECT cn.*, c.name as customer_name, c.address as customer_address, i.invoice_number 
        FROM credit_notes cn 
        JOIN customers c ON cn.customer_id = c.id 
        JOIN invoices i ON cn.invoice_id = i.id 
        WHERE cn.id = :id AND cn.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $cn_id, 'user_id' => $userId]);
$credit_note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$credit_note) { header("location: index.php"); exit; }

// Fetch company settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE user_id = ? AND setting_key LIKE 'company_%'");
$settings_stmt->execute([$userId]);
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($key, $default = '') => htmlspecialchars($settings_raw[$key] ?? $default);

$pageTitle = 'Print Credit Note ' . htmlspecialchars($credit_note['credit_note_number']);
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
                <h2 class="text-4xl font-bold uppercase text-gray-800">Credit Note</h2>
                <p class="text-gray-500 mt-2">#<?php echo htmlspecialchars($credit_note['credit_note_number']); ?></p>
            </div>
        </header>

        <section class="mt-8 flex justify-between">
            <div>
                <h4 class="font-semibold text-gray-500">Credit For</h4>
                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($credit_note['customer_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($credit_note['customer_address'])); ?></p>
            </div>
            <div class="text-right">
                <p class="font-semibold text-gray-500">Date:</p>
                <p class="text-gray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($credit_note['credit_note_date']))); ?></p>
                 <p class="font-semibold text-gray-500 mt-2">Reference Invoice:</p>
                <p class="text-gray-800"><?php echo htmlspecialchars($credit_note['invoice_number']); ?></p>
            </div>
        </section>

        <section class="mt-10">
            <div class="text-center mb-6 bg-gray-50 p-8 rounded-lg">
                <p class="text-gray-500">Credit Amount</p>
                <p class="text-5xl font-bold text-green-600"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($credit_note['amount'], 2); ?></p>
            </div>
        </section>
        
        <?php if (!empty($credit_note['notes'])): ?>
        <section class="mt-10 pt-6 border-t">
            <h4 class="text-sm font-semibold text-gray-800">Notes</h4>
            <p class="text-sm text-gray-500 mt-2"><?php echo nl2br(htmlspecialchars($credit_note['notes'])); ?></p>
        </section>
        <?php endif; ?>

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