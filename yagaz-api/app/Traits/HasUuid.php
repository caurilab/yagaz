<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Génère automatiquement un UUID v4 dans la colonne `uuid` du modèle à la
 * création, si elle n'est pas déjà renseignée.
 *
 * Utilisé par toutes les entités exposées à l'extérieur (doc 07, §1 « Identifiants »).
 */
trait HasUuid
{
    /**
     * Démarre l'écoute de l'évènement `creating` pour générer l'UUID.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->{$model->getUuidColumn()})) {
                $model->{$model->getUuidColumn()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Nom de la colonne UUID. Redéfinissable par le modèle si besoin.
     */
    public function getUuidColumn(): string
    {
        return 'uuid';
    }
}
