<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ".BASE_PATH."index.php"); exit;
}

$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($expense_id == 0) { header("location: index.php"); exit; }

$message = '';
$errors = [];

// Handle Record Payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['record_payment'])) {
    validate_csrf_token();
    $payment_amount = (float)$_POST['payment_amount'];

    // Fetch current expense to validate payment
    $stmt = $pdo->prepare("SELECT amount, amount_paid FROM expenses WHERE id = ?");
    $stmt->execute([$expense_id]);
    $expense_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_due = $expense_data['amount'] - $expense_data['amount_paid'];

    if ($payment_amount <= 0 || $payment_amount > $balance_due) {
        $errors[] = "Invalid payment amount.";
    }

    if (empty($errors)) {
        try {
            $new_amount_paid = $expense_data['amount_paid'] + $payment_amount;
            $new_status = ($new_amount_paid >= $expense_data['amount']) ? 'paid' : 'partially_paid';

            $sql = "UPDATE expenses SET amount_paid = ?, status = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$new_amount_paid, $new_status, $expense_id]);
            $message = "Payment recorded successfully!";
        } catch (Exception $e) {
            $errors[] = "Error recording payment: " . $e->getMessage();
        }
    }
}


// Fetch expense details
$sql = "SELECT e.*, ec.name as category_name, v.name as vendor_name
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN vendors v ON e.vendor_id = v.id
        WHERE e.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $expense_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) { header("location: index.php"); exit; }
$balance_due = $expense['amount'] - $expense['amount_paid'];

$pageTitle = 'View Expense';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Expense Details</h1>
        <div class="flex items-center space-x-2">
            <a href="<?php echo BASE_PATH; ?>purchases/expenses/" class="text-sm text-macblue-600 hover:text-macblue-800">&larr; Back to Expenses</a>
            <?php if ($balance_due > 0): ?>
            <button id="recordPaymentBtn" class="px-3 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center space-x-2 text-sm">
                <i data-feather="dollar-sign" class="w-4 h-4"></i><span>Record Payment</span>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-xl mx-auto">
            <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($errors[0]); ?></div><?php endif; ?>

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

                <div class="mt-6 pt-4 border-t">
                    <div class="flex justify-between"><span class="text-sm font-medium text-macgray-500">Amount Paid</span><span class="text-sm text-macgray-800">- <?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($expense['amount_paid'], 2)); ?></span></div>
                    <div class="flex justify-between mt-2 pt-2 border-t bg-macgray-50 p-2 rounded-md"><span class="text-base font-bold text-macgray-900">Balance Due</span><span class="text-base font-bold text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($balance_due, 2)); ?></span></div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="paymentModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full">
            <form action="view.php?id=<?php echo $expense_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="record_payment" value="1">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Record Payment</h3>
                    <div class="mt-4">
                        <label for="payment_amount" class="block text-sm font-medium text-gray-700">Amount*</label>
                        <input type="number" name="payment_amount" id="payment_amount" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" max="<?php echo number_format($balance_due, 2, '.', ''); ?>" step="0.01" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save Payment</button>
                    <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('paymentModal');
    const recordPaymentBtn = document.getElementById('recordPaymentBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');

    if (recordPaymentBtn) {
        recordPaymentBtn.addEventListener('click', () => modal.classList.remove('hidden'));
    }
    if(closeModalBtn) {
        closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    }
});
</script>

<?php
require_once '../../partials/footer.php';
?>