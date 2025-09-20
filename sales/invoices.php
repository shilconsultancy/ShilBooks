<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Invoices';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $customerId = intval($_POST['customer_id']);
                    $invoiceDate = $_POST['invoice_date'];
                    $dueDate = $_POST['due_date'];
                    $subtotal = floatval($_POST['subtotal']);
                    $taxAmount = floatval($_POST['tax_amount']);
                    $totalAmount = floatval($_POST['total_amount']);
                    $notes = sanitizeInput($_POST['notes']);

                    $invoiceNumber = generateInvoiceNumber();

                    $stmt = $pdo->prepare("
                        INSERT INTO invoices (invoice_number, customer_id, invoice_date, due_date, subtotal, tax_amount, total_amount, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$invoiceNumber, $customerId, $invoiceDate, $dueDate, $subtotal, $taxAmount, $totalAmount, $notes]);
                    $invoiceId = $pdo->lastInsertId();

                    // Add invoice items
                    if (isset($_POST['items']) && is_array($_POST['items'])) {
                        foreach ($_POST['items'] as $item) {
                            $stmt = $pdo->prepare("
                                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, tax_rate, total_amount)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $invoiceId,
                                $item['description'],
                                $item['quantity'],
                                $item['unit_price'],
                                $item['tax_rate'],
                                $item['total_amount']
                            ]);
                        }
                    }

                    $success = 'Invoice created successfully!';
                    break;

                case 'update_status':
                    $id = intval($_POST['id']);
                    $status = sanitizeInput($_POST['status']);

                    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    $success = 'Invoice status updated successfully!';
                    break;
            }
        }
    }

    // Get all invoices with customer information
    $stmt = $pdo->query("
        SELECT i.*, c.name as customer_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        ORDER BY i.created_at DESC
    ");
    $invoices = $stmt->fetchAll();

    // Get invoice statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM invoices");
    $totalInvoices = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM invoices WHERE status = 'paid'");
    $totalRevenue = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT SUM(total_amount - paid_amount) as outstanding FROM invoices WHERE status IN ('sent', 'overdue')");
    $totalOutstanding = $stmt->fetch()['outstanding'] ?? 0;

    // Get customers for dropdown
    $stmt = $pdo->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
    $customers = $stmt->fetchAll();

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
            <h1 class="text-xl font-semibold text-gray-800">Invoices</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addInvoiceModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Create Invoice</span>
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

            <!-- Invoice Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Invoices</h3>
                            <div class="amount"><?php echo $totalInvoices; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-file-invoice text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                            <div class="amount"><?php echo formatCurrency($totalRevenue); ?></div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Outstanding</h3>
                            <div class="amount"><?php echo formatCurrency($totalOutstanding); ?></div>
                        </div>
                        <div class="stats-icon bg-yellow-100">
                            <i class="fas fa-exclamation-circle text-yellow-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Invoices</h2>
                        <div class="flex items-center space-x-4">
                            <select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option>All Status</option>
                                <option>Draft</option>
                                <option>Sent</option>
                                <option>Paid</option>
                                <option>Overdue</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $invoice['invoice_number']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $invoice['customer_name'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($invoice['invoice_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($invoice['due_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($invoice['total_amount']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                            <?php
                                            switch($invoice['status']) {
                                                case 'paid':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'sent':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'overdue':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($invoice['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button onclick="editInvoice(<?php echo $invoice['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <select onchange="updateInvoiceStatus(<?php echo $invoice['id']; ?>, this.value)" class="text-xs border border-gray-300 rounded px-2 py-1">
                                            <option value="">Change Status</option>
                                            <option value="sent" <?php echo $invoice['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                            <option value="paid" <?php echo $invoice['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="overdue" <?php echo $invoice['status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                            <option value="cancelled" <?php echo $invoice['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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

<!-- Add Invoice Modal -->
<div id="addInvoiceModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Create New Invoice</h3>
                <button onclick="closeModal('addInvoiceModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="invoiceForm">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="form-group">
                        <label class="form-label">Customer *</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>"><?php echo $customer['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" name="invoice_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-input" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="mb-6">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Invoice Items</h4>
                    <div id="invoiceItems">
                        <div class="grid grid-cols-12 gap-2 mb-2 p-3 bg-gray-50 rounded">
                            <div class="col-span-4">
                                <label class="form-label">Description</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Qty</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Unit Price</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Tax Rate (%)</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Total</label>
                            </div>
                        </div>
                        <div class="invoice-item grid grid-cols-12 gap-2 mb-2 p-3 border border-gray-200 rounded">
                            <div class="col-span-4">
                                <input type="text" name="items[0][description]" class="form-input" placeholder="Item description" required>
                            </div>
                            <div class="col-span-2">
                                <input type="number" step="0.01" name="items[0][quantity]" class="form-input" placeholder="1" required>
                            </div>
                            <div class="col-span-2">
                                <input type="number" step="0.01" name="items[0][unit_price]" class="form-input" placeholder="0.00" required>
                            </div>
                            <div class="col-span-2">
                                <input type="number" step="0.01" name="items[0][tax_rate]" class="form-input" placeholder="0" value="0">
                            </div>
                            <div class="col-span-2">
                                <input type="number" step="0.01" name="items[0][total_amount]" class="form-input" placeholder="0.00" readonly>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addInvoiceItem()" class="mt-2 px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>

                <!-- Invoice Totals -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-input" placeholder="Additional notes..."></textarea>
                    </div>

                    <div class="bg-gray-50 p-4 rounded">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span id="subtotal">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tax:</span>
                                <span id="taxAmount">$0.00</span>
                            </div>
                            <div class="flex justify-between font-semibold text-lg border-t pt-2">
                                <span>Total:</span>
                                <span id="total">$0.00</span>
                            </div>
                        </div>
                        <input type="hidden" name="subtotal" id="subtotalInput">
                        <input type="hidden" name="tax_amount" id="taxAmountInput">
                        <input type="hidden" name="total_amount" id="totalInput">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addInvoiceModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Create Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let itemCount = 1;

function addInvoiceItem() {
    const itemsContainer = document.getElementById('invoiceItems');
    const itemHtml = `
        <div class="invoice-item grid grid-cols-12 gap-2 mb-2 p-3 border border-gray-200 rounded">
            <div class="col-span-4">
                <input type="text" name="items[${itemCount}][description]" class="form-input" placeholder="Item description" required>
            </div>
            <div class="col-span-2">
                <input type="number" step="0.01" name="items[${itemCount}][quantity]" class="form-input" placeholder="1" required>
            </div>
            <div class="col-span-2">
                <input type="number" step="0.01" name="items[${itemCount}][unit_price]" class="form-input" placeholder="0.00" required>
            </div>
            <div class="col-span-2">
                <input type="number" step="0.01" name="items[${itemCount}][tax_rate]" class="form-input" placeholder="0" value="0">
            </div>
            <div class="col-span-2">
                <input type="number" step="0.01" name="items[${itemCount}][total_amount]" class="form-input" placeholder="0.00" readonly>
            </div>
        </div>
    `;
    itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
    itemCount++;
}

function updateInvoiceStatus(invoiceId, status) {
    if (status) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="${invoiceId}">
            <input type="hidden" name="status" value="${status}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewInvoice(id) {
    // In a real application, you would redirect to a detailed invoice view
    alert('Invoice view functionality would be implemented here');
}

function editInvoice(id) {
    // In a real application, you would open an edit modal or redirect to edit page
    alert('Invoice edit functionality would be implemented here');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>