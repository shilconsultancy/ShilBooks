-- Phase 1: Database Schema Modifications for Single-Company Application
-- This script converts the multi-user system to a single-company application

-- Step 1: Drop parent_user_id from users table
ALTER TABLE users DROP FOREIGN KEY users_ibfk_1;
ALTER TABLE users DROP COLUMN parent_user_id;

-- Step 2: Drop user_id from all data tables
-- Bank Accounts
ALTER TABLE bank_accounts DROP FOREIGN KEY bank_accounts_ibfk_1;
ALTER TABLE bank_accounts DROP KEY user_id;
ALTER TABLE bank_accounts DROP COLUMN user_id;

-- Bank Transactions
ALTER TABLE bank_transactions DROP FOREIGN KEY bank_transactions_ibfk_1;
ALTER TABLE bank_transactions DROP KEY user_id;
ALTER TABLE bank_transactions DROP COLUMN user_id;

-- Chart of Accounts
ALTER TABLE chart_of_accounts DROP FOREIGN KEY chart_of_accounts_ibfk_1;
ALTER TABLE chart_of_accounts DROP KEY user_id;
ALTER TABLE chart_of_accounts DROP COLUMN user_id;

-- Credit Notes
ALTER TABLE credit_notes DROP FOREIGN KEY credit_notes_ibfk_1;
ALTER TABLE credit_notes DROP KEY user_id;
ALTER TABLE credit_notes DROP COLUMN user_id;

-- Customers
ALTER TABLE customers DROP FOREIGN KEY customers_ibfk_1;
ALTER TABLE customers DROP KEY user_id;
ALTER TABLE customers DROP COLUMN user_id;

-- Documents
ALTER TABLE documents DROP FOREIGN KEY documents_ibfk_1;
ALTER TABLE documents DROP KEY user_id;
ALTER TABLE documents DROP COLUMN user_id;

-- Expenses
ALTER TABLE expenses DROP FOREIGN KEY expenses_ibfk_1;
ALTER TABLE expenses DROP KEY user_id;
ALTER TABLE expenses DROP COLUMN user_id;

-- Expense Categories
ALTER TABLE expense_categories DROP FOREIGN KEY expense_categories_ibfk_1;
ALTER TABLE expense_categories DROP KEY user_id;
ALTER TABLE expense_categories DROP COLUMN user_id;

-- Invoices
ALTER TABLE invoices DROP FOREIGN KEY invoices_ibfk_1;
ALTER TABLE invoices DROP KEY user_id;
ALTER TABLE invoices DROP COLUMN user_id;

-- Items
ALTER TABLE items DROP FOREIGN KEY items_ibfk_1;
ALTER TABLE items DROP KEY user_id;
ALTER TABLE items DROP COLUMN user_id;

-- Journal Entries
ALTER TABLE journal_entries DROP FOREIGN KEY journal_entries_ibfk_1;
ALTER TABLE journal_entries DROP KEY user_id;
ALTER TABLE journal_entries DROP COLUMN user_id;

-- Payments
ALTER TABLE payments DROP FOREIGN KEY payments_ibfk_1;
ALTER TABLE payments DROP KEY user_id;
ALTER TABLE payments DROP COLUMN user_id;

-- Quotes
ALTER TABLE quotes DROP FOREIGN KEY quotes_ibfk_1;
ALTER TABLE quotes DROP KEY user_id;
ALTER TABLE quotes DROP COLUMN user_id;

-- Recurring Invoices
ALTER TABLE recurring_invoices DROP FOREIGN KEY recurring_invoices_ibfk_1;
ALTER TABLE recurring_invoices DROP KEY user_id;
ALTER TABLE recurring_invoices DROP COLUMN user_id;

-- Sales Receipts
ALTER TABLE sales_receipts DROP FOREIGN KEY sales_receipts_ibfk_1;
ALTER TABLE sales_receipts DROP KEY user_id;
ALTER TABLE sales_receipts DROP COLUMN user_id;

-- Settings
ALTER TABLE settings DROP FOREIGN KEY settings_ibfk_1;
ALTER TABLE settings DROP KEY user_setting;
ALTER TABLE settings DROP COLUMN user_id;

-- Tax Rates
ALTER TABLE tax_rates DROP FOREIGN KEY tax_rates_ibfk_1;
ALTER TABLE tax_rates DROP KEY user_id;
ALTER TABLE tax_rates DROP COLUMN user_id;

-- Vendors
ALTER TABLE vendors DROP FOREIGN KEY vendors_ibfk_1;
ALTER TABLE vendors DROP KEY user_id;
ALTER TABLE vendors DROP COLUMN user_id;

-- Step 3: Update settings table constraint
-- Change the unique constraint from (user_id, setting_key) to just setting_key
ALTER TABLE settings ADD UNIQUE KEY setting_key_unique (setting_key);

-- Step 4: Update indexes that are no longer needed
-- Remove user_id indexes that were dropped
ALTER TABLE bank_transactions DROP KEY account_id;
ALTER TABLE bank_transactions ADD KEY account_id (account_id);

-- Note: Some tables may need additional constraints or indexes after user_id removal
-- For example, you might want to ensure data integrity with application-level constraints