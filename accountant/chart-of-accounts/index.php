<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// --- Handle POST request (Add or Edit Account) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    $name = trim($_POST['account_name']);
    $type = $_POST['account_type'];
    $description = trim($_POST['description']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($name) || empty($type)) {
        $errors[] = "Account name and type are required.";
    }

    if (empty($errors)) {
        if ($edit_id) {
            // Only update name and description, not type. Prevent editing non-editable accounts.
            $sql = "UPDATE chart_of_accounts SET account_name = ?, description = ? WHERE id = ? AND is_editable = TRUE";
            $params = [$name, $description, $edit_id];
        } else {
            $sql = "INSERT INTO chart_of_accounts (account_name, account_type, description) VALUES (?, ?, ?)";
            $params = [$name, $type, $description];
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

// --- Handle GET request (Delete Account) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (hasPermission('admin')) {
        $delete_id = (int)$_GET['id'];
        // IMPORTANT: In a real system, check if account has transactions before deleting.
        // We also prevent deleting non-editable system accounts.
        $sql = "DELETE FROM chart_of_accounts WHERE id = ? AND is_editable = TRUE";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$delete_id])) {
            header("location: index.php");
            exit;
        }
    }
}

// Fetch all accounts for the user
$sql = "SELECT * FROM chart_of_accounts ORDER BY account_type, account_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group accounts by type for display
$grouped_accounts = [];
$account_types = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
foreach ($account_types as $type) {
    $grouped_accounts[$type] = [];
}
foreach ($all_accounts as $account) {
    $grouped_accounts[$account['account_type']][] = $account;
}

$pageTitle = 'Chart of Accounts';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Chart of Accounts</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Account</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <p class="text-macgray-600 mb-6">The chart of accounts is a list of all the financial accounts in your general ledger. System-generated accounts cannot be edited or deleted.</p>
            
            <div class="space-y-6">
                <?php foreach ($grouped_accounts as $type => $accounts): ?>
                <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-semibold text-macgray-800"><?php echo htmlspecialchars($type); ?></h2>
                    </div>
                    <table class="min-w-full">
                        <tbody class="divide-y divide-macgray-200">
                            <?php if(empty($accounts)): ?>
                                <tr><td class="px-6 py-4 text-sm text-macgray-500">No accounts in this category.</td></tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($account['account_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($account['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php if ($account['is_editable']): ?>
                                                <button class="editBtn text-macblue-600 hover:text-macblue-900"
                                                    data-id="<?php echo $account['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($account['account_name']); ?>"
                                                    data-type="<?php echo htmlspecialchars($account['account_type']); ?>"
                                                    data-description="<?php echo htmlspecialchars($account['description']); ?>">Edit</button>
                                                <?php if (hasPermission('admin')): ?>
                                                    <a href="index.php?action=delete&id=<?php echo $account['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure? This cannot be undone.');">Delete</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-macgray-400 text-xs">System Account</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New Account</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div>
                            <label for="account_type" class="block text-sm font-medium text-gray-700">Account Type*</label>
                            <select name="account_type" id="account_type" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <?php foreach($account_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="account_name" class="block text-sm font-medium text-gray-700">Account Name*</label>
                            <input type="text" name="account_name" id="account_name" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save Account</button>
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
    const typeSelect = document.getElementById('account_type');
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";

    function openModal() { 
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden'); 
    }
    function closeModal() { modal.classList.add('hidden'); form.reset(); }
    
    addBtn.addEventListener('click', () => {
        modalTitle.innerText = 'Add New Account';
        editIdField.value = '';
        typeSelect.disabled = false;
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.innerText = 'Edit Account';
            editIdField.value = btn.dataset.id;
            document.getElementById('account_name').value = btn.dataset.name;
            typeSelect.value = btn.dataset.type;
            document.getElementById('description').value = btn.dataset.description;
            
            // Prevent changing the type of an existing account
            typeSelect.disabled = true;

            openModal();
        });
    });
});
</script>

<?php
require_once '../../partials/footer.php';
?>