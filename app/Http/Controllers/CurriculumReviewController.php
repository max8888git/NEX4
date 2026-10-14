<?php

namespace App\Http\Controllers;

use App\Services\DomainCatalog;
use Illuminate\Http\Request;

/** Curriculums made by Deans and Program Chairs and reviewed by the registrars (legacy-api/curriculum-reviews.php). */
class CurriculumReviewController extends Controller
{
    public function handle(Request $request)
    {
        return DomainCatalog::respond($request, 'run_curriculum_reviews_request');
    }
}
