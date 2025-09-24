<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$name = $email = $phone = $address = '';
$edit_id = null;

// Handle POST request (Add or Edit a Vendor)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($name)) {
        $errors[] = "Vendor name is required.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        if ($edit_id) {
            $sql = "UPDATE vendors SET name = :name, email = :email, phone = :phone, address = :address WHERE id = :id AND user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        } else {
            $sql = "INSERT INTO vendors (user_id, name, email, phone, address) VALUES (:user_id, :name, :email, :phone, :address)";
            $stmt = $pdo->prepare($sql);
        }
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
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

// Handle GET request (Delete a vendor)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $sql = "DELETE FROM vendors WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    if ($stmt->execute()) {
        header("location: index.php");
        exit;
    }
}

// Fetch all vendors for the current user
$sql = "SELECT * FROM vendors WHERE user_id = :user_id ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manage Vendors';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Vendors</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 transition-colors flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>New Vendor</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($vendors)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-macgray-500">No vendors found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vendors as $vendor): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($vendor['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($vendor['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($vendor['phone']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button class="editBtn text-macblue-600 hover:text-macblue-900" 
                                                    data-id="<?php echo $vendor['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($vendor['name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($vendor['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($vendor['phone']); ?>"
                                                    data-address="<?php echo htmlspecialchars($vendor['address']); ?>">Edit</button>
                                            <a href="index.php?action=delete&id=<?php echo $vendor['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?');">Delete</a>
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
                <input type="hidden" name="csrf_token" id="csrf_token_modal">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New Vendor</h3>
                    <div class="mt-4 space-y-4">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name*</label>
                            <input type="text" name="name" id="name" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
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
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";


    const modalTitle = document.getElementById('modalTitle');
    const editIdField = document.getElementById('edit_id');

    function openModal() { 
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden'); 
    }
    function closeModal() { modal.classList.add('hidden'); form.reset(); }
    
    addBtn.addEventListener('click', () => {
        modalTitle.innerText = 'Add New Vendor';
        editIdField.value = '';
        openModal();
    });

    closeModalBtn.addEventListener('click', closeModal);

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.innerText = 'Edit Vendor';
            editIdField.value = btn.dataset.id;
            document.getElementById('name').value = btn.dataset.name;
            document.getElementById('email').value = btn.dataset.email;
            document.getElementById('phone').value = btn.dataset.phone;
            document.getElementById('address').value = btn.dataset.address;
            openModal();
        });
    });
});
</script>

<?php
require_once '../../partials/footer.php';
?>