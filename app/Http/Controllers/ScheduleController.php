<?php

namespace App\Http\Controllers;

use App\Services\DomainCatalog;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function handle(Request $request)
    {
        return DomainCatalog::respond($request, 'run_schedule_request');
    }
}
