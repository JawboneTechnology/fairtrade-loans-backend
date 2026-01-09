<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSmsTemplateRequest;
use App\Http\Requests\UpdateSmsTemplateRequest;
use App\Http\Requests\PreviewSmsTemplateRequest;
use App\Services\SmsTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsTemplateController extends Controller
{
    protected SmsTemplateService $templateService;

    public function __construct(SmsTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Get all SMS templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'is_active' => $request->input('is_active'),
                'per_page' => $request->input('per_page', 15),
            ];

            $result = $this->templateService->getTemplates($filters);

            return response()->json([
                'success' => true,
                'message' => 'SMS templates retrieved successfully',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving SMS templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS templates',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get single SMS template
     */
    public function show(string $id): JsonResponse
    {
        try {
            $template = $this->templateService->getTemplate($id);

            return response()->json([
                'success' => true,
                'message' => 'SMS template retrieved successfully',
                'data' => $template,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'SMS template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving SMS template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS template',
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
                    'message' => 'SMS template not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'SMS template retrieved successfully',
                'data' => $template,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving SMS template by type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve SMS template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Create SMS template
     */
    public function store(CreateSmsTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templateService->createTemplate($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'SMS template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating SMS template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create SMS template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update SMS template
     */
    public function update(UpdateSmsTemplateRequest $request, string $id): JsonResponse
    {
        try {
            $template = $this->templateService->updateTemplate($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'SMS template updated successfully',
                'data' => $template,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'SMS template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating SMS template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Delete SMS template
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->templateService->deleteTemplate($id);

            return response()->json([
                'success' => true,
                'message' => 'SMS template deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'SMS template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting SMS template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete SMS template',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Preview template with sample data
     */
    public function preview(PreviewSmsTemplateRequest $request, string $id): JsonResponse
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
                'message' => 'SMS template not found',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error previewing SMS template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template preview',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get available template types
     */
    public function getTypes(): JsonResponse
    {
        try {
            $types = $this->templateService->getAvailableTypes();

            return response()->json([
                'success' => true,
                'message' => 'Template types retrieved successfully',
                'data' => $types,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving template types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template types',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
