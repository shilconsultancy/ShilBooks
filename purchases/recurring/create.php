<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$errors = [];

// --- Fetch data for form dropdowns ---
$cat_sql = "SELECT id, name FROM expense_categories ORDER BY name ASC";
$cat_stmt = $pdo->prepare($cat_sql);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$ven_sql = "SELECT id, name FROM vendors ORDER BY name ASC";
$ven_stmt = $pdo->prepare($ven_sql);
$ven_stmt->execute();
$vendors = $ven_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    if (empty($_POST['description']) || empty($_POST['amount']) || empty($_POST['category_id']) || empty($_POST['start_date']) || empty($_POST['frequency'])) {
        $errors[] = "Please fill all required fields.";
    }
    if (!empty($_POST['end_date']) && $_POST['end_date'] < $_POST['start_date']) {
        $errors[] = "End date cannot be before the start date.";
    }

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO recurring_expenses (vendor_id, category_id, description, start_date, end_date, frequency, amount, notes, status)
                    VALUES (:vendor_id, :category_id, :description, :start_date, :end_date, :frequency, :amount, :notes, :status)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'vendor_id' => !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null,
                'category_id' => $_POST['category_id'],
                'description' => $_POST['description'],
                'start_date' => $_POST['start_date'],
                'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
                'frequency' => $_POST['frequency'],
                'amount' => $_POST['amount'],
                'notes' => $_POST['notes'],
                'status' => 'active'
            ]);
            
            header("location: " . BASE_PATH . "purchases/recurring/");
            exit;

        } catch (Exception $e) {
            $errors[] = "Error creating profile: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Recurring Expense';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">New Recurring Expense Profile</h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <form action="create.php" method="POST" id="recurring-expense-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 space-y-4">
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description*</label>
                        <input type="text" name="description" id="description" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700">Category*</label>
                            <select name="category_id" id="category_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat) echo "<option value='{$cat['id']}'>".htmlspecialchars($cat['name'])."</option>"; ?>
                            </select>
                        </div>
                        <div>
                             <label for="vendor_id" class="block text-sm font-medium text-gray-700">Vendor (Optional)</label>
                            <select name="vendor_id" id="vendor_id" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">None</option>
                                <?php foreach ($vendors as $ven) echo "<option value='{$ven['id']}'>".htmlspecialchars($ven['name'])."</option>"; ?>
                            </select>
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount*</label>
                            <input type="number" name="amount" id="amount" step="0.01" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="frequency" class="block text-sm font-medium text-gray-700">Frequency*</label>
                            <select name="frequency" id="frequency" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date*</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Profile</button></div>
            </form>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>