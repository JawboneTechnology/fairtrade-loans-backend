<?php

namespace App\Services;

use App\Models\User;

class USSDService
{
    public function processUSSDRequest($sessionId, $phoneNumber, $text)
    {
        $user = $this->getUserByPhone($phoneNumber);

        if (!$user) {
            return $this->handleRegistration($text, $phoneNumber);
        }

        return $this->handleMainMenu($text, $user);
    }

    private function getUserByPhone($phoneNumber)
    {
        return User::where('phone', $phoneNumber)->first();
    }

    private function handleRegistration($text, $phoneNumber)
    {
        $inputs = explode('*', $text);
        $level = count($inputs);

        if ($text == "") {
            return "CON Welcome to Loan Services. You need to register first:\n1. Register";
        } elseif ($inputs[0] == "1" && $level == 1) {
            return "CON Enter your Full Name:";
        } elseif ($inputs[0] == "1" && $level == 2) {
            return "CON Enter your Employee ID:";
        } elseif ($inputs[0] == "1" && $level == 3) {
            return "CON Enter your Monthly Salary:";
        } elseif ($inputs[0] == "1" && $level == 4) {
            // Save User Registration
            $name = $inputs[1];
            $employeeId = $inputs[2];
            $salary = $inputs[3];

            User::create([
                'phone' => $phoneNumber,
                'name' => $name,
                'employee_id' => $employeeId,
                'salary' => $salary,
                'role' => 'employee',
            ]);

            return "END Registration successful! You can now apply for loans.";
        }

        return "END Invalid input. Try again.";
    }

    private function handleMainMenu($text, $user)
    {
        $inputs = explode('*', $text);
        $level = count($inputs);

        if ($text == "") {
            return "CON Welcome back, {$user->name}:\n1. Apply for a Loan\n2. Check Loan Status\n3. Repayment Information";
        } elseif ($inputs[0] == "1") {
            return $this->handleLoanApplication($inputs, $user);
        } elseif ($inputs[0] == "2") {
            return $this->checkLoanStatus($user);
        } elseif ($inputs[0] == "3") {
            return $this->getRepaymentInfo($user);
        }

        return "END Invalid option. Try again.";
    }

    private function handleLoanApplication($inputs, $user)
    {
        $level = count($inputs);

        if ($level == 1) {
            return "CON Enter Loan Amount:";
        } elseif ($level == 2) {
            $amount = $inputs[1];

            if ($amount > ($user->salary * 0.5)) {
                return "END Loan request denied. You can only borrow up to 50% of your salary.";
            }

            return "CON You entered KES $amount. Confirm?\n1. Yes\n2. No";
        } elseif ($level == 3 && $inputs[2] == "1") {
            $amount = $inputs[1];
            // Logic to apply for the loan
            return "END Your loan application for KES $amount has been submitted.";
        }

        return "END Invalid input. Try again.";
    }

    private function checkLoanStatus($user)
    {
        // Check loan status logic
        return "END Your loan status: Approved. Balance: KES 2,000.";
    }

    private function getRepaymentInfo($user)
    {
        // Get repayment info logic
        return "END Repayment due: KES 500 on 30th Dec 2024.";
    }
}
