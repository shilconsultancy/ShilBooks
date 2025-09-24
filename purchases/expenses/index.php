<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// --- Fetch data for form dropdowns ---
$cat_sql = "SELECT id, name FROM expense_categories WHERE user_id = :user_id ORDER BY name ASC";
$cat_stmt = $pdo->prepare($cat_sql);
$cat_stmt->execute(['user_id' => $userId]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$ven_sql = "SELECT id, name FROM vendors WHERE user_id = :user_id ORDER BY name ASC";
$ven_stmt = $pdo->prepare($ven_sql);
$ven_stmt->execute(['user_id' => $userId]);
$vendors = $ven_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Handle POST request (Add or Edit an Expense) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();

    $expense_date = $_POST['expense_date'];
    $amount = $_POST['amount'];
    $category_id = $_POST['category_id'];
    $vendor_id = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;
    $description = trim($_POST['description']); // New description field
    $notes = trim($_POST['notes']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($expense_date) || empty($amount) || empty($category_id) || empty($description) || !is_numeric($amount)) {
        $errors[] = "Date, amount, category, and description are required.";
    }

    if (empty($errors)) {
        if ($edit_id) {
            $sql = "UPDATE expenses SET expense_date = ?, amount = ?, category_id = ?, vendor_id = ?, description = ?, notes = ? WHERE id = ? AND user_id = ?";
            $params = [$expense_date, $amount, $category_id, $vendor_id, $description, $notes, $edit_id, $userId];
        } else {
            $sql = "INSERT INTO expenses (user_id, expense_date, amount, category_id, vendor_id, description, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $params = [$userId, $expense_date, $amount, $category_id, $vendor_id, $description, $notes];
        }
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            header("location: index.php");
            exit;
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}

// --- Handle GET request (Delete an Expense) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $sql = "DELETE FROM expenses WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute(['id' => $delete_id, 'user_id' => $userId])) {
        header("location: index.php");
        exit;
    }
}

// --- Fetch all expenses for display ---
$sql = "SELECT e.*, ec.name as category_name, v.name as vendor_name 
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN vendors v ON e.vendor_id = v.id
        WHERE e.user_id = :user_id 
        ORDER BY e.expense_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $userId]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manage Expenses';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Expenses</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i><span>New Expense</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($expenses)): ?>
                                <tr><td colspan="5" class="px-6 py-4 text-center text-macgray-500">No expenses recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($expense['expense_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($expense['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($expense['amount'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $expense['id']; ?>" class="text-green-600 hover:text-green-900">View</a>
                                            <button class="editBtn text-macblue-600 hover:text-macblue-900 ml-4" 
                                                data-id="<?php echo $expense['id']; ?>"
                                                data-date="<?php echo htmlspecialchars($expense['expense_date']); ?>"
                                                data-amount="<?php echo htmlspecialchars($expense['amount']); ?>"
                                                data-category-id="<?php echo $expense['category_id']; ?>"
                                                data-vendor-id="<?php echo $expense['vendor_id']; ?>"
                                                data-description="<?php echo htmlspecialchars($expense['description']); ?>"
                                                data-notes="<?php echo htmlspecialchars($expense['notes']); ?>">Edit</button>
                                            <a href="index.php?action=delete&id=<?php echo $expense['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?');">Delete</a>
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
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New Expense</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label for="expense_date" class="block text-sm font-medium text-gray-700">Date*</label><input type="date" name="expense_date" id="expense_date" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                            <div><label for="amount" class="block text-sm font-medium text-gray-700">Amount*</label><input type="number" name="amount" id="amount" required step="0.01" placeholder="0.00" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        </div>
                        <div><label for="description" class="block text-sm font-medium text-gray-700">Description*</label><input type="text" name="description" id="description" required placeholder="e.g., Office Lunch" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="category_id" class="block text-sm font-medium text-gray-700">Category*</label><select name="category_id" id="category_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><option value="">Select a category...</option><?php foreach($categories as $cat) echo "<option value='{$cat['id']}'>".htmlspecialchars($cat['name'])."</option>"; ?></select></div>
                         <div><label for="vendor_id" class="block text-sm font-medium text-gray-700">Vendor (Optional)</label><select name="vendor_id" id="vendor_id" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><option value="">None</option><?php foreach($vendors as $ven) echo "<option value='{$ven['id']}'>".htmlspecialchars($ven['name'])."</option>"; ?></select></div>
                        <div><label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label><textarea name="notes" id="notes" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
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
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";


    function openModal() { 
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden'); 
    }
    function closeModal() { modal.classList.add('hidden'); form.reset(); }
    
    addBtn.addEventListener('click', () => {
        modalTitle.innerText = 'Add New Expense';
        editIdField.value = '';
        document.getElementById('expense_date').value = new Date().toISOString().slice(0, 10);
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.innerText = 'Edit Expense';
            editIdField.value = btn.dataset.id;
            document.getElementById('expense_date').value = btn.dataset.date;
            document.getElementById('amount').value = btn.dataset.amount;
            document.getElementById('category_id').value = btn.dataset.categoryId;
            document.getElementById('vendor_id').value = btn.dataset.vendorId;
            document.getElementById('description').value = btn.dataset.description;
            document.getElementById('notes').value = btn.dataset.notes;
            openModal();
        });
    });
});
</script>

<?php
require_once '../../partials/footer.php';
?>