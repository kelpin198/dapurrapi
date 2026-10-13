<?php

namespace App\Http\Controllers;

use Throwable;

abstract class Controller
{
    protected function serverError(Throwable $exception)
    {
        report($exception);

        $isAppCode = str_contains($exception->getFile(), base_path('app'));

        return response()->json([
            'status' => false,
            'message' => 'Terjadi kesalahan pada server',
            'error' => $isAppCode ? [
                'file' => str_replace(base_path(), '', $exception->getFile()),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
            ] : null,
        ], 500);
    }
}
