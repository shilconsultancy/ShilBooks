<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php"); exit;
}

$journal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($journal_id == 0) { header("location: index.php"); exit; }

// Fetch journal entry details
$sql = "SELECT * FROM journal_entries WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $journal_id]);
$journal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$journal) { header("location: index.php"); exit; }

// Fetch journal items and separate into debits and credits
$items_sql = "SELECT j_item.*, coa.account_name 
              FROM journal_entry_items j_item
              JOIN chart_of_accounts coa ON j_item.account_id = coa.id
              WHERE j_item.journal_entry_id = :id";
$items_stmt = $pdo->prepare($items_sql);
$items_stmt->execute(['id' => $journal_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

$debits = array_filter($items, fn($item) => $item['type'] == 'debit');
$credits = array_filter($items, fn($item) => $item['type'] == 'credit');
$total = array_sum(array_column($debits, 'amount'));

$pageTitle = 'View Journal Entry';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-macgray-800">Journal Entry</h1>
            <p class="text-sm text-macgray-500">Date: <?php echo htmlspecialchars(date("M d, Y", strtotime($journal['entry_date']))); ?></p>
        </div>
        <a href="<?php echo BASE_PATH; ?>accountant/manual-journals/" class="text-sm text-macblue-600 hover:text-macblue-800">
            &larr; Back to All Journals
        </a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="border-b pb-4 mb-4">
                    <h3 class="text-lg font-medium">Description</h3>
                    <p class="text-macgray-600"><?php echo htmlspecialchars($journal['description'] ?: 'No description provided.'); ?></p>
                </div>
                
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="w-3/5 py-2 text-left text-xs font-semibold text-macgray-500 uppercase">Account</th>
                            <th class="w-1/5 py-2 text-right text-xs font-semibold text-macgray-500 uppercase">Debit</th>
                            <th class="w-1/5 py-2 text-right text-xs font-semibold text-macgray-500 uppercase">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-macgray-200">
                        <?php foreach($debits as $item): ?>
                        <tr>
                            <td class="py-3 text-sm font-medium text-macgray-800"><?php echo htmlspecialchars($item['account_name']); ?></td>
                            <td class="py-3 text-sm text-right text-macgray-800">৳<?php echo number_format($item['amount'], 2); ?></td>
                            <td class="py-3 text-right"></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach($credits as $item): ?>
                        <tr class="text-macgray-800">
                            <td class="pl-8 py-3 text-sm font-medium"><?php echo htmlspecialchars($item['account_name']); ?></td>
                            <td class="py-3 text-right"></td>
                            <td class="py-3 text-sm text-right">৳<?php echo number_format($item['amount'], 2); ?></td>
                        </tr>
                         <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-macgray-50">
                        <tr>
                            <td class="py-3 text-right font-bold text-macgray-800">Totals</td>
                            <td class="py-3 text-right font-bold text-macgray-800">৳<?php echo number_format($total, 2); ?></td>
                            <td class="py-3 text-right font-bold text-macgray-800">৳<?php echo number_format($total, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>