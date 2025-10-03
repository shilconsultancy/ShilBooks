<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ".BASE_PATH."index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// Fetch Customers for the dropdown
$customer_sql = "SELECT id, name FROM customers ORDER BY name ASC";
$customer_stmt = $pdo->prepare($customer_sql);
$customer_stmt->execute();
$customers = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Define the list of payment methods ---
$payment_methods = ['Bank Transfer', 'Cash', 'Credit Card', 'Check', 'Mobile Banking', 'Online Payment Gateway'];


// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    $customer_id = $_POST['customer_id'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    $amounts_applied = $_POST['amount_applied'] ?? [];
    $invoice_ids = $_POST['invoice_id'] ?? [];
    
    // The total amount is the sum of all applied amounts
    $amount = array_sum($amounts_applied);

    if (empty($customer_id) || empty($payment_date) || $amount <= 0) {
        $errors[] = "Please select a customer, a date, and apply a payment to at least one invoice.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Insert into payments table with the calculated total
            $sql = "INSERT INTO payments (customer_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$customer_id, $payment_date, $amount, $payment_method, $notes]);
            $payment_id = $pdo->lastInsertId();

            $affected_invoice_ids = [];

            // 2. Insert into invoice_payments and update invoices
            foreach ($invoice_ids as $key => $invoice_id) {
                $amount_applied = (float)($amounts_applied[$key] ?? 0);
                if ($amount_applied > 0) {
                    $sql_link = "INSERT INTO invoice_payments (payment_id, invoice_id, amount_applied) VALUES (?, ?, ?)";
                    $pdo->prepare($sql_link)->execute([$payment_id, $invoice_id, $amount_applied]);

                    $sql_update = "UPDATE invoices SET amount_paid = amount_paid + ? WHERE id = ?";
                    $pdo->prepare($sql_update)->execute([$amount_applied, $invoice_id]);
                    
                    $affected_invoice_ids[] = $invoice_id;
                }
            }
            
            // 3. Update status for any invoices that are now fully paid
            if (!empty($affected_invoice_ids)) {
                $placeholders = implode(',', array_fill(0, count($affected_invoice_ids), '?'));
                $sql_status = "UPDATE invoices SET status = 'paid' WHERE id IN ($placeholders) AND total <= amount_paid";
                $pdo->prepare($sql_status)->execute($affected_invoice_ids);
            }

            $pdo->commit();
            header("location: ".BASE_PATH."sales/payments/");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error recording payment: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Record Payment';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6"><h1 class="text-xl font-semibold text-macgray-800">Record New Payment</h1></header>
    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><ul><?php foreach ($errors as $error) echo "<li>".htmlspecialchars($error)."</li>"; ?></ul></div>
            <?php endif; ?>

            <form action="create.php" method="POST" id="payment-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer*</label>
                            <select name="customer_id" id="customer_id" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select a customer...</option>
                                <?php foreach ($customers as $customer) echo "<option value='{$customer['id']}'>".htmlspecialchars($customer['name'])."</option>"; ?>
                            </select>
                        </div>
                        <div>
                            <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date*</label>
                            <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select a method...</option>
                                <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method); ?>"><?php echo htmlspecialchars($method); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>

                <div id="invoices-section" class="mt-6 bg-white p-6 rounded-xl shadow-sm border border-macgray-200 hidden">
                    <h2 class="text-lg font-medium text-macgray-800 mb-4">Apply Payment to Invoices</h2>
                    <div id="invoices-list-container" class="space-y-4">
                        </div>
                    <div class="mt-4 pt-4 border-t flex justify-end items-center space-x-4 text-sm font-medium">
                        <div class="font-bold text-lg">Total Received: <span id="total-received-display" class="text-green-600">৳0.00</span></div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end"><button type="submit" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Record Payment</button></div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer_id');
    const invoicesSection = document.getElementById('invoices-section');
    const invoicesContainer = document.getElementById('invoices-list-container');
    const totalReceivedDisplay = document.getElementById('total-received-display');

    customerSelect.addEventListener('change', function() {
        const customerId = this.value;
        invoicesContainer.innerHTML = '';
        if (!customerId) {
            invoicesSection.classList.add('hidden');
            return;
        }

        invoicesSection.classList.remove('hidden');
        invoicesContainer.innerHTML = '<p>Loading invoices...</p>';

        fetch(`<?php echo BASE_PATH; ?>api/get_invoices.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                invoicesContainer.innerHTML = '';
                if (data.error) {
                    invoicesContainer.innerHTML = `<p class="text-red-500">${data.error}</p>`;
                } else if (data.length === 0) {
                    invoicesContainer.innerHTML = '<p>This customer has no outstanding invoices.</p>';
                } else {
                    const headerHtml = `
                        <div class="grid grid-cols-5 gap-4 items-center font-semibold text-xs text-gray-500">
                            <div class="col-span-2">Invoice #</div>
                            <div>Balance Due</div>
                            <div class="col-span-2">Payment Amount</div>
                        </div>
                    `;
                    invoicesContainer.insertAdjacentHTML('beforeend', headerHtml);

                    data.forEach(invoice => {
                        const invoiceHtml = `
                            <div class="grid grid-cols-5 gap-4 items-center border-t pt-2">
                                <div class="col-span-2">
                                    <input type="hidden" name="invoice_id[]" value="${invoice.id}">
                                    <span class="font-medium">${invoice.invoice_number}</span>
                                    <span class="text-xs text-gray-500 block">Due: ${invoice.due_date}</span>
                                </div>
                                <div class="text-gray-600">৳${parseFloat(invoice.balance_due).toFixed(2)}</div>
                                <div class="col-span-2">
                                    <input type="number" name="amount_applied[]" 
                                           class="amount-applied-input mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" 
                                           placeholder="0.00" step="0.01" min="0" max="${invoice.balance_due}">
                                </div>
                            </div>
                        `;
                        invoicesContainer.insertAdjacentHTML('beforeend', invoiceHtml);
                    });
                }
                calculateTotalReceived();
            })
            .catch(error => {
                invoicesContainer.innerHTML = '<p class="text-red-500">Failed to load invoices.</p>';
                console.error('Error:', error);
            });
    });

    invoicesContainer.addEventListener('input', function(e) {
        if (e.target.classList.contains('amount-applied-input')) {
            calculateTotalReceived();
        }
    });

    function calculateTotalReceived() {
        let totalApplied = 0;
        document.querySelectorAll('.amount-applied-input').forEach(input => {
            const max = parseFloat(input.max);
            if (parseFloat(input.value) > max) {
                input.value = max.toFixed(2);
            }
            totalApplied += parseFloat(input.value) || 0;
        });
        totalReceivedDisplay.innerText = '৳' + totalApplied.toFixed(2);
    }
});
</script>

<?php
require_once '../../partials/footer.php';
?>