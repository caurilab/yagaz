<?php

namespace App\Services\Ia;

use App\Contracts\Ia\IaProvider;

use function Laravel\Ai\agent;

/**
 * Implémentation `IaProvider` via le SDK unifié `laravel/ai` (ADR 0013).
 * Activée par `IA_DRIVER=laravel_ai` (`config/ia.php`) ; le provider
 * (`AI_PROVIDER`, ex. `anthropic`) et le modèle (`AI_MODEL`, ex.
 * `claude-opus-5`) sont choisis ici, la clé du provider vivant dans le
 * `config/ai.php` du package (`ANTHROPIC_API_KEY`). Aucun contrôleur ni
 * service métier n'est modifié.
 *
 * Confidentialité (ADR 0013, §Confidentialité) : comme pour `ClaudeProvider`,
 * `$instructions`/`$message` ne doivent contenir que des agrégats déjà
 * visibles par l'utilisateur, jamais de PII — garanti par le service appelant
 * (`App\Services\Analyse\AssistantFoyer`). La clé ne vit que côté serveur.
 *
 * Robustesse (ADR 0013, §Coût et robustesse) : aucune erreur n'est capturée
 * ici ; le service appelant retombe sur `SimulateurIa`/un message neutre en
 * cas d'échec, jamais un 500 à l'utilisateur.
 */
final class LaravelAiProvider implements IaProvider
{
    public function generer(string $instructions, string $message): string
    {
        $provider = (string) config('ia.laravel_ai.provider');
        $model = (string) config('ia.laravel_ai.model');

        return agent($instructions)
            ->prompt(
                $message,
                provider: $provider !== '' ? $provider : null,
                model: $model !== '' ? $model : null,
            )
            ->text;
    }
}
