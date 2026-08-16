<?php

namespace App\Support\Mails;

use App\Models\User;
use App\Support\AppMail;

final class AdminWorkflowMail
{
    public static function passwordResetByAdmin(User $user, string $temporaryPassword): void
    {
        if (! filled($user->email)) {
            return;
        }

        AppMail::notify($user, 'Votre mot de passe a été réinitialisé', [
            'title' => 'Réinitialisation de mot de passe',
            'content' => 'Un administrateur a défini un nouveau mot de passe temporaire pour votre compte '.config('app.name').'.',
            'action_required' => 'Connectez-vous avec ce mot de passe, puis changez-le immédiatement.',
            'details' => [
                'Compte' => $user->email,
                'Mot de passe temporaire' => $temporaryPassword,
            ],
            'action_url' => url('/login'),
            'action_text' => 'Se connecter',
            'footer_note' => 'Si vous n’êtes pas à l’origine de cette demande, contactez l’équipe IT.',
        ]);
    }
}
