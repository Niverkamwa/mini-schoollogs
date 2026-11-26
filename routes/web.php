<?php

use App\Http\Controllers\Api\PassageSyncController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortailController;
use App\Models\Badge;
use App\Models\PointControle;
use App\Services\BadgeService;
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
    Route::get('/scan', function () {
        $badgeActif = Badge::where('etat', 'actif')->first();
        $jetonExemple = $badgeActif ? app(BadgeService::class)->genererJeton($badgeActif) : '';

        return view('portail.scan', [
            'pointsControle' => PointControle::orderBy('id')->get(),
            'jetonExemple' => $jetonExemple,
        ]);
    })->name('scan');

    Route::post('/api/passages/sync', [PassageSyncController::class, 'sync'])->name('api.passages.sync');
});