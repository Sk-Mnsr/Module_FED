<?php

namespace App\Support\Mails;

use App\Models\OdClasseur;
use App\Support\AppMail;

final class OdWorkflowMail
{
    public static function awaitingCheckerValidation(OdClasseur $classeur): void
    {
        $classeur->loadMissing('assignedChecker:id,name,email', 'user:id,name', 'integratedBy:id,name');
        $checker = $classeur->assignedChecker;
        if ($checker === null) {
            return;
        }

        $maker = $classeur->integratedBy?->name ?? $classeur->user?->name ?? 'un collègue';

        AppMail::notify($checker, 'OD en attente de votre validation', [
            'title' => 'Validation maker / checker',
            'content' => $maker.' a soumis une opération diverse pour votre validation.',
            'action_required' => 'Contrôler puis valider ou rejeter le classeur.',
            'details' => array_filter([
                'Classeur' => $classeur->nom_classeur,
                'Batch' => $classeur->numero_batch,
                'Date valeur' => $classeur->date_valeur?->format('d/m/Y'),
            ]),
            'action_url' => url('/operations-diverses/attente-validation'),
            'action_text' => 'Ouvrir la file',
        ]);
    }
}
