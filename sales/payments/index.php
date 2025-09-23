<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$errors = [];

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $payment_id_to_delete = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        
        // Check if payment belongs to user
        $check_sql = "SELECT id FROM payments WHERE id = :id AND user_id = :user_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['id' => $payment_id_to_delete, 'user_id' => $userId]);

        if ($check_stmt->fetch()) {
            // Find all invoice applications for this payment
            $apps_sql = "SELECT invoice_id, amount_applied FROM invoice_payments WHERE payment_id = :payment_id";
            $apps_stmt = $pdo->prepare($apps_sql);
            $apps_stmt->execute(['payment_id' => $payment_id_to_delete]);
            $applications = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Reverse the application on each invoice
            foreach ($applications as $app) {
                $update_sql = "UPDATE invoices SET amount_paid = amount_paid - :amount_applied WHERE id = :id AND user_id = :user_id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute(['amount_applied' => $app['amount_applied'], 'id' => $app['invoice_id'], 'user_id' => $userId]);

                // Re-evaluate status
                $status_sql = "UPDATE invoices SET status = 'sent' WHERE id = :id AND total > amount_paid AND status = 'paid'";
                $status_stmt = $pdo->prepare($status_sql);
                $status_stmt->execute(['id' => $app['invoice_id']]);
            }

            // Delete the invoice_payments links
            $stmt = $pdo->prepare("DELETE FROM invoice_payments WHERE payment_id = :payment_id");
            $stmt->execute(['payment_id' => $payment_id_to_delete]);

            // Delete the main payment
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = :id");
            $stmt->execute(['id' => $payment_id_to_delete]);
            
            $pdo->commit();
            $message = "Payment deleted and invoice balances updated successfully!";
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error deleting payment: " . $e->getMessage();
    }
}


// Fetch all payments for the current user
$sql = "SELECT p.*, c.name AS customer_name FROM payments p JOIN customers c ON p.customer_id = c.id WHERE p.user_id = :user_id ORDER BY p.payment_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Payments Received';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Payments Received</h1>
        <a href="<?php echo BASE_PATH; ?>sales/payments/create.php" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>Record Payment</span>
        </a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($errors[0]); ?></div><?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Payment Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="5" class="px-6 py-4 text-center text-macgray-500">No payments recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($payment['payment_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-900">৳<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $payment['id']; ?>" class="text-macblue-600 hover:text-macblue-900">View</a>
                                            <a href="index.php?action=delete&id=<?php echo $payment['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to delete this payment? This will update the balance on all linked invoices.');">Delete</a>
                                        </td>
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