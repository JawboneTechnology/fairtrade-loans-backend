<?php

use App\Http\Controllers\Api\V1\AdminController;
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
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\GuarantorController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ImageUploadController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\MpesaController;

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
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('admins-get', [AdminController::class, 'getAdmins']);

    // Testing SMS Endpoint
    Route::post('send-test-sms', [SMSController::class, 'testSMS']);

    // M-Pesa Callback Routes (No authentication required for callbacks)
    Route::prefix('mpesa')->group(function () {
        // STK Push callbacks
        Route::post('stk/callback', [MpesaController::class, 'stkCallback'])->name('mpesa.stk-callback');
        
        // C2B callbacks
        Route::post('c2b/validation', [MpesaController::class, 'c2bValidation']);
        Route::post('c2b/confirmation', [MpesaController::class, 'c2bConfirmation']);
        
        // B2C callbacks
        Route::post('b2c/result', [MpesaController::class, 'b2cResult']);
        Route::post('b2c/timeout', [MpesaController::class, 'b2cTimeout']);
        
        // Transaction Status callbacks
        Route::post('status/result', [MpesaController::class, 'statusResult']);
        Route::post('status/timeout', [MpesaController::class, 'statusTimeout']);
        
        // Account Balance callbacks
        Route::post('balance/result', [MpesaController::class, 'balanceResult']);
        Route::post('balance/timeout', [MpesaController::class, 'balanceTimeout']);
        
        // Reversal callbacks
        Route::post('reversal/result', [MpesaController::class, 'reversalResult']);
        Route::post('reversal/timeout', [MpesaController::class, 'reversalTimeout']);
        
        // B2B callbacks
        Route::post('b2b/result', [MpesaController::class, 'b2bResult']);
        Route::post('b2b/timeout', [MpesaController::class, 'b2bTimeout']);
    });

     // Group Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/reset-password', [AuthController::class, 'forgotPassword']);
        Route::get('/verify-reset-password', [AuthController::class, 'verifyResetPasswordCode']);
        Route::patch('/change-password-internal', [AuthController::class, 'changePasswordInternal']);

        Route::post('loan-types', [LoanController::class, 'createLoanType']);
        Route::get('loans/credit-score', [LoanController::class, 'getCreditScores']);
        Route::get('loan-types', [LoanController::class, 'getLoanTypes']);
        Route::get('user-loan-types', [LoanController::class, 'getUserLoanType']);
        Route::get('loan-details/{id}', [LoanController::class, 'getUserLoanDetails']);
        Route::get('user-guaranteed-loans', [LoanController::class, 'getUserGuaranteedLoans']);
        Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancelLoan']);
        Route::post('test-loan-acceptance', [LoanController::class, 'testLoanAcceptance']);
        Route::get('/loans/{loanId}/mini-statement', [LoanController::class, 'getMiniStatement']);

        Route::get('loan/user/recent', [LoanController::class, 'getRecentLoan']); // Get user's recent loan
        Route::get('loan/user', [LoanController::class, 'getUserLoans']); // Get the user's loans

        // M-Pesa Payment Routes (Authenticated)
        Route::prefix('mpesa')->group(function () {
            // Method 1: App-based payments
            Route::post('loan-payment', [MpesaController::class, 'initiateLoanPayment']); // Loan payment via app
            Route::post('stk-push', [MpesaController::class, 'initiateStkPush']); // General STK Push
            
            // Transaction management
            Route::get('transactions', [MpesaController::class, 'getUserTransactions']); // Get user transactions
            Route::post('query-status', [MpesaController::class, 'queryTransactionStatus']); // Query transaction status
            
            // Loan information for paybill users
            Route::get('loan-info/{loan_identifier}', [MpesaController::class, 'getLoanPaymentInfo']); // Get loan payment info
            
            // Testing endpoints
            Route::post('test-notification', [MpesaController::class, 'testPaymentNotification']); // Test SMS notification
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
        Route::post('notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::get('mobile-notifications', [NotificationController::class, 'userNotifications']);

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
            Route::get('grants/{grantId}', [GrantController::class, 'show']);
            Route::post('grants/{grantId}/approve', [GrantController::class, 'approve']);
            Route::post('grants/{grantId}/reject', [GrantController::class, 'reject']);
            Route::post('grants/{grantId}/paid', [GrantController::class, 'markAsPaid']);

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
            Route::delete('user/{id}', [UserController::class, 'deleteUser']); // ->middleware('can:delete user');
            Route::post('user/create', [UserController::class, 'createUser']); // ->middleware('can:create user');
            Route::patch('/user/{id}', [UserController::class, 'updateUser']); // ->middleware('can:update user');
            Route::get('user/get-employees', [UserController::class, 'getSystemEmployees']);
            Route::get('user/{id}/employee', [UserController::class, 'getEmployee']);

            // Loan Endpoints
            Route::post('/employees/{employeeId}/loans', [LoanController::class, 'applyForLoan']); // ->middleware('can: apply loan');
            Route::get('/employees/{loan_id}/loan-status', [LoanController::class, 'getLoanStatus']); // ->middleware('can: get loan status');
            Route::post('/approve-loan/{loan_id}', [LoanController::class, 'approveLoan']); // ->middleware('can: approve loan');
            Route::get('/employees/{employeeId}/loan-limit', [LoanController::class, 'calculateLoanLimit']); // ->middleware('can: calculate loan limit');
            Route::get('/process-deductions/{employee_id}', [LoanController::class, 'processDeductions']); // ->middleware('can: process deductions');
            Route::post('/loans/{loanId}/approve', [LoanController::class, 'approveLoan']); // ->middleware('can: approve loan');
            Route::patch('/employees/{loanId}/salary', [UserController::class, 'setEmployeeSalary']); // ->middleware('can: set employee salary');
            Route::post('/send-sms', [SMSController::class, 'sendSMS']); // ->middleware('can: send sms');
            Route::get('/sent-sms', [SMSController::class, 'getSentSMS']); // ->middleware('can: get sent sms');
            Route::post('/send-bulk-sms', [SMSController::class, 'sendBulkSMS']); // ->middleware('can: send bulk sms');

            // Roles Endpoints
            Route::get('roles', [RoleController::class, 'index']); // ->middleware('can:view role');
            Route::get('roles-get', [RoleController::class, 'getRoles']); // ->middleware('can:view role');
            Route::post('role', [RoleController::class, 'store']); // ->middleware('can:create role');
            Route::get('role/{id}', [RoleController::class, 'show']); // ->middleware('can:view role');
            Route::patch('role/{id}', [RoleController::class, 'update']); // ->middleware('can:update role');
            Route::delete('role/{id}', [RoleController::class, 'removeRole']); // ->middleware('can:delete role');
            Route::patch('permissions/sync/{id}', [RoleController::class, 'syncPermissions']); // ->middleware('can:update role');
            Route::get('role-permissions', [RoleController::class, 'getRolePermissions']); // ->middleware('can:view role');
            Route::post('remove-permission/{id}', [RoleController::class, 'removePermissionFromRole']); // ->middleware('can:delete role');
            Route::post('assign-role/{id}', [RoleController::class, 'assignRoleToUser']); // ->middleware('can:update role');
            Route::post('remove-role/{id}', [RoleController::class, 'removeRoleFromUser']); // ->middleware('can:delete role');
            Route::delete('role-permissions/{id}', [RoleController::class, 'deleteRoleAndPermission']); // ->middleware('can:delete role');

            // Permissions Endpoints
            Route::post('permission', [PermissionController::class, 'storePermission']); // ->middleware('can:create permission');
            Route::get('permissions', [PermissionController::class, 'getPermissions']); // ->middleware('can:view permission');
            Route::get('permissions-get', [PermissionController::class, 'index']); // ->middleware('can:view permission');
            Route::get('permission/{id}', [PermissionController::class, 'getSinglePermission']); // ->middleware('can:view permission');
            Route::patch('permission/{id}', [PermissionController::class, 'updatePermission']); // ->middleware('can:update permission');
            Route::delete('permission/{id}', [PermissionController::class, 'deletePermission']); // ->middleware('can:delete permission');

            // Loan Endpoints
            Route::post('loans/accept-payment', [LoanController::class, 'acceptPayment']); // ->middleware('can:accept payment');
            Route::get('loans/{loanId}/transactions', [LoanController::class, 'getLoanTransactions']); // ->middleware('can:get loan transactions');

            Route::get('loans', [LoanController::class, 'getLoans']); // ->middleware('can:get loans');
            Route::get('loans/{userId}/personal', [LoanController::class, 'getPersonalLoans']); // ->middleware('can:get personal loans');
            Route::get('loans/{userId}/personal-deduction', [LoanController::class, 'getLoanPersonalDeductions']); // ->middleware('can:get personal deduction')
        });
    });
});
