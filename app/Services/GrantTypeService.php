<?php

namespace App\Services;

use App\Helpers\ValidationCallbackHelper;
use App\Repositories\GrantTypeRepository;

class GrantTypeService
{
    protected GrantTypeRepository $grantTypeRepository;

    public function __construct(GrantTypeRepository $grantTypeRepository)
    {
        $this->grantTypeRepository = $grantTypeRepository;
    }

    /**
     * @throws \Exception
     */
    public function getAllGrantTypes($activeOnly = true)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($activeOnly) {
            return $this->grantTypeRepository->getAllGrantTypes($activeOnly);
        }, "Error getting grantType list.");
    }

    /**
     * @throws \Exception
     */
    public function getGrantTypeById($grantTypeId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantTypeId) {
            return $this->grantTypeRepository->getGrantTypeById($grantTypeId);
        }, "Error getting grant type.");
    }

    /**
     * @throws \Exception
     */
    public function createGrantType(array $grantTypeDetails)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantTypeDetails) {
            return $this->grantTypeRepository->createGrantType($grantTypeDetails);
        }, "Error adding grant type.");
    }

    /**
     * @throws \Exception
     */
    public function updateGrantType($grantTypeId, array $newDetails)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantTypeId, $newDetails) {
            return $this->grantTypeRepository->updateGrantType($grantTypeId, $newDetails);
        }, "Error updating grant type.");
    }

    /**
     * @throws \Exception
     */
    public function deleteGrantType($grantTypeId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantTypeId) {
            return $this->grantTypeRepository->deleteGrantType($grantTypeId);
        }, "Error deleting grant type.");
    }

    /**
     * @throws \Exception
     */
    public function restoreGrantType($grantTypeId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantTypeId) {
            return $this->grantTypeRepository->restoreGrantType($grantTypeId);
        }, "Error restoring grant type.");
    }

    /**
     * @throws \Exception
     */
    public function getGrantTypesForDropdown()
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () {
            return $this->grantTypeRepository->getAllGrantTypes()
                ->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'max_amount' => $type->max_amount,
                        'requires_dependent' => $type->requires_dependent
                    ];
                });
        }, "Error getting grant types for dropdown.");
    }
}
