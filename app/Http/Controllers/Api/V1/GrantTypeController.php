<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGrantTypeRequest;
use App\Http\Requests\UpdateGrantTypeRequest;
use App\Services\GrantTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GrantTypeController extends Controller
{
    protected GrantTypeService $grantTypeService;

    public function __construct(GrantTypeService $grantTypeService)
    {
        $this->grantTypeService = $grantTypeService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $activeOnly = $request->has('all') ? false : true;
            $grantTypes = $this->grantTypeService->getAllGrantTypes($activeOnly);
            return response()->json([ "success" => true, "message" => "Grant types fetched successfully.", 'data' => $grantTypes]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
    }

    public function store(StoreGrantTypeRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $grantType = $this->grantTypeService->createGrantType($data);
            return response()->json(["success" => true, "message" => "Grant type created successfully.", 'data' => $grantType]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $grantType = $this->grantTypeService->getGrantTypeById($id);
            return response()->json(["success" => true, "message" => "Grant type retrieved.", 'data' => $grantType]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function updateGrantType(UpdateGrantTypeRequest $request, string $id): JsonResponse
    {
        try {
            $grantType = $this->grantTypeService->updateGrantType($id, $request->validated());
            return response()->json(["success" => true, "message" => "Grant type updated successfully.", 'data' => $grantType]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $data = $this->grantTypeService->deleteGrantType($id);
            return response()->json(["success" => true, "message" => "Grant type deleted successfully", "data" => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function restore($id): JsonResponse
    {
        try {
            $data = $this->grantTypeService->restoreGrantType($id);
            return response()->json(['success' => true, 'message' => 'Grant type restored', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null]);
        }
    }

    public function dropdownOptions(): JsonResponse
    {
        try {
            $options = $this->grantTypeService->getGrantTypesForDropdown();
            return response()->json(['success' => true, 'message' => 'Grant types fetched successfully', 'data' => $options]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null]);
        }
    }
}
