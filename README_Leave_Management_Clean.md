# Clean Leave Management System

## Overview

This is a clean, optimized, and maintainable version of the Leave Management System that consolidates the functionality from both original versions while improving code organization, security, and user experience.

## Key Improvements

### 1. **Object-Oriented Architecture**
- Encapsulated all functionality in a `LeaveManagementSystem` class
- Clear separation of concerns between business logic and presentation
- Improved code reusability and maintainability

### 2. **Enhanced Security**
- Consistent input sanitization using `sanitizeInput()` method
- Proper prepared statements for all database operations
- Role-based access control with hierarchical permissions
- Protection against SQL injection and XSS attacks

### 3. **Smart Leave Balance Management**
- Automated leave balance calculation with fallback to annual leave
- Support for different leave types (annual, sick, maternity, etc.)
- Real-time deduction preview with warnings
- Comprehensive audit trail for all transactions

### 4. **Multi-Level Approval Workflow**
- Section Head → Department Head → HR approval flow
- Role-based filtering of applications
- Automatic status progression with proper authorization checks

### 5. **Modular Template System**
- Separated presentation layer into modular template files
- Easy to maintain and extend individual tabs
- Clean HTML structure with enhanced CSS styling

## File Structure

```
leave_management_clean.php          # Main application file
templates/
  leave_tabs/
    apply_tab.php                   # Leave application form and user's applications
    manage_tab.php                  # Leave approval/rejection interface
    profile_tab.php                 # User's leave profile and history
    holidays_tab.php                # Holiday management
    history_tab.php                 # System-wide leave history and reports
README_Leave_Management_Clean.md    # This documentation
```

## Features

### For Employees
- **Apply for Leave**: Smart form with real-time balance calculation
- **Leave Balance Overview**: Visual cards showing allocated, used, and remaining days
- **Application History**: Track status of all submitted applications
- **Deduction Preview**: See exactly how leave will be deducted before submission

### For Section Heads
- **Approve/Reject**: Applications from their section employees
- **Filtered View**: Only see applications requiring their approval
- **Quick Actions**: One-click approve/reject with confirmation

### For Department Heads
- **Department-wide Management**: Applications from entire department
- **Second-level Approval**: After section head approval
- **Balance Processing**: Final balance deductions on approval

### For HR Managers
- **System-wide Access**: View and manage all leave applications
- **Holiday Management**: Add and manage company holidays
- **Reports and Analytics**: Comprehensive leave history and statistics
- **User Management**: Full administrative controls

## Database Schema Requirements

The system expects the following key tables:

### leave_applications
```sql
- id (primary key)
- employee_id (foreign key)
- leave_type_id (foreign key)
- start_date, end_date
- days_requested
- reason
- status (pending_section_head, pending_dept_head, approved, rejected)
- applied_at, approved_date
- section_head_emp_id, dept_head_emp_id
- approver_id, approver_comments
- deduction_details (JSON)
- primary_days, annual_days, unpaid_days
```

### leave_types
```sql
- id (primary key)
- name
- max_days_per_year
- counts_weekends (boolean)
- deducted_from_annual (boolean)
- is_active (boolean)
```

### leave_balances
```sql
- employee_id (foreign key)
- leave_type_id (foreign key)
- financial_year
- annual_leave_entitled, annual_leave_used, annual_leave_balance
- sick_leave_used, other_leave_used
- created_at, updated_at
```

### leave_transactions (for audit trail)
```sql
- id (primary key)
- application_id (foreign key)
- employee_id (foreign key)
- transaction_date
- transaction_type
- details (JSON)
```

### leave_history (for audit trail)
```sql
- id (primary key)
- leave_application_id (foreign key)
- action
- performed_by (user_id)
- comments
- performed_at
```

## Configuration

### Role Hierarchy
The system uses a hierarchical role system:
```php
'super_admin' => 5
'hr_manager' => 4
'dept_head' => 3
'section_head' => 2
'manager' => 1
'employee' => 0
```

### Leave Type Configuration
Each leave type supports:
- **max_days_per_year**: Maximum days allowed per year
- **counts_weekends**: Whether weekends are included in calculations
- **deducted_from_annual**: Whether to fallback to annual leave when exhausted

## Usage Examples

### Applying for Leave
1. Employee selects leave type and dates
2. System calculates business days (excluding weekends/holidays)
3. Real-time deduction preview shows balance impact
4. Application submitted with automatic routing to section head

### Approval Workflow
1. **Section Head**: Reviews and approves applications from their section
2. **Department Head**: Reviews section head approved applications
3. **HR**: Final review and balance processing

### Balance Deduction Logic
```
IF requested_days <= primary_balance:
    deduct_from_primary(requested_days)
ELSE:
    deduct_from_primary(primary_balance)
    remaining = requested_days - primary_balance
    
    IF leave_type.deducted_from_annual AND annual_balance >= remaining:
        deduct_from_annual(remaining)
    ELSE:
        mark_as_unpaid(remaining - annual_balance)
```

## Customization

### Adding New Leave Types
1. Insert into `leave_types` table with appropriate settings
2. Configure balance calculations in `getLeaveTypeBalance()`
3. Update `updateLeaveBalance()` if special handling needed

### Modifying Approval Workflow
1. Update status flow in `getNextApprovalStatus()`
2. Modify authorization logic in `canApprove()`
3. Adjust template filters in `getManageTabData()`

### Custom Styling
CSS is embedded in the main file for easy customization:
- Modify `.leave-balance-card` for balance display styling
- Update `.badge-*` classes for status indicators
- Customize `.form-grid` for form layouts

## Security Considerations

1. **Input Validation**: All user inputs are sanitized
2. **SQL Injection Prevention**: Prepared statements throughout
3. **XSS Protection**: HTML encoding of all outputs
4. **Authorization**: Role-based access controls on all actions
5. **Audit Trail**: Complete transaction and history logging

## Performance Optimizations

1. **Efficient Queries**: Optimized database queries with proper indexing
2. **Conditional Loading**: Tab data loaded only when needed
3. **Caching**: Static methods for formatting to reduce overhead
4. **Minimal DOM Manipulation**: JavaScript optimization for real-time calculations

## Maintenance

### Regular Tasks
1. **Archive Old Records**: Archive completed leave applications annually
2. **Update Balances**: Reset annual balances at year-end
3. **Clean Logs**: Rotate audit logs as needed
4. **Update Holidays**: Maintain current holiday calendar

### Troubleshooting
1. **Balance Issues**: Check `leave_balances` table and transaction logs
2. **Approval Problems**: Verify user roles and organizational structure
3. **Calculation Errors**: Review leave type settings and business day logic

## Upgrading from Original Version

1. **Backup Database**: Full backup before migration
2. **Run Migration Script**: Update table schemas if needed
3. **Test Permissions**: Verify role assignments work correctly
4. **Validate Balances**: Ensure existing balances transfer correctly
5. **User Training**: Brief users on new interface features

## Support

For issues or questions:
1. Check error logs for detailed error messages
2. Verify database connectivity and permissions
3. Ensure all required tables exist with proper schema
4. Test with minimal user permissions to isolate issues

---

**Version**: 1.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+, MySQL 5.7+