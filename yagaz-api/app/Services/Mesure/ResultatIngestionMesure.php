<?php

namespace App\Services\Mesure;

use App\Enums\StatutIngestionMesure;
use App\Models\Alerte;
use App\Models\Mesure;
use App\Models\NiveauCourant;

/**
 * Résultat structuré du traitement d'un message de mesure — ce que
 * `TraitementMesure::traiter()` retourne, pour être exploité par la commande
 * d'ingestion (log) et par les tests.
 */
final readonly class ResultatIngestionMesure
{
    public function __construct(
        public StatutIngestionMesure $statut,
        public ?string $raison = null,
        public ?Mesure $mesure = null,
        public ?NiveauCourant $niveauCourant = null,
        public ?Alerte $alerte = null,
    ) {}

    public static function rangee(
        Mesure $mesure,
        ?NiveauCourant $niveauCourant = null,
        ?Alerte $alerte = null,
    ): self {
        return new self(StatutIngestionMesure::Rangee, mesure: $mesure, niveauCourant: $niveauCourant, alerte: $alerte);
    }

    public static function doublon(string $raison): self
    {
        return new self(StatutIngestionMesure::Doublon, raison: $raison);
    }

    public static function rejetee(string $raison): self
    {
        return new self(StatutIngestionMesure::Rejetee, raison: $raison);
    }
}
