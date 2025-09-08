<?php

namespace App\Interfaces;

interface GrantTypeRepositoryInterface
{
    public function getAllGrantTypes($activeOnly = true);
    public function getGrantTypeById($grantTypeId);
    public function createGrantType(array $grantTypeDetails);
    public function updateGrantType($grantTypeId, array $newDetails);
    public function deleteGrantType($grantTypeId);
    public function restoreGrantType($grantTypeId);
    public function requiresDependents(string $grantTypeId);
    public function amountExceedsLimit(string $grantTypeId, $amount);
}
