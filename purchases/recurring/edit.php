<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profile_id == 0) { header("location: index.php"); exit; }
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
            $sql = "UPDATE recurring_expenses SET 
                        vendor_id = :vendor_id, 
                        category_id = :category_id, 
                        description = :description, 
                        start_date = :start_date, 
                        end_date = :end_date, 
                        frequency = :frequency, 
                        amount = :amount, 
                        notes = :notes,
                        status = :status
                    WHERE id = :id";

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
                'status' => $_POST['status'],
                'id' => $profile_id
            ]);
            
            header("location: view.php?id=" . $profile_id);
            exit;

        } catch (Exception $e) {
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// --- Fetch existing data for form ---
$stmt = $pdo->prepare("SELECT * FROM recurring_expenses WHERE id = ?");
$stmt->execute([$profile_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) { header("location: index.php"); exit; }

$pageTitle = 'Edit Recurring Expense';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">Edit Recurring Expense Profile</h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <form action="edit.php?id=<?php echo $profile_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 space-y-4">
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description*</label>
                        <input type="text" name="description" id="description" value="<?php echo htmlspecialchars($profile['description']); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700">Category*</label>
                            <select name="category_id" id="category_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <?php foreach ($categories as $cat) echo "<option value='{$cat['id']}' ".($cat['id'] == $profile['category_id'] ? 'selected' : '').">".htmlspecialchars($cat['name'])."</option>"; ?>
                            </select>
                        </div>
                        <div>
                             <label for="vendor_id" class="block text-sm font-medium text-gray-700">Vendor (Optional)</label>
                            <select name="vendor_id" id="vendor_id" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">None</option>
                                <?php foreach ($vendors as $ven) echo "<option value='{$ven['id']}' ".($ven['id'] == $profile['vendor_id'] ? 'selected' : '').">".htmlspecialchars($ven['name'])."</option>"; ?>
                            </select>
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount*</label>
                            <input type="number" name="amount" id="amount" value="<?php echo htmlspecialchars($profile['amount']); ?>" step="0.01" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="frequency" class="block text-sm font-medium text-gray-700">Frequency*</label>
                            <select name="frequency" id="frequency" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="weekly" <?php echo $profile['frequency'] == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $profile['frequency'] == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="quarterly" <?php echo $profile['frequency'] == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="yearly" <?php echo $profile['frequency'] == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                            </select>
                        </div>
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date*</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($profile['start_date']); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status*</label>
                        <select name="status" id="status" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <option value="active" <?php echo $profile['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo $profile['status'] == 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="finished" <?php echo $profile['status'] == 'finished' ? 'selected' : ''; ?>>Finished</option>
                        </select>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($profile['notes']); ?></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Changes</button></div>
            </form>
        </div>
    </main>
</div>

<?php
require_once '../../partials/footer.php';
?>