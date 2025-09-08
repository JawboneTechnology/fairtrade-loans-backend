<?php

namespace App\Services;

use App\Helpers\ValidationCallbackHelper;
use App\Models\Dependant;
use App\Repositories\DependantRepository;

class DependantService
{
    protected DependantRepository $dependentRepository;

    public function __construct(DependantRepository $dependentRepository)
    {
        $this->dependentRepository = $dependentRepository;
    }

    /**
     * @throws \Exception
     */
    public function getUserDependents($userId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($userId) {
            return $this->dependentRepository->getUserDependents($userId);
        }, "Error getting user dependants.");

    }

    /**
     * @throws \Exception
     */
    public function getSystemDependents()
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () {
            return $this->dependentRepository->getSystemDependents();
        }, "Error getting system dependants.");
    }

    /**
     * @throws \Exception
     */
    public function getDependentById($dependentId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($dependentId) {
            return $this->dependentRepository->getDependentById($dependentId);
        }, "Error getting dependant with id {$dependentId}.");
    }

    /**
     * @throws \Exception
     */
    public function createDependent(array $dependentDetails)
    {
        return ValidationCallbackHelper::executeWithTransaction(function () use ($dependentDetails) {
            return $this->dependentRepository->createDependent($dependentDetails);
        }, "Error creating dependants.");
    }

    /**
     * @throws \Exception
     */
    public function updateDependent(string $dependentId, array $newDetails)
    {
        return ValidationCallbackHelper::executeWithTransaction(function () use ($dependentId, $newDetails) {
            $dependent = Dependant::query()->find($dependentId);

            if (!$dependent) {
                throw new \Exception("Dependant not found.");
            }

            return $this->dependentRepository->updateDependent($dependent, $newDetails);
        }, "Error updating dependant with id {$dependentId}.");
    }

    /**
     * @throws \Exception
     */
    public function deleteDependent($dependentId)
    {
        return ValidationCallbackHelper::executeWithTransaction(function () use ($dependentId) {
            return $this->dependentRepository->deleteDependent($dependentId);
        }, "Error deleting dependant with id {$dependentId}.");
    }
}
