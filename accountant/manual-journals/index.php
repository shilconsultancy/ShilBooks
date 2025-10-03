<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch all journal entries for the current user
$sql = "SELECT je.*, SUM(j_item.amount) as total_amount
        FROM journal_entries je
        JOIN journal_entry_items j_item ON je.id = j_item.journal_entry_id
        WHERE j_item.type = 'debit'
        GROUP BY je.id
        ORDER BY je.entry_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manual Journals';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Manual Journals</h1>
        <a href="<?php echo BASE_PATH; ?>accountant/manual-journals/create.php" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Journal Entry</span>
        </a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($journals)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-macgray-500">No journal entries found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($journals as $journal): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($journal['entry_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($journal['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-900 text-right">৳<?php echo htmlspecialchars(number_format($journal['total_amount'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $journal['id']; ?>" class="text-macblue-600 hover:text-macblue-900">View</a>
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