<?php

namespace App\Providers;

use App\Contracts\Ia\IaProvider;
use App\Contracts\Notification\CanalNotification;
use App\Contracts\Payment\PaymentProvider;
use App\Services\Ia\ClaudeProvider;
use App\Services\Ia\LaravelAiProvider;
use App\Services\Ia\SimulateurIa;
use App\Services\Notification\CanalLog;
use App\Services\Payment\AgregateurPaiement;
use App\Services\Payment\SimulateurPaiement;
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
        // Canal de notification par défaut (contrat API doc 11, §3) : un
        // vrai provider (FCM/APNs, SMS, WhatsApp) prendra la place de
        // `CanalLog` ici, sans changer `Notificateur` ni le code appelant.
        $this->app->bind(CanalNotification::class, CanalLog::class);

        // Provider de paiement Mobile Money par défaut (ADR 0010, v2 brique
        // 1) : `simulateur` (aucun appel réseau réel) tant que l'agrégateur
        // réel n'est pas tranché. `agregateur` (squelette `AgregateurPaiement`)
        // est prêt à activer via `PAIEMENT_PROVIDER=agregateur` une fois les
        // identifiants du compte agrégateur disponibles (`config/paiement.php`,
        // section `agregateur`) — sans toucher au cycle de commande ni aux
        // contrôleurs.
        $this->app->bind(PaymentProvider::class, match (config('paiement.provider')) {
            'agregateur' => AgregateurPaiement::class,
            default => SimulateurPaiement::class,
        });

        // Provider IA (ADR 0013, brique 1). `IA_DRIVER=laravel_ai` route par le
        // SDK unifié `laravel/ai` (`LaravelAiProvider`, provider/modèle dans
        // `config/ia.laravel_ai`). Sinon on garde le comportement historique
        // (`IA_PROVIDER=claude` -> `ClaudeProvider`, défaut `simulateur`, aucun
        // appel réseau) — tests/CI sans clé restent hors-ligne.
        $this->app->bind(IaProvider::class, match (true) {
            config('ia.driver') === 'laravel_ai' => LaravelAiProvider::class,
            config('ia.provider') === 'claude' => ClaudeProvider::class,
            default => SimulateurIa::class,
        });
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

        // Throttle dédié à `PATCH /me/reglages-alertes` (audit sécurité,
        // [FAIBLE] throttle réglages d'alerte) : en plus du throttle global
        // `api`, cette route valide `livreur_habituel` par
        // `exists:users,telephone`, un oracle d'énumération de téléphones
        // inscrits — une limite plus stricte que `api` freine le sondage
        // répété d'un même compte authentifié.
        RateLimiter::for('reglages-alertes', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
