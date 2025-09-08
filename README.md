# Loan Management System

## Overview
The Loan Management System is a Laravel-based application designed to manage employee loans efficiently. It includes functionalities such as loan application, approval, monthly deductions, and SMS notifications for loan-related updates. The system uses role-based access control and integrates with Africastalking for SMS services.

---

## Features

- **Loan Application**: Employees can apply for loans with specific details.
- **Loan Approval**: Admins approve or reject loan applications.
- **Loan Limit Calculation**: Dynamically calculates loan limits based on predefined rules.
- **Monthly Deductions**: Automatically processes deductions for approved loans.
- **SMS Notifications**: Sends SMS updates to employees about loan activities.
- **Audit Trail**: Logs all loan and deduction-related actions for transparency.

---

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-repo/loan-management.git
   ```
2. Navigate to the project directory:
   ```bash
   cd loan-management
   ```
3. Install dependencies:
   ```bash
   composer install
   npm install
   ```
4. Create a `.env` file and configure your database and SMS API settings.
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   AFRICASTALKING_USERNAME=your_username
   AFRICASTALKING_API_KEY=your_api_key
   ```
5. Run migrations:
   ```bash
   php artisan migrate
   ```
6. Seed the database (optional):
   ```bash
   php artisan db:seed
   ```
7. Start the development server:
   ```bash
   php artisan serve
   ```

---

## Usage

### Loan Application
Employees can submit loan applications via the system.

**API Endpoint**:
```http
POST /api/loans/apply
```
**Request Body**:
```json
{
  "employee_id": "12345",
  "loan_amount": 50000,
  "repayment_period": 12
}
```

### Loan Approval
Admins approve or reject loans.

**API Endpoint**:
```http
POST /api/loans/approve/{id}
```
**Request Body**:
```json
{
  "status": "approved",
  "approval_date": "2024-12-31"
}
```

### Monthly Deductions
The system processes deductions daily at midnight using a Laravel command.

**Command**:
```bash
php artisan schedule:run
```

### SMS Notifications
The system queues SMS notifications for loan-related updates. SMS logs are stored in the database.

---

## SMS Service
The application integrates with Africastalking to send SMS. It includes the following components:

1. **SMS Service**: Handles SMS sending and logging.
2. **SMS Jobs**: Sends SMS to multiple users asynchronously.

**Sample Code**:
```php
$smsService = app(\App\Services\SmsService::class);
$smsService->sendSms("+254700000000", "Your loan has been approved.");
```

---

## Scheduling Deductions
The monthly deductions process is automated using a Laravel command.

**Command Setup**:
1. Open the `App\Console\Kernel.php` file.
2. Add the following line to the `schedule` method:
   ```php
   $schedule->command('loans:deduct')->dailyAt('00:00');
   ```
3. Register the command in `App\Console\Commands\ProcessDeductions`.

---

## Database Schema

### Migrations
- **Loans**: Stores loan details.
- **Loan Deductions**: Records monthly deductions.
- **SMS Logs**: Stores SMS notification records.

### Relationships
- `User` has many `Loans`.
- `Loan` has many `Loan Deductions`.

---

## Testing

### Loan Application
Test the loan application endpoint using Postman.

**Request**:
```http
POST /api/loans/apply
```
**Expected Response**:
```json
{
  "message": "Loan application submitted successfully."
}
```

### Loan Limit Calculation
Test the loan limit endpoint:
```http
GET /api/loans/limit/{employee_id}
```

### Deduction Processing
Run the deduction command manually:
```bash
php artisan loans:deduct
```

---

## Contributions
Contributions are welcome. Please create a pull request or report issues on the GitHub repository.

---

## License
This project is licensed under the MIT License.
