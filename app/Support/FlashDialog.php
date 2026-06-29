<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Messages flash structurés pour affichage en dialogue (message clair + journal technique).
 *
 * @return array{title: string, message: string, log?: string}
 */
final class FlashDialog
{
    /**
     * @return array{title: string, message: string, log?: string}
     */
    public static function error(string $message, ?string $log = null, ?string $title = null): array
    {
        $payload = [
            'title' => $title ?? 'Erreur',
            'message' => $message,
        ];

        if ($log !== null && trim($log) !== '') {
            $payload['log'] = trim($log);
        }

        return $payload;
    }

    /**
     * @return array{title: string, message: string, log?: string}
     */
    public static function fromThrowable(string $context, \Throwable $e): array
    {
        $technical = trim($context."\n\n".$e->getMessage());
        $friendly = self::friendlyNetworkMessage($e->getMessage());

        return self::error(
            $friendly['message'],
            $technical,
            $friendly['title'],
        );
    }

    /**
     * @return array{title: string, message: string, log?: string}
     */
    public static function httpRejected(int $status, string $body): array
    {
        $log = trim('HTTP '.$status."\n\n".Str::limit($body, 4000));

        return self::error(
            'La plateforme comptable a refusé le fichier d’intégration. Vérifiez le contenu du CSV ou contactez le support.',
            $log,
            'Fichier rejeté par la plateforme',
        );
    }

    /**
     * @return array{title: string, message: string}
     */
    private static function friendlyNetworkMessage(string $raw): array
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'could not resolve host')) {
            return [
                'title' => 'Plateforme injoignable',
                'message' => 'Le serveur de la plateforme comptable est inaccessible. Vérifiez votre connexion réseau ou contactez le support IT.',
            ];
        }

        if (str_contains($lower, 'connection refused') || str_contains($lower, 'failed to connect')) {
            return [
                'title' => 'Connexion refusée',
                'message' => 'Impossible de se connecter à la plateforme comptable. Le service est peut-être indisponible.',
            ];
        }

        if (str_contains($lower, 'curl error') || str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return [
                'title' => 'Erreur réseau',
                'message' => 'La communication avec la plateforme comptable a échoué. Réessayez plus tard ou contactez le support IT.',
            ];
        }

        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return [
                'title' => 'Délai dépassé',
                'message' => 'La plateforme comptable n’a pas répondu à temps. Réessayez dans quelques instants.',
            ];
        }

        return [
            'title' => 'Intégration impossible',
            'message' => 'L’envoi vers la plateforme comptable a échoué. Consultez les détails techniques ou contactez le support.',
        ];
    }
}
