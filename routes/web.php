<?php

use App\Http\Controllers\LtiController;
use App\Http\Controllers\MoodleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::any('/', function () {
    return "Instancia no válida: No se ha lanzado correctamente "/*view('welcome')*/;
});

Route::any('/lti', [LtiController::class, 'saveSession']);
Route::get('/lti/jwks', [LtiController::class, 'getJWKS']);
Route::get('/lti/get_resource', [LtiController::class, 'getResource']);
Route::get('/lti/get_server_info', [LtiController::class, 'getServerInfo']);
Route::get('/lti/get_grade_module', [MoodleController::class, 'getGradeModule']);
