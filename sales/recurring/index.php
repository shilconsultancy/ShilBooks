<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$message = '';
$errors = [];

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('admin')) {
        $profile_id_to_delete = (int)$_GET['id'];
        try {
            $pdo->beginTransaction();

            $check_sql = "SELECT id FROM recurring_invoices WHERE id = :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute(['id' => $profile_id_to_delete]);

            if ($check_stmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM recurring_invoice_items WHERE recurring_invoice_id = :id");
                $stmt->execute(['id' => $profile_id_to_delete]);

                $stmt = $pdo->prepare("DELETE FROM recurring_invoices WHERE id = :id");
                $stmt->execute(['id' => $profile_id_to_delete]);

                $pdo->commit();
                $message = "Recurring profile deleted successfully!";
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error deleting profile: " . $e->getMessage();
        }
    }
}

// NOTE: The invoice generation logic has been moved to the /cron/generate_invoices.php script

// Fetch all recurring profiles to display
$sql = "SELECT r.*, c.name AS customer_name FROM recurring_invoices r JOIN customers c ON r.customer_id = c.id ORDER BY r.start_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Recurring Invoices';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';

// Function to get status badge color
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'paused': return 'bg-yellow-100 text-yellow-800';
        case 'finished': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Recurring Invoices</h1>
        <a href="<?php echo BASE_PATH; ?>sales/recurring/create.php" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Profile</span>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Frequency</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Next Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($profiles)): ?>
                                <tr><td colspan="6" class="px-6 py-4 text-center text-macgray-500">No recurring profiles found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($profiles as $profile): 
                                    $last_gen = $profile['last_generated_date'] ? new DateTime($profile['last_generated_date']) : new DateTime($profile['start_date']);
                                    if($profile['last_generated_date']) {
                                        switch ($profile['frequency']) {
                                            case 'weekly': $last_gen->modify('+1 week'); break;
                                            case 'monthly': $last_gen->modify('+1 month'); break;
                                            case 'quarterly': $last_gen->modify('+3 months'); break;
                                            case 'yearly': $last_gen->modify('+1 year'); break;
                                        }
                                    }
                                    $next_date = $last_gen->format('M d, Y');
                                ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($profile['customer_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo htmlspecialchars(ucfirst($profile['frequency'])); ?></td>
                                        <td class="px-6 py-4 text-sm text-macgray-900">৳<?php echo htmlspecialchars(number_format($profile['total'], 2)); ?></td>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo $next_date; ?></td>
                                        <td class="px-6 py-4 text-sm"><span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusBadgeClass($profile['status']); ?>"><?php echo htmlspecialchars(ucfirst($profile['status'])); ?></span></td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $profile['id']; ?>" class="text-macblue-600 hover:text-macblue-900">View</a>
                                            <?php if (hasPermission('admin')): ?>
                                                <a href="index.php?action=delete&id=<?php echo $profile['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to delete this recurring profile?');">Delete</a>
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