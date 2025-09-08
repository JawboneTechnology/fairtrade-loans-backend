<?php

namespace App\Interfaces;

use App\Models\Dependant;

interface DependantRepositoryInterface
{
    public function getUserDependents($userId);
    public function getDependentById($dependentId);
    public function createDependent(array $dependentDetails);
    public function updateDependent(Dependant $dependent, array $newDetails);
    public function deleteDependent($dependentId);
}
