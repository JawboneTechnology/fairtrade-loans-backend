<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDependentRequest;
use App\Http\Requests\UpdateDependentRequest;
use App\Services\DependantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DependentController extends Controller
{
    protected DependantService $dependentService;

    public function __construct(DependantService $dependentService)
    {
        $this->dependentService = $dependentService;
    }

    public function index(): JsonResponse
    {
        try {
            return $this->dependentService->getSystemDependents();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function getUsersDependents(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->id;
            $dependents = $this->dependentService->getUserDependents($userId);
            return response()->json([ "success" => true, "message" => "User dependants fetched successfully.", 'total_dependents' => count($dependents), 'data' => $dependents]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function store(StoreDependentRequest $request): JsonResponse
    {
        try {
            $dependentData = $request->validated();
            $dependent = $this->dependentService->createDependent($dependentData);
            return response()->json(["success" => true, "message" => "Dependants created successfully.", 'data' => $dependent], 201);
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
            $dependent = $this->dependentService->getDependentById($id);
            return response()->json(["success" => true, "message" => "Dependant fetched successfully.", 'data' => $dependent]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function update(UpdateDependentRequest $request, string $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $dependent = $this->dependentService->updateDependent($id, $data);
            return response()->json(["success" => true, "message" => "Dependants updated successfully.", 'data' => $dependent]);
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
            $this->dependentService->deleteDependent($id);
            return response()->json(["success" => true, "message" => "Dependant deleted successfully", "data" => null]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
