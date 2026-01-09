<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Log;

class NotificationTemplateService
{
    /**
     * Get all notification templates with pagination and filters
     */
    public function getTemplates(array $filters = []): array
    {
        $query = NotificationTemplate::query();

        // Search by type or title
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('type', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('title', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Filter by channel
        if (isset($filters['channel']) && !empty($filters['channel'])) {
            $query->whereJsonContains('channels', $filters['channel']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $templates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'current_page' => $templates->currentPage(),
            'data' => $templates->items(),
            'total' => $templates->total(),
            'per_page' => $templates->perPage(),
            'last_page' => $templates->lastPage(),
        ];
    }

    /**
     * Get single notification template
     */
    public function getTemplate(string $id): NotificationTemplate
    {
        return NotificationTemplate::findOrFail($id);
    }

    /**
     * Get template by type
     */
    public function getTemplateByType(string $type): ?NotificationTemplate
    {
        return NotificationTemplate::where('type', $type)->first();
    }

    /**
     * Create notification template
     */
    public function createTemplate(array $data): NotificationTemplate
    {
        // Ensure channels is JSON encoded if it's an array
        if (isset($data['channels']) && is_array($data['channels'])) {
            $data['channels'] = json_encode($data['channels']);
        }

        return NotificationTemplate::create($data);
    }

    /**
     * Update notification template
     */
    public function updateTemplate(string $id, array $data): NotificationTemplate
    {
        $template = NotificationTemplate::findOrFail($id);

        // Ensure channels is JSON encoded if it's an array
        if (isset($data['channels']) && is_array($data['channels'])) {
            $data['channels'] = json_encode($data['channels']);
        }

        $template->update($data);
        return $template->fresh();
    }

    /**
     * Delete notification template
     */
    public function deleteTemplate(string $id): bool
    {
        $template = NotificationTemplate::findOrFail($id);
        return $template->delete();
    }

    /**
     * Preview template with sample data
     */
    public function previewTemplate(string $id, array $sampleData): array
    {
        $template = NotificationTemplate::findOrFail($id);
        
        $parsedMessage = $this->parseTemplate($template->message, $sampleData);
        
        return [
            'title' => $template->title,
            'message' => $template->message,
            'parsed_message' => $parsedMessage,
        ];
    }

    /**
     * Parse template with data
     */
    private function parseTemplate(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            return $data[$matches[1]] ?? $matches[0];
        }, $template);
    }

    /**
     * Get available notification types with descriptions
     */
    public function getAvailableTypes(): array
    {
        return [
            [
                'type' => 'loan_approved',
                'title' => 'Loan Approved',
                'description' => 'Sent when a loan application is approved',
                'available_variables' => ['loan_number', 'amount', 'approved_amount', 'applicant_name']
            ],
            [
                'type' => 'loan_rejected',
                'title' => 'Loan Rejected',
                'description' => 'Sent when a loan application is rejected',
                'available_variables' => ['loan_number', 'amount', 'remarks', 'applicant_name']
            ],
            [
                'type' => 'loan_application_submitted',
                'title' => 'Loan Application Submitted',
                'description' => 'Sent when a loan application is submitted',
                'available_variables' => ['loan_number', 'amount', 'applicant_name']
            ],
            [
                'type' => 'loan_paid',
                'title' => 'Loan Fully Paid',
                'description' => 'Sent when a loan is fully paid',
                'available_variables' => ['loan_number', 'amount']
            ],
            [
                'type' => 'loan_canceled',
                'title' => 'Loan Canceled',
                'description' => 'Sent when a loan application is canceled',
                'available_variables' => ['loan_number', 'amount']
            ],
            [
                'type' => 'guarantor_request',
                'title' => 'Loan Guarantee Request',
                'description' => 'Sent when a user is requested to guarantee a loan',
                'available_variables' => ['amount', 'applicant_name', 'loan_number']
            ],
            [
                'type' => 'guarantor_acceptance',
                'title' => 'Loan Guarantee Accepted',
                'description' => 'Sent when all guarantors have accepted',
                'available_variables' => ['loan_number', 'amount', 'applicant_name']
            ],
            [
                'type' => 'guarantor_rejection',
                'title' => 'Loan Guarantee Rejected',
                'description' => 'Sent when a guarantor rejects',
                'available_variables' => ['loan_number', 'amount', 'guarantor_name']
            ],
            [
                'type' => 'payment_received',
                'title' => 'Payment Received',
                'description' => 'Sent when a payment is received',
                'available_variables' => ['amount', 'loan_number', 'new_balance']
            ],
            [
                'type' => 'deduction_processed',
                'title' => 'Deduction Processed',
                'description' => 'Sent when a deduction is processed',
                'available_variables' => ['amount', 'loan_number', 'new_balance']
            ],
            [
                'type' => 'grant_approved',
                'title' => 'Grant Approved',
                'description' => 'Sent when a grant is approved',
                'available_variables' => ['grant_number', 'amount', 'applicant_name']
            ],
            [
                'type' => 'grant_rejected',
                'title' => 'Grant Rejected',
                'description' => 'Sent when a grant is rejected',
                'available_variables' => ['grant_number', 'amount', 'remarks']
            ],
        ];
    }
}

