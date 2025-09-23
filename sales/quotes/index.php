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
    $quote_id_to_delete = (int)$_GET['id'];

    try {
        $pdo->beginTransaction();

        // First, check if the quote belongs to the user
        $check_sql = "SELECT id FROM quotes WHERE id = :id AND user_id = :user_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['id' => $quote_id_to_delete, 'user_id' => $userId]);

        if ($check_stmt->fetch()) {
            // If it exists, delete line items first
            $stmt = $pdo->prepare("DELETE FROM quote_items WHERE quote_id = :quote_id");
            $stmt->execute(['quote_id' => $quote_id_to_delete]);

            // Then, delete the main quote
            $stmt = $pdo->prepare("DELETE FROM quotes WHERE id = :id");
            $stmt->execute(['id' => $quote_id_to_delete]);

            $pdo->commit();
            $message = "Quote deleted successfully!";
        } else {
            // If quote doesn't belong to user, rollback
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error deleting quote: " . $e->getMessage();
    }
}


// Fetch all quotes for the current user, joining with customers table to get customer name
$sql = "SELECT q.*, c.name AS customer_name 
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.user_id = :user_id 
        ORDER BY q.quote_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Quotes';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';

// Function to get status badge color
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'sent': return 'bg-blue-100 text-blue-800';
        case 'accepted': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        case 'draft':
        default: return 'bg-yellow-100 text-yellow-800';
    }
}
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Quotes</h1>
        <a href="<?php echo BASE_PATH; ?>sales/quotes/create.php" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Quote</span>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($quotes)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-macgray-500">No quotes found. Create one to get started.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotes as $quote): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($quote['quote_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($quote['quote_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($quote['customer_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-900">৳<?php echo htmlspecialchars(number_format($quote['total'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusBadgeClass($quote['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($quote['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $quote['id']; ?>" class="text-macblue-600 hover:text-macblue-900">View</a>
                                            <a href="index.php?action=delete&id=<?php echo $quote['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to permanently delete this quote?');">Delete</a>
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