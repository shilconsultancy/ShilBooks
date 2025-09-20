<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Expenses';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $vendorId = intval($_POST['vendor_id']);
                    $expenseDate = $_POST['expense_date'];
                    $category = sanitizeInput($_POST['category']);
                    $description = sanitizeInput($_POST['description']);
                    $amount = floatval($_POST['amount']);
                    $taxAmount = floatval($_POST['tax_amount']);
                    $paymentMethod = sanitizeInput($_POST['payment_method']);

                    $expenseNumber = 'EXP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                    $stmt = $pdo->prepare("
                        INSERT INTO expenses (expense_number, vendor_id, expense_date, category, description, amount, tax_amount, payment_method, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$expenseNumber, $vendorId, $expenseDate, $category, $description, $amount, $taxAmount, $paymentMethod, 1]);
                    $success = 'Expense added successfully!';
                    break;

                case 'update_status':
                    $id = intval($_POST['id']);
                    $status = sanitizeInput($_POST['status']);

                    $stmt = $pdo->prepare("UPDATE expenses SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    $success = 'Expense status updated successfully!';
                    break;
            }
        }
    }

    // Get all expenses with vendor information
    $stmt = $pdo->query("
        SELECT e.*, v.name as vendor_name
        FROM expenses e
        LEFT JOIN vendors v ON e.vendor_id = v.id
        ORDER BY e.created_at DESC
    ");
    $expenses = $stmt->fetchAll();

    // Get expense statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM expenses");
    $totalExpenses = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses WHERE status = 'paid'");
    $totalPaid = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses WHERE status = 'pending'");
    $totalPending = $stmt->fetch()['total'] ?? 0;

    // Get vendors for dropdown
    $stmt = $pdo->query("SELECT id, name FROM vendors WHERE is_active = 1 ORDER BY name");
    $vendors = $stmt->fetchAll();

    // Get expense categories
    $categories = [
        'Office Supplies',
        'Utilities',
        'Rent',
        'Marketing',
        'Travel',
        'Software & Licenses',
        'Equipment',
        'Professional Services',
        'Insurance',
        'Other'
    ];

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
            <h1 class="text-xl font-semibold text-gray-800">Expenses</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addExpenseModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Expense</span>
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

            <!-- Expense Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Expenses</h3>
                            <div class="amount"><?php echo $totalExpenses; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-receipt text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Paid</h3>
                            <div class="amount"><?php echo formatCurrency($totalPaid); ?></div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Pending Approval</h3>
                            <div class="amount"><?php echo formatCurrency($totalPending); ?></div>
                        </div>
                        <div class="stats-icon bg-yellow-100">
                            <i class="fas fa-clock text-yellow-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Expenses</h2>
                        <div class="flex items-center space-x-4">
                            <select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option>All Status</option>
                                <option>Pending</option>
                                <option>Approved</option>
                                <option>Paid</option>
                                <option>Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expense #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $expense['expense_number']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($expense['expense_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $expense['vendor_name'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $expense['category']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo substr($expense['description'], 0, 50) . (strlen($expense['description']) > 50 ? '...' : ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($expense['amount']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                            <?php
                                            switch($expense['status']) {
                                                case 'paid':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'approved':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'pending':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'rejected':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($expense['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewExpense(<?php echo $expense['id']; ?>)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <select onchange="updateExpenseStatus(<?php echo $expense['id']; ?>, this.value)" class="text-xs border border-gray-300 rounded px-2 py-1">
                                            <option value="">Change Status</option>
                                            <option value="pending" <?php echo $expense['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $expense['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="paid" <?php echo $expense['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="rejected" <?php echo $expense['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add New Expense</h3>
                <button onclick="closeModal('addExpenseModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Select Vendor</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo $vendor['id']; ?>"><?php echo $vendor['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expense Date *</label>
                        <input type="date" name="expense_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="card">Card</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" name="amount" class="form-input" placeholder="0.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tax Amount</label>
                        <input type="number" step="0.01" name="tax_amount" class="form-input" placeholder="0.00" value="0">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description *</label>
                        <textarea name="description" rows="3" class="form-input" placeholder="Describe the expense..." required></textarea>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Receipt</label>
                        <input type="file" name="receipt" class="form-input" accept="image/*,.pdf,.doc,.docx">
                        <p class="text-xs text-gray-500 mt-1">Upload receipt or supporting document (optional)</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addExpenseModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateExpenseStatus(expenseId, status) {
    if (status) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="${expenseId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewExpense(id) {
    // In a real application, you would redirect to a detailed expense view
    alert('Expense view functionality would be implemented here');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>