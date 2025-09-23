<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php"); exit;
}

$userId = $_SESSION['user_id'];
$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($expense_id == 0) { header("location: index.php"); exit; }

// Fetch expense details
$sql = "SELECT e.*, ec.name as category_name, v.name as vendor_name 
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN vendors v ON e.vendor_id = v.id
        WHERE e.id = :id AND e.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $expense_id, 'user_id' => $userId]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) { header("location: index.php"); exit; }

$pageTitle = 'View Expense';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Expense Details</h1>
        <a href="<?php echo BASE_PATH; ?>purchases/expenses/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Expenses</a>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-xl mx-auto">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="flex justify-between items-start pb-4 border-b">
                    <div>
                        <p class="text-sm font-semibold text-macgray-500">Amount</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($expense['amount'], 2)); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-macgray-500">Expense Date:</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($expense['expense_date']))); ?></p>
                    </div>
                </div>

                <div class="mt-6 space-y-4 text-sm">
                     <div>
                        <p class="font-semibold text-macgray-500">Description</p>
                        <p class="text-lg font-medium text-macgray-800"><?php echo htmlspecialchars($expense['description']); ?></p>
                    </div>
                     <div>
                        <p class="font-semibold text-macgray-500">Category</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars($expense['category_name']); ?></p>
                    </div>
                    <div>
                        <p class="font-semibold text-macgray-500">Vendor</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars($expense['vendor_name'] ?? 'N/A'); ?></p>
                    </div>
                     <div>
                        <p class="font-semibold text-macgray-500">Notes</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars($expense['notes'] ?: 'No notes.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>