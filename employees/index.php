<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . __DIR__ . '/../index.php');
    exit;
}

$pageTitle = 'Employees';
$success = '';
$error = '';

try {
    $pdo = getDBConnection();

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $employeeCode = sanitizeInput($_POST['employee_code']);
                    $firstName = sanitizeInput($_POST['first_name']);
                    $lastName = sanitizeInput($_POST['last_name']);
                    $email = sanitizeInput($_POST['email']);
                    $phone = sanitizeInput($_POST['phone']);
                    $hireDate = $_POST['hire_date'];
                    $salary = floatval($_POST['salary']);
                    $department = sanitizeInput($_POST['department']);
                    $position = sanitizeInput($_POST['position']);

                    $stmt = $pdo->prepare("
                        INSERT INTO employees (employee_code, first_name, last_name, email, phone, hire_date, salary, department, position)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$employeeCode, $firstName, $lastName, $email, $phone, $hireDate, $salary, $department, $position]);
                    $success = 'Employee added successfully!';
                    break;

                case 'update':
                    $id = intval($_POST['id']);
                    $employeeCode = sanitizeInput($_POST['employee_code']);
                    $firstName = sanitizeInput($_POST['first_name']);
                    $lastName = sanitizeInput($_POST['last_name']);
                    $email = sanitizeInput($_POST['email']);
                    $phone = sanitizeInput($_POST['phone']);
                    $hireDate = $_POST['hire_date'];
                    $salary = floatval($_POST['salary']);
                    $department = sanitizeInput($_POST['department']);
                    $position = sanitizeInput($_POST['position']);

                    $stmt = $pdo->prepare("
                        UPDATE employees SET employee_code = ?, first_name = ?, last_name = ?, email = ?, phone = ?, hire_date = ?, salary = ?, department = ?, position = ? WHERE id = ?
                    ");
                    $stmt->execute([$employeeCode, $firstName, $lastName, $email, $phone, $hireDate, $salary, $department, $position, $id]);
                    $success = 'Employee updated successfully!';
                    break;

                case 'delete':
                    $id = intval($_POST['id']);
                    $stmt = $pdo->prepare("UPDATE employees SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Employee removed successfully!';
                    break;

                case 'process_payroll':
                    $employeeId = intval($_POST['employee_id']);
                    $payPeriodStart = $_POST['pay_period_start'];
                    $payPeriodEnd = $_POST['pay_period_end'];
                    $basicSalary = floatval($_POST['basic_salary']);
                    $allowances = floatval($_POST['allowances']);
                    $deductions = floatval($_POST['deductions']);

                    $grossPay = $basicSalary + $allowances;
                    $netPay = $grossPay - $deductions;

                    $stmt = $pdo->prepare("
                        INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, basic_salary, allowances, deductions, gross_pay, net_pay)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$employeeId, $payPeriodStart, $payPeriodEnd, $basicSalary, $allowances, $deductions, $grossPay, $netPay]);
                    $success = 'Payroll processed successfully!';
                    break;
            }
        }
    }

    // Get all employees
    $stmt = $pdo->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY first_name, last_name");
    $employees = $stmt->fetchAll();

    // Get employee statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE is_active = 1");
    $totalEmployees = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(salary) as total FROM employees WHERE is_active = 1");
    $totalMonthlySalary = $stmt->fetch()['total'] ?? 0;

    // Get departments for dropdown
    $departments = [
        'Management',
        'Sales',
        'Marketing',
        'Human Resources',
        'Finance',
        'Operations',
        'IT',
        'Customer Service',
        'Research & Development',
        'Other'
    ];

    // Get recent payroll
    $stmt = $pdo->query("
        SELECT p.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM payroll p
        LEFT JOIN employees e ON p.employee_id = e.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $recentPayroll = $stmt->fetchAll();

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
            <h1 class="text-xl font-semibold text-gray-800">Employees</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 rounded-full hover:bg-gray-100 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button onclick="openModal('addEmployeeModal')" class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition-colors flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Employee</span>
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

            <!-- Employee Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Total Employees</h3>
                            <div class="amount"><?php echo $totalEmployees; ?></div>
                        </div>
                        <div class="stats-icon bg-blue-100">
                            <i class="fas fa-users text-blue-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Monthly Salary</h3>
                            <div class="amount"><?php echo formatCurrency($totalMonthlySalary); ?></div>
                        </div>
                        <div class="stats-icon bg-green-100">
                            <i class="fas fa-money-bill-wave text-green-500"></i>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-card-content">
                        <div class="stats-info">
                            <h3 class="text-sm font-medium text-gray-500">Departments</h3>
                            <div class="amount">
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(DISTINCT department) as count FROM employees WHERE is_active = 1 AND department IS NOT NULL AND department != ''");
                                echo $stmt->fetch()['count'];
                                ?>
                            </div>
                        </div>
                        <div class="stats-icon bg-purple-100">
                            <i class="fas fa-building text-purple-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employees Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">All Employees</h2>
                        <div class="flex items-center space-x-4">
                            <select class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option>All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salary</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hire Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $employee['employee_code']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $employee['department'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $employee['position'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $employee['email']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($employee['salary']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($employee['hire_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editEmployee(<?php echo $employee['id']; ?>)" class="text-primary-600 hover:text-primary-900 mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button onclick="processPayroll(<?php echo $employee['id']; ?>)" class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-calculator"></i> Payroll
                                        </button>
                                        <button onclick="deleteEmployee(<?php echo $employee['id']; ?>)" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Payroll -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Payroll</h2>
                        <a href="#" class="text-sm text-primary-500 hover:text-primary-600">View All</a>
                    </div>
                </div>

                <div class="p-6">
                    <?php if (empty($recentPayroll)): ?>
                        <p class="text-gray-500 text-center py-8">No payroll records yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Pay</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Pay</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentPayroll as $payroll): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $payroll['employee_name']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo formatDate($payroll['pay_period_start']) . ' - ' . formatDate($payroll['pay_period_end']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($payroll['gross_pay']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($payroll['net_pay']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                                    <?php
                                                    switch($payroll['status']) {
                                                        case 'paid':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'processed':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        default:
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($payroll['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Employee Modal -->
<div id="addEmployeeModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Add New Employee</h3>
                <button onclick="closeModal('addEmployeeModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Employee Code *</label>
                        <input type="text" name="employee_code" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hire Date *</label>
                        <input type="date" name="hire_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-input" placeholder="Job title">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Salary *</label>
                        <input type="number" step="0.01" name="salary" class="form-input" required>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addEmployeeModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div id="processPayrollModal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Process Payroll</h3>
                <button onclick="closeModal('processPayrollModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="process_payroll">
                <input type="hidden" name="employee_id" id="payrollEmployeeId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Pay Period Start *</label>
                        <input type="date" name="pay_period_start" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pay Period End *</label>
                        <input type="date" name="pay_period_end" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Basic Salary *</label>
                        <input type="number" step="0.01" name="basic_salary" id="basicSalary" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Allowances</label>
                        <input type="number" step="0.01" name="allowances" class="form-input" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deductions</label>
                        <input type="number" step="0.01" name="deductions" class="form-input" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Gross Pay</label>
                        <input type="number" step="0.01" name="gross_pay" id="grossPay" class="form-input" readonly>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="form-label">Net Pay</label>
                        <input type="number" step="0.01" name="net_pay" id="netPay" class="form-input" readonly>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('processPayrollModal')" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Process Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editEmployee(id) {
    // In a real application, you would fetch the employee data via AJAX
    // For now, we'll just open the modal
    openModal('editEmployeeModal');
}

function processPayroll(employeeId) {
    document.getElementById('payrollEmployeeId').value = employeeId;
    // In a real application, you would fetch the employee salary via AJAX
    document.getElementById('basicSalary').value = '0.00';
    calculatePayroll();
    openModal('processPayrollModal');
}

function deleteEmployee(id) {
    if (confirm('Are you sure you want to remove this employee?')) {
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

function calculatePayroll() {
    const basicSalary = parseFloat(document.getElementById('basicSalary').value) || 0;
    const allowances = parseFloat(document.getElementById('allowances').value) || 0;
    const deductions = parseFloat(document.getElementById('deductions').value) || 0;

    const grossPay = basicSalary + allowances;
    const netPay = grossPay - deductions;

    document.getElementById('grossPay').value = grossPay.toFixed(2);
    document.getElementById('netPay').value = netPay.toFixed(2);
}

// Add event listeners for payroll calculation
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('basicSalary').addEventListener('input', calculatePayroll);
    document.getElementById('allowances').addEventListener('input', calculatePayroll);
    document.getElementById('deductions').addEventListener('input', calculatePayroll);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>