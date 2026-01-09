<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminAuditController;
use App\Http\Controllers\Api\V1\DependentController;
use App\Http\Controllers\Api\V1\GrantController;
use App\Http\Controllers\Api\V1\GrantTypeController;
use App\Http\Controllers\Api\V1\SystemController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SMSController;
use App\Http\Controllers\Api\V1\CsvController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\LoanSettingsController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SmsTemplateController;
use App\Http\Controllers\Api\V1\SmsSettingsController;
use App\Http\Controllers\Api\V1\MpesaSettingsController;
use App\Http\Controllers\Api\V1\UserRoleController;
use App\Http\Controllers\Api\V1\GuarantorController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ImageUploadController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationConnectionController;
use App\Http\Controllers\Api\V1\UserNotificationPreferencesController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\MpesaController;
use App\Http\Controllers\Api\V1\MpesaCallbackController;
use App\Http\Controllers\Api\V1\MpesaTestController;
use App\Http\Controllers\Api\V1\SupportController;

Route::prefix('v1')->group(function () {
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // Test route
        Route::get('test-route', function () {
            return response()->json(['message' => 'API is working fine'], 200);
        });

     // Unauthenticated routes
    Route::post('/signup', [AuthController::class, 'register']); // Sign Up user
    Route::post('/login', [AuthController::class, 'loginDashboardUsers']); // Login dashboard user
    Route::post('/login-user', [AuthController::class, 'loginUser']);
    Route::patch('/change-password', [AuthController::class, 'resetPassword']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetCode']); // Send reset code
    Route::post('/verify-reset-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::patch('/reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::post('/create-employee-csv', [CsvController::class, 'handleCreateEmptyEmployeeCSV']);
    Route::get('/download-employee-csv', [CsvController::class, 'downloadEmptyCSV']);
    Route::post('/upload-employee-csv', [CsvController::class, 'uploadEmployeeCSV']);
    Route::get('/verify-account', [AuthController::class, 'verifyAccount']);
    Route::get('loans/guarantor-response', [LoanController::class, 'guarantorResponse']); // Guarantor response to loan request
    Route::post('user-otp-auth', [AuthController::class, 'userOtpAuth']);
    Route::post('phone-number-auth', [AuthController::class, 'phoneNumberAuth']);
    Route::patch('user-profile/{id}/update', [UserController::class, 'updateProfile']);
    Route::get('/verify-reset-password-external', [AuthController::class, 'verifyResetPasswordCode']);
    Route::post('/register', [AuthController::class, 'externalRegister']);

    Route::post('upload-image', [ImageUploadController::class, 'uploadImages']); // ->middleware('can:upload image');
    Route::get('notifications/stream', [NotificationController::class, 'index']); // SSE stream endpoint
    Route::get('admins-get', [AdminController::class, 'getAdmins']);

    // Testing SMS Endpoint
    Route::post('send-test-sms', [SMSController::class, 'testSMS']);

    // Public endpoint to register callback URLs (C2B/B2C)
    // M-Pesa Callback Routes (No authentication required for callbacks)
    Route::post('register-callbacks', [MpesaCallbackController::class, 'register']);
    
    // C2B callbacks
    // M-Pesa Callback Routes (No authentication required for callbacks)
    Route::post('c2b/validation', [MpesaController::class, 'c2bValidation']);
    Route::post('c2b/confirmation', [MpesaController::class, 'c2bConfirmation']);
    
    Route::prefix('mpesa')->group(function () {
        // M-Pesa test routes (for debugging - remove in production)
        Route::get('test-token', [MpesaTestController::class, 'testAccessToken']);
        Route::get('test-c2b', [MpesaTestController::class, 'testC2BRegistration']);
        Route::post('test-stk-push', [MpesaTestController::class, 'testStkPush']);
        Route::post('test-b2c', [MpesaTestController::class, 'testB2C']);
        Route::post('test-c2b-validation', [MpesaTestController::class, 'testC2BValidation']);
        Route::post('test-c2b-confirmation', [MpesaTestController::class, 'testC2BConfirmation']);
        Route::post('test-b2c-result', [MpesaTestController::class, 'testB2CResult']);
        Route::post('test-b2c-timeout', [MpesaTestController::class, 'testB2CTimeout']);
        Route::get('test-transactions', [MpesaTestController::class, 'getTestTransactions']);
        Route::get('check-callback-config', [MpesaTestController::class, 'checkCallbackConfig']);
        
        // STK Push callbacks
        Route::post('stk/callback', [MpesaController::class, 'stkCallback'])->name('mpesa.stk-callback');

        // B2C callbacks
        Route::post('b2c/result', [MpesaController::class, 'b2cResult']);
        Route::post('b2c/timeout', [MpesaController::class, 'b2cTimeout']);
    
        // B2B callbacks
        Route::post('b2b/result', [MpesaController::class, 'b2bResult']);
        Route::post('b2b/timeout', [MpesaController::class, 'b2bTimeout']);

        // Transaction Status callbacks
        Route::post('status/result', [MpesaController::class, 'statusResult']);
        Route::post('status/timeout', [MpesaController::class, 'statusTimeout']);
        
        // Account Balance callbacks
        Route::post('balance/result', [MpesaController::class, 'balanceResult']);
        Route::post('balance/timeout', [MpesaController::class, 'balanceTimeout']);
        
        // Reversal callbacks
        Route::post('reversal/result', [MpesaController::class, 'reversalResult']);
        Route::post('reversal/timeout', [MpesaController::class, 'reversalTimeout']);
    });

     // Group Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::get('/me/roles-permissions', [AuthController::class, 'getMyRolesAndPermissions']); // Get current user's roles and permissions
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/reset-password', [AuthController::class, 'forgotPassword']);
        Route::get('/verify-reset-password', [AuthController::class, 'verifyResetPasswordCode']);
        Route::patch('/change-password-internal', [AuthController::class, 'changePasswordInternal']);
        Route::put('/profile', [AuthController::class, 'updateProfile']); // Update authenticated user's profile

        Route::post('loan-types', [LoanController::class, 'createLoanType']);
        Route::get('loans/credit-score', [LoanController::class, 'getCreditScores']);
        Route::get('loan-types', [LoanController::class, 'getLoanTypes']);
        Route::get('user-loan-types', [LoanController::class, 'getUserLoanType']);
        Route::get('loan-details/{id}', [LoanController::class, 'getUserLoanDetails']);
        Route::get('user-guaranteed-loans', [LoanController::class, 'getUserGuaranteedLoans']);
        Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancelLoan']);
        Route::post('test-loan-acceptance', [LoanController::class, 'testLoanAcceptance']);
        Route::get('/loans/{loanId}/mini-statement', [LoanController::class, 'getMiniStatement']);

        Route::get('loan/user/recent', [LoanController::class, 'getRecentLoan']); // Get user's active loans with detailed calculations
        Route::get('loan/user', [LoanController::class, 'getUserLoans']); // Get the user's loans
        Route::post('support/message', [SupportController::class, 'submitSupportMessage']);

        // M-Pesa Payment Routes (Authenticated)
        Route::prefix('mpesa')->group(function () {
            // Method 1: App-based payments
            Route::post('loan-payment', [MpesaController::class, 'initiateLoanPayment']); // Loan payment via app
            Route::post('stk-push', [MpesaController::class, 'initiateStkPush']); // General STK Push
            
            // Transaction management
            Route::get('transactions', [MpesaController::class, 'getUserTransactions']); // Get user transactions
            Route::post('query-status', [MpesaController::class, 'queryTransactionStatus']); // Query transaction status from M-Pesa API
            Route::post('verify-payment', [MpesaController::class, 'verifyPayment']); // Verify payment after STK Push callback
            
            // Loan information for paybill users
            Route::get('loan-info/{loan_identifier}', [MpesaController::class, 'getLoanPaymentInfo']); // Get loan payment info
            
            // Testing endpoints
            Route::post('test-notification', [MpesaController::class, 'testPaymentNotification']); // Test SMS notification
            // B2C Disbursement (initiate disbursement to a user by loan number or user id)
            Route::post('b2c/{identifier}', [MpesaController::class, 'initiateB2CPayment']); // Disburse funds via M-Pesa B2C
        });
        
        Route::get('loan/user/loan-details', [LoanController::class, 'getUserLoanDetails']); // Get user single loan details
        Route::get('loan/user/payments', [LoanController::class, 'getUserLoanPayments']); // Get user payments
        Route::delete('loan/user/payment', [LoanController::class, 'deleteUserLoanPayment']); // Delete a user single payment
        Route::delete('loan/user/payments', [LoanController::class, 'deleteUserLoanPayments']); // Delete all payments

        Route::get('user/search', [UserController::class, 'searchUser']); // Search for user/guarantor
        Route::get('user/get', [UserController::class, 'getUsers']); // Get all users guarantors

        // System Image Uploader Route
        Route::get('upload-image', [ImageUploadController::class, 'index']); // ->middleware('can:view image');
        Route::post('remove-image', [ImageUploadController::class, 'destroy']); // ->middleware('can:delete image');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'getNotifications']); // Get all notifications (paginated)
        Route::get('notifications/unread', [NotificationController::class, 'getUnreadNotifications']); // Get unread notifications
        Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']); // Get unread notification count
        Route::post('notifications/test', [NotificationController::class, 'testNotification']); // Test notification endpoint
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']); // Mark all notifications as read
        Route::post('notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']); // Mark single notification as read
        Route::delete('notifications/{notificationId}', [NotificationController::class, 'delete']); // Delete a notification
        Route::get('mobile-notifications', [NotificationController::class, 'userNotifications']); // Get user notifications (paginated) - legacy endpoint

        // User Notification Preferences
        Route::get('notifications/preferences', [UserNotificationPreferencesController::class, 'getPreferences']); // Get user notification preferences
        Route::put('notifications/preferences', [UserNotificationPreferencesController::class, 'updatePreferences']); // Update user notification preferences
        Route::post('notifications/preferences/reset', [UserNotificationPreferencesController::class, 'resetPreferences']); // Reset notification preferences to defaults

        // Notification Connection Management
        Route::prefix('notifications/connection')->group(function () {
            Route::get('config', [NotificationConnectionController::class, 'getConfig']); // Get connection configuration
            Route::get('status', [NotificationConnectionController::class, 'getStatus']); // Get connection status
            Route::get('health', [NotificationConnectionController::class, 'getHealth']); // Get connection health
            Route::post('test', [NotificationConnectionController::class, 'testConnection']); // Test connection
        });

        // Guarantor Responses
        Route::post('guarantor/{guarantorId}/respond', [GuarantorController::class, 'respond']);

        Route::middleware(['role:super-admin|employer|employee'])->group(function () {
            // System Routes
            Route::get('system/roles', [SystemController::class, 'getRoles']);
            Route::get('loan-statistics', [LoanController::class, 'getUserLoanStats']);

            // User routes
            Route::get('my-grants', [GrantController::class, 'userGrants']);
            Route::post('grants', [GrantController::class, 'store']);
            Route::patch('grants/{grant}/update', [GrantController::class, 'update']);
            Route::delete('grants/{grant}/delete', [GrantController::class, 'destroy']);
            Route::post('grants/{grant}/cancel', [GrantController::class, 'cancelGrant']);

            // Dependents routes
            Route::get('dependents', [DependentController::class, 'index']);
            Route::get('my-dependents', [DependentController::class, 'getUsersDependents']);
            Route::post('dependents', [DependentController::class, 'store']);
            Route::get('dependents/{dependent}', [DependentController::class, 'show']);
            Route::patch('dependents/{dependent}', [DependentController::class, 'update']);
            Route::delete('dependents/{dependent}', [DependentController::class, 'destroy']);

            // For Admin
            Route::get('grants', [GrantController::class, 'index']);
            Route::get('grants-get', [GrantController::class, 'getGrantsForTable']);
            Route::get('grants/statistics', [GrantController::class, 'getGrantStatistics']); // Get grant statistics for dashboard
            Route::get('grants/{grantId}', [GrantController::class, 'show']);
            Route::get('grants/{grantId}/admin-details', [GrantController::class, 'getAdminGrantDetails']); // Get comprehensive grant details for administrators
            Route::post('grants/{grantId}/approve', [GrantController::class, 'approve']);
            Route::post('grants/{grantId}/reject', [GrantController::class, 'reject']);
            Route::post('grants/{grantId}/paid', [GrantController::class, 'markAsPaid']);

            // Admin Audit Routes
            Route::prefix('admin/audit')->group(function () {
                Route::get('loan-notifications', [AdminAuditController::class, 'getLoanNotifications']);
                Route::get('system-activities', [AdminAuditController::class, 'getSystemActivities']);
                Route::get('recent-activities', [AdminAuditController::class, 'getRecentActivities']);
                Route::get('notification-stats', [AdminAuditController::class, 'getNotificationStats']);
                Route::get('system-metrics', [AdminAuditController::class, 'getSystemMetrics']);
            });

            Route::get('grant-types', [GrantTypeController::class, 'index']);
            Route::post('grant-types', [GrantTypeController::class, 'store']);
            Route::get('grant-types/{grantType}', [GrantTypeController::class, 'show']);
            Route::patch('grant-types/{grantType}', [GrantTypeController::class, 'updateGrantType']);
            Route::delete('grant-types/{grantType}', [GrantTypeController::class, 'destroy']);
            Route::post('grant-types/{grantType}/restore', [GrantTypeController::class, 'restore']);

            // For frontend dropdowns
            Route::get('grant-types-options', [GrantTypeController::class, 'dropdownOptions']);

            // Users Endpoints
            Route::get('users', [UserController::class, 'getUsers']); // ->middleware('can:view user');
            Route::get('employees', [UserController::class, 'getSystemEmployees']); // Get employees list
            Route::get('employees/statistics', [UserController::class, 'getEmployeeStatistics']); // Get employee statistics for dashboard
            Route::delete('user/{id}', [UserController::class, 'deleteUser']); // ->middleware('can:delete user');
            Route::post('user/create', [UserController::class, 'createUser']); // ->middleware('can:create user');
            Route::patch('/user/{id}', [UserController::class, 'updateUser']); // ->middleware('can:update user');
            Route::patch('/employee/{id}/profile', [UserController::class, 'updateEmployeeProfile']); // Update employee profile
            Route::patch('/employee/{id}/change-password', [UserController::class, 'changeEmployeePassword']); // Change employee password (Admin)
            Route::patch('/employee/{id}/roles-change', [UserController::class, 'changeEmployeeRoles']); // Change employee roles (Admin)
            Route::delete('/user/{id}/delete-account', [UserController::class, 'deleteUserAccount']); // Delete user account (Admin)
            Route::post('/employees/bulk-import', [UserController::class, 'bulkImportEmployees']); // Bulk import employees from CSV (Admin)
            Route::get('user/get-employees', [UserController::class, 'getSystemEmployees']);
            Route::get('user/{id}/employee', [UserController::class, 'getEmployee']);

            // Loan Endpoints
            Route::get('/loans/{loanId}/details', [LoanController::class, 'getUserLoanDetails']); // Get detailed loan information by loan ID
            Route::get('/loans/{loanId}/admin-details', [LoanController::class, 'getAdminLoanDetails']); // Get comprehensive loan details for administrators
            Route::get('/employees/{employeeId}/loans', [LoanController::class, 'getEmployeeLoans']); // Get employee loans
            Route::post('/employees/{employeeId}/loans', [LoanController::class, 'applyForLoan']); // ->middleware('can: apply loan');
            Route::post('/employees/{employeeId}/deductions', [LoanController::class, 'processEmployeeDeduction']); // Process employee deduction
            Route::get('/employees/{loan_id}/loan-status', [LoanController::class, 'getLoanStatus']); // ->middleware('can: get loan status');
            Route::post('/approve-loan/{loan_id}', [LoanController::class, 'approveLoan']); // ->middleware('can: approve loan');
            Route::get('/employees/{employeeId}/loan-limit', [LoanController::class, 'calculateLoanLimit']); // ->middleware('can: calculate loan limit');
            Route::get('/deductions/monthly/datatables', [LoanController::class, 'processDeductions']); // Get monthly deductions for DataTables
            Route::put('/loans/{loanId}/approve', [LoanController::class, 'approveLoan']); // ->middleware('can: approve loan');
            Route::patch('/employees/{loanId}/salary', [UserController::class, 'setEmployeeSalary']); // ->middleware('can: set employee salary');
            Route::post('/send-sms', [SMSController::class, 'sendSMS']); // ->middleware('can: send sms');
            Route::get('/sent-sms', [SMSController::class, 'getSentSMS']); // ->middleware('can: get sent sms');
            Route::post('/send-bulk-sms', [SMSController::class, 'sendBulkSMS']); // ->middleware('can: send bulk sms');
            Route::get('/sms/messages/datatables', [SMSController::class, 'getSmsMessagesForDataTables']); // Get SMS messages for DataTables
            Route::get('/sms/statistics', [SMSController::class, 'getSMSStatistics']); // Get SMS statistics for dashboard

            // Legacy Roles Endpoints (for backward compatibility - must come before standardized routes)
            Route::get('roles', [RoleController::class, 'index']); // DataTables format
            Route::get('roles-get', [RoleController::class, 'getRoles']); // JSON format
            Route::post('role', [RoleController::class, 'storeLegacy']); // Create role (legacy)
            Route::get('role/{id}', [RoleController::class, 'showLegacy']); // Get single role (legacy)
            Route::patch('role/{id}', [RoleController::class, 'updateLegacy']); // Update role (legacy)
            Route::delete('role/{id}', [RoleController::class, 'removeRole']); // Delete role (legacy)
            Route::patch('permissions/sync/{id}', [RoleController::class, 'syncPermissionsLegacy']); // Sync permissions (legacy)
            Route::get('role-permissions', [RoleController::class, 'getRolePermissions']); // Get role permissions (DataTables)
            Route::post('remove-permission/{id}', [RoleController::class, 'removePermissionFromRole']); // Remove permission (legacy)
            Route::post('assign-role/{id}', [RoleController::class, 'assignRoleToUser']); // Assign role to user (legacy)
            Route::post('remove-role/{id}', [RoleController::class, 'removeRoleFromUser']); // Remove role from user (legacy)
            Route::delete('role-permissions/{id}', [RoleController::class, 'deleteRoleAndPermission']); // Delete role and permissions (legacy)

            // Roles Endpoints (Standardized - more specific routes first)
            Route::prefix('roles')->group(function () {
                Route::get('/statistics', [RoleController::class, 'getStatistics']); // Get role statistics
                Route::post('/bulk-assign', [RoleController::class, 'bulkAssign']); // Bulk assign role to users
                Route::post('/bulk-remove', [RoleController::class, 'bulkRemove']); // Bulk remove role from users
                Route::put('/{id}/permissions', [RoleController::class, 'syncPermissions']); // Sync permissions to role
                Route::post('/{id}/permissions', [RoleController::class, 'addPermission']); // Add permission to role
                Route::delete('/{id}/permissions/{permissionId}', [RoleController::class, 'removePermission']); // Remove permission from role
                Route::post('/{id}/permissions/bulk', [RoleController::class, 'bulkAssignPermissions']); // Bulk assign permissions to role
                Route::get('/{id}/users', [RoleController::class, 'getUsersByRole']); // Get users by role
                Route::get('/{id}', [RoleController::class, 'show']); // Get single role
                Route::put('/{id}', [RoleController::class, 'update']); // Update role
                Route::delete('/{id}', [RoleController::class, 'destroy']); // Delete role
                Route::post('/', [RoleController::class, 'store']); // Create role (standardized)
                Route::get('/', [RoleController::class, 'getRolesStandardized']); // Get all roles (standardized) - must be last
            });

            // Legacy Permissions Endpoints (for backward compatibility - must come before standardized routes)
            Route::post('permission', [PermissionController::class, 'storePermission']); // Create permission (legacy)
            Route::get('permissions', [PermissionController::class, 'getPermissions']); // Get permissions (DataTables)
            Route::get('permissions-get', [PermissionController::class, 'index']); // Get permissions (JSON) - same as new
            Route::get('permission/{id}', [PermissionController::class, 'getSinglePermission']); // Get single permission (legacy)
            Route::patch('permission/{id}', [PermissionController::class, 'updatePermission']); // Update permission (legacy)
            Route::delete('permission/{id}', [PermissionController::class, 'deletePermission']); // Delete permission (legacy)

            // Permissions Endpoints (Standardized - more specific routes first)
            Route::prefix('permissions')->group(function () {
                Route::get('/groups', [PermissionController::class, 'getPermissionsByGroup']); // Get permissions by group
                Route::get('/groups/list', [PermissionController::class, 'getGroups']); // Get permission groups list
                Route::get('/statistics', [PermissionController::class, 'getStatistics']); // Get permission statistics
                Route::get('/{id}', [PermissionController::class, 'show']); // Get single permission
                Route::put('/{id}', [PermissionController::class, 'update']); // Update permission
                Route::delete('/{id}', [PermissionController::class, 'destroy']); // Delete permission
                Route::post('/', [PermissionController::class, 'store']); // Create permission
                Route::get('/', [PermissionController::class, 'index']); // Get all permissions (standardized) - must be last
            });

            // User Role and Permission Management
            Route::prefix('users/{userId}')->group(function () {
                Route::get('/roles-permissions', [UserRoleController::class, 'getUserRolesAndPermissions']); // Get user's roles and permissions
                Route::post('/roles', [UserRoleController::class, 'assignRoles']); // Assign roles to user
                Route::put('/roles', [UserRoleController::class, 'syncRoles']); // Sync roles to user
                Route::delete('/roles/{roleId}', [UserRoleController::class, 'removeRole']); // Remove role from user
                Route::post('/permissions', [UserRoleController::class, 'assignPermission']); // Assign direct permission to user
                Route::delete('/permissions/{permissionId}', [UserRoleController::class, 'removePermission']); // Remove direct permission from user
            });

            // Loan Endpoints
            Route::post('loans/accept-payment', [LoanController::class, 'acceptPayment']); // ->middleware('can:accept payment');
            Route::get('loans/{loanId}/transactions', [LoanController::class, 'getLoanTransactions']); // ->middleware('can:get loan transactions');

            Route::get('loans', [LoanController::class, 'getLoans']); // ->middleware('can:get loans');
            Route::get('loans/statistics', [LoanController::class, 'getLoanStatistics']); // Get loan statistics for dashboard
            Route::get('loans/{userId}/personal', [LoanController::class, 'getPersonalLoans']); // ->middleware('can:get personal loans');
            Route::get('loans/{userId}/personal-deduction', [LoanController::class, 'getLoanPersonalDeductions']); // ->middleware('can:get personal deduction')
            Route::get('loans/deductions/datatables', [LoanController::class, 'getLoanDeductionsForDataTables']); // Get loan deductions for DataTables
            Route::get('transactions/datatables', [LoanController::class, 'getTransactionsForDataTables']); // Get all transactions for DataTables (Admin)
            Route::get('transactions/{employeeId}/datatables', [LoanController::class, 'getUserTransactionsForDataTables']); // Get user transactions for DataTables

            // M-Pesa Admin Endpoints
            Route::get('mpesa/transactions/datatables', [MpesaController::class, 'getMpesaTransactionsForDataTables']); // Get M-Pesa transactions for DataTables

            // Loan Settings Endpoints
            Route::prefix('loan-settings')->group(function () {
                // Global Settings
                Route::get('global', [LoanSettingsController::class, 'getGlobalSettings']);
                Route::put('global', [LoanSettingsController::class, 'updateGlobalSettings']);
                
                // Limit Calculation Settings
                Route::get('limit-calculation', [LoanSettingsController::class, 'getLimitCalculationSettings']);
                Route::put('limit-calculation', [LoanSettingsController::class, 'updateLimitCalculationSettings']);
                
                // Payment Settings
                Route::get('payments', [LoanSettingsController::class, 'getPaymentSettings']);
                Route::put('payments', [LoanSettingsController::class, 'updatePaymentSettings']);
                
                // Approval Workflow Settings
                Route::get('approval-workflow', [LoanSettingsController::class, 'getApprovalWorkflowSettings']);
                Route::put('approval-workflow', [LoanSettingsController::class, 'updateApprovalWorkflowSettings']);
                
                // Notification Settings
                Route::get('notifications', [LoanSettingsController::class, 'getNotificationSettings']);
                Route::put('notifications', [LoanSettingsController::class, 'updateNotificationSettings']);
            });

            // Loan Type Management (Enhanced CRUD)
            Route::get('loan-types', [LoanSettingsController::class, 'getLoanTypes']); // Get all loan types with filters
            Route::get('loan-types/{id}', [LoanSettingsController::class, 'getLoanType']); // Get single loan type
            Route::put('loan-types/{id}', [LoanSettingsController::class, 'updateLoanType']); // Update loan type
            Route::delete('loan-types/{id}', [LoanSettingsController::class, 'deleteLoanType']); // Delete loan type

            // Notification Template Management
            Route::get('notification-templates', [NotificationTemplateController::class, 'index']); // Get all notification templates
            Route::get('notification-templates/types', [NotificationTemplateController::class, 'getTypes']); // Get available notification types
            Route::get('notification-templates/type/{type}', [NotificationTemplateController::class, 'getByType']); // Get template by type
            Route::get('notification-templates/{id}', [NotificationTemplateController::class, 'show']); // Get single notification template
            Route::post('notification-templates', [NotificationTemplateController::class, 'store']); // Create notification template
            Route::put('notification-templates/{id}', [NotificationTemplateController::class, 'update']); // Update notification template
            Route::delete('notification-templates/{id}', [NotificationTemplateController::class, 'destroy']); // Delete notification template
            Route::post('notification-templates/{id}/preview', [NotificationTemplateController::class, 'preview']); // Preview template with sample data

            // SMS Settings Management
            Route::prefix('sms')->group(function () {
                Route::get('settings', [SmsSettingsController::class, 'getSettings']); // Get SMS settings
                Route::put('settings', [SmsSettingsController::class, 'updateSettings']); // Update SMS settings
                
                // SMS Template Management
                Route::get('templates', [SmsTemplateController::class, 'index']); // Get all SMS templates
                Route::get('templates/types', [SmsTemplateController::class, 'getTypes']); // Get available template types
                Route::get('templates/type/{type}', [SmsTemplateController::class, 'getByType']); // Get template by type
                Route::get('templates/{id}', [SmsTemplateController::class, 'show']); // Get single SMS template
                Route::post('templates', [SmsTemplateController::class, 'store']); // Create SMS template
                Route::put('templates/{id}', [SmsTemplateController::class, 'update']); // Update SMS template
                Route::delete('templates/{id}', [SmsTemplateController::class, 'destroy']); // Delete SMS template
                Route::post('templates/{id}/preview', [SmsTemplateController::class, 'preview']); // Preview template with sample data
            });

            // M-Pesa Settings Management
            Route::prefix('mpesa')->group(function () {
                Route::get('settings', [MpesaSettingsController::class, 'getSettings']); // Get M-Pesa settings
                Route::put('settings', [MpesaSettingsController::class, 'updateSettings']); // Update M-Pesa settings
                Route::post('settings/test-connection', [MpesaSettingsController::class, 'testConnection']); // Test M-Pesa connection
                Route::get('settings/validate', [MpesaSettingsController::class, 'validateConfiguration']); // Validate M-Pesa configuration
                Route::get('settings/statistics', [MpesaSettingsController::class, 'getStatistics']); // Get M-Pesa transaction statistics
            });
        });
    });
});
