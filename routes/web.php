<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/connexion');
});

Route::get('/connexion', [AuthController::class, 'afficherFormulaire'])->name('connexion');
Route::post('/connexion', [AuthController::class, 'connecter']);
Route::post('/deconnexion', [AuthController::class, 'deconnecter'])->middleware('auth');

Route::get('/tableau-de-bord', function () {
    return 'Connecté';
})->middleware('auth');