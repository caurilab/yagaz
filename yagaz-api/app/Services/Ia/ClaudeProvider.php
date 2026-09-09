<?php

namespace App\Services\Ia;

use App\Contracts\Ia\IaProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Implémentation `IaProvider` appelant l'API Anthropic Messages (ADR 0013,
 * brique 1). Activée par `IA_PROVIDER=claude` + `IA_CLAUDE_API_KEY`
 * (`config/ia.php`), sans toucher aux contrôleurs ni aux services métier.
 *
 * Confidentialité (ADR 0013, §Confidentialité) : `$instructions`/`$message`
 * ne doivent contenir que des agrégats déjà visibles par l'utilisateur,
 * jamais de PII (identifiants, téléphone...) ; c'est au service appelant
 * (`App\Services\Analyse\AssistantFoyer`) de le garantir. La clé API ne vit
 * que côté serveur (variable d'environnement), jamais transmise au client.
 *
 * Robustesse (ADR 0013, §Coût et robustesse) : cette classe ne capture
 * aucune erreur ; c'est au service appelant de retomber sur `SimulateurIa`
 * ou un message neutre en cas d'échec, jamais un 500 à l'utilisateur.
 */
final class ClaudeProvider implements IaProvider
{
    public function generer(string $instructions, string $message): string
    {
        $apiKey = (string) config('ia.claude.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('IA_CLAUDE_API_KEY manquant');
        }

        $reponse = Http::timeout((int) config('ia.claude.timeout'))
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) config('ia.claude.version'),
                'content-type' => 'application/json',
            ])
            ->post(rtrim((string) config('ia.claude.base_url'), '/').'/v1/messages', [
                'model' => (string) config('ia.claude.model'),
                'max_tokens' => (int) config('ia.claude.max_tokens'),
                'system' => $instructions,
                'messages' => [
                    ['role' => 'user', 'content' => $message],
                ],
            ]);

        if ($reponse->failed()) {
            throw new RuntimeException('Appel à l\'API Anthropic Messages en échec : '.$reponse->status());
        }

        return $this->extraireTexte($reponse->json('content', []));
    }

    /**
     * Concatène tous les blocs `type === 'text'` de `content`, par
     * sécurité, au cas où la réponse contiendrait plusieurs blocs plutôt que
     * le seul `content[0].text` attendu en usage normal.
     *
     * @param  array<int, array<string, mixed>>  $blocsContenu
     */
    private function extraireTexte(array $blocsContenu): string
    {
        return collect($blocsContenu)
            ->filter(fn (array $bloc) => ($bloc['type'] ?? null) === 'text')
            ->map(fn (array $bloc) => (string) ($bloc['text'] ?? ''))
            ->implode('');
    }
}
