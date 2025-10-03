<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php"); exit;
}

$userId = $_SESSION['user_id'];
$cn_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cn_id == 0) { header("location: index.php"); exit; }

// Fetch details
$sql = "SELECT cn.*, c.name as customer_name, i.invoice_number FROM credit_notes cn JOIN customers c ON cn.customer_id = c.id JOIN invoices i ON cn.invoice_id = i.id WHERE cn.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $cn_id]);
$credit_note = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$credit_note) { header("location: index.php"); exit; }

$pageTitle = 'View Credit Note ' . htmlspecialchars($credit_note['credit_note_number']);
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Credit Note: <?php echo htmlspecialchars($credit_note['credit_note_number']); ?></h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>sales/credit-notes/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to List</a>
            <a href="print.php?id=<?php echo $cn_id; ?>" target="_blank" class="px-3 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2 text-sm">
                <i data-feather="printer" class="w-4 h-4"></i>
                <span>Print</span>
            </a>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-xl mx-auto">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-macgray-200">
                <div class="flex justify-between items-start pb-4 border-b">
                    <div>
                        <h2 class="text-2xl font-bold text-macgray-900">CREDIT NOTE</h2>
                        <p class="text-macgray-500">#<?php echo htmlspecialchars($credit_note['credit_note_number']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-macgray-500">Date:</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars(date("M d, Y", strtotime($credit_note['credit_note_date']))); ?></p>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                     <div>
                        <p class="text-sm font-semibold text-macgray-500">Customer</p>
                        <p class="text-lg font-medium text-macgray-800"><?php echo htmlspecialchars($credit_note['customer_name']); ?></p>
                    </div>
                     <div>
                        <p class="text-sm font-semibold text-macgray-500">Reference Invoice</p>
                        <p class="text-lg font-medium text-macgray-800"><?php echo htmlspecialchars($credit_note['invoice_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-macgray-500">Amount</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($credit_note['amount'], 2)); ?></p>
                    </div>
                     <div>
                        <p class="text-sm font-semibold text-macgray-500">Notes</p>
                        <p class="text-macgray-800"><?php echo htmlspecialchars($credit_note['notes'] ?: 'No notes.'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once '../../partials/footer.php'; ?>