<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Documents';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'upload':
                    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERROR_OK) {
                        $result = uploadFile($_FILES['document']);

                        if ($result['success']) {
                            $description = sanitizeInput($_POST['description']);
                            $relatedTable = sanitizeInput($_POST['related_table']);
                            $relatedId = intval($_POST['related_id']);

                            $stmt = $pdo->prepare("
                                INSERT INTO documents (file_name, original_name, file_path, file_type, file_size, uploaded_by, description, related_table, related_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $result['filename'],
                                $_FILES['document']['name'],
                                'uploads/' . $result['filename'],
                                pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION),
                                $_FILES['document']['size'],
                                1,
                                $description,
                                $relatedTable ?: null,
                                $relatedId ?: null
                            ]);
                            $success = 'Document uploaded successfully!';
                        } else {
                            $error = $result['error'];
                        }
                    } else {
                        $error = 'Error uploading file. Please try again.';
                    }
                    break;

                case 'delete':
                    $id = intval($_POST['id']);

                    // Get file path before deleting record
                    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
                    $stmt->execute([$id]);
                    $document = $stmt->fetch();

                    if ($document) {
                        // Delete physical file
                        if (file_exists($document['file_path'])) {
                            unlink($document['file_path']);
                        }

                        // Delete database record
                        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = 'Document deleted successfully!';
                    }
                    break;
            }
        }
    }

    // Get all documents
    $stmt = $pdo->query("
        SELECT d.*, u.username as uploaded_by_name
        FROM documents d
        LEFT JOIN users u ON d.uploaded_by = u.id
        ORDER BY d.created_at DESC
    ");
    $documents = $stmt->fetchAll();

    // Get document statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM documents");
    $totalDocuments = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(file_size) as total FROM documents");
    $totalSize = $stmt->fetch()['total'] ?? 0;

    // Get document types for filter
    $stmt = $pdo->query("SELECT file_type, COUNT(*) as count FROM documents GROUP BY file_type ORDER BY count DESC");
    $documentTypes = $stmt->fetchAll();

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
            <h1 class="text-xl font-semibold text-gray-800">Documents</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('uploadDocumentModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-upload"></i>
                <span>Upload Document</span>
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

            <!-- Document Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Documents</h3>
                            <div class="amount"><?php echo $totalDocuments; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-file-alt text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Size</h3>
                            <div class="amount">
                                <?php
                                $sizeInMB = $totalSize / (1024 * 1024);
                                echo number_format($sizeInMB, 2) . ' MB';
                                ?>
                            </div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-database text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">File Types</h3>
                            <div class="amount"><?php echo count($documentTypes); ?></div>
                        </div>
                        <div class="stats-icon bg-purple-100">
                            <i class="fas fa-file text-purple-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Documents</h2>
                        <div class="flex items-center space-x-4">
                            <select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option>All Types</option>
                                <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?php echo $type['file_type']; ?>">
                                        <?php echo strtoupper($type['file_type']); ?> (<?php echo $type['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <i class="fas fa-file text-gray-400 text-lg"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo $document['original_name']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo $document['file_name']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">
                                            <?php echo strtoupper($document['file_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php
                                        $sizeInKB = $document['file_size'] / 1024;
                                        echo number_format($sizeInKB, 1) . ' KB';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $document['uploaded_by_name'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($document['created_at']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="<?php echo $document['file_path']; ?>" target="_blank" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <button onclick="deleteDocument(<?php echo $document['id']; ?>)" class="text-red-600 hover:text-red-900">
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

<!-- Upload Document Modal -->
<div id="uploadDocumentModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Upload Document</h3>
                <button onclick="closeModal('uploadDocumentModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">

                <div class="space-y-4">
                    <div class="form-group">
                        <label class="form-label">File *</label>
                        <input type="file" name="document" class="form-input" required
                               accept="image/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx">
                        <p class="text-xs text-gray-500 mt-1">
                            Maximum file size: <?php echo MAX_FILE_SIZE / (1024 * 1024); ?>MB
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-input" placeholder="Optional description of the document"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">Related To</label>
                            <select name="related_table" class="form-select">
                                <option value="">None</option>
                                <option value="customers">Customer</option>
                                <option value="vendors">Vendor</option>
                                <option value="invoices">Invoice</option>
                                <option value="expenses">Expense</option>
                                <option value="employees">Employee</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Related ID</label>
                            <input type="number" name="related_id" class="form-input" placeholder="ID number" min="1">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('uploadDocumentModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteDocument(id) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
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