<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Customers';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $customerCode = sanitizeInput($_POST['customer_code']);
                    $name = sanitizeInput($_POST['name']);
                    $email = sanitizeInput($_POST['email']);
                    $phone = sanitizeInput($_POST['phone']);
                    $address = sanitizeInput($_POST['address']);
                    $city = sanitizeInput($_POST['city']);
                    $state = sanitizeInput($_POST['state']);
                    $zipCode = sanitizeInput($_POST['zip_code']);
                    $country = sanitizeInput($_POST['country']);
                    $paymentTerms = intval($_POST['payment_terms']);
                    $creditLimit = floatval($_POST['credit_limit']);

                    $stmt = $pdo->prepare("
                        INSERT INTO customers (customer_code, name, email, phone, address, city, state, zip_code, country, payment_terms, credit_limit)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$customerCode, $name, $email, $phone, $address, $city, $state, $zipCode, $country, $paymentTerms, $creditLimit]);
                    $success = 'Customer added successfully!';
                    break;

                case 'update':
                    $id = intval($_POST['id']);
                    $customerCode = sanitizeInput($_POST['customer_code']);
                    $name = sanitizeInput($_POST['name']);
                    $email = sanitizeInput($_POST['email']);
                    $phone = sanitizeInput($_POST['phone']);
                    $address = sanitizeInput($_POST['address']);
                    $city = sanitizeInput($_POST['city']);
                    $state = sanitizeInput($_POST['state']);
                    $zipCode = sanitizeInput($_POST['zip_code']);
                    $country = sanitizeInput($_POST['country']);
                    $paymentTerms = intval($_POST['payment_terms']);
                    $creditLimit = floatval($_POST['credit_limit']);

                    $stmt = $pdo->prepare("
                        UPDATE customers SET customer_code = ?, name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, country = ?, payment_terms = ?, credit_limit = ? WHERE id = ?
                    ");
                    $stmt->execute([$customerCode, $name, $email, $phone, $address, $city, $state, $zipCode, $country, $paymentTerms, $creditLimit, $id]);
                    $success = 'Customer updated successfully!';
                    break;

                case 'delete':
                    $id = intval($_POST['id']);
                    $stmt = $pdo->prepare("UPDATE customers SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Customer deleted successfully!';
                    break;
            }
        }
    }

    // Get all customers
    $stmt = $pdo->query("SELECT * FROM customers WHERE is_active = 1 ORDER BY name");
    $customers = $stmt->fetchAll();

    // Get customer statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers WHERE is_active = 1");
    $totalCustomers = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(total_amount - paid_amount) as outstanding FROM invoices WHERE customer_id IN (SELECT id FROM customers WHERE is_active = 1)");
    $totalOutstanding = $stmt->fetch()['outstanding'] ?? 0;

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
            <h1 class="text-xl font-semibold text-gray-800">Customers</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addCustomerModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Customer</span>
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

            <!-- Customer Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Customers</h3>
                            <div class="amount"><?php echo $totalCustomers; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-users text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Outstanding Amount</h3>
                            <div class="amount"><?php echo formatCurrency($totalOutstanding); ?></div>
                        </div>
                        <div class="stats-icon bg-yellow-100">
                            <i class="fas fa-exclamation-circle text-yellow-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Active Customers</h3>
                            <div class="amount"><?php echo $totalCustomers; ?></div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Customers</h2>
                        <div class="flex items-center space-x-4">
                            <input type="text" placeholder="Search customers..." class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $customer['customer_code']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $customer['name']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $customer['email'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $customer['phone'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php
                                        $stmt = $pdo->prepare("SELECT SUM(total_amount - paid_amount) as outstanding FROM invoices WHERE customer_id = ?");
                                        $stmt->execute([$customer['id']]);
                                        $outstanding = $stmt->fetch()['outstanding'] ?? 0;
                                        echo formatCurrency($outstanding);
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editCustomer(<?php echo $customer['id']; ?>)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
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

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add New Customer</h3>
                <button onclick="closeModal('addCustomerModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Customer Code *</label>
                        <input type="text" name="customer_code" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Address</label>
                        <textarea name="address" rows="2" class="form-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ZIP Code</label>
                        <input type="text" name="zip_code" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-input" value="Bangladesh">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Payment Terms (days)</label>
                        <select name="payment_terms" class="form-select">
                            <option value="30">Net 30</option>
                            <option value="15">Net 15</option>
                            <option value="7">Net 7</option>
                            <option value="0">Due on Receipt</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Credit Limit</label>
                        <input type="number" step="0.01" name="credit_limit" class="form-input" value="0">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addCustomerModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCustomer(id) {
    // In a real application, you would fetch the customer data via AJAX
    // For now, we'll just open the modal
    openModal('editCustomerModal');
}

function deleteCustomer(id) {
    if (confirm('Are you sure you want to delete this customer?')) {
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