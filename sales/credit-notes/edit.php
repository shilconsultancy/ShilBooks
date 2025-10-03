<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$credit_note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($credit_note_id == 0) {
    header("location: index.php");
    exit;
}

// --- Handle Form Submission for UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();

    $customer_id = $_POST['customer_id'];
    $invoice_id = $_POST['invoice_id'];
    $credit_note_date = $_POST['credit_note_date'];
    $amount = $_POST['amount'];
    $notes = $_POST['notes'];

    if (empty($customer_id) || empty($invoice_id) || empty($credit_note_date) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please fill all required fields with valid data.";
    }

    // Server-side validation for credit amount
    $inv_balance_sql = "SELECT (total - amount_paid) as balance_due FROM invoices WHERE id = ?";
    $inv_stmt = $pdo->prepare($inv_balance_sql);
    $inv_stmt->execute([$invoice_id]);
    $balance = $inv_stmt->fetchColumn();

    if ($amount > $balance) {
        $errors[] = "Credit amount cannot be greater than the invoice balance due of ৳" . number_format($balance, 2);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update the credit note
            $sql = "UPDATE credit_notes SET customer_id = ?, invoice_id = ?, credit_note_date = ?, amount = ?, notes = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$customer_id, $invoice_id, $credit_note_date, $amount, $notes, $credit_note_id]);

            // Update the invoice balance
            $update_sql = "UPDATE invoices SET amount_paid = amount_paid - (SELECT amount FROM credit_notes WHERE id = ?) + ? WHERE id = ?";
            $pdo->prepare($update_sql)->execute([$credit_note_id, $amount, $invoice_id]);

            // Update invoice status if needed
            $status_sql = "UPDATE invoices SET status = 'sent' WHERE id = ? AND amount_paid < total";
            $pdo->prepare($status_sql)->execute([$invoice_id]);

            $pdo->commit();
            header("location: view.php?id=" . $credit_note_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error updating credit note: " . $e->getMessage();
        }
    }
}


// --- Fetch existing data for the form ---
$cn_sql = "SELECT cn.*, c.name as customer_name, i.invoice_number FROM credit_notes cn JOIN customers c ON cn.customer_id = c.id JOIN invoices i ON cn.invoice_id = i.id WHERE cn.id = ?";
$cn_stmt = $pdo->prepare($cn_sql);
$cn_stmt->execute([$credit_note_id]);
$credit_note = $cn_stmt->fetch(PDO::FETCH_ASSOC);

if (!$credit_note) {
    header("location: index.php");
    exit;
}

// Fetch Customers for dropdown
$customer_stmt = $pdo->prepare("SELECT id, name FROM customers ORDER BY name ASC");
$customer_stmt->execute();
$customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = 'Edit Credit Note ' . htmlspecialchars($credit_note['credit_note_number']);
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6">
        <h1 class="text-xl font-semibold text-macgray-800">Edit Credit Note #<?php echo htmlspecialchars($credit_note['credit_note_number']); ?></h1>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <ul><?php foreach ($errors as $error) echo "<li>".htmlspecialchars($error)."</li>"; ?></ul>
            </div>
            <?php endif; ?>

            <form action="edit.php?id=<?php echo $credit_note_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 space-y-4">
                    <div>
                        <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer*</label>
                        <select name="customer_id" id="customer_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo ($customer['id'] == $credit_note['customer_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="invoice_id" class="block text-sm font-medium text-gray-700">Apply to Invoice*</label>
                        <select name="invoice_id" id="invoice_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select an invoice...</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="credit_note_date" class="block text-sm font-medium text-gray-700">Credit Note Date*</label>
                            <input type="date" name="credit_note_date" id="credit_note_date" value="<?php echo htmlspecialchars($credit_note['credit_note_date']); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">Credit Amount*</label>
                            <input type="number" name="amount" id="amount" required step="0.01" value="<?php echo htmlspecialchars($credit_note['amount']); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($credit_note['notes']); ?></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Update Credit Note</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer_id');
    const invoiceSelect = document.getElementById('invoice_id');
    const amountInput = document.getElementById('amount');

    let invoicesData = [];

    // Load current invoice data
    fetchInvoicesForCustomer(<?php echo $credit_note['customer_id']; ?>);

    function fetchInvoicesForCustomer(customerId) {
        fetch(`<?php echo BASE_PATH; ?>api/get_all_invoices.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                invoicesData = data;
                invoiceSelect.innerHTML = '<option value="">Select an invoice...</option>';
                if (data.error) {
                    console.error(data.error);
                } else {
                    data.forEach(invoice => {
                        if(parseFloat(invoice.balance_due) > 0) {
                            const selected = invoice.id == <?php echo $credit_note['invoice_id']; ?> ? 'selected' : '';
                            invoiceSelect.add(new Option(`${invoice.invoice_number} (Balance: ৳${invoice.balance_due})`, invoice.id, false, selected));
                        }
                    });
                }
            });
    }

    customerSelect.addEventListener('change', function() {
        const customerId = this.value;
        if (customerId) {
            fetchInvoicesForCustomer(customerId);
        } else {
            invoiceSelect.innerHTML = '<option value="">Select a customer first</option>';
        }
    });

    invoiceSelect.addEventListener('change', function() {
        const invoiceId = this.value;
        const selectedInvoice = invoicesData.find(inv => inv.id == invoiceId);
        if(selectedInvoice){
            amountInput.max = parseFloat(selectedInvoice.balance_due).toFixed(2);
        } else {
            amountInput.max = null;
        }
    });

    amountInput.addEventListener('input', function(){
        const max = parseFloat(this.max);
        if(parseFloat(this.value) > max){
            this.value = max.toFixed(2);
        }
    });
});
</script>

<?php
require_once '../../partials/footer.php';
?>