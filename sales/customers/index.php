<?php
// We are two levels deep, so we need to go up two directories
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$customer_type = 'individual';
$name = $contact_person = $email = $phone = $address = '';
$edit_id = null;

// Handle POST request (Add or Edit a Customer)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_type = $_POST['customer_type'];
    $name = trim($_POST['name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($name)) {
        $errors[] = "Customer/Company name is required.";
    }
    if (empty($customer_type) || !in_array($customer_type, ['individual', 'company'])) {
        $errors[] = "Invalid customer type.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // If customer is an individual, clear the contact person field
    if ($customer_type == 'individual') {
        $contact_person = null;
    }

    if (empty($errors)) {
        if ($edit_id) {
            $sql = "UPDATE customers SET customer_type = :customer_type, name = :name, contact_person = :contact_person, email = :email, phone = :phone, address = :address WHERE id = :id AND user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        } else {
            $sql = "INSERT INTO customers (user_id, customer_type, name, contact_person, email, phone, address) VALUES (:user_id, :customer_type, :name, :contact_person, :email, :phone, :address)";
            $stmt = $pdo->prepare($sql);
        }
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':customer_type', $customer_type, PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':contact_person', $contact_person, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
        $stmt->bindParam(':address', $address, PDO::PARAM_STR);

        if ($stmt->execute()) {
            header("location: index.php");
            exit;
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}

// Handle GET request (Delete a customer)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $sql = "DELETE FROM customers WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    if ($stmt->execute()) {
        header("location: index.php");
        exit;
    }
}

// Fetch all customers for the current user
$sql = "SELECT * FROM customers WHERE user_id = :user_id ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manage Customers';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Customers</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Customer</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Name / Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-macgray-500">No customers found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="font-medium text-macgray-900"><?php echo htmlspecialchars($customer['name']); ?></div>
                                            <?php if ($customer['customer_type'] == 'company' && !empty($customer['contact_person'])): ?>
                                                <div class="text-macgray-500"><?php echo htmlspecialchars($customer['contact_person']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(ucfirst($customer['customer_type'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($customer['phone']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button class="editBtn text-macblue-600 hover:text-macblue-900" 
                                                    data-id="<?php echo $customer['id']; ?>"
                                                    data-customer-type="<?php echo htmlspecialchars($customer['customer_type']); ?>"
                                                    data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                                    data-contact-person="<?php echo htmlspecialchars($customer['contact_person']); ?>"
                                                    data-email="<?php echo htmlspecialchars($customer['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                    data-address="<?php echo htmlspecialchars($customer['address']); ?>">Edit</button>
                                            <a href="index.php?action=delete&id=<?php echo $customer['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?');">Delete</a>
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
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New Customer</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer Type*</label>
                            <div class="mt-2 flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" class="form-radio" name="customer_type" value="individual" checked>
                                    <span class="ml-2">Individual</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" class="form-radio" name="customer_type" value="company">
                                    <span class="ml-2">Company</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label for="name" id="nameLabel" class="block text-sm font-medium text-gray-700">Full Name*</label>
                            <input type="text" name="name" id="name" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div id="contactPersonWrapper" class="hidden">
                            <label for="contact_person" class="block text-sm font-medium text-gray-700">Contact Person</label>
                            <input type="text" name="contact_person" id="contact_person" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="tel" name="phone" id="phone" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" id="address" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
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
    const nameLabel = document.getElementById('nameLabel');
    const contactPersonWrapper = document.getElementById('contactPersonWrapper');
    const customerTypeRadios = document.querySelectorAll('input[name="customer_type"]');

    function toggleContactPersonField() {
        const selectedType = document.querySelector('input[name="customer_type"]:checked').value;
        if (selectedType === 'company') {
            contactPersonWrapper.classList.remove('hidden');
            nameLabel.innerText = 'Company Name*';
        } else {
            contactPersonWrapper.classList.add('hidden');
            nameLabel.innerText = 'Full Name*';
        }
    }

    customerTypeRadios.forEach(radio => radio.addEventListener('change', toggleContactPersonField));

    function openModal() { modal.classList.remove('hidden'); }
    function closeModal() { modal.classList.add('hidden'); form.reset(); }
    
    addBtn.addEventListener('click', () => {
        modalTitle.innerText = 'Add New Customer';
        editIdField.value = '';
        document.querySelector('input[name="customer_type"][value="individual"]').checked = true;
        toggleContactPersonField();
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.innerText = 'Edit Customer';
            editIdField.value = btn.dataset.id;
            
            document.querySelector(`input[name="customer_type"][value="${btn.dataset.customerType}"]`).checked = true;
            
            document.getElementById('name').value = btn.dataset.name;
            document.getElementById('contact_person').value = btn.dataset.contactPerson;
            document.getElementById('email').value = btn.dataset.email;
            document.getElementById('phone').value = btn.dataset.phone;
            document.getElementById('address').value = btn.dataset.address;

            toggleContactPersonField();
            openModal();
        });
    });
});
</script>

<?php
require_once '../../partials/footer.php';
?>