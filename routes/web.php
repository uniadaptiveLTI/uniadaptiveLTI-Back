<?php

use App\Http\Controllers\LtiController;
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

Route::get('/', function () {
    return "Instancia no válida: No se ha lanzado correctamente "/*view('welcome')*/;
});

Route::post('/lti/launch', [LtiController::class, 'saveSession']);
Route::get('/lti/get_session', [LtiController::class, 'getSession']);
Route::get('/lti/get_modules', [LtiController::class, 'getModules']);
Route::get('/lti/get_modules_by_type', [LtiController::class, 'getModulesByType']);
Route::get('/lti/jwks', [LtiController::class, 'getJWKS']);
