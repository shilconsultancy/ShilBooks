<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// Fetch Customers
$customer_sql = "SELECT id, name FROM customers WHERE user_id = :user_id ORDER BY name ASC";
$customer_stmt = $pdo->prepare($customer_sql);
$customer_stmt->execute(['user_id' => $userId]);
$customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    $invoice_id = $_POST['invoice_id'];
    $credit_note_date = $_POST['credit_note_date'];
    $amount = $_POST['amount'];
    $notes = $_POST['notes'];

    if (empty($customer_id) || empty($invoice_id) || empty($credit_note_date) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please fill all required fields with valid data.";
    }

    // Server-side validation for credit amount
    $inv_balance_sql = "SELECT (total - amount_paid) as balance_due FROM invoices WHERE id = ? AND user_id = ?";
    $inv_stmt = $pdo->prepare($inv_balance_sql);
    $inv_stmt->execute([$invoice_id, $userId]);
    $balance = $inv_stmt->fetchColumn();

    if ($amount > $balance) {
        $errors[] = "Credit amount cannot be greater than the invoice balance due of ৳" . number_format($balance, 2);
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM credit_notes WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            $cn_count = $stmt->fetchColumn();
            $credit_note_number = 'CN-' . str_pad($cn_count + 1, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO credit_notes (user_id, customer_id, invoice_id, credit_note_number, credit_note_date, amount, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $customer_id, $invoice_id, $credit_note_number, $credit_note_date, $amount, $notes]);
            
            // Apply the credit to the invoice by increasing its amount_paid
            $update_sql = "UPDATE invoices SET amount_paid = amount_paid + ? WHERE id = ?";
            $pdo->prepare($update_sql)->execute([$amount, $invoice_id]);

            // Update invoice status if it's now fully paid
            $status_sql = "UPDATE invoices SET status = 'paid' WHERE id = ? AND total <= amount_paid";
            $pdo->prepare($status_sql)->execute([$invoice_id]);

            $pdo->commit();
            header("location: " . BASE_PATH . "sales/credit-notes/");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error creating credit note: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Credit Note';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6"><h1 class="text-xl font-semibold text-macgray-800">Create New Credit Note</h1></header>
    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-xl mx-auto">
             <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><ul><?php foreach ($errors as $error) echo "<li>".htmlspecialchars($error)."</li>"; ?></ul></div>
            <?php endif; ?>
            <form action="create.php" method="POST" id="credit-note-form">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200 space-y-4">
                    <div>
                        <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer*</label>
                        <select name="customer_id" id="customer_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select a customer...</option>
                            <?php foreach ($customers as $customer) echo "<option value='{$customer['id']}'>".htmlspecialchars($customer['name'])."</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label for="invoice_id" class="block text-sm font-medium text-gray-700">Apply to Invoice*</label>
                        <select name="invoice_id" id="invoice_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" disabled>
                            <option value="">Select a customer first...</option>
                        </select>
                    </div>
                    <div id="invoice-details" class="text-sm text-gray-600 bg-macgray-50 p-3 rounded-md hidden"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                            <label for="credit_note_date" class="block text-sm font-medium text-gray-700">Credit Note Date*</label>
                            <input type="date" name="credit_note_date" id="credit_note_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">Credit Amount*</label>
                            <input type="number" name="amount" id="amount" required step="0.01" placeholder="0.00" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Create Credit Note</button></div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer_id');
    const invoiceSelect = document.getElementById('invoice_id');
    const invoiceDetails = document.getElementById('invoice-details');
    const amountInput = document.getElementById('amount');

    let invoicesData = [];

    customerSelect.addEventListener('change', function() {
        const customerId = this.value;
        invoiceSelect.innerHTML = '<option value="">Loading...</option>';
        invoiceDetails.classList.add('hidden');
        amountInput.value = '';
        amountInput.max = null;

        if (!customerId) {
            invoiceSelect.innerHTML = '<option value="">Select a customer first</option>';
            invoiceSelect.disabled = true;
            return;
        }
        fetch(`<?php echo BASE_PATH; ?>api/get_all_invoices.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                invoicesData = data;
                invoiceSelect.innerHTML = '<option value="">Select an invoice...</option>';
                if (data.error) { console.error(data.error); } 
                else {
                    data.forEach(invoice => {
                        if(parseFloat(invoice.balance_due) > 0) {
                           invoiceSelect.add(new Option(`${invoice.invoice_number} (Balance: ৳${invoice.balance_due})`, invoice.id));
                        }
                    });
                }
                invoiceSelect.disabled = false;
            });
    });

    invoiceSelect.addEventListener('change', function() {
        const invoiceId = this.value;
        const selectedInvoice = invoicesData.find(inv => inv.id == invoiceId);
        if(selectedInvoice){
            invoiceDetails.innerHTML = `Invoice Total: <strong>৳${parseFloat(selectedInvoice.total).toFixed(2)}</strong>, Amount Paid: <strong>৳${parseFloat(selectedInvoice.amount_paid).toFixed(2)}</strong>`;
            invoiceDetails.classList.remove('hidden');
            amountInput.max = parseFloat(selectedInvoice.balance_due).toFixed(2);
        } else {
            invoiceDetails.classList.add('hidden');
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