<?php

namespace App\Console\Commands;

use App\Enums\RoleMembership;
use App\Enums\TypeOrganisation;
use App\Models\Organisation;
use App\Services\Compte\ProvisionnementMembre;
use Illuminate\Console\Command;

/**
 * Amorçage Yagaz (contrat API doc 11 — création de comptes) : crée une
 * organisation du haut de la chaîne (distributeur ou mandataire, ou un dépôt
 * pour dépanner) et, optionnellement, son responsable (compte + membership du
 * bon rôle). Sert de « back-office » tant qu'un vrai écran d'admin n'existe
 * pas : les niveaux du dessous se créent ensuite entre eux dans l'app
 * (mandataire → dépôts, dépôt → livreurs).
 */
class CreerOrganisation extends Command
{
    /**
     * @var string
     */
    protected $signature = 'yagaz:creer-organisation
        {type : distributeur | mandataire | depot}
        {nom : nom de l\'organisation}
        {--zone= : zone de desserte}
        {--parent= : uuid de l\'organisation parente (mandataire pour un dépôt, distributeur pour un mandataire)}
        {--responsable-nom= : nom du responsable à rattacher}
        {--responsable-tel= : téléphone du responsable (identifiant de connexion)}
        {--mot-de-passe= : mot de passe imposé (sinon un temporaire est généré et affiché)}';

    /**
     * @var string
     */
    protected $description = 'Crée une organisation (distributeur/mandataire/dépôt) et, en option, son responsable.';

    public function handle(ProvisionnementMembre $provisionnementMembre): int
    {
        $type = TypeOrganisation::tryFrom((string) $this->argument('type'));
        if ($type === null) {
            $this->error('Type invalide. Attendu : distributeur, mandataire ou depot.');

            return self::FAILURE;
        }

        $parent = null;
        if ($this->option('parent') !== null) {
            $parent = Organisation::where('uuid', $this->option('parent'))->first();
            if ($parent === null) {
                $this->error('Organisation parente introuvable pour cet uuid.');

                return self::FAILURE;
            }
        }

        $organisation = Organisation::create([
            'type' => $type,
            'nom' => (string) $this->argument('nom'),
            'zone' => $this->option('zone'),
            'parent_id' => $parent?->id,
        ]);

        $this->info("Organisation créée : {$organisation->nom} ({$type->value})");
        $this->line("uuid : {$organisation->uuid}");

        $tel = $this->option('responsable-tel');
        if ($tel !== null) {
            $role = match ($type) {
                TypeOrganisation::Distributeur => RoleMembership::Distributeur,
                TypeOrganisation::Mandataire => RoleMembership::Mandataire,
                TypeOrganisation::Depot => RoleMembership::GerantDepot,
            };

            $resultat = $provisionnementMembre->attacher(
                (string) ($this->option('responsable-nom') ?? 'Responsable'),
                (string) $tel,
                $role,
                $organisation,
                $this->option('mot-de-passe'),
            );

            $this->info(
                $resultat['cree']
                    ? "Compte responsable créé ({$role->value}) : {$tel}"
                    : "Compte existant rattaché ({$role->value}) : {$tel}"
            );
            if ($resultat['mot_de_passe_temporaire'] !== null) {
                $this->warn("Mot de passe temporaire (à communiquer) : {$resultat['mot_de_passe_temporaire']}");
            }
        }

        return self::SUCCESS;
    }
}
