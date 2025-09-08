<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Dependant;
use Yajra\DataTables\DataTables;
use Illuminate\Database\Eloquent\Collection;
use App\Interfaces\DependantRepositoryInterface;

class DependantRepository implements DependantRepositoryInterface
{
    public function getUserDependents($userId): Collection
    {
        return Dependant::query()->where('user_id', $userId)->get();
    }

    public function getSystemDependents(): \Illuminate\Http\JsonResponse
    {
        $dependants = Dependant::all();
        return DataTables::of($dependants)->make(true);
    }

    public function getDependentById($dependentId)
    {
        return Dependant::query()->findOrFail($dependentId);
    }

    public function createDependent(array $dependentDetails): array
    {
        return $this->create($dependentDetails);
    }

    public function updateDependent(Dependant $dependent, array $newDetails): Dependant
    {
        $dependent->update($newDetails);
        return $dependent;
    }

    public function deleteDependent($dependentId): int
    {
        return Dependant::destroy($dependentId);
    }

    protected function create(array $dependantDetails): array
    {
        $user = User::query()->find($dependantDetails['user_id']);

        $createdCount = 0;
        $dependents = [];

        foreach ($dependantDetails['dependants'] as $dep) {
            $dependant = $user->dependants()->create([
                'user_id'       => $user->id,
                'first_name'    => $dep['first_name'],
                'last_name'     => $dep['last_name'],
                'phone'         => $dep['phone'],
                'email'         => $dep['email'],
                'gender'        => $dep['gender'],
                'date_of_birth' => $dep['date_of_birth'],
                'relationship'  => $dep['relationship'],
            ]);

            $createdCount++;
            $dependents[] = $dependant;
        }

        return [
            'createdCount' => $createdCount,
            'dependants' => $dependents,
        ];
    }
}
