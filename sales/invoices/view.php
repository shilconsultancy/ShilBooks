<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ".BASE_PATH."index.php");
    exit;
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$errors = [];

if ($invoice_id == 0) {
    header("location: index.php");
    exit;
}

// Handle Record Payment Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_payment'])) {
    validate_csrf_token();
    $payment_amount = (float)$_POST['payment_amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $payment_notes = trim($_POST['payment_notes']);

    $stmt = $pdo->prepare("SELECT total, amount_paid FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_due = $invoice_data['total'] - $invoice_data['amount_paid'];

    if ($payment_amount <= 0 || $payment_amount > round($balance_due, 2)) {
        $errors[] = "Invalid payment amount. Must be between 0.01 and ".number_format($balance_due, 2);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $sql = "INSERT INTO payments (customer_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['customer_id'], $payment_date, $payment_amount, $payment_method, $payment_notes]);
            $payment_id = $pdo->lastInsertId();

            $sql_link = "INSERT INTO invoice_payments (payment_id, invoice_id, amount_applied) VALUES (?, ?, ?)";
            $pdo->prepare($sql_link)->execute([$payment_id, $invoice_id, $payment_amount]);

            $sql_update = "UPDATE invoices SET amount_paid = amount_paid + ? WHERE id = ?";
            $pdo->prepare($sql_update)->execute([$payment_amount, $invoice_id]);

            $sql_status = "UPDATE invoices SET status = 'paid' WHERE id = ? AND total <= amount_paid";
            $pdo->prepare($sql_status)->execute([$invoice_id]);

            $pdo->commit();
            $message = "Payment recorded successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: ".$e->getMessage();
        }
    }
}


// Fetch invoice details
$sql = "SELECT i.*, c.name as customer_name, c.email as customer_email, c.address as customer_address
        FROM invoices i
        JOIN customers c ON i.customer_id = c.id
        WHERE i.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("location: index.php");
    exit;
}
$balance_due = $invoice['total'] - $invoice['amount_paid'];

$items_sql = "SELECT ii.*, i.name as item_name FROM invoice_items ii JOIN items i ON ii.item_id = i.id WHERE ii.invoice_id = :invoice_id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['invoice_id' => $invoice_id]);
$invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'company_%'");
$settings_stmt->execute();
$settings_raw = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function ($key, $default = '') use ($settings_raw) {
    return htmlspecialchars($settings_raw[$key] ?? $default);
};
$payment_methods = ['Bank Transfer', 'Cash', 'Credit Card', 'Check', 'Mobile Banking', 'Online Payment Gateway'];

$pageTitle = 'View Invoice '.$invoice['invoice_number'];
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>
<style>
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
    .invoice-box h2 {
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
    .invoice-box table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .invoice-box table thead {
      background: #f0f0f0;
    }
    .invoice-box table th {
      padding: 10px;
      font-size: 13px;
      text-transform: uppercase;
      color: #333;
      border-bottom: 2px solid #ddd;
    }
    .invoice-box table td {
      padding: 10px;
      font-size: 13px;
      border-bottom: 1px solid #eee;
    }
    .invoice-box table tr:last-child td {
      border-bottom: none;
    }

    /* Alignment */
    .invoice-box table th:nth-child(1),
    .invoice-box table td:nth-child(1) {
      text-align: left;
    }
    .invoice-box table th:nth-child(2),
    .invoice-box table th:nth-child(3),
    .invoice-box table th:nth-child(4),
    .invoice-box table td:nth-child(2),
    .invoice-box table td:nth-child(3),
    .invoice-box table td:nth-child(4) {
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
</style>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Invoice: <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <div class="flex items-center space-x-2">
            <?php if ($balance_due > 0): ?>
            <button id="recordPaymentBtn" class="px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center space-x-2 text-sm"><i data-feather="dollar-sign" class="w-4 h-4"></i><span>Record Payment</span></button>
            <?php endif; ?>
            <a href="edit.php?id=<?php echo $invoice_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm"><i data-feather="edit-2" class="w-4 h-4"></i><span>Edit</span></a>
            <a href="print.php?id=<?php echo $invoice_id; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm"><i data-feather="printer" class="w-4 h-4"></i><span>Print</span></a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <?php if ($message): ?><div class="max-w-4xl mx-auto bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo $message; ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="max-w-4xl mx-auto bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $errors[0]; ?></div><?php endif; ?>
        
        <div class="invoice-box">
             <div class="top-bar">
                <div class="logo">
                    <?php $logoPath = $s('company_logo') ? BASE_PATH.'uploads/company/'.$s('company_logo') : 'https://scontent.fcgp29-1.fna.fbcdn.net/v/t39.30808-6/375987847_7182796938414760_3494980128420191368_n.jpg?_nc_cat=101&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeFy-dv2v6XVMQseac9nN8ZaoECahBEJ8CSgQJqEEQnwJFm9iZAVgmwP-h7UF1HEorOemwno7VwmMe2HnXsljLX6&_nc_ohc=z6CQW_q9aAkQ7kNvwHYIwJY&_nc_oc=Adk-IbY31kKBN-OWCfWT_7JISUs271MdfpjpitHRaSpU5TQRkx-WR3WL7pwtMtBZrg8&_nc_zt=23&_nc_ht=scontent.fcgp29-1.fna&_nc_gid=oK0VdhqfSxssWS90xyEy9g&oh=00_AfemJ1g4PyPEvYavYYgPbRjzqBPwWlWZrsITnC2hG6Ue9w&oe=68E65F4A'; ?>
                    <img src="<?php echo $logoPath; ?>" alt="Company Logo">
                </div>
                <div class="invoice-details">
                    <h2>Invoice</h2>
                    <strong>No:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?><br>
                    <strong>Date:</strong> <?php echo date("d/m/Y", strtotime($invoice['invoice_date'])); ?><br>
                    <strong>Due:</strong> <?php echo date("d/m/Y", strtotime($invoice['due_date'])); ?>
                </div>
            </div>

            <div class="billing">
                <div class="bill-from">
                    <strong>Bill From:</strong><br>
                    <?php echo $s('company_name', 'Your Company'); ?><br>
                    <?php echo nl2br($s('company_address')); ?><br>
                    Phone: <?php echo $s('company_phone'); ?><br>
                    Email: <?php echo $s('company_email'); ?>
                </div>
                <div class="bill-to">
                    <strong>Bill To:</strong><br>
                    <?php echo htmlspecialchars($invoice['customer_name']); ?><br>
                    <?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?><br>
                    <?php echo htmlspecialchars($invoice['customer_email']); ?>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['price'], 2); ?></td>
                        <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <td class="label">Tax</td>
                    <td class="value"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['tax'], 2); ?></td>
                </tr>
                <tr>
                    <td class="label"><strong>Grand Total</strong></td>
                    <td class="value"><strong><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['total'], 2); ?></strong></td>
                </tr>
                 <tr>
                    <td class="label">Amount Paid</td>
                    <td class="value"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($invoice['amount_paid'], 2); ?></td>
                </tr>
                <tr>
                    <td class="label"><strong>Balance Due</strong></td>
                    <td class="value"><strong><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($balance_due, 2); ?></strong></td>
                </tr>
            </table>

            <div class="payment-details">
                <?php if (!empty($invoice['notes'])): ?>
                    <strong>Notes:</strong><br>
                    <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?><br><br>
                <?php endif; ?>
                <strong>Payment Details:</strong><br>
                Bank: Example Bank<br>
                Account: Company Name<br>
                Account No: 123456789<br>
                SWIFT: EXAMP123
            </div>

            <div class="footer">
                Thank you for your business! <br>
                This is a computer-generated invoice.
            </div>
        </div>
    </main>
</div>

<div id="paymentModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="view.php?id=<?php echo $invoice_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="customer_id" value="<?php echo $invoice['customer_id']; ?>">
                <input type="hidden" name="record_payment" value="1">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Record Payment</h3>
                     <div class="mt-4 space-y-4">
                        <div><label for="payment_amount" class="block text-sm font-medium text-gray-700">Amount*</label><input type="number" name="payment_amount" id="payment_amount" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" max="<?php echo number_format($balance_due, 2, '.', ''); ?>" step="0.01" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date*</label><input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                            <div><label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label><select name="payment_method" id="payment_method" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php foreach ($payment_methods as $method) echo "<option value='".htmlspecialchars($method)."'>".htmlspecialchars($method)."</option>"; ?></select></div>
                        </div>
                         <div><label for="payment_notes" class="block text-sm font-medium text-gray-700">Notes</label><textarea name="payment_notes" id="payment_notes" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save Payment</button>
                    <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('paymentModal');
    const recordPaymentBtn = document.getElementById('recordPaymentBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    if (recordPaymentBtn) recordPaymentBtn.addEventListener('click', () => modal.classList.remove('hidden'));
    if(closeModalBtn) closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
});
</script>

<?php require_once '../../partials/footer.php'; ?>