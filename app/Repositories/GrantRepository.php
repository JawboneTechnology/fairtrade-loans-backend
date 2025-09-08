<?php

namespace App\Repositories;

use App\Models\Grant;
use App\Events\GrantApplied;
use App\Events\GrantApproved;
use App\Interfaces\GrantRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;

class GrantRepository implements GrantRepositoryInterface
{
    public function getAllGrants(): Collection
    {
        return Grant::query()->with(['user', 'dependent'])->get();
    }

    public function getGrantsTable(): JsonResponse
    {
        $grants = Grant::query()->get();
        return DataTables::of($grants)->make(true);
    }

    public function getGrantById($grantId): Grant
    {
        return Grant::query()
        ->with([
            'user' => function($query) {
                $query->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'employee_id');
            },
            'dependent' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'phone', 'relationship', 'gender');
            },
            'grantType' => function($query) {
                $query->select('id', 'grant_code', 'name', 'description', 'max_amount');
            }
        ])
        ->findOrFail($grantId);
    }

    public function getUserGrants($userId): Collection
    {
        return Grant::query()->with('dependent')->where('user_id', $userId)->get();
    }

    public function createGrant(array $grantDetails): Grant
    {
        $grant = Grant::query()->create($grantDetails);

        GrantApplied::dispatch($grant);

        return $grant;
    }

    public function updateGrant($grant, array $newDetails): Grant
    {
        $grant->update($newDetails);
        return $grant;
    }

    public function deleteGrant(Grant $grant, bool $force): Grant
    {
        if ($force) {
            $grant->forceDelete();
        } else {
            $grant->delete();
        }

        return $grant;
    }

    public function changeGrantStatus($grantId, $status, $adminNotes = null): bool
    {
        $grant = Grant::query()->find($grantId);

        if (!$grant) {
            return false;
        }

        // Validate status if needed
        // if (!in_array($status, ['approved', 'paid', ...])) {
        //     return false;
        // }

        $updateData = ['status' => $status];

        if ($status === 'approved') {
            $updateData['approval_date'] = now();
        } elseif ($status === 'paid') {
            $updateData['disbursement_date'] = now();
        } elseif ($status === 'cancelled') {
            $updateData['cancelled_date'] = now();
        }

        if ($adminNotes) {
            $updateData['admin_notes'] = $adminNotes;
        }

        $success = $grant->update($updateData);

        if ($success && $status === 'approved') {
            GrantApproved::dispatch($grant->fresh());
        }

        return $success;
    }

    public function findOrFailGrant($grantId): Grant
    {
        return Grant::query()->with(["user", "dependent", "grantType"])->findOrFail($grantId);
    }
}
