<?php

namespace App\Services\Temperature;

use App\Enums\StatutIngestionTemperature;
use App\Models\Alerte;
use App\Models\SessionCuisson;
use App\Models\Temperature;

/**
 * Résultat structuré du traitement d'un message de température — ce que
 * `TraitementTemperature::traiter()` retourne, pour être exploité par la
 * commande d'ingestion (log) et par les tests (calqué sur
 * `ResultatIngestionMesure`).
 */
final readonly class ResultatIngestionTemperature
{
    public function __construct(
        public StatutIngestionTemperature $statut,
        public ?string $raison = null,
        public ?Temperature $temperature = null,
        public bool $cuissonEnCours = false,
        public ?SessionCuisson $sessionCuisson = null,
        public ?Alerte $alerte = null,
    ) {}

    public static function rangee(
        Temperature $temperature,
        bool $cuissonEnCours,
        ?SessionCuisson $sessionCuisson = null,
        ?Alerte $alerte = null,
    ): self {
        return new self(
            StatutIngestionTemperature::Rangee,
            temperature: $temperature,
            cuissonEnCours: $cuissonEnCours,
            sessionCuisson: $sessionCuisson,
            alerte: $alerte,
        );
    }

    public static function doublon(string $raison): self
    {
        return new self(StatutIngestionTemperature::Doublon, raison: $raison);
    }

    public static function rejetee(string $raison): self
    {
        return new self(StatutIngestionTemperature::Rejetee, raison: $raison);
    }
}
