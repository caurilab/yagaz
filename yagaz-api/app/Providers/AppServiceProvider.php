<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Throttle strict de l'authentification (audit sécurité, [ÉLEVÉ]
        // rate-limiting) : `POST /api/auth/login` et `POST /api/auth/register`
        // sont les seules routes ouvertes de l'API — sans limite, elles sont
        // exposées au bruteforce de mot de passe et à l'énumération de
        // téléphones déjà inscrits. Clé = IP + `telephone` du corps, pour
        // qu'un attaquant ne puisse ni saturer un compte précis depuis
        // plusieurs IP, ni contourner la limite en changeant de téléphone
        // depuis la même IP.
        RateLimiter::for('auth', function (Request $request) {
            $telephone = (string) $request->input('telephone');

            return Limit::perMinute(6)->by($request->ip().'|'.$telephone);
        });

        // Throttle global raisonnable sur l'ensemble du groupe API (audit
        // sécurité, [ÉLEVÉ] rate-limiting), activé via `withMiddleware`
        // (`bootstrap/app.php`) qui pose `throttle:api` sur le groupe `api`.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
