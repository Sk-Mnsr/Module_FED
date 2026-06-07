<?php

namespace App\Services\Integrations;

use App\Models\EcritureComptable;
use App\Support\EcritureComptableFlexCsv;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class EcritureComptableImportApiClient
{
    /**
     * @return array<string, mixed>
     */
    protected function cfg(): array
    {
        return config('services.ecritures_comptables_import', []);
    }

    public function isConfigured(): bool
    {
        $c = $this->cfg();
        $url = $c['url'] ?? null;
        $key = isset($c['api_key']) ? trim((string) $c['api_key']) : '';

        return is_string($url) && $url !== '' && $key !== '';
    }

    /**
     * Colonne CSV « user_id » : IDFLEX utilisateur (matricule), pas l’id Laravel.
     *
     * POST vers l’URL Flex avec en-tête « apikey » (valeur depuis ECRITURES_COMPTABLES_IMPORT_KEY).
     *
     * @param  Collection<int, EcritureComptable>  $ecritures
     */
    public function upload(Collection $ecritures): Response
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Intégration comptable : URL ou clé API manquante.');
        }

        $c = $this->cfg();
        $delimiter = (string) ($c['csv_delimiter'] ?? ';');
        $filename = (string) ($c['csv_filename'] ?? 'ecritures_comptables.csv');
        $csv = $this->buildCsvString($ecritures, $delimiter);

        return $this->postPayload($csv, $filename);
    }

    /**
     * Envoie un fichier CSV déjà prêt (ex. généré ailleurs) — sans enregistrement en base.
     */
    public function uploadFileContents(string $contents, string $filename): Response
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Intégration comptable : URL ou clé API manquante.');
        }

        return $this->postPayload($contents, $filename);
    }

    protected function postPayload(string $body, string $filename): Response
    {
        $c = $this->cfg();
        $fileField = (string) ($c['file_field'] ?? 'file');
        $headerName = strtolower((string) ($c['api_key_header'] ?? 'apikey'));
        $bodyMode = (string) ($c['body_mode'] ?? 'multipart');
        $timeout = (int) ($c['timeout'] ?? 120);
        $verify = (bool) ($c['verify_ssl'] ?? true);
        $url = (string) $c['url'];
        $apiKey = trim((string) ($c['api_key'] ?? ''));

        $headers = array_filter([
            'Accept' => 'application/json, text/plain, */*',
            $headerName => $apiKey !== '' ? $apiKey : null,
            'Origin' => ! empty($c['origin']) ? (string) $c['origin'] : null,
            'Referer' => ! empty($c['referer']) ? (string) $c['referer'] : null,
        ]);

        $request = Http::timeout($timeout)
            ->withOptions(['verify' => $verify])
            ->withHeaders($headers);

        if ($bodyMode === 'raw') {
            return $request
                ->withBody($body, 'text/csv; charset=UTF-8')
                ->post($url); /* API externe Flex Compta SN */
        }

        return $request
            ->attach($fileField, $body, $filename, ['Content-Type' => 'text/csv'])
            ->post($url); /* API externe Flex Compta SN */
    }

    /**
     * @param  Collection<int, EcritureComptable>  $ecritures
     */
    protected function buildCsvString(Collection $ecritures, string $delimiter): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de créer le flux CSV en mémoire.');
        }

        if (EcritureComptableFlexCsv::includeBom()) {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        fputcsv($handle, EcritureComptableFlexCsv::headerRow(), $delimiter);

        foreach ($ecritures as $ecriture) {
            fputcsv($handle, EcritureComptableFlexCsv::dataRow($ecriture), $delimiter);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }
}
