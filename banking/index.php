<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Banking';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_account':
                    $accountName = sanitizeInput($_POST['account_name']);
                    $accountNumber = sanitizeInput($_POST['account_number']);
                    $bankName = sanitizeInput($_POST['bank_name']);
                    $accountType = sanitizeInput($_POST['account_type']);
                    $balance = floatval($_POST['balance']);

                    $stmt = $pdo->prepare("
                        INSERT INTO bank_accounts (account_name, account_number, bank_name, account_type, balance)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$accountName, $accountNumber, $bankName, $accountType, $balance]);
                    $success = 'Bank account added successfully!';
                    break;

                case 'add_transaction':
                    $accountId = intval($_POST['account_id']);
                    $transactionDate = $_POST['transaction_date'];
                    $description = sanitizeInput($_POST['description']);
                    $amount = floatval($_POST['amount']);
                    $transactionType = sanitizeInput($_POST['transaction_type']);
                    $referenceNumber = sanitizeInput($_POST['reference_number']);

                    $stmt = $pdo->prepare("
                        INSERT INTO bank_transactions (account_id, transaction_date, description, amount, transaction_type, reference_number)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$accountId, $transactionDate, $description, $amount, $transactionType, $referenceNumber]);

                    // Update account balance
                    $balanceChange = ($transactionType === 'credit') ? $amount : -$amount;
                    $stmt = $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$balanceChange, $accountId]);

                    $success = 'Transaction added successfully!';
                    break;

                case 'reconcile':
                    $transactionId = intval($_POST['transaction_id']);
                    $stmt = $pdo->prepare("UPDATE bank_transactions SET reconciled = 1 WHERE id = ?");
                    $stmt->execute([$transactionId]);
                    $success = 'Transaction reconciled successfully!';
                    break;
            }
        }
    }

    // Get all bank accounts
    $stmt = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name, account_name");
    $accounts = $stmt->fetchAll();

    // Get account statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bank_accounts WHERE is_active = 1");
    $totalAccounts = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(balance) as total FROM bank_accounts WHERE is_active = 1");
    $totalBalance = $stmt->fetch()['total'] ?? 0;

    // Get recent transactions
    $stmt = $pdo->query("
        SELECT bt.*, ba.account_name, ba.bank_name
        FROM bank_transactions bt
        LEFT JOIN bank_accounts ba ON bt.account_id = ba.id
        ORDER BY bt.transaction_date DESC, bt.created_at DESC
        LIMIT 10
    ");
    $recentTransactions = $stmt->fetchAll();

    // Get unreconciled transactions count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bank_transactions WHERE reconciled = 0");
    $unreconciledCount = $stmt->fetch()['total'];

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
            <h1 class="text-xl font-semibold text-gray-800">Banking</h1>
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

            <!-- Banking Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Accounts</h3>
                            <div class="amount"><?php echo $totalAccounts; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-university text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Balance</h3>
                            <div class="amount"><?php echo formatCurrency($totalBalance); ?></div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Unreconciled</h3>
                            <div class="amount"><?php echo $unreconciledCount; ?></div>
                        </div>
                        <div class="stats-icon bg-yellow-100">
                            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Accounts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-800">Bank Accounts</h2>
                            <button onclick="openModal('addAccountModal')" class="px-3 py-1 bg-primary-500 text-white rounded text-sm hover:bg-primary-600">
                                <i class="fas fa-plus"></i> Add Account
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <?php if (empty($accounts)): ?>
                            <p class="text-gray-500 text-center py-8">No bank accounts added yet.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($accounts as $account): ?>
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                        <div>
                                            <h3 class="font-medium text-gray-900"><?php echo $account['account_name']; ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo $account['bank_name']; ?> • <?php echo ucfirst($account['account_type']); ?></p>
                                            <p class="text-xs text-gray-400">Account: <?php echo $account['account_number']; ?></p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-semibold text-gray-900">
                                                <?php echo formatCurrency($account['balance']); ?>
                                            </div>
                                            <button onclick="addTransaction(<?php echo $account['id']; ?>)" class="text-sm text-primary-600 hover:text-primary-800">
                                                Add Transaction
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-800">Recent Transactions</h2>
                            <a href="#" class="text-sm text-primary-500 hover:text-primary-600">View All</a>
                        </div>
                    </div>

                    <div class="p-6">
                        <?php if (empty($recentTransactions)): ?>
                            <p class="text-gray-500 text-center py-8">No transactions recorded yet.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <div class="flex items-center justify-between py-2">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900"><?php echo $transaction['description']; ?></p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo $transaction['account_name']; ?> • <?php echo formatDate($transaction['transaction_date']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium
                                                <?php echo $transaction['transaction_type'] === 'credit' ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo ($transaction['transaction_type'] === 'credit' ? '+' : '-') . formatCurrency($transaction['amount']); ?>
                                            </div>
                                            <?php if (!$transaction['reconciled']): ?>
                                                <button onclick="reconcileTransaction(<?php echo $transaction['id']; ?>)" class="text-xs text-yellow-600 hover:text-yellow-800">
                                                    Reconcile
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-green-600">✓ Reconciled</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Account Modal -->
<div id="addAccountModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add Bank Account</h3>
                <button onclick="closeModal('addAccountModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add_account">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Account Name *</label>
                        <input type="text" name="account_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bank Name *</label>
                        <input type="text" name="bank_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Number *</label>
                        <input type="text" name="account_number" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Type *</label>
                        <select name="account_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="checking">Checking</option>
                            <option value="savings">Savings</option>
                            <option value="credit_card">Credit Card</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Current Balance</label>
                        <input type="number" step="0.01" name="balance" class="form-input" value="0.00">
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

<!-- Add Transaction Modal -->
<div id="addTransactionModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add Transaction</h3>
                <button onclick="closeModal('addTransactionModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add_transaction">
                <input type="hidden" name="account_id" id="transactionAccountId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Transaction Date *</label>
                        <input type="date" name="transaction_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Type *</label>
                        <select name="transaction_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="debit">Debit (Money Out)</option>
                            <option value="credit">Credit (Money In)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" name="amount" class="form-input" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-input" placeholder="Check #, Transaction ID">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description *</label>
                        <textarea name="description" rows="3" class="form-input" placeholder="Transaction description..." required></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addTransactionModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addTransaction(accountId) {
    document.getElementById('transactionAccountId').value = accountId;
    openModal('addTransactionModal');
}

function reconcileTransaction(transactionId) {
    if (confirm('Mark this transaction as reconciled?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reconcile">
            <input type="hidden" name="transaction_id" value="${transactionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>