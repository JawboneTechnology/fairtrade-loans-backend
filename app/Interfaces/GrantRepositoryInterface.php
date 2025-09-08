<?php

namespace App\Interfaces;

use App\Models\Grant;

interface GrantRepositoryInterface
{
    public function getAllGrants();
    public function getGrantsTable();
    public function getGrantById($grantId);
    public function getUserGrants($userId);
    public function createGrant(array $grantDetails);
    public function updateGrant($grant, array $newDetails);
    public function deleteGrant(Grant $grant, bool $force);
    public function changeGrantStatus($grantId, $status, $adminNotes = null);
    public function findOrFailGrant($grantId);
}
