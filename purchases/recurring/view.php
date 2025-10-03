<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profile_id == 0) { header("location: index.php"); exit; }

// Fetch profile details
$sql = "SELECT r.*, v.name as vendor_name, ec.name as category_name 
        FROM recurring_expenses r 
        LEFT JOIN vendors v ON r.vendor_id = v.id
        JOIN expense_categories ec ON r.category_id = ec.id
        WHERE r.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $profile_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) { header("location: index.php"); exit; }

$pageTitle = 'View Recurring Expense';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Recurring Expense Profile</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>purchases/recurring/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to List</a>
            <a href="edit.php?id=<?php echo $profile_id; ?>" class="px-3 py-2 bg-macgray-200 text-macgray-800 rounded-md hover:bg-macgray-300 flex items-center space-x-2 text-sm">
                <i data-feather="edit-2" class="w-4 h-4"></i><span>Edit</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="grid grid-cols-2 gap-8">
                    <div><p class="font-semibold text-macgray-500">Description</p><p class="font-bold text-macgray-800"><?php echo htmlspecialchars($profile['description']); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Amount</p><p class="font-bold text-lg text-red-600"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($profile['amount'], 2); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Category</p><p class="text-macgray-800"><?php echo htmlspecialchars($profile['category_name']); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Vendor</p><p class="text-macgray-800"><?php echo htmlspecialchars($profile['vendor_name'] ?? 'N/A'); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Frequency</p><p class="text-macgray-800"><?php echo htmlspecialchars(ucfirst($profile['frequency'])); ?></p></div>
                    <div><p class="font-semibold text-macgray-500">Start Date</p><p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($profile['start_date']))); ?></p></div>
                </div>
                 <div class="mt-6">
                    <p class="font-semibold text-macgray-500">Notes</p>
                    <p class="text-macgray-800"><?php echo htmlspecialchars($profile['notes'] ?: 'No notes provided.'); ?></p>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>