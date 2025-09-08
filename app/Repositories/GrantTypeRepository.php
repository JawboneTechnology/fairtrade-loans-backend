<?php

namespace App\Repositories;

use App\Models\GrantType;
use Illuminate\Database\Eloquent\Collection;
use App\Interfaces\GrantTypeRepositoryInterface;

class GrantTypeRepository implements GrantTypeRepositoryInterface
{
    public function getAllGrantTypes($activeOnly = true): Collection
    {
        $query = GrantType::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function getGrantTypeById($grantTypeId)
    {
        return GrantType::withTrashed()->findOrFail($grantTypeId);
    }

    public function createGrantType(array $grantTypeDetails): GrantType
    {
        return GrantType::query()->create($grantTypeDetails);
    }

    public function updateGrantType($grantTypeId, array $newDetails)
    {
        $grantType = GrantType::query()->where('id', $grantTypeId)->first();
        $grantType->update($newDetails);
        return $grantType;
    }

    public function deleteGrantType($grantTypeId)
    {
        $grantType = GrantType::query()->find($grantTypeId);
        $grantType->delete();
        return $grantType;
    }

    public function restoreGrantType($grantTypeId)
    {
        $grantType = GrantType::withTrashed()->find($grantTypeId);
        $grantType->restore();
        return $grantType;
    }

    public function requiresDependents(string $grantTypeId): bool
    {
        $grantType = GrantType::withTrashed()->find($grantTypeId);
        return $grantType->requires_dependents === 1;
    }

    public function amountExceedsLimit(string $grantTypeId, $amount): bool
    {
        $grantType = GrantType::withTrashed()->find($grantTypeId);

        if (!$grantType) {
            return false;
        }

        if (is_null($grantType->max_amount)) {
            return false;
        }

        return $amount > $grantType->max_amount;
    }
}
