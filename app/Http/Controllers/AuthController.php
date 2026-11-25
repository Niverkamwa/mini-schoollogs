<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConnexionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function afficherFormulaire(): View
    {
        return view('auth.connexion');
    }

    public function connecter(ConnexionRequest $request): RedirectResponse
    {
        $donnees = $request->validated();
        $cle = strtolower($donnees['identifiant']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            abort(429, 'Trop de tentatives de connexion.');
        }

        $ok = Auth::attempt([
            'identifiant' => $donnees['identifiant'],
            'password' => $donnees['password'],
        ]);

        if (! $ok) {
            RateLimiter::hit($cle, 60);

            return back()
                ->withInput(['identifiant' => $donnees['identifiant']])
                ->withErrors(['identifiant' => 'Identifiants invalides.']);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        return redirect()->intended('/tableau-de-bord');
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }
}