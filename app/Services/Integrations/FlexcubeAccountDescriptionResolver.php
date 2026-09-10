<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Intitulés de comptes Flexcube (colonne « Compte » pièce OD).
 *
 * Comptes clients :
 *   SELECT CUST_AC_NO AS CPT, AC_DESC AS LIBELLE
 *   FROM {schema}.STTM_CUST_ACCOUNT WHERE CUST_AC_NO IN (...)
 *
 * Comptes GL :
 *   SELECT GL_CODE AS CPT, GL_DESC AS LIBELLE
 *   FROM {schema}.GLTM_GLMASTER WHERE GL_CODE IN (...)
 *
 * Drivers : oci8 puis Python oracledb thin (comme Accorgl).
 */
final class FlexcubeAccountDescriptionResolver
{
    /**
     * @param  list<string>  $accounts
     * @return array<string, string> map n° compte → AC_DESC
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

        if (! $this->isEnabled() || ! $this->isConfigured()) {
            return [];
        }

        try {
            if (extension_loaded('oci8')) {
                return $this->queryOci8($accounts);
            }

            return $this->queryPython($accounts);
        } catch (\Throwable $e) {
            Log::warning('FlexcubeAccountDescriptionResolver: échec résolution intitulés', [
                'error' => $e->getMessage(),
                'accounts_count' => count($accounts),
            ]);

            return [];
        }
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

    /**
     * @return array<string, mixed>
     */
    private function cfg(): array
    {
        return config('services.oracle_flexcube', []);
    }

    /**
     * @param  list<string>  $accounts
     * @return array<string, string>
     */
    private function queryOci8(array $accounts): array
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
SELECT CUST_AC_NO AS CPT, AC_DESC AS LIBELLE
FROM {$schema}.STTM_CUST_ACCOUNT
WHERE CUST_AC_NO IN ({$inList})
UNION ALL
SELECT GL_CODE AS CPT, GL_DESC AS LIBELLE
FROM {$schema}.GLTM_GLMASTER
WHERE GL_CODE IN ({$inList})
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

            if (@oci_execute($stid) === false) {
                $err = oci_error($stid);
                throw new RuntimeException(
                    'Oracle execute error : '.(is_array($err) ? ($err['message'] ?? 'inconnu') : 'inconnu')
                );
            }

            $map = [];
            while (($row = oci_fetch_assoc($stid)) !== false) {
                $cpt = trim((string) ($row['CPT'] ?? ''));
                $libelle = trim((string) ($row['LIBELLE'] ?? ''));
                // Premier gagnant (client puis GL) : on ne remplace pas un intitulé déjà trouvé.
                if ($cpt !== '' && $libelle !== '' && ! isset($map[$cpt])) {
                    $map[$cpt] = $libelle;
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
     * @return array<string, string>
     */
    private function queryPython(array $accounts): array
    {
        $python = $this->pythonBinary();
        $script = base_path('scripts/flexcube_account_desc_resolve.py');

        if ($python === null || ! is_file($script)) {
            throw new RuntimeException(
                'Extension PHP oci8 absente et script Python oracledb indisponible pour les intitulés de comptes.'
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
            throw new RuntimeException('Impossible d’encoder la requête Oracle (intitulés).');
        }

        $result = Process::timeout(60)
            ->input($payload)
            ->run([$python, $script]);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput());
            $stdout = trim($result->output());
            throw new RuntimeException(
                'Résolution intitulés Oracle (Python) échouée : '
                .($stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'code '.$result->exitCode()))
            );
        }

        $decoded = json_decode($result->output(), true);
        if (! is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($decoded['error'] ?? 'Réponse Oracle intitulés invalide'));
        }

        $map = [];
        foreach (($decoded['map'] ?? []) as $cpt => $libelle) {
            $key = trim((string) $cpt);
            $desc = trim((string) $libelle);
            if ($key !== '' && $desc !== '') {
                $map[$key] = $desc;
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
