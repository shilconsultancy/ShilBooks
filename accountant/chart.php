<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Chart of Accounts';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $accountCode = sanitizeInput($_POST['account_code']);
                    $accountName = sanitizeInput($_POST['account_name']);
                    $accountType = sanitizeInput($_POST['account_type']);
                    $parentId = intval($_POST['parent_id']);
                    $description = sanitizeInput($_POST['description']);

                    $stmt = $pdo->prepare("
                        INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, description)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$accountCode, $accountName, $accountType, $parentId ?: null, $description]);
                    $success = 'Account added successfully!';
                    break;

                case 'update':
                    $id = intval($_POST['id']);
                    $accountCode = sanitizeInput($_POST['account_code']);
                    $accountName = sanitizeInput($_POST['account_name']);
                    $accountType = sanitizeInput($_POST['account_type']);
                    $parentId = intval($_POST['parent_id']);
                    $description = sanitizeInput($_POST['description']);

                    $stmt = $pdo->prepare("
                        UPDATE chart_of_accounts SET account_code = ?, account_name = ?, account_type = ?, parent_id = ?, description = ? WHERE id = ?
                    ");
                    $stmt->execute([$accountCode, $accountName, $accountType, $parentId ?: null, $description, $id]);
                    $success = 'Account updated successfully!';
                    break;

                case 'delete':
                    $id = intval($_POST['id']);
                    // Check if account has children or is used in transactions
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chart_of_accounts WHERE parent_id = ?");
                    $stmt->execute([$id]);
                    $hasChildren = $stmt->fetch()['count'] > 0;

                    if ($hasChildren) {
                        $error = 'Cannot delete account with child accounts.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM chart_of_accounts WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = 'Account deleted successfully!';
                    }
                    break;
            }
        }
    }

    // Get all accounts organized by type
    $accountsByType = [];
    $accountTypes = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expenses'];

    foreach ($accountTypes as $type => $label) {
        $stmt = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE account_type = ? AND is_active = 1 ORDER BY account_code");
        $stmt->execute([$type]);
        $accountsByType[$type] = $stmt->fetchAll();
    }

    // Get all accounts for parent dropdown
    $stmt = $pdo->query("SELECT id, account_code, account_name, account_type FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_type, account_code");
    $allAccounts = $stmt->fetchAll();

    // Get account statistics
    $totalAccounts = array_sum(array_map('count', $accountsByType));

} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!-- Mobile menu button -->
<div class="md:hidden fixed top-4 left-4 z-50">
    <button id="menuToggle" class="p-2 rounded-md bg-white shadow-md text-gray-600">
        <i class="fas fa-bars"></i>
    </button>
</div>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main content -->
<div class="main-content">
    <!-- Top bar -->
    <header class="top-bar">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-gray-800">Chart of Accounts</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addAccountModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Account</span>
            </button>
        </div>
    </header>

    <!-- Content area -->
    <main class="content-area">
        <div class="max-w-7xl mx-auto">
            <?php if ($success): ?>
                <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Account Stats -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <?php foreach ($accountTypes as $type => $label): ?>
                    <div class="stats-card">
                        <div class="stats-card-content">
                            <div class="stats-info">
                                <h3 class="text-sm font-medium text-gray-500"><?php echo $label; ?></h3>
                                <div class="amount"><?php echo count($accountsByType[$type]); ?></div>
                            </div>
                            <div class="stats-icon
                                <?php
                                switch($type) {
                                    case 'asset':
                                        echo 'bg-green-100';
                                        break;
                                    case 'liability':
                                        echo 'bg-red-100';
                                        break;
                                    case 'equity':
                                        echo 'bg-blue-100';
                                        break;
                                    case 'income':
                                        echo 'bg-purple-100';
                                        break;
                                    case 'expense':
                                        echo 'bg-yellow-100';
                                        break;
                                }
                                ?>">
                                <i class="fas fa-file-invoice text-gray-600"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Accounts by Type -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($accountsByType as $type => $accounts): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-gray-800">
                                    <?php echo $accountTypes[$type]; ?>
                                    <span class="text-sm font-normal text-gray-500">(<?php echo count($accounts); ?> accounts)</span>
                                </h2>
                            </div>
                        </div>

                        <div class="p-6">
                            <?php if (empty($accounts)): ?>
                                <p class="text-gray-500 text-center py-8">No accounts in this category.</p>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($accounts as $account): ?>
                                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                            <div>
                                                <h3 class="font-medium text-gray-900"><?php echo $account['account_name']; ?></h3>
                                                <p class="text-sm text-gray-500">Code: <?php echo $account['account_code']; ?></p>
                                                <?php if ($account['description']): ?>
                                                    <p class="text-xs text-gray-400"><?php echo $account['description']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <button onclick="editAccount(<?php echo $account['id']; ?>)" class="text-primary-600 hover:text-primary-900">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="deleteAccount(<?php echo $account['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add Account Modal -->
<div id="addAccountModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add New Account</h3>
                <button onclick="closeModal('addAccountModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Account Code *</label>
                        <input type="text" name="account_code" class="form-input" placeholder="e.g., 1000, 1100" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Name *</label>
                        <input type="text" name="account_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Type *</label>
                        <select name="account_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Parent Account</label>
                        <select name="parent_id" class="form-select">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($allAccounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-input" placeholder="Optional description of the account"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addAccountModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div id="editAccountModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Edit Account</h3>
                <button onclick="closeModal('editAccountModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="editAccountForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editAccountId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Account Code *</label>
                        <input type="text" name="account_code" id="editAccountCode" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Name *</label>
                        <input type="text" name="account_name" id="editAccountName" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Type *</label>
                        <select name="account_type" id="editAccountType" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Parent Account</label>
                        <select name="parent_id" id="editParentId" class="form-select">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($allAccounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" rows="3" class="form-input"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editAccountModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Update Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAccount(id) {
    // In a real application, you would fetch the account data via AJAX
    // For now, we'll just open the modal
    document.getElementById('editAccountId').value = id;
    openModal('editAccountModal');
}

function deleteAccount(id) {
    if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>