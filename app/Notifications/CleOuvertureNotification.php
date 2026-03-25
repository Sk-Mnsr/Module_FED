<?php

namespace App\Notifications;

use App\Models\AppelOffre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CleOuvertureNotification extends Notification
{
    use Queueable;

    public $appelOffre;
    public $clePart;
    public $position;

    /**
     * Create a new notification instance.
     */
    public function __construct(AppelOffre $appelOffre, string $clePart, int $position = 1)
    {
        $this->appelOffre = $appelOffre;
        $this->clePart = $clePart;
        $this->position = $position;
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
        return (new MailMessage)
                    ->subject('Votre fragment de clé pour l\'Appel d\'Offres : ' . $this->appelOffre->reference)
                    ->greeting('Bonjour ' . $notifiable->name . ',')
                    ->line('Vous avez été désigné(e) comme membre du comité pour l\'appel d\'offres suivant :')
                    ->line('**' . $this->appelOffre->reference . ' - ' . $this->appelOffre->objet . '**')
                    ->line('Voici votre **fragment de clé secret** nécessaire pour l\'ouverture conjointe des offres :')
                    ->line('')
                    ->line('### Fragment (Position n°' . $this->position . ') : ' . $this->clePart)
                    ->line('')
                    ->line('Veuillez conserver précieusement ce fragment. Il sera demandé lors de la séance d\'ouverture des plis. L\'ordre de reconstitution suit le numéro des positions.')
                    ->action('Consulter le TDR', url('/appel-offres/' . $this->appelOffre->id))
                    ->line('Merci de votre collaboration !');
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
