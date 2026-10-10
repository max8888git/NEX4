<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FacultyLoadingController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SectionController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], 'auth.php', [AuthController::class, 'handle']);
Route::get('health.php', [HealthController::class, 'handle']);
Route::get('logs.php', [LogsController::class, 'handle']);
Route::match(['GET', 'POST', 'PUT'], 'org.php', [OrgController::class, 'handle']);
Route::match(['GET', 'POST', 'PATCH', 'DELETE'], 'sections.php', [SectionController::class, 'handle']);
Route::match(['GET', 'POST', 'PUT'], 'facilities.php', [FacilityController::class, 'handle']);
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'curricula.php', [CurriculumController::class, 'handle']);
Route::match(['GET', 'POST'], 'schedule.php', [ScheduleController::class, 'handle']);
Route::match(['GET', 'POST'], 'requests.php', [RequestController::class, 'handle']);
Route::match(['GET', 'POST'], 'faculty-loading.php', [FacultyLoadingController::class, 'handle']);
Route::match(['GET', 'POST'], 'users.php', [AccountController::class, 'handle']);
Route::match(['GET', 'POST', 'PUT', 'PATCH'], 'room-types.php', [RoomTypeController::class, 'handle']);
Route::match(['GET', 'POST'], 'notifications.php', [NotificationController::class, 'handle']);
