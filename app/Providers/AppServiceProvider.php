<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('connexion', function (Request $request) {
            $identifiant = strtolower((string) $request->input('identifiant'));
            $cle = $identifiant.'|'.$request->ip();

            return Limit::perMinute(5)->by($cle);
        });
    }
}