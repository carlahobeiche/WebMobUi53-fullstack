<?php

use App\Http\Controllers\Api\v1\ApiPostController;
use App\Http\Controllers\Api\v1\ApiFooController;
use App\Http\Controllers\Api\v1\ApiPollController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('v1/posts', ApiPostController::class)
    ->middlewareFor(['index', 'show'], ['auth:sanctum', 'abilities:posts:read'])
    ->middlewareFor(['store'], ['auth:sanctum', 'abilities:posts:create'])
    ->middlewareFor(['update'], ['auth:sanctum', 'abilities:posts:update'])
    ->middlewareFor(['destroy'], ['auth:sanctum', 'abilities:posts:delete']);

// Accessible sans connexion : pour voir un sondage via son lien de partage
Route::get('/v1/polls/{token}', [ApiPollController::class, 'show']);

// Routes qui nécessitent d'être connecté
Route::middleware('auth:sanctum')->group(function () {
//Au lieu d'écrire ->middleware('auth:sanctum') sur chacune des 5 routes séparément, on les regroupe toutes dans un group() = le middleware s'applique à tous

    Route::get('/v1/polls', [ApiPollController::class, 'index']);        // lister ses sondages
    Route::post('/v1/polls', [ApiPollController::class, 'store']);       // créer un sondage
    Route::put('/v1/polls/{id}', [ApiPollController::class, 'update']);  // modifier un sondage
    Route::delete('/v1/polls/{id}', [ApiPollController::class, 'remove']); // supprimer un sondage
    Route::post('/v1/polls/{token}/vote', [ApiPollController::class, 'vote']); // voter
});