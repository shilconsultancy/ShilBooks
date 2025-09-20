<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Journal Entries';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $entryDate = $_POST['entry_date'];
                    $reference = sanitizeInput($_POST['reference']);
                    $description = sanitizeInput($_POST['description']);

                    // Start transaction
                    $pdo->beginTransaction();

                    // Insert journal entry
                    $stmt = $pdo->prepare("
                        INSERT INTO journal_entries (entry_date, reference, description, created_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$entryDate, $reference, $description, 1]);
                    $journalEntryId = $pdo->lastInsertId();

                    $totalDebit = 0;
                    $totalCredit = 0;

                    // Insert journal entry lines
                    if (isset($_POST['lines']) && is_array($_POST['lines'])) {
                        foreach ($_POST['lines'] as $line) {
                            if (!empty($line['account_id']) && ($line['debit'] > 0 || $line['credit'] > 0)) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit_amount, credit_amount)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $journalEntryId,
                                    $line['account_id'],
                                    $line['description'],
                                    floatval($line['debit']),
                                    floatval($line['credit'])
                                ]);

                                $totalDebit += floatval($line['debit']);
                                $totalCredit += floatval($line['credit']);
                            }
                        }
                    }

                    // Update journal entry totals
                    $stmt = $pdo->prepare("
                        UPDATE journal_entries SET total_debit = ?, total_credit = ? WHERE id = ?
                    ");
                    $stmt->execute([$totalDebit, $totalCredit, $journalEntryId]);

                    $pdo->commit();
                    $success = 'Journal entry added successfully!';
                    break;

                case 'delete':
                    $id = intval($_POST['id']);
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
                    $stmt->execute([$id]);
                    $stmt = $pdo->prepare("DELETE FROM journal_entries WHERE id = ?");
                    $stmt->execute([$id]);
                    $pdo->commit();
                    $success = 'Journal entry deleted successfully!';
                    break;
            }
        }
    }

    // Get all journal entries
    $stmt = $pdo->query("
        SELECT je.*, u.username as created_by_name
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        ORDER BY je.entry_date DESC, je.created_at DESC
    ");
    $journalEntries = $stmt->fetchAll();

    // Get all accounts for dropdown
    $stmt = $pdo->query("SELECT id, account_code, account_name, account_type FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_type, account_code");
    $accounts = $stmt->fetchAll();

    // Get journal statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM journal_entries");
    $totalEntries = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(total_debit) as total FROM journal_entries");
    $totalDebits = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT SUM(total_credit) as total FROM journal_entries");
    $totalCredits = $stmt->fetch()['total'] ?? 0;

} catch(PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
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
            <h1 class="text-xl font-semibold text-gray-800">Journal Entries</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addJournalModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Entry</span>
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

            <!-- Journal Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Entries</h3>
                            <div class="amount"><?php echo $totalEntries; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-book text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Debits</h3>
                            <div class="amount"><?php echo formatCurrency($totalDebits); ?></div>
                        </div>
                        <div class="stats-icon bg-red-100">
                            <i class="fas fa-arrow-down text-red-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Credits</h3>
                            <div class="amount"><?php echo formatCurrency($totalCredits); ?></div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-arrow-up text-green-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Journal Entries Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Journal Entries</h2>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($journalEntries as $entry): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatDate($entry['entry_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $entry['reference'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo substr($entry['description'], 0, 50) . (strlen($entry['description']) > 50 ? '...' : ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                        <?php echo formatCurrency($entry['total_debit']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                        <?php echo formatCurrency($entry['total_credit']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $entry['created_by_name'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewJournalEntry(<?php echo $entry['id']; ?>)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button onclick="deleteJournalEntry(<?php echo $entry['id']; ?>)" class="text-red-600 hover:text-red-900">
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

<!-- Add Journal Entry Modal -->
<div id="addJournalModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add Journal Entry</h3>
                <button onclick="closeModal('addJournalModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="journalForm">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="form-group">
                        <label class="form-label">Entry Date *</label>
                        <input type="date" name="entry_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-input" placeholder="e.g., INV-001, ADJ-001">
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Description *</label>
                        <textarea name="description" rows="2" class="form-input" placeholder="Journal entry description..." required></textarea>
                    </div>
                </div>

                <!-- Journal Entry Lines -->
                <div class="mb-6">
                    <h4 class="text-md font-semibold text-gray-800 mb-3">Journal Entry Lines</h4>
                    <div id="journalLines">
                        <div class="grid grid-cols-12 gap-2 mb-2 p-3 bg-gray-50 rounded">
                            <div class="col-span-3">
                                <label class="form-label">Account</label>
                            </div>
                            <div class="col-span-3">
                                <label class="form-label">Description</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Debit</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Credit</label>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Actions</label>
                            </div>
                        </div>

                        <div class="journal-line grid grid-cols-12 gap-2 mb-2 p-3 border border-gray-200 rounded">
                            <div class="col-span-3">
                                <select name="lines[0][account_id]" class="form-select" required>
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>">
                                            <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-3">
                                <input type="text" name="lines[0][description]" class="form-input" placeholder="Line description">
                            </div>
                            <div class="col-span-2">
                                <input type="number" step="0.01" name="lines[0][debit]" class="form-input" placeholder="0.00">
                            </div>
                            <div class="col-span-2">
                                <input type="number" step="0.01" name="lines[0][credit]" class="form-input" placeholder="0.00">
                            </div>
                            <div class="col-span-2">
                                <button type="button" onclick="removeJournalLine(this)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" onclick="addJournalLine()" class="mt-2 px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        <i class="fas fa-plus"></i> Add Line
                    </button>
                </div>

                <!-- Journal Totals -->
                <div class="bg-gray-50 p-4 rounded mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span>Total Debit:</span>
                                <span id="totalDebit" class="font-semibold">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Credit:</span>
                                <span id="totalCredit" class="font-semibold">$0.00</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span>Difference:</span>
                                <span id="difference" class="font-semibold text-green-600">$0.00</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-info-circle"></i> Debit and Credit totals should match for balanced entry
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addJournalModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitJournal">
                        Add Journal Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let lineCount = 1;

function addJournalLine() {
    const linesContainer = document.getElementById('journalLines');
    const lineHtml = `
        <div class="journal-line grid grid-cols-12 gap-2 mb-2 p-3 border border-gray-200 rounded">
            <div class="col-span-3">
                <select name="lines[${lineCount}][account_id]" class="form-select" required>
                    <option value="">Select Account</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>">
                            <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-span-3">
                <input type="text" name="lines[${lineCount}][description]" class="form-input" placeholder="Line description">
            </div>
            <div class="col-span-2">
                <input type="number" step="0.01" name="lines[${lineCount}][debit]" class="form-input journal-debit" placeholder="0.00">
            </div>
            <div class="col-span-2">
                <input type="number" step="0.01" name="lines[${lineCount}][credit]" class="form-input journal-credit" placeholder="0.00">
            </div>
            <div class="col-span-2">
                <button type="button" onclick="removeJournalLine(this)" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    linesContainer.insertAdjacentHTML('beforeend', lineHtml);
    lineCount++;
    updateJournalTotals();
}

function removeJournalLine(button) {
    button.closest('.journal-line').remove();
    updateJournalTotals();
}

function updateJournalTotals() {
    let totalDebit = 0;
    let totalCredit = 0;

    document.querySelectorAll('.journal-debit').forEach(input => {
        totalDebit += parseFloat(input.value) || 0;
    });

    document.querySelectorAll('.journal-credit').forEach(input => {
        totalCredit += parseFloat(input.value) || 0;
    });

    const difference = Math.abs(totalDebit - totalCredit);

    document.getElementById('totalDebit').textContent = formatCurrency(totalDebit);
    document.getElementById('totalCredit').textContent = formatCurrency(totalCredit);
    document.getElementById('difference').textContent = formatCurrency(difference);

    const differenceElement = document.getElementById('difference');
    const submitButton = document.getElementById('submitJournal');

    if (difference === 0 && totalDebit > 0 && totalCredit > 0) {
        differenceElement.className = 'font-semibold text-green-600';
        submitButton.disabled = false;
    } else {
        differenceElement.className = 'font-semibold text-red-600';
        submitButton.disabled = true;
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function viewJournalEntry(id) {
    // In a real application, you would redirect to a detailed view
    alert('Journal entry view functionality would be implemented here');
}

function deleteJournalEntry(id) {
    if (confirm('Are you sure you want to delete this journal entry? This action cannot be undone.')) {
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

// Add event listeners for journal calculations
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('journal-debit') || e.target.classList.contains('journal-credit')) {
            updateJournalTotals();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>