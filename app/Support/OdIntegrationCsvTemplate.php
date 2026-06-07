<?php

namespace App\Support;

/**
 * Modèle CSV simplifié pour l'intégration automatique OD.
 */
final class OdIntegrationCsvTemplate
{
    public static function build(): string
    {
        return OdSimpleIntegrationCsv::buildTemplate();
    }
}
