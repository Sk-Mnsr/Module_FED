<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ReconciliationGatewayClient
{
    /**
     * @return array<string, mixed>
     */
    protected function cfg(): array
    {
        return config('services.reconciliation_gateway', []);
    }

    public function isConfigured(): bool
    {
        $url = $this->cfg()['url'] ?? null;

        return is_string($url) && trim($url) !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->cfg()['url'] ?? ''), '/');
    }

    /**
     * @throws RuntimeException
     */
    protected function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Gateway de réconciliation : URL manquante (RECONCILIATION_GATEWAY_URL).');
        }

        $c = $this->cfg();
        $timeout = (int) ($c['timeout'] ?? 180);
        $verify = (bool) ($c['verify_ssl'] ?? true);
        $apiKey = trim((string) ($c['api_key'] ?? ''));
        $headerName = strtolower((string) ($c['api_key_header'] ?? 'apikey'));

        $request = Http::baseUrl($this->baseUrl())
            ->timeout($timeout)
            ->withOptions(['verify' => $verify])
            ->acceptJson();

        if ($apiKey !== '') {
            $request = $request->withHeaders([$headerName => $apiKey]);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->jsonGet('/health');
    }

    /**
     * @return list<array{key: string, label: string, mode: string}>
     */
    public function partenaires(): array
    {
        /** @var list<array{key: string, label: string, mode: string}> */
        return $this->jsonGet('/partenaires');
    }

    /**
     * Normalise l’identifiant Laravel vers la clé gateway (WAVE, ORANGE_AGENCE, …).
     */
    public function normalizePartenaireKey(string $partenaireKey): string
    {
        $raw = strtoupper(trim($partenaireKey));
        $raw = str_replace([' ', '-'], '_', $raw);

        $aliases = [
            'ORANGE' => 'ORANGE_AGENCE',
            'ORANGE_MONEY' => 'ORANGE_AGENCE',
            'ORANGE_AG' => 'ORANGE_AGENCE',
            'WAVE_INTER' => 'WAVE',
            'WAVE_INT' => 'WAVE',
            // Identifiant Module_FED « WAVE USSD » → pipeline Wave (fichier banque DATE TRANSACTION)
            'WAVE_USSD' => 'WAVE',
            'RIA' => 'RIA_AGENCE',
            'USSD' => 'ORANGE_USSD',
            'ORANGE_USSD_PARTENAIRE' => 'ORANGE_USSD',
        ];

        return $aliases[$raw] ?? $raw;
    }

    public function resolveMode(string $partenaireKey): string
    {
        $key = $this->normalizePartenaireKey($partenaireKey);

        foreach ($this->partenaires() as $item) {
            if (strcasecmp((string) ($item['key'] ?? ''), $key) === 0) {
                return (string) ($item['mode'] ?? 'two_pointers');
            }
        }

        throw new RuntimeException(
            "Partenaire gateway inconnu : « {$partenaireKey} » (clé : {$key}). "
            .'Attendu : WAVE, ORANGE_AGENCE, ORANGE_USSD, WIZZ, WAVE_AGENCE ou RIA_AGENCE.'
        );
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return array<string, mixed>
     */
    public function charger(string $partenaireKey, array $files, string $dateDebut, string $dateFin): array
    {
        if ($files === []) {
            throw new RuntimeException('Au moins un fichier est requis pour le chargement.');
        }

        $partenaireKey = $this->normalizePartenaireKey($partenaireKey);

        $query = http_build_query([
            'partenaire' => $partenaireKey,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);

        $request = $this->http()->asMultipart();

        foreach ($files as $file) {
            $request = $request->attach(
                'files',
                file_get_contents($file->getRealPath()) ?: '',
                $file->getClientOriginalName()
            );
        }

        try {
            $response = $request->post('/charger?'.$query);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Gateway inaccessible ('.$this->baseUrl().'). Démarrez reconc.py : npm run gateway:recon.',
                0,
                $e
            );
        }

        return $this->decodeOrFail($response, 'Chargement gateway');
    }

    public function run(string $partenaireKey, string $mode = 'two_pointers'): Response
    {
        $partenaireKey = $this->normalizePartenaireKey($partenaireKey);

        $path = $mode === 'agence'
            ? '/reconciliation/run-agence'
            : '/reconciliation/run';

        try {
            $response = $this->http()
                ->withHeaders(['Accept' => '*/*'])
                ->post($path.'?'.http_build_query(['partenaire' => $partenaireKey]));
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Gateway inaccessible ('.$this->baseUrl().'). Démarrez reconc.py : npm run gateway:recon.',
                0,
                $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->extractError($response, 'Réconciliation gateway'));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function reset(string $partenaireKey): array
    {
        $partenaireKey = $this->normalizePartenaireKey($partenaireKey);

        try {
            $response = $this->http()->post('/reset?'.http_build_query(['partenaire' => $partenaireKey]));
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Gateway inaccessible ('.$this->baseUrl().'). Démarrez reconc.py : npm run gateway:recon.',
                0,
                $e
            );
        }

        return $this->decodeOrFail($response, 'Reset gateway');
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    public function reconciliationSummary(string $partenaireKey, string $mode = 'two_pointers'): array
    {
        $partenaireKey = $this->normalizePartenaireKey($partenaireKey);

        $path = $mode === 'agence'
            ? '/db/reconciliation-agence-resume'
            : '/db/reconciliation-summary';

        return $this->jsonGet($path, ['partenaire' => $partenaireKey]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reconciliationTaux(string $partenaireKey, string $mode = 'two_pointers'): array
    {
        $partenaireKey = $this->normalizePartenaireKey($partenaireKey);

        $path = $mode === 'agence'
            ? '/db/reconciliation-agence-taux'
            : '/db/reconciliation-taux';

        /** @var array<string, mixed> */
        return $this->jsonGet($path, ['partenaire' => $partenaireKey]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reconciliationResume(string $partenaireKey, string $mode = 'two_pointers'): array
    {
        $partenaireKey = $this->normalizePartenaireKey($partenaireKey);

        $path = $mode === 'agence'
            ? '/db/reconciliation-agence-resume'
            : '/db/reconciliation-resume';

        /** @var list<array<string, mixed>> */
        return $this->jsonGet($path, ['partenaire' => $partenaireKey]);
    }

    /**
     * @return list<string>
     */
    public function statuts(): array
    {
        /** @var list<string> */
        return $this->jsonGet('/statuts');
    }

    /**
     * @return array<string, mixed>
     */
    public function tables(): array
    {
        return $this->jsonGet('/db/tables');
    }

    /**
     * Endpoints /db/* documentés (PDF gateway).
     *
     * @return array<string, string>
     */
    public static function dbResourceMap(): array
    {
        return [
            'excel' => '/db/excel',
            'excel-w2b' => '/db/excel-w2b',
            'excel-b2w' => '/db/excel-b2w',
            'flex' => '/db/flex',
            'flex-w2b' => '/db/flex-w2b',
            'flex-b2w' => '/db/flex-b2w',
            'reconciliation' => '/db/reconciliation',
            'reconciliation-resume' => '/db/reconciliation-resume',
            'reconciliation-taux' => '/db/reconciliation-taux',
            'reconciliation-summary' => '/db/reconciliation-summary',
            'reconciliation-carte-resume' => '/db/reconciliation-carte-resume',
            'reconciliation-agence' => '/db/reconciliation-agence',
            'reconciliation-agence-resume' => '/db/reconciliation-agence-resume',
            'reconciliation-agence-taux' => '/db/reconciliation-agence-taux',
        ];
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    public function db(string $resource, string $partenaireKey): array
    {
        $map = self::dbResourceMap();
        if (! isset($map[$resource])) {
            throw new RuntimeException(
                "Ressource gateway inconnue : {$resource}. Valides : ".implode(', ', array_keys($map))
            );
        }

        return $this->jsonGet($map[$resource], [
            'partenaire' => $this->normalizePartenaireKey($partenaireKey),
        ]);
    }

    /**
     * Carte résumé : ignore le HTML Streamlit, garde les métriques numériques.
     *
     * @return array{diff_montant_partenaire: mixed, diff_montant_flexcube: mixed, ecart_difference: mixed}
     */
    public function carteResume(string $partenaireKey): array
    {
        /** @var array<string, mixed> $raw */
        $raw = $this->db('reconciliation-carte-resume', $partenaireKey);

        return [
            'diff_montant_partenaire' => $raw['diff_montant_partenaire'] ?? null,
            'diff_montant_flexcube' => $raw['diff_montant_flexcube'] ?? null,
            'ecart_difference' => $raw['ecart_difference'] ?? null,
        ];
    }

    /**
     * Spec Plotly JSON brute (à consommer côté front avec Plotly.js si besoin).
     *
     * @return array<string, mixed>|null
     */
    public function grapheStatut(string $partenaireKey): ?array
    {
        return $this->jsonGetNullable('/db/reconciliation-graphe-statut', [
            'partenaire' => $this->normalizePartenaireKey($partenaireKey),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function grapheEvolution(string $partenaireKey, string $typeTransaction = 'W2B'): ?array
    {
        return $this->jsonGetNullable('/db/reconciliation-graphe-evolution', [
            'partenaire' => $this->normalizePartenaireKey($partenaireKey),
            'type_transaction' => $typeTransaction,
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>|null
     */
    protected function jsonGetNullable(string $path, array $query = []): ?array
    {
        $url = $path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        try {
            $response = $this->http()->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Gateway inaccessible ('.$this->baseUrl().'). Démarrez reconc.py : npm run gateway:recon.',
                0,
                $e
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->extractError($response, 'Gateway '.$path));
        }

        $json = $response->json();
        if ($json === null) {
            return null;
        }
        if (! is_array($json)) {
            throw new RuntimeException('Gateway '.$path.' : réponse JSON invalide.');
        }

        return $json;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>|list<mixed>
     */
    protected function jsonGet(string $path, array $query = []): array
    {
        $url = $path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        try {
            $response = $this->http()->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Gateway inaccessible ('.$this->baseUrl().'). Démarrez reconc.py : npm run gateway:recon.',
                0,
                $e
            );
        }

        return $this->decodeOrFail($response, 'Gateway '.$path);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    protected function decodeOrFail(Response $response, string $context): array
    {
        if (! $response->successful()) {
            throw new RuntimeException($this->extractError($response, $context));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException($context.' : réponse JSON invalide.');
        }

        return $json;
    }

    protected function extractError(Response $response, string $context): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $detail = $json['detail'] ?? $json['message'] ?? $json['error'] ?? null;
            if (is_string($detail) && $detail !== '') {
                return $context.' : '.$detail;
            }
            if (is_array($detail)) {
                return $context.' : '.json_encode($detail, JSON_UNESCAPED_UNICODE);
            }
        }

        $body = trim($response->body());
        if ($body !== '') {
            return $context.' (HTTP '.$response->status().') : '.mb_substr($body, 0, 500);
        }

        return $context.' a échoué (HTTP '.$response->status().').';
    }
}
