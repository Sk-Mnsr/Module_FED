<?php

namespace App\Jobs;

use App\Mail\EmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public string $subject,
        public string|array $to,
        public array $cc = [],
        public array $bcc = [],
        public string $view = '',
        public array $data = []
    ) {}

    public function handle(): void
    {
        try {
            $mailable = new EmailSender($this->subject, $this->view, $this->data);

            $mail = Mail::to($this->to);

            if (!empty($this->cc))  $mail->cc($this->cc);
            if (!empty($this->bcc)) $mail->bcc($this->bcc);

            $mail->send($mailable);

            Log::info("Email envoyé - to: " . json_encode($this->to) . ", subject: \"{$this->subject}\"");

        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            Log::error("Échec envoi (transport) - to: " . json_encode($this->to) . ", error: \"{$e->getMessage()}\"");
            throw $e;
        } catch (\Exception $e) {
            Log::error("Échec envoi - to: " . json_encode($this->to) . ", error: \"{$e->getMessage()}\"");
        }
    }
}