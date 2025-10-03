<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customer_id == 0) {
    header("location: index.php");
    exit;
}

// Fetch customer details
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        header("location: index.php");
        exit;
    }
} catch (Exception $e) {
    // Log the error and redirect
    error_log("Error fetching customer {$customer_id}: " . $e->getMessage());
    header("location: index.php");
    exit;
}

// --- Fetch all transactions for this customer ---
$transactions = [];

try {
    // Invoices
    $inv_stmt = $pdo->prepare("SELECT id, invoice_number as number, invoice_date as date, total, 'invoice' as type FROM invoices WHERE customer_id = ?");
    $inv_stmt->execute([$customer_id]);
    while ($row = $inv_stmt->fetch(PDO::FETCH_ASSOC)) { $transactions[] = $row; }

    // Payments (Updated Query)
    $pay_sql = "SELECT
                    p.id,
                    p.payment_date as date,
                    p.amount as total,
                    'payment' as type,
                    GROUP_CONCAT(i.invoice_number SEPARATOR ', ') as number
                FROM payments p
                LEFT JOIN invoice_payments ip ON p.id = ip.payment_id
                LEFT JOIN invoices i ON ip.invoice_id = i.id
                WHERE p.customer_id = ?
                GROUP BY p.id";
    $pay_stmt = $pdo->prepare($pay_sql);
    $pay_stmt->execute([$customer_id]);
    while ($row = $pay_stmt->fetch(PDO::FETCH_ASSOC)) { $transactions[] = $row; }

    // Sales Receipts
    $rcpt_stmt = $pdo->prepare("SELECT id, receipt_number as number, receipt_date as date, total, 'receipt' as type FROM sales_receipts WHERE customer_id = ?");
    $rcpt_stmt->execute([$customer_id]);
    while ($row = $rcpt_stmt->fetch(PDO::FETCH_ASSOC)) { $transactions[] = $row; }

    // Credit Notes (Updated Query)
    $cn_sql = "SELECT
                cn.id,
                cn.credit_note_number as number,
                cn.credit_note_date as date,
                cn.amount as total,
                'credit_note' as type,
                i.invoice_number as reference_invoice
            FROM credit_notes cn
            LEFT JOIN invoices i ON cn.invoice_id = i.id
            WHERE cn.customer_id = ?";
    $cn_stmt = $pdo->prepare($cn_sql);
    $cn_stmt->execute([$customer_id]);
    while ($row = $cn_stmt->fetch(PDO::FETCH_ASSOC)) { $transactions[] = $row; }
} catch (Exception $e) {
    // Log error but don't fail completely - just show no transactions
    error_log("Error fetching transactions for customer {$customer_id}: " . $e->getMessage());
    $transactions = [];
}


// Sort all transactions by date DESC
usort($transactions, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});


$pageTitle = 'View Customer';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';

function getTransactionTypeDetails($type) {
    switch ($type) {
        case 'invoice': return ['label' => 'Invoice', 'color' => 'bg-blue-100 text-blue-800', 'url' => BASE_PATH . 'sales/invoices/view.php'];
        case 'payment': return ['label' => 'Payment', 'color' => 'bg-green-100 text-green-800', 'url' => BASE_PATH . 'sales/payments/view.php'];
        case 'receipt': return ['label' => 'Sales Receipt', 'color' => 'bg-purple-100 text-purple-800', 'url' => BASE_PATH . 'sales/receipts/view.php'];
        case 'credit_note': return ['label' => 'Credit Note', 'color' => 'bg-yellow-100 text-yellow-800', 'url' => BASE_PATH . 'sales/credit-notes/view.php'];
        default: return ['label' => 'Unknown', 'color' => 'bg-gray-100 text-gray-800', 'url' => '#'];
    }
}
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Customer Details</h1>
        <a href="<?php echo BASE_PATH; ?>sales/customers/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Customers</a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 mb-6">
                <h2 class="text-lg font-semibold text-macgray-800 border-b pb-4 mb-4">Customer Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-macgray-500">Name / Company</p>
                        <p class="font-medium text-macgray-900"><?php echo htmlspecialchars($customer['name']); ?></p>
                    </div>
                     <?php if ($customer['customer_type'] == 'company'): ?>
                    <div>
                        <p class="text-sm text-macgray-500">Contact Person</p>
                        <p class="font-medium text-macgray-900"><?php echo htmlspecialchars($customer['contact_person'] ?: 'N/A'); ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-sm text-macgray-500">Email</p>
                        <p class="font-medium text-macgray-900"><?php echo htmlspecialchars($customer['email'] ?: 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-macgray-500">Phone</p>
                        <p class="font-medium text-macgray-900"><?php echo htmlspecialchars($customer['phone'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-macgray-500">Address</p>
                        <p class="font-medium text-macgray-900"><?php echo nl2br(htmlspecialchars($customer['address'] ?: 'N/A')); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <h2 class="text-lg font-semibold text-macgray-800 p-4 border-b">Transaction History</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Number / Reference</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-macgray-500">No transactions found for this customer.</td></tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): 
                                    $details = getTransactionTypeDetails($tx['type']);
                                    $amount_display = ($tx['type'] == 'credit_note') ? '-' : '';
                                    $amount_display .= CURRENCY_SYMBOL . number_format($tx['total'], 2);
                                ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($tx['date']))); ?></td>
                                        <td class="px-6 py-4 text-sm">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $details['color']; ?>">
                                                <?php echo $details['label']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium text-macblue-600 hover:text-macblue-800">
                                            <a href="<?php echo $details['url']; ?>?id=<?php echo $tx['id']; ?>">
                                                <?php echo htmlspecialchars($tx['number'] ?? ''); ?>
                                            </a>
                                            <?php if ($tx['type'] == 'credit_note' && isset($tx['reference_invoice'])): ?>
                                                <span class="block text-xs text-macgray-500">Ref: <?php echo htmlspecialchars($tx['reference_invoice']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-macgray-900 text-right"><?php echo $amount_display; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>