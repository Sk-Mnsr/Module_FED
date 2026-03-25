<?php

namespace App\Notifications;

use App\Models\AppelOffre;
use App\Models\Fournisseur;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class InvitationFournisseurNotification extends Notification
{
    use Queueable;

    public $appelOffre;
    public $fournisseur;

    /**
     * Create a new notification instance.
     */
    public function __construct(AppelOffre $appelOffre, Fournisseur $fournisseur)
    {
        $this->appelOffre = $appelOffre;
        $this->fournisseur = $fournisseur;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // URL Signée valide jusqu'à la date limite
        $url = URL::temporarySignedRoute(
            'public.soumission.create',
            $this->appelOffre->date_limite_soumission,
            [
                'appelOffre' => $this->appelOffre->id,
                'fournisseur' => $this->fournisseur->id,
            ]
        );

        return (new MailMessage)
                    ->subject('Invitation à soumissionner : ' . $this->appelOffre->reference)
                    ->greeting('Bonjour ' . $this->fournisseur->nom . ',')
                    ->line('Vous êtes invité(e) à participer à l\'appel d\'offres suivant :')
                    ->line('**' . $this->appelOffre->reference . ' - ' . $this->appelOffre->objet . '**')
                    ->line('Vous pouvez soumettre votre offre technique et financière directement via notre portail sécurisé.')
                    ->line('**Date limite de soumission :** ' . $this->appelOffre->date_limite_soumission->format('d/m/Y H:i'))
                    ->action('Accéder au portail de soumission', $url)
                    ->line('Ce lien est privé et expirera après la date limite.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
