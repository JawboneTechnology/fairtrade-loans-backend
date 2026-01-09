<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGlobalLoanSettingsRequest;
use App\Http\Requests\UpdateLoanLimitCalculationRequest;
use App\Http\Requests\UpdatePaymentSettingsRequest;
use App\Http\Requests\UpdateApprovalWorkflowRequest;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Http\Requests\UpdateLoanTypeRequest;
use App\Http\Requests\CreateLoanTypeRequest;
use App\Services\LoanSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoanSettingsController extends Controller
{
    protected LoanSettingsService $loanSettingsService;

    public function __construct(LoanSettingsService $loanSettingsService)
    {
        $this->loanSettingsService = $loanSettingsService;
    }

    /**
     * Get global loan settings
     */
    public function getGlobalSettings(): JsonResponse
    {
        try {
            $settings = $this->loanSettingsService->getGlobalSettings();

            return response()->json([
                'success' => true,
                'message' => 'Global loan settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving global loan settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve global loan settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update global loan settings
     */
    public function updateGlobalSettings(UpdateGlobalLoanSettingsRequest $request): JsonResponse
    {
        try {
            $setting = $this->loanSettingsService->updateGlobalSettings($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Global loan settings updated successfully',
                'data' => [
                    'id' => $setting->id,
                    'updated_at' => $setting->updated_at,
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating global loan settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update global loan settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get loan limit calculation settings
     */
    public function getLimitCalculationSettings(): JsonResponse
    {
        try {
            $settings = $this->loanSettingsService->getLimitCalculationSettings();

            return response()->json([
                'success' => true,
                'message' => 'Loan limit calculation settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving limit calculation settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve limit calculation settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update loan limit calculation settings
     */
    public function updateLimitCalculationSettings(UpdateLoanLimitCalculationRequest $request): JsonResponse
    {
        try {
            $setting = $this->loanSettingsService->updateLimitCalculationSettings($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan limit calculation settings updated successfully',
                'data' => [
                    'updated_at' => $setting->updated_at,
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating limit calculation settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update limit calculation settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get payment settings
     */
    public function getPaymentSettings(): JsonResponse
    {
        try {
            $settings = $this->loanSettingsService->getPaymentSettings();

            return response()->json([
                'success' => true,
                'message' => 'Payment settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving payment settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update payment settings
     */
    public function updatePaymentSettings(UpdatePaymentSettingsRequest $request): JsonResponse
    {
        try {
            $setting = $this->loanSettingsService->updatePaymentSettings($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Payment settings updated successfully',
                'data' => [
                    'updated_at' => $setting->updated_at,
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating payment settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get approval workflow settings
     */
    public function getApprovalWorkflowSettings(): JsonResponse
    {
        try {
            $settings = $this->loanSettingsService->getApprovalWorkflowSettings();

            return response()->json([
                'success' => true,
                'message' => 'Approval workflow settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving approval workflow settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve approval workflow settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update approval workflow settings
     */
    public function updateApprovalWorkflowSettings(UpdateApprovalWorkflowRequest $request): JsonResponse
    {
        try {
            $setting = $this->loanSettingsService->updateApprovalWorkflowSettings($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Approval workflow settings updated successfully',
                'data' => [
                    'updated_at' => $setting->updated_at,
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating approval workflow settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update approval workflow settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings(): JsonResponse
    {
        try {
            $settings = $this->loanSettingsService->getNotificationSettings();

            return response()->json([
                'success' => true,
                'message' => 'Notification settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving notification settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        try {
            $setting = $this->loanSettingsService->updateNotificationSettings($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => [
                    'updated_at' => $setting->updated_at,
                ],
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error updating notification settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get all loan types with pagination and filters
     */
    public function getLoanTypes(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'per_page' => $request->input('per_page', 15),
            ];

            $result = $this->loanSettingsService->getLoanTypes($filters);

            return response()->json([
                'success' => true,
                'message' => 'Loan types retrieved successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving loan types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan types',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get single loan type
     */
    public function getLoanType(string $id): JsonResponse
    {
        try {
            $loanType = $this->loanSettingsService->getLoanType($id);

            return response()->json([
                'success' => true,
                'message' => 'Loan type retrieved successfully',
                'data' => $loanType,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Loan type not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving loan type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan type',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Create loan type
     */
    public function createLoanType(CreateLoanTypeRequest $request): JsonResponse
    {
        try {
            $loanType = $this->loanSettingsService->createLoanType($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan type created successfully',
                'data' => $loanType,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating loan type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create loan type',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update loan type
     */
    public function updateLoanType(UpdateLoanTypeRequest $request, string $id): JsonResponse
    {
        try {
            $loanType = $this->loanSettingsService->updateLoanType($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Loan type updated successfully',
                'data' => $loanType,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Loan type not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating loan type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update loan type',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Delete loan type
     */
    public function deleteLoanType(string $id): JsonResponse
    {
        try {
            $this->loanSettingsService->deleteLoanType($id);

            return response()->json([
                'success' => true,
                'message' => 'Loan type deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Loan type not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting loan type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }
}

