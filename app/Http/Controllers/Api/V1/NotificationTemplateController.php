<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateNotificationTemplateRequest;
use App\Http\Requests\UpdateNotificationTemplateRequest;
use App\Http\Requests\PreviewNotificationTemplateRequest;
use App\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationTemplateController extends Controller
{
    protected NotificationTemplateService $templateService;

    public function __construct(NotificationTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Get all notification templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'channel' => $request->input('channel'),
                'per_page' => $request->input('per_page', 15),
            ];

            $result = $this->templateService->getTemplates($filters);

            return response()->json([
                'success' => true,
                'message' => 'Notification templates retrieved successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving notification templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification templates',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get single notification template
     */
    public function show(string $id): JsonResponse
    {
        try {
            $template = $this->templateService->getTemplate($id);

            // Decode channels if it's JSON
            if (is_string($template->channels)) {
                $template->channels = json_decode($template->channels, true);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification template retrieved successfully',
                'data' => $template,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving notification template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get template by type
     */
    public function getByType(string $type): JsonResponse
    {
        try {
            $template = $this->templateService->getTemplateByType($type);

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification template not found',
                    'data' => null,
                ], 404);
            }

            // Decode channels if it's JSON
            if (is_string($template->channels)) {
                $template->channels = json_decode($template->channels, true);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification template retrieved successfully',
                'data' => $template,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving notification template by type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Create notification template
     */
    public function store(CreateNotificationTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templateService->createTemplate($request->validated());

            // Decode channels if it's JSON
            if (is_string($template->channels)) {
                $template->channels = json_decode($template->channels, true);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating notification template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update notification template
     */
    public function update(UpdateNotificationTemplateRequest $request, string $id): JsonResponse
    {
        try {
            $template = $this->templateService->updateTemplate($id, $request->validated());

            // Decode channels if it's JSON
            if (is_string($template->channels)) {
                $template->channels = json_decode($template->channels, true);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification template updated successfully',
                'data' => $template,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating notification template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Delete notification template
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->templateService->deleteTemplate($id);

            return response()->json([
                'success' => true,
                'message' => 'Notification template deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting notification template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Preview template with sample data
     */
    public function preview(PreviewNotificationTemplateRequest $request, string $id): JsonResponse
    {
        try {
            $preview = $this->templateService->previewTemplate($id, $request->input('sample_data'));

            return response()->json([
                'success' => true,
                'message' => 'Template preview generated successfully',
                'data' => $preview,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error previewing notification template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template preview',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get available notification types
     */
    public function getTypes(): JsonResponse
    {
        try {
            $types = $this->templateService->getAvailableTypes();

            return response()->json([
                'success' => true,
                'message' => 'Notification types retrieved successfully',
                'data' => $types,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving notification types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification types',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}

