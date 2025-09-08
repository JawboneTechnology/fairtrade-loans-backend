<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Services\GrantService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGrantRequest;
use App\Http\Requests\UpdateGrantRequest;
use App\Http\Requests\UpdateGrantStatusRequest;
use Illuminate\Http\JsonResponse;

class GrantController extends Controller
{
    protected $grantService;

    public function __construct(GrantService $grantService)
    {
        $this->grantService = $grantService;
    }

    // Admin endpoints
    public function index(): JsonResponse
    {
        try {
            $grants = $this->grantService->getAllGrants();
            return response()->json(["success" => true, "message" => "Grants fetched successfully", "data" => $grants]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function getGrantsForTable(): JsonResponse
    {
        try {
            return $this->grantService->getAllGrantsTable();
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
            $grant = $this->grantService->getGrantById($id);
            return response()->json(["success" => true, "message" => "Grant fetched successfully", "data" => $grant]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function approve(string $id, UpdateGrantStatusRequest $request): JsonResponse
    {
        try {
            $grant = $this->grantService->approveGrant($id, $request->admin_notes);
            if ($grant) {
                return response()->json(["success" => true, "message" => "Grant approved successfully", "data" => $grant]);
            } else {
                return response()->json(["success" => false, "message" => "Something went wrong", "data" => null]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function reject(string $id, UpdateGrantStatusRequest $request): JsonResponse
    {
        try {
            $grant = $this->grantService->rejectGrant($id, $request->admin_notes);
            if ($grant) {
                return response()->json(["success" => true, "message" => "Grant rejected successfully", "data" => $grant]);
            } else {
                return response()->json(["success" => false, "message" => "Something went wrong", "data" => null]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function cancelGrant(string $id, UpdateGrantStatusRequest $request): JsonResponse
    {
        try {
            $grant = $this->grantService->cancelGrant($id, $request->admin_notes);
            if ($grant) {
                return response()->json(["success" => true, "message" => "Grant cancelled successfully", "data" => $grant]);
            } else {
                return response()->json(["success" => false, "message" => "Something went wrong", "data" => null]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function markAsPaid(string $id): JsonResponse
    {
        try {
            $grant = $this->grantService->markAsPaid($id);
            if ($grant) {
                return response()->json(["success" => true, "message" => "Grant marked as paid successfully", "data" => $grant]);
            } else {
                return response()->json(["success" => false, "message" => "Something went wrong", "data" => null]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    // User endpoints
    public function userGrants(Request $request): JsonResponse
    {
        try {
            $grants = $this->grantService->getUserGrants($request->user()->id);
            return response()->json(["success" => true, "message" => "Grants fetched successfully", "data" => $grants]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function store(StoreGrantRequest $request): JsonResponse
    {
        try {
            $grantData = $request->validated();
            $grantData['user_id'] = $request->user()->id;
            $grant = $this->grantService->createGrant($grantData);
            return response()->json(["success" => true, "message" => "Grant created successfully", "data" => $grant], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function update(UpdateGrantRequest $request, string $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $grant = $this->grantService->updateGrant($id, $data);
            return response()->json(["success" => true, "message" => "Grant updated successfully", "data" => $grant]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $force = $request->query('force', false);
            $this->grantService->deleteGrant($id, $force);
            return response()->json(["success" => true, "message" => "Grant deleted successfully", "data" => null]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
