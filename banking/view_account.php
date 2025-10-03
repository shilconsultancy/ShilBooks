<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$account_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($account_id == 0) { header("location: index.php"); exit; }

// Handle POST request (Add new transaction)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    $date = $_POST['transaction_date'];
    $desc = trim($_POST['description']);
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];

    if (!empty($date) && !empty($desc) && !empty($type) && is_numeric($amount) && $amount > 0) {
        try {
            $pdo->beginTransaction();
            
            // Insert transaction
            $sql = "INSERT INTO bank_transactions (account_id, transaction_date, description, type, amount) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$account_id, $date, $desc, $type, $amount]);
            
            // Update account balance
            if ($type == 'deposit') {
                $update_sql = "UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?";
            } else { // withdrawal
                $update_sql = "UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?";
            }
            $pdo->prepare($update_sql)->execute([$amount, $account_id]);
            
            $pdo->commit();
            header("location: view_account.php?id=" . $account_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            // Optionally, set an error message to display
        }
    }
}


// Fetch account details
$stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ?");
$stmt->execute([$account_id]);
$account = $stmt->fetch();
if (!$account) { header("location: index.php"); exit; }

// Fetch all transactions for this account
$stmt = $pdo->prepare("SELECT * FROM bank_transactions WHERE account_id = ? ORDER BY transaction_date DESC, id DESC");
$stmt->execute([$account_id]);
$transactions = $stmt->fetchAll();


$pageTitle = 'Account Ledger';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-macgray-800"><?php echo htmlspecialchars($account['account_name']); ?></h1>
            <p class="text-sm text-macgray-500">Ledger</p>
        </div>
        <div class="flex items-center space-x-4">
             <a href="<?php echo BASE_PATH; ?>banking/" class="text-sm text-macblue-600 hover:text-macblue-800">
                &larr; Back to Accounts
            </a>
            <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
                <i data-feather="plus" class="w-4 h-4"></i><span>New Transaction</span>
            </button>
        </div>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="p-4 border-b">
                    <h3 class="text-lg font-medium">Current Balance: <span class="font-bold text-green-600">৳<?php echo number_format($account['current_balance'], 2); ?></span></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Withdrawal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Deposit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($tx['transaction_date']))); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($tx['description']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600">
                                        <?php if($tx['type'] == 'withdrawal') echo '৳' . number_format($tx['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
                                        <?php if($tx['type'] == 'deposit') echo '৳' . number_format($tx['amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-macgray-500">No transactions recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="formModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="view_account.php?id=<?php echo $account_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" id="csrf_token_modal">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Transaction</h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="transaction_date" class="block text-sm font-medium text-gray-700">Date*</label>
                            <input type="date" name="transaction_date" id="transaction_date" required value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description*</label>
                            <input type="text" name="description" id="description" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700">Type*</label>
                                <select name="type" id="type" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    <option value="withdrawal">Withdrawal</option>
                                    <option value="deposit">Deposit</option>
                                </select>
                            </div>
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700">Amount*</label>
                                <input type="number" name="amount" id="amount" required step="0.01" placeholder="0.00" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save Transaction</button>
                    <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('formModal');
    const addBtn = document.getElementById('addBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";
    
    addBtn.addEventListener('click', () => {
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden');
    });
    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
});
</script>

<?php
require_once '../partials/footer.php';
?>