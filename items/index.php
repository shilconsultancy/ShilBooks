<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$name = $description = $sale_price = $purchase_price = '';
$item_type = 'product';
$quantity = 0;
$edit_id = null;

// Handle POST request (Add or Edit an Item)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $sale_price = filter_var($_POST['sale_price'], FILTER_VALIDATE_FLOAT);
    $item_type = $_POST['item_type'];
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT) : 0;
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($name)) { $errors[] = "Item name is required."; }
    if (empty($item_type) || !in_array($item_type, ['product', 'service'])) { $errors[] = "Invalid item type selected.";}
    if ($sale_price === false || $sale_price < 0) { $errors[] = "Invalid sale price."; }

    $purchase_price_input = trim($_POST['purchase_price']);
    if (empty($purchase_price_input)) {
        $purchase_price = 0.00;
    } else {
        $purchase_price = filter_var($purchase_price_input, FILTER_VALIDATE_FLOAT);
        if ($purchase_price === false || $purchase_price < 0) {
            $errors[] = "If provided, the purchase price must be a valid number.";
        }
    }

    if ($item_type === 'product') {
        if ($quantity === false || $quantity < 0) {
            $errors[] = "Invalid quantity for a product.";
        }
    } else {
        $quantity = 0;
    }

    if (empty($errors)) {
        if ($edit_id) {
            $sql = "UPDATE items SET name = :name, description = :description, item_type = :item_type, sale_price = :sale_price, purchase_price = :purchase_price, quantity = :quantity WHERE id = :id AND user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        } else {
            $sql = "INSERT INTO items (user_id, name, description, item_type, sale_price, purchase_price, quantity) VALUES (:user_id, :name, :description, :item_type, :sale_price, :purchase_price, :quantity)";
            $stmt = $pdo->prepare($sql);
        }
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':item_type', $item_type, PDO::PARAM_STR);
        $stmt->bindParam(':sale_price', $sale_price);
        $stmt->bindParam(':purchase_price', $purchase_price);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);

        if ($stmt->execute()) {
            header("location: index.php");
            exit;
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}


// Handle GET request (Delete an item)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $sql = "DELETE FROM items WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    if ($stmt->execute()) {
        header("location: index.php");
        exit;
    }
}


// Fetch all items for the current user
$sql = "SELECT * FROM items WHERE user_id = :user_id ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manage Items';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Items and Services</h1>
        <button id="addItemBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Item</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Sale Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Qty on Hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-macgray-500">No items found. Add one to get started!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><span class="px-2 py-1 text-xs font-medium rounded-full <?php echo ($item['item_type'] == 'product') ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>"><?php echo htmlspecialchars(ucfirst($item['item_type'])); ?></span></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(substr($item['description'], 0, 40)) . (strlen($item['description']) > 40 ? '...' : ''); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo CURRENCY_SYMBOL; ?><?php echo htmlspecialchars(number_format($item['sale_price'], 2)); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo ($item['quantity'] <= 0 && $item['item_type'] == 'product') ? 'text-red-500' : 'text-macgray-800'; ?>"><?php echo ($item['item_type'] == 'product') ? htmlspecialchars($item['quantity']) : 'N/A'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button class="editItemBtn text-macblue-600 hover:text-macblue-900" 
                                                    data-id="<?php echo $item['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                    data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                    data-item-type="<?php echo htmlspecialchars($item['item_type']); ?>"
                                                    data-sale-price="<?php echo htmlspecialchars($item['sale_price']); ?>"
                                                    data-purchase-price="<?php echo htmlspecialchars($item['purchase_price']); ?>"
                                                    data-quantity="<?php echo htmlspecialchars($item['quantity']); ?>">Edit</button>
                                            <a href="index.php?action=delete&id=<?php echo $item['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to delete this item?');">Delete</a>
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

<div id="itemModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="index.php" method="POST">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New Item</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Item Type*</label>
                            <div class="mt-2 flex space-x-4" id="itemTypeSelector">
                                <label class="inline-flex items-center"><input type="radio" class="form-radio" name="item_type" value="product" checked><span class="ml-2">Product</span></label>
                                <label class="inline-flex items-center"><input type="radio" class="form-radio" name="item_type" value="service"><span class="ml-2">Service</span></label>
                            </div>
                        </div>
                        <div><label for="name" class="block text-sm font-medium text-gray-700">Name*</label><input type="text" name="name" id="name" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        <div><label for="description" class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" id="description" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div><label for="sale_price" class="block text-sm font-medium text-gray-700">Sale Price*</label><input type="number" name="sale_price" id="sale_price" step="0.01" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                            <div><label for="purchase_price" class="block text-sm font-medium text-gray-700">Purchase Price</label><input type="number" name="purchase_price" id="purchase_price" step="0.01" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                        </div>
                        <div id="quantityWrapper"><label for="quantity" class="block text-sm font-medium text-gray-700">Quantity on Hand*</label><input type="number" name="quantity" id="quantity" step="1" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 text-base font-medium text-white hover:bg-macblue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                    <button type="button" id="closeModalBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('itemModal');
    const addItemBtn = document.getElementById('addItemBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const editItemBtns = document.querySelectorAll('.editItemBtn');
    const modalTitle = document.getElementById('modalTitle');
    const editIdField = document.getElementById('edit_id');
    const nameField = document.getElementById('name');
    const descriptionField = document.getElementById('description');
    const salePriceField = document.getElementById('sale_price');
    const purchasePriceField = document.getElementById('purchase_price');
    const quantityField = document.getElementById('quantity');
    const quantityWrapper = document.getElementById('quantityWrapper');
    const itemTypeRadios = document.querySelectorAll('input[name="item_type"]');

    function toggleQuantityField() {
        const selectedType = document.querySelector('input[name="item_type"]:checked').value;
        if (selectedType === 'product') {
            quantityWrapper.style.display = 'block';
            quantityField.required = true;
        } else {
            quantityWrapper.style.display = 'none';
            quantityField.required = false;
        }
    }
    itemTypeRadios.forEach(radio => radio.addEventListener('change', toggleQuantityField));

    function openModal() { modal.classList.remove('hidden'); }
    function closeModal() { modal.classList.add('hidden'); document.querySelector('#itemModal form').reset(); }
    
    function resetForm() {
        document.querySelector('#itemModal form').reset();
        modalTitle.innerText = 'Add New Item';
        editIdField.value = '';
        document.querySelector('input[name="item_type"][value="product"]').checked = true;
        toggleQuantityField();
    }
    addItemBtn.addEventListener('click', () => { resetForm(); openModal(); });
    closeModalBtn.addEventListener('click', closeModal);
    editItemBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            resetForm();
            modalTitle.innerText = 'Edit Item';
            editIdField.value = btn.dataset.id;
            nameField.value = btn.dataset.name;
            descriptionField.value = btn.dataset.description;
            salePriceField.value = btn.dataset.salePrice;
            purchasePriceField.value = btn.dataset.purchasePrice;
            quantityField.value = btn.dataset.quantity;
            document.querySelector(`input[name="item_type"][value="${btn.dataset.itemType}"]`).checked = true;
            toggleQuantityField();
            openModal();
        });
    });
});
</script>

<?php
require_once '../partials/footer.php';
?>