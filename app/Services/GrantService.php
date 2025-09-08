<?php

namespace App\Services;

use App\Helpers\ValidationCallbackHelper;
use App\Models\Grant;
use App\Repositories\DependantRepository;
use App\Repositories\GrantRepository;
use App\Repositories\GrantTypeRepository;

class GrantService
{
    protected GrantRepository $grantRepository;
    protected DependantRepository $dependentRepository;
    protected GrantTypeRepository $grantTypeRepository;

    public function __construct(GrantRepository $grantRepository, DependantRepository $dependentRepository, GrantTypeRepository $grantTypeRepository) {
        $this->grantRepository = $grantRepository;
        $this->dependentRepository = $dependentRepository;
        $this->grantTypeRepository = $grantTypeRepository;
    }

    /**
     * @throws \Exception
     */
    public function getAllGrants()
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () {
            return $this->grantRepository->getAllGrants();
        }, "Error getting grants");
    }

    /**
     * @throws \Exception
     */
    public function getAllGrantsTable()
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () {
            return $this->grantRepository->getGrantsTable();
        }, "Error getting grants");
    }

    /**
     * @throws \Exception
     */
    public function getGrantById($grantId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantId) {
            return $this->grantRepository->getGrantById($grantId);
        }, "Error getting grant");
    }

    /**
     * @throws \Exception
     */
    public function getUserGrants($userId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($userId) {
            return $this->grantRepository->getUserGrants($userId);
        }, "Error getting user grants");
    }

    /**
     * @throws \Exception
     */
    public function createGrant(array $grantDetails)
    {
        return ValidationCallbackHelper::executeWithTransaction(function () use ($grantDetails) {
            if ($this->grantTypeRepository->requiresDependents($grantDetails['grant_type_id'])) {
                if (isset($grantDetails['dependent_id'])) {
                    $dependent = $this->dependentRepository->getDependentById($grantDetails['dependent_id']);
                    if ($dependent->user_id != $grantDetails['user_id']) {
                        throw new \Exception('Dependent does not belong to the user');
                    }
                }
            }

            if ($this->grantTypeRepository->amountExceedsLimit($grantDetails['grant_type_id'], $grantDetails['amount'])) {
                throw new \Exception('Requested amount exceeds maximum allowed.');
            }

            return $this->grantRepository->createGrant($grantDetails);
        }, "Error creating grant");

    }

    /**
     * @throws \Exception
     */
    public function updateGrant(string $grantId, array $newDetails)
    {
        return ValidationCallbackHelper::executeWithTransaction(function () use ($grantId, $newDetails) {
            $grant = $this->grantRepository->getGrantById($grantId);

            return $this->grantRepository->updateGrant($grant, $newDetails);
        }, "Error updating grant.");
    }

    /**
     * @throws \Exception
     */
    public function deleteGrant(string $grantId, $force = false)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantId, $force) {
            if ($force) {
                $grant = Grant::onlyTrashed()->find($grantId);
            } else {
                $grant = Grant::query()->find($grantId);
            }

            if (!$grant) {
                throw new \Exception('Grant not found');
            }

            return $this->grantRepository->deleteGrant($grant, $force);
        }, "Error deleting grant");
    }

    /**
     * @throws \Exception
     */
    public function approveGrant($grantId, $adminNotes = null)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantId, $adminNotes) {
            return $this->grantRepository->changeGrantStatus($grantId, 'approved', $adminNotes);
        }, "Error approving grant");
    }

    /**
     * @throws \Exception
     */
    public function rejectGrant($grantId, $adminNotes)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantId, $adminNotes) {
            return $this->grantRepository->changeGrantStatus($grantId, 'rejected', $adminNotes);
        }, "Error rejecting grant");
    }

    /**
     * @throws \Exception
     */
    public function cancelGrant($grantId, $adminNotes)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantId, $adminNotes) {
            return $this->grantRepository->changeGrantStatus($grantId, 'cancelled', $adminNotes);
        }, "Error canceling grant");
    }

    /**
     * @throws \Exception
     */
    public function markAsPaid($grantId)
    {
        return ValidationCallbackHelper::executeWithExceptionHandling(function () use ($grantId) {
            return $this->grantRepository->changeGrantStatus($grantId, 'paid');
        }, "Error marking grant as paid");
    }
}
