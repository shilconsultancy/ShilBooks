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
    header("location: index.php");
    exit;
}

// Fetch payment details
$sql = "SELECT p.*, c.name as customer_name FROM payments p JOIN customers c ON p.customer_id = c.id WHERE p.id = :id AND p.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $payment_id, 'user_id' => $userId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header("location: index.php");
    exit;
}

// Fetch linked invoices
$invoices_sql = "SELECT ip.amount_applied, i.invoice_number, i.invoice_date, i.total 
                 FROM invoice_payments ip
                 JOIN invoices i ON ip.invoice_id = i.id
                 WHERE ip.payment_id = :payment_id";
$invoices_stmt = $pdo->prepare($invoices_sql);
$invoices_stmt->execute(['payment_id' => $payment_id]);
$linked_invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'View Payment';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Payment Details</h1>
        <a href="<?php echo BASE_PATH; ?>sales/payments/" class="text-sm text-macblue-600 hover:text-macblue-800">
            &larr; Back to All Payments
        </a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 border-b pb-6">
                    <div>
                        <p class="text-sm font-semibold text-macgray-500">Customer</p>
                        <p class="text-lg font-medium text-macgray-800"><?php echo htmlspecialchars($payment['customer_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-macgray-500">Payment Date</p>
                        <p class="text-lg font-medium text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($payment['payment_date']))); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-macgray-500">Amount Paid</p>
                        <p class="text-2xl font-bold text-green-600">৳<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6">
                    <div>
                        <p class="text-sm font-semibold text-macgray-500">Payment Method</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars($payment['payment_method'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm font-semibold text-macgray-500">Notes</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars($payment['notes'] ?: 'No notes.'); ?></p>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-medium text-macgray-800 mb-4">Payment Applied To</h3>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="min-w-full">
                            <thead class="bg-macgray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-macgray-500 uppercase">Invoice #</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-macgray-500 uppercase">Invoice Date</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-macgray-500 uppercase">Amount Applied</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-macgray-200">
                                <?php if (empty($linked_invoices)): ?>
                                    <tr><td colspan="3" class="p-4 text-center text-gray-500">This payment has not been applied to any invoices.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($linked_invoices as $link): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-macgray-800"><?php echo htmlspecialchars($link['invoice_number']); ?></td>
                                        <td class="px-4 py-3 text-macgray-600"><?php echo htmlspecialchars(date("M d, Y", strtotime($link['invoice_date']))); ?></td>
                                        <td class="px-4 py-3 text-right font-medium text-macgray-800">৳<?php echo htmlspecialchars(number_format($link['amount_applied'], 2)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>