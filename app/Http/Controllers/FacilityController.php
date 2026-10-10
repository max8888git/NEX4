<?php

namespace App\Http\Controllers;

use App\Services\DomainCatalog;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function handle(Request $request)
    {
        return DomainCatalog::respond($request, 'run_facilities_request');
    }
}
