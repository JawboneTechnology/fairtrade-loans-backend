<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Role;

class SystemController extends Controller
{
    // Get System Roles
    public function getRoles(): JsonResponse
    {
        try {
            $roles = Role::all();

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $roles
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching roles',
                'error' => $exception->getMessage()
            ], 500);
        }
    }
}
