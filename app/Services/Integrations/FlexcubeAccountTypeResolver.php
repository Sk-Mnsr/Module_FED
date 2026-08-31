<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Résout accorgl (A = compte client, G = GL) via Oracle Flexcube.
 *
 * SELECT GL_CODE AS CPT, 'G' AS ACCORGL FROM {schema}.GLTM_GLMASTER WHERE GL_CODE IN (...)
 * UNION
 * SELECT CUST_AC_NO AS CPT, 'A' AS ACCORGL FROM {schema}.STTM_CUST_ACCOUNT WHERE CUST_AC_NO IN (...)
 *
 * Drivers (dans l’ordre) :
 * 1. Extension PHP oci8 (si installée)
 * 2. Script Python oracledb en mode thin (scripts/flexcube_accorgl_resolve.py) — sans Instant Client
 */
final class FlexcubeAccountTypeResolver
{
    /**
     * @param  list<string>  $accounts
     * @return array<string, 'A'|'G'>  map compte → accorgl
     */
    public function resolveMany(array $accounts): array
    {
        $accounts = array_values(array_unique(array_filter(array_map(
            static fn ($a) => trim((string) $a),
            $accounts,
        ), static fn (string $a) => $a !== '')));

        if ($accounts === []) {
            return [];
        }

        if (! $this->isEnabled()) {
            return [];
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Résolution accorgl Oracle activée mais incomplète : renseignez ORACLE_HOST, ORACLE_SERVICE_NAME, ORACLE_USER, ORACLE_PASSWORD.'
            );
        }

        if (extension_loaded('oci8')) {
            return $this->queryAccorglOci8($accounts);
        }

        return $this->queryAccorglPython($accounts);
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.flexcube_online_journal.accorgl_resolve_oracle', true);
    }

    public function isConfigured(): bool
    {
        $c = $this->cfg();

        return filled($c['host'] ?? null)
            && filled($c['service_name'] ?? null)
            && filled($c['username'] ?? null)
            && array_key_exists('password', $c)
            && $c['password'] !== null
            && $c['password'] !== '';
    }

    public function driverLabel(): string
    {
        if (extension_loaded('oci8')) {
            return 'oci8';
        }

        return $this->pythonBinary() !== null ? 'python-oracledb (thin)' : 'aucun';
    }

    /**
     * @return array<string, mixed>
     */
    private function cfg(): array
    {
        return config('services.oracle_flexcube', []);
    }

    /**
     * @param  list<string>  $accounts
     * @return array<string, 'A'|'G'>
     */
    private function queryAccorglOci8(array $accounts): array
    {
        $c = $this->cfg();
        $host = (string) $c['host'];
        $port = (string) ($c['port'] ?? '1522');
        $service = (string) $c['service_name'];
        $user = (string) $c['username'];
        $password = (string) $c['password'];
        $charset = (string) ($c['charset'] ?? 'AL32UTF8');
        $schema = $this->safeSchema((string) ($c['schema'] ?? 'CFSFCUBS145'));

        $ezConnect = "//{$host}:{$port}/{$service}";

        $conn = @oci_connect($user, $password, $ezConnect, $charset);
        if ($conn === false) {
            $err = oci_error();
            $msg = is_array($err) ? ($err['message'] ?? 'erreur inconnue') : 'erreur inconnue';
            Log::error('FlexcubeAccountTypeResolver: connexion Oracle échouée (oci8)', [
                'host' => $host,
                'port' => $port,
                'service' => $service,
                'error' => $msg,
            ]);
            throw new RuntimeException('Connexion Oracle Flexcube impossible : '.$msg);
        }

        try {
            $binds = [];
            $placeholders = [];
            foreach ($accounts as $i => $account) {
                $name = 'a'.$i;
                $placeholders[] = ':'.$name;
                $binds[$name] = $account;
            }
            $inList = implode(',', $placeholders);

            $sql = <<<SQL
SELECT GL_CODE AS CPT, 'G' AS ACCORGL
FROM {$schema}.GLTM_GLMASTER
WHERE GL_CODE IN ({$inList})
UNION
SELECT CUST_AC_NO AS CPT, 'A' AS ACCORGL
FROM {$schema}.STTM_CUST_ACCOUNT
WHERE CUST_AC_NO IN ({$inList})
SQL;

            $stid = oci_parse($conn, $sql);
            if ($stid === false) {
                $err = oci_error($conn);
                throw new RuntimeException(
                    'Oracle parse error : '.(is_array($err) ? ($err['message'] ?? 'inconnu') : 'inconnu')
                );
            }

            foreach ($binds as $name => $value) {
                oci_bind_by_name($stid, ':'.$name, $binds[$name], 64);
            }

            $ok = @oci_execute($stid);
            if ($ok === false) {
                $err = oci_error($stid);
                throw new RuntimeException(
                    'Oracle execute error : '.(is_array($err) ? ($err['message'] ?? 'inconnu') : 'inconnu')
                );
            }

            /** @var array<string, 'A'|'G'> $map */
            $map = [];
            while (($row = oci_fetch_assoc($stid)) !== false) {
                $cpt = trim((string) ($row['CPT'] ?? ''));
                $type = strtoupper(trim((string) ($row['ACCORGL'] ?? '')));
                if ($cpt === '' || ! in_array($type, ['A', 'G'], true)) {
                    continue;
                }
                if (! isset($map[$cpt]) || $type === 'G') {
                    $map[$cpt] = $type;
                }
            }

            oci_free_statement($stid);

            return $map;
        } finally {
            oci_close($conn);
        }
    }

    /**
     * @param  list<string>  $accounts
     * @return array<string, 'A'|'G'>
     */
    private function queryAccorglPython(array $accounts): array
    {
        $python = $this->pythonBinary();
        $script = base_path('scripts/flexcube_accorgl_resolve.py');

        if ($python === null || ! is_file($script)) {
            throw new RuntimeException(
                'Extension PHP oci8 absente et script Python oracledb indisponible. '
                .'Installez oci8, ou : python3 -m venv storage/app/oracle-venv && storage/app/oracle-venv/bin/pip install oracledb'
            );
        }

        $c = $this->cfg();
        $payload = json_encode([
            'host' => (string) $c['host'],
            'port' => (int) ($c['port'] ?? 1522),
            'service_name' => (string) $c['service_name'],
            'username' => (string) $c['username'],
            'password' => (string) $c['password'],
            'schema' => (string) ($c['schema'] ?? 'CFSFCUBS145'),
            'accounts' => $accounts,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            throw new RuntimeException('Impossible d’encoder la requête Oracle.');
        }

        $result = Process::timeout(60)
            ->input($payload)
            ->run([$python, $script]);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput());
            $stdout = trim($result->output());
            Log::error('FlexcubeAccountTypeResolver: script Python échoué', [
                'exit' => $result->exitCode(),
                'stderr' => $stderr,
                'stdout' => $stdout,
            ]);
            throw new RuntimeException(
                'Résolution Oracle (Python) échouée : '.($stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'code '.$result->exitCode()))
            );
        }

        $decoded = json_decode($result->output(), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Réponse Oracle (Python) invalide.');
        }

        if (($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($decoded['error'] ?? 'Erreur Oracle inconnue'));
        }

        /** @var array<string, 'A'|'G'> $map */
        $map = [];
        foreach (($decoded['map'] ?? []) as $cpt => $type) {
            $key = trim((string) $cpt);
            $accorgl = strtoupper(trim((string) $type));
            if ($key !== '' && in_array($accorgl, ['A', 'G'], true)) {
                $map[$key] = $accorgl;
            }
        }

        return $map;
    }

    private function pythonBinary(): ?string
    {
        $candidates = [
            base_path('storage/app/oracle-venv/bin/python'),
            base_path('storage/app/oracle-venv/bin/python3'),
            'python3',
            'python',
        ];

        foreach ($candidates as $bin) {
            if (str_contains($bin, DIRECTORY_SEPARATOR)) {
                if (is_executable($bin)) {
                    return $bin;
                }
                continue;
            }

            $which = Process::run(['which', $bin]);
            if ($which->successful() && trim($which->output()) !== '') {
                return trim($which->output());
            }
        }

        return null;
    }

    private function safeSchema(string $schema): string
    {
        $schema = trim($schema);
        if ($schema === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9_$#]*$/', $schema)) {
            throw new RuntimeException('ORACLE_SCHEMA invalide.');
        }

        return $schema;
    }
}
