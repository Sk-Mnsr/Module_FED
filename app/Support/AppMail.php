<?php

namespace App\Support;

use App\Jobs\SendEmail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Envoi d’e-mails applicatifs via la file d’attente (templates emails/*).
 */
final class AppMail
{
    public const VIEW_NOTIFICATION = 'emails.notification.notification';

    public const VIEW_VALIDATION = 'emails.valide.validation';

    public const VIEW_REJET = 'emails.rejet.rejet';

    public const VIEW_ALERTE = 'emails.alerte.alerte';

    /**
     * @param  string|User|iterable<int, User|string>|null  $to
     * @param  array<string, mixed>  $data
     */
    public static function queue(
        string|User|iterable|null $to,
        string $subject,
        string $view,
        array $data = [],
    ): void {
        $recipients = self::normalizeRecipients($to);
        if ($recipients === []) {
            return;
        }

        $payload = array_merge([
            'subject' => $subject,
        ], $data);

        try {
            SendEmail::dispatch($subject, $recipients, [], [], $view, $payload);
        } catch (\Throwable $e) {
            Log::warning('AppMail: échec dispatch', [
                'subject' => $subject,
                'to' => $recipients,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  string|User|iterable<int, User|string>|null  $to
     * @param  array<string, mixed>  $data
     */
    public static function notify(string|User|iterable|null $to, string $subject, array $data): void
    {
        self::queue($to, $subject, self::VIEW_NOTIFICATION, $data);
    }

    /**
     * @param  string|User|iterable<int, User|string>|null  $to
     * @param  array<string, mixed>  $data
     */
    public static function validated(string|User|iterable|null $to, string $subject, array $data): void
    {
        self::queue($to, $subject, self::VIEW_VALIDATION, $data);
    }

    /**
     * @param  string|User|iterable<int, User|string>|null  $to
     * @param  array<string, mixed>  $data
     */
    public static function rejected(string|User|iterable|null $to, string $subject, array $data): void
    {
        self::queue($to, $subject, self::VIEW_REJET, $data);
    }

    /**
     * @param  string|User|iterable<int, User|string>|null  $to
     * @param  array<string, mixed>  $data
     */
    public static function alert(string|User|iterable|null $to, string $subject, array $data): void
    {
        self::queue($to, $subject, self::VIEW_ALERTE, $data);
    }

    /**
     * @return Collection<int, User>
     */
    public static function usersWithRole(string $slug): Collection
    {
        return User::query()
            ->where('activated', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', $slug))
            ->get(['id', 'name', 'email']);
    }

    /**
     * @param  list<string>  $slugs
     * @return Collection<int, User>
     */
    public static function usersWithAnyRole(array $slugs): Collection
    {
        return User::query()
            ->where('activated', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', $slugs))
            ->get(['id', 'name', 'email']);
    }

    /**
     * @param  string|User|iterable<int, User|string>|null  $to
     * @return list<string>
     */
    private static function normalizeRecipients(string|User|iterable|null $to): array
    {
        if ($to === null) {
            return [];
        }

        if ($to instanceof User) {
            return filled($to->email) ? [(string) $to->email] : [];
        }

        if (is_string($to)) {
            $email = trim($to);

            return $email !== '' ? [$email] : [];
        }

        $emails = [];
        foreach ($to as $item) {
            if ($item instanceof User && filled($item->email)) {
                $emails[] = (string) $item->email;
            } elseif (is_string($item) && trim($item) !== '') {
                $emails[] = trim($item);
            }
        }

        return array_values(array_unique($emails));
    }
}
