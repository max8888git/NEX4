<?php

namespace App\Http\Controllers;

use App\Models\College;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthController extends Controller
{
    public function handle()
    {
        try {
            $installed = Schema::hasTable((new College)->getTable());

            return response()->json([
                'ok' => true,
                'configured' => true,
                'installed' => $installed,
                'database' => (string) config('database.connections.mysql.database'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => true,
                'configured' => true,
                'installed' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
