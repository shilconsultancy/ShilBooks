# ShilBooks - Accounting and Payroll System

A comprehensive web-based accounting and invoicing system with integrated payroll functionality. Built with PHP, MySQL, and modern web technologies.

## Features

### Core Modules
- **Dashboard**: Financial overview with charts and key metrics
- **Items**: Product and service management with inventory tracking
- **Sales**: Complete customer and revenue management
  - Customer relationship management (CRM)
  - Quote and invoice generation
  - Payment tracking and receipts
  - Credit notes and recurring invoices
- **Purchases**: Vendor management and expense tracking
- **Banking**: Bank account management and transaction reconciliation
- **Employees**: Payroll system with salary processing
- **Accountant**: Chart of accounts and manual journal entries
- **Reports**: Financial statements and business analytics
- **Documents**: Centralized file management

### Key Features
- Modern, responsive design with Tailwind CSS
- Multi-currency support
- Tax management and calculations
- User role management
- Real-time notifications
- Mobile-optimized interface
- Secure authentication system
- Comprehensive reporting

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Styling**: Tailwind CSS
- **Icons**: Font Awesome
- **Charts**: Chart.js (planned)

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional)

### Setup Steps

1. **Clone or Download** the project files to your web server directory

2. **Create Database**
   ```sql
   CREATE DATABASE shilbooks;
   ```

3. **Import Database Schema**
   ```bash
   mysql -u username -p shilbooks < database_schema.sql
   ```

4. **Configure Database Connection**
   Edit `config.php` and update the database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'shilbooks');
   ```

5. **Set File Permissions**
   ```bash
   chmod -R 755 uploads/
   chmod -R 755 assets/
   ```

6. **Access the Application**
   Open your browser and navigate to:
   ```
   http://localhost/shilbooks/
   ```

## Default Login Credentials

- **Username**: admin
- **Password**: admin123

⚠️ **Important**: Change these credentials immediately after first login!

## Usage

### Getting Started
1. Log in with the default credentials
2. Update company settings in Settings
3. Add your customers and vendors
4. Create items/products for your business
5. Start creating invoices and tracking expenses

### Creating Your First Invoice
1. Go to Sales > Invoices
2. Click "Create Invoice"
3. Select a customer
4. Add invoice items
5. Set invoice and due dates
6. Save the invoice

### Managing Expenses
1. Go to Purchases > Expenses
2. Click "Add Expense"
3. Enter expense details
4. Attach receipt if available
5. Save the expense

## Project Structure

```
shilbooks/
├── config.php                 # Database and system configuration
├── index.php                  # Login page
├── dashboard.php              # Main dashboard
├── database_schema.sql        # Database structure
├── README.md                  # This file
├── assets/                    # Static assets
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   └── js/
│       └── main.js          # JavaScript functionality
├── includes/                  # Reusable components
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── items/                     # Items module
│   └── index.php
├── sales/                     # Sales module
│   ├── customers.php
│   ├── invoices.php
│   └── ... (other sales features)
├── purchases/                 # Purchases module
├── banking/                   # Banking module
├── employees/                 # Payroll module
├── accountant/                # Accounting module
├── reports/                   # Reports module
├── documents/                 # Document management
└── uploads/                   # File uploads
```

## Security Features

- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- CSRF protection on forms
- Secure password hashing
- Session management
- File upload validation

## Customization

### Adding New Modules
1. Create a new directory under the root
2. Add index.php with the module functionality
3. Update the sidebar navigation in `includes/sidebar.php`
4. Add database tables if needed

### Styling
- Modify `assets/css/style.css` for custom styles
- Update Tailwind configuration in `includes/header.php`
- Add custom colors in the CSS variables section

### Database Changes
- Update `database_schema.sql` with new tables
- Modify existing queries in PHP files
- Update the config file if needed

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config.php`
   - Ensure MySQL service is running
   - Verify database exists and user has permissions

2. **File Upload Issues**
   - Check file permissions on `uploads/` directory
   - Verify `MAX_FILE_SIZE` in config.php
   - Check PHP upload limits

3. **Styling Issues**
   - Ensure Tailwind CSS CDN is accessible
   - Check browser console for JavaScript errors
   - Verify CSS file paths

4. **Permission Errors**
   - Set proper file permissions (755 for directories, 644 for files)
   - Check web server user permissions

### Debug Mode
Enable debug mode by setting in `config.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the MIT License.

## Support

For support and questions:
- Check the troubleshooting section above
- Review the code comments
- Create an issue in the repository

## Roadmap

### Planned Features
- [ ] Advanced reporting with charts
- [ ] Multi-company support
- [ ] API integration
- [ ] Email notifications
- [ ] Mobile app
- [ ] Advanced inventory management
- [ ] Budgeting and forecasting
- [ ] Multi-language support

### Version History
- **v1.0.0**: Initial release with core functionality
- **v1.1.0**: Enhanced UI and additional features (planned)
- **v1.2.0**: Advanced reporting and analytics (planned)

## Acknowledgments

- Built with modern web technologies
- Designed for small to medium businesses
- Focus on user experience and functionality
- Comprehensive accounting solution

---

**ShilBooks** - Your complete accounting and payroll solution.