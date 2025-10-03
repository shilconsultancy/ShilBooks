<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('admin')) {
        $receipt_id_to_delete = (int)$_GET['id'];

    try {
        $pdo->beginTransaction();

        // Check if the receipt exists
        $check_sql = "SELECT id FROM sales_receipts WHERE id = :id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['id' => $receipt_id_to_delete]);

        if ($check_stmt->fetch()) {
            // Get receipt items to restore inventory
            $items_sql = "SELECT item_id, quantity FROM sales_receipt_items WHERE receipt_id = :receipt_id";
            $items_stmt = $pdo->prepare($items_sql);
            $items_stmt->execute(['receipt_id' => $receipt_id_to_delete]);
            $items_to_restore = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Restore inventory for each product
            foreach ($items_to_restore as $item) {
                $update_sql = "UPDATE items SET quantity = quantity + :quantity WHERE id = :id AND item_type = 'product'";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    'quantity' => $item['quantity'],
                    'id' => $item['item_id']
                ]);
            }

            // Delete line items
            $stmt = $pdo->prepare("DELETE FROM sales_receipt_items WHERE receipt_id = :receipt_id");
            $stmt->execute(['receipt_id' => $receipt_id_to_delete]);

            // Delete the main receipt
            $stmt = $pdo->prepare("DELETE FROM sales_receipts WHERE id = :id");
            $stmt->execute(['id' => $receipt_id_to_delete]);

            $pdo->commit();
            $message = "Sales Receipt deleted and inventory restored successfully!";
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error deleting sales receipt: " . $e->getMessage();
    }
    }
}


// Fetch all sales receipts
$sql = "SELECT sr.*, c.name AS customer_name
        FROM sales_receipts sr
        JOIN customers c ON sr.customer_id = c.id
        ORDER BY sr.receipt_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Sales Receipts';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Sales Receipts</h1>
        <a href="<?php echo BASE_PATH; ?>sales/receipts/create.php" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Sales Receipt</span>
        </a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
             <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
            <?php endif; ?>
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($receipts)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-macgray-500">No sales receipts found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($receipt['receipt_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($receipt['receipt_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($receipt['customer_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-900">৳<?php echo htmlspecialchars(number_format($receipt['total'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $receipt['id']; ?>" class="text-macblue-600 hover:text-macblue-900">View</a>
                                            <a href="edit.php?id=<?php echo $receipt['id']; ?>" class="text-macblue-600 hover:text-macblue-900 ml-4">Edit</a>
                                            <?php if (hasPermission('admin')): ?>
                                                <a href="index.php?action=delete&id=<?php echo $receipt['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to delete this receipt? This will restore item quantities to inventory.');">Delete</a>
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

<?php
require_once '../../partials/footer.php';
?>