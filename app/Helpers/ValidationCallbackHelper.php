<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class ValidationCallbackHelper
{
    public static function executeWithTransaction(callable $callback, string $errorMessage)
    {
        try {
            DB::beginTransaction();
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("$errorMessage: " . $e->getMessage());
        }
    }

    public static function executeWithExceptionHandling(callable $callback, string $errorMessage)
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            throw new \Exception("$errorMessage: " . $e->getMessage());
        }
    }
}
