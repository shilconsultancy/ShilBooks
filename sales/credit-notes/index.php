<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php"); exit;
}

$message = '';
$errors = [];

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('admin')) {
        $cn_id_to_delete = (int)$_GET['id'];
        try {
            $pdo->beginTransaction();

            $sql = "SELECT invoice_id, amount FROM credit_notes WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $cn_id_to_delete]);
            $credit_note = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($credit_note) {
                // Reverse the credit on the original invoice
                $update_sql = "UPDATE invoices SET amount_paid = amount_paid - ? WHERE id = ?";
                $pdo->prepare($update_sql)->execute([$credit_note['amount'], $credit_note['invoice_id']]);

                // Re-evaluate invoice status
                $status_sql = "UPDATE invoices SET status = 'sent' WHERE id = ? AND amount_paid < total";
                $pdo->prepare($status_sql)->execute([$credit_note['invoice_id']]);

                // Delete the credit note
                $delete_sql = "DELETE FROM credit_notes WHERE id = ?";
                $pdo->prepare($delete_sql)->execute([$cn_id_to_delete]);

                $pdo->commit();
                $message = "Credit Note deleted and invoice balance restored.";
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error deleting credit note: " . $e->getMessage();
        }
    }
}

// Fetch all credit notes for display
$sql = "SELECT
            cn.*,
            c.name AS customer_name,
            i.invoice_number
        FROM credit_notes cn
        JOIN customers c ON cn.customer_id = c.id
        LEFT JOIN invoices i ON cn.invoice_id = i.id
        ORDER BY cn.credit_note_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$credit_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Credit Notes';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Credit Notes</h1>
        <a href="<?php echo BASE_PATH; ?>sales/credit-notes/create.php" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Credit Note</span>
        </a>
    </header>
    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
             <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
             <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars(end($errors)); ?></div><?php endif; ?>
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Reference Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                             <?php if (empty($credit_notes)): ?>
                                <tr><td colspan="6" class="px-6 py-4 text-center text-macgray-500">No credit notes found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($credit_notes as $cn): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($cn['credit_note_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($cn['credit_note_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($cn['customer_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($cn['invoice_number'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-900">৳<?php echo htmlspecialchars(number_format($cn['amount'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $cn['id']; ?>" class="text-macblue-600 hover:text-macblue-900">View</a>
                                            <?php if (hasPermission('admin')): ?>
                                                <a href="index.php?action=delete&id=<?php echo $cn['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure? This will reverse the credit on the linked invoice.');">Delete</a>
                                            <?php endif; ?>
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
<?php require_once '../../partials/footer.php'; ?>