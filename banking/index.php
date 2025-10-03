<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$errors = [];

// Handle POST request (Add or Edit Account)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    $account_name = trim($_POST['account_name']);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $initial_balance = (float)$_POST['initial_balance'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($account_name)) { $errors[] = "Account name is required."; }
    if (!is_numeric($initial_balance)) { $errors[] = "Initial balance must be a number."; }

    if (empty($errors)) {
        if ($edit_id) {
            // Note: Editing initial balance is disabled in the form as it would require complex recalculations.
            // We only allow editing names and numbers for existing accounts.
            $sql = "UPDATE bank_accounts SET account_name = ?, bank_name = ?, account_number = ? WHERE id = ?";
            $params = [$account_name, $bank_name, $account_number, $edit_id];
        } else {
            $sql = "INSERT INTO bank_accounts (account_name, bank_name, account_number, initial_balance, current_balance) VALUES (?, ?, ?, ?, ?)";
            $params = [$account_name, $bank_name, $account_number, $initial_balance, $initial_balance];
        }
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            header("location: index.php");
            exit;
        } else {
            $errors[] = "Something went wrong.";
        }
    }
}

// Handle GET request (Delete)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('admin')) {
        $delete_id = (int)$_GET['id'];
        // In a full system, you would first check if there are transactions linked to this account before deleting.
        $sql = "DELETE FROM bank_accounts WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$delete_id])) {
            header("location: index.php");
            exit;
        }
    }
}

// Fetch all accounts
$sql = "SELECT * FROM bank_accounts ORDER BY account_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Banking';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Bank Accounts</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i><span>New Account</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Account Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Bank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Current Balance</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($accounts)): ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-macgray-500">No bank accounts found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($account['account_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                        <td class="px-6 py-4 text-sm font-medium text-macgray-900">৳<?php echo htmlspecialchars(number_format($account['current_balance'], 2)); ?></td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <a href="view_account.php?id=<?php echo $account['id']; ?>" class="text-green-600 hover:text-green-900">View</a>
                                            <button class="editBtn text-macblue-600 hover:text-macblue-900 ml-4" 
                                                data-id="<?php echo $account['id']; ?>"
                                                data-account_name="<?php echo htmlspecialchars($account['account_name']); ?>"
                                                data-bank_name="<?php echo htmlspecialchars($account['bank_name']); ?>"
                                                data-account_number="<?php echo htmlspecialchars($account['account_number']); ?>">Edit</button>
                                            <?php if (hasPermission('admin')): ?>
                                                <a href="index.php?action=delete&id=<?php echo $account['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?');">Delete</a>
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

<div id="formModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="index.php" method="POST">
                <input type="hidden" name="csrf_token" id="csrf_token_modal">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New Bank Account</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div>
                            <label for="account_name" class="block text-sm font-medium text-gray-700">Account Name*</label>
                            <input type="text" name="account_name" id="account_name" required placeholder="e.g., Checking Account" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div>
                                <label for="bank_name" class="block text-sm font-medium text-gray-700">Bank Name</label>
                                <input type="text" name="bank_name" id="bank_name" placeholder="e.g., City Bank" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="account_number" class="block text-sm font-medium text-gray-700">Account Number</label>
                                <input type="text" name="account_number" id="account_number" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div id="initialBalanceWrapper">
                            <label for="initial_balance" class="block text-sm font-medium text-gray-700">Opening Balance*</label>
                            <input type="number" name="initial_balance" id="initial_balance" required step="0.01" value="0.00" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save</button>
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
    const editBtns = document.querySelectorAll('.editBtn');
    const form = modal.querySelector('form');
    const modalTitle = document.getElementById('modalTitle');
    const editIdField = document.getElementById('edit_id');
    const initialBalanceWrapper = document.getElementById('initialBalanceWrapper');
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";

    function openModal() { 
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden'); 
    }
    function closeModal() { modal.classList.add('hidden'); form.reset(); }
    
    addBtn.addEventListener('click', () => {
        modalTitle.innerText = 'Add New Bank Account';
        editIdField.value = '';
        initialBalanceWrapper.style.display = 'block';
        document.getElementById('initial_balance').required = true;
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.innerText = 'Edit Bank Account';
            editIdField.value = btn.dataset.id;
            document.getElementById('account_name').value = btn.dataset.account_name;
            document.getElementById('bank_name').value = btn.dataset.bank_name;
            document.getElementById('account_number').value = btn.dataset.account_number;
            
            // Hide and disable initial balance field when editing
            initialBalanceWrapper.style.display = 'none';
            document.getElementById('initial_balance').required = false;
            
            openModal();
        });
    });
});
</script>

<?php
require_once '../partials/footer.php';
?>