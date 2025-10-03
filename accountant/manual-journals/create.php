<?php
require_once '../../config.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_PATH . "index.php");
    exit;
}

$errors = [];

// --- Fetch accounts for the dropdowns ---
$accounts_sql = "SELECT id, account_name, account_type FROM chart_of_accounts ORDER BY account_name ASC";
$accounts_stmt = $pdo->prepare($accounts_sql);
$accounts_stmt->execute();
$accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    $entry_date = $_POST['entry_date'];
    $description = trim($_POST['description']);
    $account_ids = $_POST['account_id'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];

    $total_debits = 0;
    $total_credits = 0;
    
    // Validate and calculate totals
    if (empty($entry_date)) {
        $errors[] = "Entry date is required.";
    }
    if (count($account_ids) < 2) {
        $errors[] = "A journal entry must have at least two lines.";
    }
    
    foreach ($debits as $debit) { $total_debits += (float)$debit; }
    foreach ($credits as $credit) { $total_credits += (float)$credit; }

    if (round($total_debits, 2) !== round($total_credits, 2)) {
        $errors[] = "Total debits must equal total credits.";
    }
    if ($total_debits == 0) {
        $errors[] = "Journal entry amounts cannot be zero.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert into the main `journal_entries` table
            $sql = "INSERT INTO journal_entries (entry_date, description) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$entry_date, $description]);
            $journal_entry_id = $pdo->lastInsertId();

            // 2. Loop through and insert into `journal_entry_items`
            foreach ($account_ids as $key => $account_id) {
                $debit_amount = (float)($debits[$key] ?? 0);
                $credit_amount = (float)($credits[$key] ?? 0);

                if ($debit_amount > 0) {
                    $type = 'debit';
                    $amount = $debit_amount;
                } elseif ($credit_amount > 0) {
                    $type = 'credit';
                    $amount = $credit_amount;
                } else {
                    continue; // Skip lines with no amount
                }

                $item_sql = "INSERT INTO journal_entry_items (journal_entry_id, account_id, type, amount) VALUES (?, ?, ?, ?)";
                $item_stmt = $pdo->prepare($item_sql);
                $item_stmt->execute([$journal_entry_id, $account_id, $type, $amount]);
            }

            $pdo->commit();
            header("location: " . BASE_PATH . "accountant/manual-journals/");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error creating journal entry: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Manual Journal';
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="bg-white border-b border-macgray-200 py-3 px-6"><h1 class="text-xl font-semibold text-macgray-800">New Manual Journal Entry</h1></header>

    <main class="content-area flex-1 overflow-y-auto p-6 bg-macgray-50">
        <div class="max-w-4xl mx-auto">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded mb-4"><ul><?php foreach ($errors as $error) echo "<li>".htmlspecialchars($error)."</li>"; ?></ul></div>
            <?php endif; ?>

            <form action="create.php" method="POST" id="journal-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="entry_date" class="block text-sm font-medium text-gray-700">Date*</label>
                            <input type="date" name="entry_date" id="entry_date" value="<?php echo date('Y-m-d'); ?>" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <div class="col-span-2">
                             <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" name="description" id="description" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>

                <div class="mt-6 bg-white p-6 rounded-xl shadow-sm border border-macgray-200">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="w-2/5 text-left text-sm font-medium text-gray-500 pb-2">Account</th>
                                <th class="w-1/4 text-right text-sm font-medium text-gray-500 pb-2">Debit</th>
                                <th class="w-1/4 text-right text-sm font-medium text-gray-500 pb-2">Credit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="line-items" class="divide-y divide-macgray-200"></tbody>
                        <tfoot class="bg-macgray-50">
                            <tr>
                                <td class="px-2 py-3 text-right font-semibold">Totals</td>
                                <td class="px-2 py-3 text-right font-bold" id="total-debit">เงณ0.00</td>
                                <td class="px-2 py-3 text-right font-bold" id="total-credit">เงณ0.00</td>
                                <td></td>
                            </tr>
                             <tr>
                                <td colspan="4" class="px-2 py-2 text-center">
                                    <span id="balance-status" class="text-sm font-semibold text-red-500">Debits and Credits must be equal.</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <button type="button" id="add-line-item" class="mt-4 px-3 py-2 text-sm font-medium text-macblue-600 hover:text-macblue-800">+ Add Line</button>
                </div>

                <div class="mt-6 flex justify-end"><button type="submit" id="save-journal" class="px-6 py-2 bg-macblue-500 text-white font-semibold rounded-md hover:bg-macblue-600">Save Entry</button></div>
            </form>
        </div>
    </main>
</div>

<template id="line-item-template">
    <tr class="line-item-row">
        <td class="pr-2 py-2"><select name="account_id[]" required class="account-select block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><option value="">Select an account...</option><?php foreach ($accounts as $acc) echo "<option value='{$acc['id']}'>".htmlspecialchars($acc['account_name'])."</option>"; ?></select></td>
        <td class="px-2 py-2"><input type="number" name="debit[]" value="" placeholder="0.00" step="0.01" min="0" class="debit-input block w-full text-right shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
        <td class="pl-2 py-2"><input type="number" name="credit[]" value="" placeholder="0.00" step="0.01" min="0" class="credit-input block w-full text-right shadow-sm sm:text-sm border-gray-300 rounded-md"></td>
        <td class="pl-2 py-2"><button type="button" class="remove-line-item text-red-500 hover:text-red-700 p-1"><i data-feather="trash-2" class="w-4 h-4"></i></button></td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lineItemsContainer = document.getElementById('line-items');
    const template = document.getElementById('line-item-template');
    const addLineBtn = document.getElementById('add-line-item');
    const totalDebitEl = document.getElementById('total-debit');
    const totalCreditEl = document.getElementById('total-credit');
    const balanceStatusEl = document.getElementById('balance-status');
    const saveBtn = document.getElementById('save-journal');
    
    function addLineItem() {
        const clone = template.content.cloneNode(true);
        lineItemsContainer.appendChild(clone);
        feather.replace();
    }
    
    function calculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        document.querySelectorAll('.line-item-row').forEach(row => {
            totalDebit += parseFloat(row.querySelector('.debit-input').value) || 0;
            totalCredit += parseFloat(row.querySelector('.credit-input').value) || 0;
        });
        
        totalDebitEl.innerText = 'เงณ' + totalDebit.toFixed(2);
        totalCreditEl.innerText = 'เงณ' + totalCredit.toFixed(2);

        if (totalDebit.toFixed(2) === totalCredit.toFixed(2) && totalDebit > 0) {
            balanceStatusEl.innerText = 'Totals are balanced.';
            balanceStatusEl.classList.remove('text-red-500');
            balanceStatusEl.classList.add('text-green-600');
            saveBtn.disabled = false;
            saveBtn.classList.remove('bg-macblue-300', 'cursor-not-allowed');
            saveBtn.classList.add('bg-macblue-500', 'hover:bg-macblue-600');
        } else {
            balanceStatusEl.innerText = 'Debits and Credits must be equal and not zero.';
            balanceStatusEl.classList.remove('text-green-600');
            balanceStatusEl.classList.add('text-red-500');
            saveBtn.disabled = true;
            saveBtn.classList.add('bg-macblue-300', 'cursor-not-allowed');
            saveBtn.classList.remove('bg-macblue-500', 'hover:bg-macblue-600');
        }
    }
    
    addLineBtn.addEventListener('click', addLineItem);
    
    lineItemsContainer.addEventListener('click', e => {
        if (e.target.closest('.remove-line-item')) {
            e.target.closest('.line-item-row').remove();
            calculateTotals();
        }
    });

    lineItemsContainer.addEventListener('input', e => {
        const row = e.target.closest('.line-item-row');
        if (!row) return;

        const debitInput = row.querySelector('.debit-input');
        const creditInput = row.querySelector('.credit-input');

        if (e.target === debitInput && e.target.value > 0) {
            creditInput.value = '';
        } else if (e.target === creditInput && e.target.value > 0) {
            debitInput.value = '';
        }
        calculateTotals();
    });

    // Add initial two lines
    addLineItem();
    addLineItem();
    calculateTotals();
});
</script>

<?php
require_once '../../partials/footer.php';
?>