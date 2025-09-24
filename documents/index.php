<?php
require_once '../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$message = '';

// --- Handle File Upload ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["document"])) {
    validate_csrf_token();
    
    $description = trim($_POST['description']);
    
    if (isset($_FILES["document"]) && $_FILES["document"]["error"] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'text/csv'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'csv'];
        $max_size = 5 * 1024 * 1024; // 5 MB
        
        $file_extension = strtolower(pathinfo(basename($_FILES["document"]["name"]), PATHINFO_EXTENSION));

        if (!in_array($_FILES["document"]["type"], $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Invalid file type. Only JPG, PNG, PDF, and CSV are allowed.";
        }
        if ($_FILES["document"]["size"] > $max_size) {
            $errors[] = "File size exceeds the 5MB limit.";
        }

        if (empty($errors)) {
            $upload_dir_relative = 'uploads/' . $userId . '/';
            $upload_dir_absolute = '../' . $upload_dir_relative;

            if (!is_dir($upload_dir_absolute)) {
                if (!mkdir($upload_dir_absolute, 0755, true)) {
                    $errors[] = "Error: Failed to create the upload directory. Please check server permissions for the 'uploads' folder.";
                }
            }
            
            if (!is_writable($upload_dir_absolute)) {
                 $errors[] = "Error: The upload directory is not writable. Please check server permissions.";
            }

            if(empty($errors)) {
                $original_filename = basename($_FILES["document"]["name"]);
                $stored_filename = uniqid() . '.' . $file_extension;
                
                if (move_uploaded_file($_FILES["document"]["tmp_name"], $upload_dir_absolute . $stored_filename)) {
                    $sql = "INSERT INTO documents (user_id, original_filename, stored_filename, file_path, file_type, file_size, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$userId, $original_filename, $stored_filename, $upload_dir_relative, $_FILES["document"]["type"], $_FILES["document"]["size"], $description])) {
                        $message = "File uploaded successfully.";
                    } else {
                        $errors[] = "Failed to save file information to the database.";
                    }
                } else {
                    $errors[] = "Failed to move uploaded file. This is often a permissions issue.";
                }
            }
        }
    } else {
        $errors[] = "Error uploading file code: " . $_FILES["document"]["error"];
    }
}

// --- Handle Delete Action ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT file_path, stored_filename FROM documents WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $userId]);
    $doc = $stmt->fetch();

    if ($doc) {
        $file_on_disk = '../' . $doc['file_path'] . $doc['stored_filename'];
        if (file_exists($file_on_disk)) {
            unlink($file_on_disk);
        }
        $delete_stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $delete_stmt->execute([$delete_id]);
        $message = "Document deleted successfully.";
    }
}


// Fetch all documents for the user
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$userId]);
$documents = $stmt->fetchAll();

$pageTitle = 'Documents';
require_once '../partials/header.php';
require_once '../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-macgray-800">Documents</h1>
        <button id="addBtn" class="px-4 py-2 bg-macblue-500 text-white rounded-md hover:bg-macblue-600 flex items-center space-x-2">
            <i data-feather="upload" class="w-4 h-4"></i><span>Upload Document</span>
        </button>
    </header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-7xl mx-auto">
             <?php if ($message): ?><div class="bg-green-100 border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
             <?php if (!empty($errors)): ?><div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php foreach($errors as $error) { echo htmlspecialchars($error).'<br>'; } ?></div><?php endif; ?>
            <div class="bg-white rounded-xl shadow-sm border border-macgray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-macgray-200">
                        <thead class="bg-macgray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Filename</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-macgray-500 uppercase">Date Uploaded</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-macgray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-macgray-200">
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="5" class="px-6 py-4 text-center text-macgray-500">No documents uploaded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-macgray-900"><?php echo htmlspecialchars($doc['original_filename']); ?></td>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo htmlspecialchars($doc['description']); ?></td>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo round($doc['file_size'] / 1024, 2); ?> KB</td>
                                        <td class="px-6 py-4 text-sm text-macgray-500"><?php echo htmlspecialchars(date("M d, Y", strtotime($doc['uploaded_at']))); ?></td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <a href="view.php?id=<?php echo $doc['id']; ?>" target="_blank" class="text-green-600 hover:text-green-900">View</a>
                                            <a href="download.php?id=<?php echo $doc['id']; ?>" class="text-macblue-600 hover:text-macblue-900 ml-4">Download</a>
                                            <a href="index.php?action=delete&id=<?php echo $doc['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?');">Delete</a>
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
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" id="csrf_token_modal">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Upload New Document</h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="document" class="block text-sm font-medium text-gray-700">File*</label>
                            <input type="file" name="document" id="document" required class="mt-1 block w-full text-sm text-macgray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-macblue-50 file:text-macblue-700 hover:file:bg-macblue-100">
                            <p class="text-xs text-gray-500 mt-1">Allowed types: JPG, PNG, PDF, CSV. Max size: 5MB.</p>
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-macblue-600 font-medium text-white hover:bg-macblue-700 sm:ml-3 sm:w-auto sm:text-sm">Upload</button>
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
    const csrfTokenModal = document.getElementById('csrf_token_modal');
    const mainCsrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";

    addBtn.addEventListener('click', () => {
        csrfTokenModal.value = mainCsrfToken;
        modal.classList.remove('hidden');
    });
    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
});
</script>

<?php
require_once '../partials/footer.php';
?>