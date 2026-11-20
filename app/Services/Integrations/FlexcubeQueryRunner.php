<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Exécute une requête SELECT paramétrée sur Oracle Flexcube, en lecture seule.
 * Les valeurs saisies passent uniquement par des variables liées (:NOM), jamais par concaténation.
 *
 * Drivers : oci8, sinon Python oracledb thin (scripts/flexcube_query.py).
 */
class FlexcubeQueryRunner
{
    private const MOTS_INTERDITS = [
        'INSERT', 'UPDATE', 'DELETE', 'MERGE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT',
        'REVOKE', 'EXECUTE', 'EXEC', 'BEGIN', 'DECLARE', 'CALL', 'COMMIT', 'ROLLBACK', 'LOCK',
        'RENAME', 'COMMENT', 'PURGE', 'FLASHBACK',
    ];

    /**
     * @param  array<string, scalar|null>  $binds
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $binds = [], int $maxRows = 50): array
    {
        $sql = self::assertLectureSeule($sql);
        $attendus = self::bindNames($sql);

        $valeurs = [];
        foreach ($attendus as $nom) {
            if (! array_key_exists($nom, $binds)) {
                throw new RuntimeException("Valeur manquante pour le paramètre :{$nom} de la requête.");
            }
            $valeurs[$nom] = $binds[$nom] === null ? null : (string) $binds[$nom];
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Connexion Flexcube non configurée (ORACLE_HOST, ORACLE_SERVICE_NAME, ORACLE_USER, ORACLE_PASSWORD).');
        }

        try {
            return extension_loaded('oci8')
                ? $this->selectOci8($sql, $valeurs, $maxRows)
                : $this->selectPython($sql, $valeurs, $maxRows);
        } catch (RuntimeException $e) {
            Log::warning('FlexcubeQueryRunner: requête en échec', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function isConfigured(): bool
    {
        $c = config('services.oracle_flexcube', []);

        return filled($c['host'] ?? null)
            && filled($c['service_name'] ?? null)
            && filled($c['username'] ?? null)
            && filled($c['password'] ?? null);
    }

    /**
     * Refuse tout ce qui n’est pas une requête SELECT / WITH unique.
     */
    public static function assertLectureSeule(string $sql): string
    {
        $sql = trim($sql);
        $sql = rtrim($sql, " \t\n\r\0\x0B;");
        $sansCommentaires = preg_replace(['~/\*.*?\*/~s', '~--[^\n]*~'], ' ', $sql) ?? $sql;
        $sansChaines = preg_replace("~'(?:[^']|'')*'~", "''", $sansCommentaires) ?? $sansCommentaires;
        $normalise = strtoupper((string) preg_replace('/\s+/', ' ', trim($sansChaines)));

        if ($normalise === '' || ! preg_match('/^(SELECT|WITH)\b/', $normalise)) {
            throw new RuntimeException('Seules les requêtes SELECT (ou WITH … SELECT) sont autorisées.');
        }
        if (str_contains($sansChaines, ';')) {
            throw new RuntimeException('Une seule requête est autorisée (pas de « ; » au milieu).');
        }
        foreach (self::MOTS_INTERDITS as $mot) {
            if (preg_match('/\b'.$mot.'\b/', $normalise)) {
                throw new RuntimeException("Mot-clé interdit dans la requête : {$mot}.");
            }
        }
        if (preg_match('/\bFOR\s+UPDATE\b/', $normalise)) {
            throw new RuntimeException('FOR UPDATE est interdit.');
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    public static function bindNames(string $sql): array
    {
        $sansChaines = preg_replace(["~'(?:[^']|'')*'~", '~/\*.*?\*/~s', '~--[^\n]*~'], ' ', $sql) ?? $sql;
        preg_match_all('/(?<![:\w]):([A-Za-z][A-Za-z0-9_]*)/', $sansChaines, $m);

        return array_values(array_unique(array_map('strtoupper', $m[1])));
    }

    /**
     * @param  array<string, string|null>  $binds
     * @return list<array<string, mixed>>
     */
    private function selectOci8(string $sql, array $binds, int $maxRows): array
    {
        $c = config('services.oracle_flexcube', []);
        $ezConnect = '//'.$c['host'].':'.($c['port'] ?? '1522').'/'.$c['service_name'];
        $conn = @oci_connect((string) $c['username'], (string) $c['password'], $ezConnect, (string) ($c['charset'] ?? 'AL32UTF8'));
        if ($conn === false) {
            $err = oci_error();
            throw new RuntimeException('Connexion Oracle Flexcube impossible : '.(is_array($err) ? ($err['message'] ?? 'inconnue') : 'inconnue'));
        }

        try {
            $schema = $this->schema();
            if ($schema !== '') {
                $alter = oci_parse($conn, 'ALTER SESSION SET CURRENT_SCHEMA = '.$schema);
                if ($alter !== false) {
                    @oci_execute($alter);
                }
            }

            $ro = oci_parse($conn, 'SET TRANSACTION READ ONLY');
            if ($ro !== false) {
                @oci_execute($ro, OCI_NO_AUTO_COMMIT);
            }

            $stid = oci_parse($conn, $sql);
            if ($stid === false) {
                $err = oci_error($conn);
                throw new RuntimeException('Requête invalide : '.(is_array($err) ? ($err['message'] ?? 'inconnue') : 'inconnue'));
            }
            foreach ($binds as $nom => $valeur) {
                oci_bind_by_name($stid, ':'.$nom, $binds[$nom], 4000);
            }
            if (@oci_execute($stid, OCI_NO_AUTO_COMMIT) === false) {
                $err = oci_error($stid);
                throw new RuntimeException('Erreur Oracle : '.(is_array($err) ? ($err['message'] ?? 'inconnue') : 'inconnue'));
            }

            $rows = [];
            // oci_fetch_assoc() n'accepte qu'un argument : oci_fetch_array() permet de garder les colonnes NULL.
            while (count($rows) < $maxRows && ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) !== false) {
                $rows[] = array_change_key_case($row, CASE_UPPER);
            }
            oci_free_statement($stid);
            oci_rollback($conn);

            return $rows;
        } finally {
            oci_close($conn);
        }
    }

    /**
     * @param  array<string, string|null>  $binds
     * @return list<array<string, mixed>>
     */
    private function selectPython(string $sql, array $binds, int $maxRows): array
    {
        $python = $this->pythonBinary();
        $script = base_path('scripts/flexcube_query.py');
        if ($python === null || ! is_file($script)) {
            throw new RuntimeException(
                'Extension PHP oci8 absente et script Python oracledb indisponible. '
                .'Installez oci8, ou : python3 -m venv storage/app/oracle-venv && storage/app/oracle-venv/bin/pip install oracledb'
            );
        }

        $c = config('services.oracle_flexcube', []);
        $payload = json_encode([
            'host' => (string) $c['host'],
            'port' => (int) ($c['port'] ?? 1522),
            'service_name' => (string) $c['service_name'],
            'username' => (string) $c['username'],
            'password' => (string) $c['password'],
            'schema' => $this->schema(),
            'sql' => $sql,
            'binds' => (object) $binds,
            'max_rows' => $maxRows,
            'timeout_ms' => 30000,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            throw new RuntimeException('Impossible d’encoder la requête Oracle.');
        }

        $result = Process::timeout(60)->input($payload)->run([$python, $script]);
        $decoded = json_decode(trim($result->output()), true);

        if (! is_array($decoded)) {
            $stderr = trim($result->errorOutput());
            throw new RuntimeException('Requête Flexcube échouée : '.($stderr !== '' ? $stderr : 'réponse invalide'));
        }
        if (($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException('Requête Flexcube échouée : '.(string) ($decoded['error'] ?? 'erreur inconnue'));
        }

        return array_values(array_map(
            fn ($row) => array_change_key_case((array) $row, CASE_UPPER),
            $decoded['rows'] ?? [],
        ));
    }

    private function schema(): string
    {
        $schema = trim((string) (config('services.oracle_flexcube.schema') ?? ''));
        if ($schema !== '' && ! preg_match('/^[A-Za-z][A-Za-z0-9_$#]*$/', $schema)) {
            throw new RuntimeException('ORACLE_SCHEMA invalide.');
        }

        return $schema;
    }

    private function pythonBinary(): ?string
    {
        foreach ([base_path('storage/app/oracle-venv/bin/python'), base_path('storage/app/oracle-venv/bin/python3')] as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }

        foreach (['python3', 'python'] as $bin) {
            $which = Process::run(['which', $bin]);
            if ($which->successful() && trim($which->output()) !== '') {
                return trim($which->output());
            }
        }

        return null;
    }
}
