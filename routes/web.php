<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/connexion');
});

Route::get('/connexion', [AuthController::class, 'afficherFormulaire'])->name('connexion');
Route::post('/connexion', [AuthController::class, 'connecter']);
Route::post('/deconnexion', [AuthController::class, 'deconnecter'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/tableau-de-bord', [PortailController::class, 'tableauDeBord'])->name('tableau-de-bord');
    Route::get('/eleves/{eleve}', [PortailController::class, 'afficherEleve'])->name('eleves.show');
});