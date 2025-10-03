<?php
require_once '../config.php';

// Admin-only access check
if (!hasPermission('admin')) {
    // Redirect non-admins to the dashboard or show an error
    header("location: " . BASE_PATH . "dashboard.php");
    exit;
}

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$message = '';

// --- Handle POST request (Add or Edit User) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'staff';
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if (empty($name) || empty($email)) { $errors[] = "Name and email are required."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }
    if (!in_array($role, ['admin, staff', 'accountant', 'auditor'])) { $errors[] = "Invalid role selected."; }


    // Check if email is already taken by another user
    if (empty($errors)) {
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $edit_id ?: 0]);
        if ($stmt->fetch()) {
            $errors[] = "This email address is already in use.";
        }
    }

    if (empty($errors)) {
        $sql_query = '';
        $params = [];

        if ($edit_id) { // This is an UPDATE
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_query = "UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ? AND parent_user_id = ?";
                $params = [$name, $email, $role, $hashed_password, $edit_id, $userId];
            } else {
                $sql_query = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ? AND parent_user_id = ?";
                $params = [$name, $email, $role, $edit_id, $userId];
            }
        } else { // This is an INSERT
            if (empty($password)) {
                $errors[] = "Password is required for new users.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_query = "INSERT INTO users (parent_user_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)";
                $params = [$userId, $name, $email, $hashed_password, $role];
            }
        }
        
        if (empty($errors) && !empty($sql_query)) {
            $stmt = $pdo->prepare($sql_query);
            if ($stmt->execute($params)) {
                header("location: users.php?message=success");
                exit;
            } else {
                $errors[] = "Database operation failed. Please try again.";
            }
        }
    }
}

// --- Handle GET request (Delete User) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    if ($delete_id != $userId) {
        $sql = "DELETE FROM users WHERE id = ? AND parent_user_id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$delete_id, $userId])) {
            header("location: users.php?message=deleted");
            exit;
        }
    }
}

if(isset($_GET['message'])) {
    if($_GET['message'] == 'success') $message = "User saved successfully!";
    if($_GET['message'] == 'deleted') $message = "User deleted successfully!";
}


// Fetch all users associated with the logged-in admin's account
$sql = "SELECT id, name, email, role, created_at FROM users WHERE (id = :user_id AND parent_user_id IS NULL) OR parent_user_id = :user_id ORDER BY created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $userId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'User Management';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">User & Role Management</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="plus" class="w-4 h-4"></i>
            <span>Add User</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
            <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php foreach($errors as $error) echo htmlspecialchars($error).'<br>'; ?></div><?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Date Added</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><span class="px-2 py-1 text-xs font-medium rounded-full <?php echo ($user['role'] == 'admin') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($user['created_at']))); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($user['id'] != $userId): ?>
                                        <button class="editBtn text-macblue-600 hover:text-macblue-900" 
                                            data-id="<?php echo $user['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-role="<?php echo htmlspecialchars($user['role']); ?>">Edit</button>
                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                        <?php endif; ?>
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

<div id="formModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="users.php" method="POST">
                <input type="hidden" name="csrf_token" id="csrf_token_modal">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Add New User</h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name*</label>
                            <input type="text" name="name" id="name" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email*</label>
                            <input type="email" name="email" id="email" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role*</label>
                            <select name="role" id="role" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <option value="admin">Admin</option>    
                                <option value="staff">Staff</option>
                                <option value="accountant">Accountant</option>
                                <option value="auditor">Auditor</option>
                            </select>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" name="password" id="password" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <p class="text-xs text-gray-500 mt-1" id="password-help-text">Leave blank to keep the current password.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Save User</button>
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
    const passwordInput = document.getElementById('password');
    const passwordHelpText = document.getElementById('password-help-text');
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";

    function openModal() { 
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden'); 
    }
    function closeModal() { modal.classList.add('hidden'); form.reset(); }
    
    addBtn.addEventListener('click', () => {
        form.reset();
        modalTitle.innerText = 'Add New User';
        editIdField.value = '';
        passwordInput.required = true;
        passwordHelpText.style.display = 'none';
        openModal();
    });
    
    closeModalBtn.addEventListener('click', closeModal);

    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            form.reset();
            modalTitle.innerText = 'Edit User';
            editIdField.value = btn.dataset.id;
            document.getElementById('name').value = btn.dataset.name;
            document.getElementById('email').value = btn.dataset.email;
            document.getElementById('role').value = btn.dataset.role;
            passwordInput.required = false;
            passwordHelpText.style.display = 'block';
            openModal();
        });
    });
});
</script>

<?php
require_once '../partials/footer.php';
?>