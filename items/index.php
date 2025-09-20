<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Items';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $itemCode = sanitizeInput($_POST['item_code']);
                    $name = sanitizeInput($_POST['name']);
                    $description = sanitizeInput($_POST['description']);
                    $category = sanitizeInput($_POST['category']);
                    $unit = sanitizeInput($_POST['unit']);
                    $salesPrice = floatval($_POST['sales_price']);
                    $purchasePrice = floatval($_POST['purchase_price']);
                    $taxRate = floatval($_POST['tax_rate']);

                    $stmt = $pdo->prepare("
                        INSERT INTO items (item_code, name, description, category, unit, sales_price, purchase_price, tax_rate)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$itemCode, $name, $description, $category, $unit, $salesPrice, $purchasePrice, $taxRate]);
                    $success = 'Item added successfully!';
                    break;

                case 'update':
                    $id = intval($_POST['id']);
                    $itemCode = sanitizeInput($_POST['item_code']);
                    $name = sanitizeInput($_POST['name']);
                    $description = sanitizeInput($_POST['description']);
                    $category = sanitizeInput($_POST['category']);
                    $unit = sanitizeInput($_POST['unit']);
                    $salesPrice = floatval($_POST['sales_price']);
                    $purchasePrice = floatval($_POST['purchase_price']);
                    $taxRate = floatval($_POST['tax_rate']);

                    $stmt = $pdo->prepare("
                        UPDATE items SET item_code = ?, name = ?, description = ?, category = ?, unit = ?,
                        sales_price = ?, purchase_price = ?, tax_rate = ? WHERE id = ?
                    ");
                    $stmt->execute([$itemCode, $name, $description, $category, $unit, $salesPrice, $purchasePrice, $taxRate, $id]);
                    $success = 'Item updated successfully!';
                    break;

                case 'delete':
                    $id = intval($_POST['id']);
                    $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Item deleted successfully!';
                    break;
            }
        }
    }

    // Get all items
    $stmt = $pdo->query("SELECT * FROM items WHERE is_active = 1 ORDER BY name");
    $items = $stmt->fetchAll();

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
            <h1 class="text-xl font-semibold text-gray-800">Items</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addItemModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Item</span>
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

            <!-- Items Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Items</h2>
                        <div class="flex items-center space-x-4">
                            <input type="text" placeholder="Search items..." class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option>All Categories</option>
                                <option>Products</option>
                                <option>Services</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $item['item_code']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $item['name']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $item['category'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $item['unit'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($item['sales_price']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($item['purchase_price']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editItem(<?php echo $item['id']; ?>)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="deleteItem(<?php echo $item['id']; ?>)" class="text-red-600 hover:text-red-900">
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

<!-- Add Item Modal -->
<div id="addItemModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add New Item</h3>
                <button onclick="closeModal('addItemModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Item Code *</label>
                        <input type="text" name="item_code" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">Select Category</option>
                            <option value="Products">Products</option>
                            <option value="Services">Services</option>
                            <option value="Software">Software</option>
                            <option value="Hardware">Hardware</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-input" placeholder="e.g., pcs, hours, kg">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sales Price</label>
                        <input type="number" step="0.01" name="sales_price" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" class="form-input">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" name="tax_rate" class="form-input" value="0">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addItemModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal (similar structure) -->
<div id="editItemModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Edit Item</h3>
                <button onclick="closeModal('editItemModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="editItemForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editItemId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Item Code *</label>
                        <input type="text" name="item_code" id="editItemCode" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="editItemName" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="editItemCategory" class="form-select">
                            <option value="">Select Category</option>
                            <option value="Products">Products</option>
                            <option value="Services">Services</option>
                            <option value="Software">Software</option>
                            <option value="Hardware">Hardware</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" id="editItemUnit" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sales Price</label>
                        <input type="number" step="0.01" name="sales_price" id="editItemSalesPrice" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" id="editItemPurchasePrice" class="form-input">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editItemDescription" rows="3" class="form-input"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" name="tax_rate" id="editItemTaxRate" class="form-input">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editItemModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editItem(id) {
    // In a real application, you would fetch the item data via AJAX
    // For now, we'll just open the modal
    document.getElementById('editItemId').value = id;
    openModal('editItemModal');
}

function deleteItem(id) {
    if (confirm('Are you sure you want to delete this item?')) {
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