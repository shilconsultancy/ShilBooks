<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if ($profile_id == 0) { header("location: index.php"); exit; }

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_status'])) {
    validate_csrf_token();
    
    $new_status = $_POST['new_status'];
    $allowed_statuses = ['active', 'paused', 'finished'];
    if (in_array($new_status, $allowed_statuses)) {
        $sql = "UPDATE recurring_invoices SET status = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(['status' => $new_status, 'id' => $profile_id])) {
            $message = "Status updated successfully!";
        }
    }
}

// Fetch profile details
$sql = "SELECT r.*, c.name as customer_name FROM recurring_invoices r JOIN customers c ON r.customer_id = c.id WHERE r.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $profile_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) { header("location: index.php"); exit; }

// Fetch profile items
$items_sql = "SELECT ri.*, i.name as item_name FROM recurring_invoice_items ri JOIN items i ON ri.item_id = i.id WHERE ri.recurring_invoice_id = :id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['id' => $profile_id]);
$profile_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'View Recurring Profile';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Recurring Profile</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>sales/recurring/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to List</a>
            <a href="edit.php?id=<?php echo $profile_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm">
                <i data-feather="edit-2" class="w-4 h-4"></i><span>Edit</span>
            </a>
            <a href="print.php?id=<?php echo $profile_id; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i><span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
             <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-macgray-200 mb-6 flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700 mr-4">Profile Status: <strong class="uppercase"><?php echo htmlspecialchars($profile['status']); ?></strong></span>
                <form method="POST" action="view.php?id=<?php echo $profile_id; ?>" class="flex items-center space-x-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="change_status" value="1">
                    <select name="new_status" class="text-sm border-gray-300 rounded-md">
                        <option value="active" <?php echo ($profile['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="paused" <?php echo ($profile['status'] == 'paused') ? 'selected' : ''; ?>>Paused</option>
                        <option value="finished" <?php echo ($profile['status'] == 'finished') ? 'selected' : ''; ?>>Finished</option>
                    </select>
                    <button type="submit" class="px-3 py-1 bg-macgray-700 text-white text-xs font-semibold rounded-md hover:bg-macgray-800">Update</button>
                </form>
            </div>

            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="grid grid-cols-2 gap-8">
                    <div><p class="font-semibold text-macgray-500">Customer</p><p class="font-bold text-macgray-800"><?php echo htmlspecialchars($profile['customer_name']); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Frequency</p><p class="font-bold text-macgray-800"><?php echo htmlspecialchars(ucfirst($profile['frequency'])); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Start Date</p><p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($profile['start_date']))); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">End Date</p><p class="text-macgray-800"><?php echo $profile['end_date'] ? htmlspecialchars(date("M d, Y", strtotime($profile['end_date']))) : 'No end date'; ?></p></div>
                </div>

                <div class="mt-8">
                    <p class="font-semibold text-macgray-500 mb-2">Invoice Template Items</p>
                    <table class="min-w-full">
                        <thead class="border-b-2 border-macgray-200">
                            <tr>
                                <th class="px-2 py-3 text-left text-xs font-semibold text-macgray-500 uppercase tracking-wider">Item</th>
                                <th class="px-2 py-3 text-center text-xs font-semibold text-macgray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-2 py-3 text-right text-xs font-semibold text-macgray-500 uppercase tracking-wider">Price</th>
                                <th class="px-2 py-3 text-right text-xs font-semibold text-macgray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-100">
                            <?php foreach ($profile_items as $item): ?>
                            <tr>
                                <td class="px-2 py-4 text-sm"><div class="font-medium text-macgray-800"><?php echo htmlspecialchars($item['item_name']); ?></div></td>
                                <td class="px-2 py-4 text-sm text-center text-macgray-500"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-2 py-4 text-sm text-right text-macgray-500">৳<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                                <td class="px-2 py-4 text-sm text-right font-medium text-macgray-800">৳<?php echo htmlspecialchars(number_format($item['total'], 2)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-8">
                    <div class="w-full max-w-xs">
                        <div class="space-y-2">
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Subtotal</span><span class="text-sm text-macgray-800">৳<?php echo htmlspecialchars(number_format($profile['subtotal'], 2)); ?></span></div>
                            <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Tax</span><span class="text-sm text-macgray-800">৳<?php echo htmlspecialchars(number_format($profile['tax'], 2)); ?></span></div>
                            <div class="flex justify-between pt-2 border-t"><span class="text-base font-bold text-macgray-900">Total</span><span class="text-base font-bold text-macgray-900">৳<?php echo htmlspecialchars(number_format($profile['total'], 2)); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>