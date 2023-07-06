<?php

use App\Http\Controllers\LtiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });




// Route::get('/verify', function (Illuminate\Http\Request $request) {
//     $token = $request->input('token');
//     if ($token === 'valor esperado') {
//         return response('Token válido');
//     } else {
//         return response('Token inválido', 401);
//     }
// });
Route::post('/lti/store_version', [LtiController::class, 'storeVersion']);
Route::post('/lti/export_version', [LtiController::class, 'exportVersion']);