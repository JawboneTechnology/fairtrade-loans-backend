<?php

namespace App\Helpers;

use App\Models\GrantType;

class GrantHelper
{
    private static int $lastGeneratedNumber = 0;

    public static function generateGrantCode(): string
    {
        // Get the highest existing code from database
        $lastGrantType = GrantType::query()->orderBy('grant_code', 'desc')->first();

        $lastNumber = 0;

        if ($lastGrantType && $lastGrantType->grant_code) {
            $lastNumber = (int) substr($lastGrantType->grant_code, 1);
        }

        // Use the higher of either database value or in-memory counter
        $newNumber = max($lastNumber, self::$lastGeneratedNumber) + 1;
        self::$lastGeneratedNumber = $newNumber;

        return 'G' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public static function resetCounter(): void
    {
        self::$lastGeneratedNumber = 0;
    }
}
