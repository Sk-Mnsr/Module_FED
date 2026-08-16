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
            'L’intégration a été refusée. Vérifiez vos écritures ou contactez le support.',
            $log,
            'Intégration refusée',
        );
    }

    /**
     * Erreur métier renvoyée par Flexcube (fcubsErrorResp).
     *
     * @param  list<array{code: string, message: string}>  $errors
     * @return array{title: string, message: string, log?: string}
     */
    public static function flexcubeRejected(array $errors, int $status, string $body): array
    {
        $lines = [];
        foreach ($errors as $error) {
            $code = $error['code'] ?? '';
            $message = $error['message'] ?? '';
            $lines[] = trim($code !== '' ? $code.' — '.$message : $message);
        }

        $codes = array_map(static fn (array $e) => strtoupper((string) ($e['code'] ?? '')), $errors);

        if (in_array('RVAL-014', $codes, true)) {
            $friendly = 'Vous êtes déjà connecté ailleurs. Déconnectez-vous puis réessayez.';
        } else {
            $descriptions = array_values(array_filter(array_map(
                static fn (array $e) => trim((string) ($e['message'] ?? '')),
                $errors,
            )));
            $friendly = $descriptions !== []
                ? $descriptions[0]
                : 'L’intégration a été refusée. Vérifiez vos écritures ou contactez le support.';
        }

        $log = trim('HTTP '.$status."\n\n".implode("\n", $lines)."\n\n".Str::limit($body, 4000));

        return self::error($friendly, $log, 'Intégration refusée');
    }

    /**
     * @return array{title: string, message: string}
     */
    private static function friendlyNetworkMessage(string $raw): array
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'could not resolve host')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'failed to connect')
            || str_contains($lower, 'curl error')
            || str_contains($lower, 'ssl')
            || str_contains($lower, 'certificate')) {
            return [
                'title' => 'Service indisponible',
                'message' => 'Impossible de joindre le service d’intégration. Réessayez plus tard ou contactez le support.',
            ];
        }

        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return [
                'title' => 'Délai dépassé',
                'message' => 'Le service met trop de temps à répondre. Réessayez dans quelques instants.',
            ];
        }

        if (str_contains($lower, 'idflex') || str_contains($lower, 'userid')) {
            return [
                'title' => 'Identifiant manquant',
                'message' => 'Votre identifiant Flexcube (IDFLEX) n’est pas renseigné. Contactez un administrateur.',
            ];
        }

        return [
            'title' => 'Intégration impossible',
            'message' => 'L’intégration n’a pas pu être effectuée. Réessayez ou contactez le support.',
        ];
    }
}
