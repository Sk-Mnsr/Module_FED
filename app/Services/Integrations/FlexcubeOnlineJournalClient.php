<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client Flexcube Online Journal Entry — CreateMjrnlbook.
 */
class FlexcubeOnlineJournalClient
{
    /**
     * @return array<string, mixed>
     */
    protected function cfg(): array
    {
        return config('services.flexcube_online_journal', []);
    }

    public function isConfigured(): bool
    {
        $url = trim((string) ($this->cfg()['url'] ?? ''));

        // USERID = IDFLEX de l’utilisateur connecté (passé à l’appel).
        return $url !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createJournal(array $payload, ?string $userid = null): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Le service d’intégration n’est pas disponible.');
        }

        $c = $this->cfg();
        $resolvedUserid = trim((string) ($userid ?: ($c['userid'] ?? '')));
        if ($resolvedUserid === '') {
            throw new RuntimeException(
                'Votre identifiant Flexcube (IDFLEX) n’est pas renseigné.'
            );
        }

        $timeout = (int) ($c['timeout'] ?? 120);
        $verify = (bool) ($c['verify_ssl'] ?? false);
        $password = (string) ($c['password'] ?? '');

        $headers = array_filter([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'USERID' => $resolvedUserid,
            'PASSWORD' => $password !== '' ? $password : null,
            'ENTITY' => trim((string) ($c['entity'] ?? 'ENTITY_ID1')),
            'SOURCE' => trim((string) ($c['source'] ?? 'FCAT')),
            'BRANCH' => trim((string) ($c['branch'] ?? '501')),
            // Flexcube refuse une session déjà ouverte (RVAL-014) si le MSGID est rejoué.
            'MSGID' => $this->nextMessageId(),
        ], static fn ($v) => $v !== null && $v !== '');

        return Http::timeout($timeout)
            ->withOptions(['verify' => $verify])
            ->withHeaders($headers)
            ->asJson()
            ->post((string) $c['url'], $payload);
    }

    public function nextMessageId(): string
    {
        return now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Erreurs métier Flexcube (fcubsErrorResp) même sur réponse 200.
     *
     * @return list<array{code: string, message: string}>
     */
    public function extractErrors(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        $raw = data_get($json, 'fcubsErrorResp.error')
            ?? data_get($json, 'fcubsErrorResp.ERROR')
            ?? [];

        if (is_array($raw) && isset($raw['ecode'])) {
            $raw = [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        $errors = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = trim((string) ($item['ecode'] ?? $item['ECODE'] ?? ''));
            $message = trim((string) ($item['edesc'] ?? $item['EDESC'] ?? ''));

            if ($code !== '' || $message !== '') {
                $errors[] = ['code' => $code, 'message' => $message];
            }
        }

        return $errors;
    }

    /**
     * Extrait le n° de batch renvoyé par Flexcube si présent.
     */
    public function extractBatchNo(Response $response): ?string
    {
        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $candidates = [
            data_get($json, 'detbsjrnltxnmaster.batchNo'),
            data_get($json, 'detbsJrnlTxnMaster.batchNo'),
            data_get($json, 'DETBSJRNLTXNMASTER.BATCHNO'),
            data_get($json, 'batchNo'),
            data_get($json, 'batchNumber'),
            data_get($json, 'detbsBatchMaster.batchNo'),
            data_get($json, 'devwsBatchMaster.batchNumber'),
        ];

        // Parcours profond : certaines réponses imbriquent le batch autrement.
        $walker = static function ($node) use (&$walker): ?string {
            if (! is_array($node)) {
                return null;
            }
            foreach (['batchNo', 'batchNumber', 'BATCHNO', 'BatchNo'] as $key) {
                if (isset($node[$key]) && (is_string($node[$key]) || is_numeric($node[$key]))) {
                    $value = trim((string) $node[$key]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $found = $walker($child);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            return null;
        };

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return $walker($json);
    }
}
