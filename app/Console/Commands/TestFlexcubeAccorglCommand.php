<?php

namespace App\Console\Commands;

use App\Services\Integrations\FlexcubeAccountTypeResolver;
use Illuminate\Console\Command;

class TestFlexcubeAccorglCommand extends Command
{
    protected $signature = 'flexcube:test-accorgl
                            {comptes?* : Numéros de compte à résoudre (sinon exemples)}';

    protected $description = 'Teste la connexion Oracle Flexcube et la résolution accorgl (A/G)';

    public function handle(FlexcubeAccountTypeResolver $resolver): int
    {
        $this->info('Configuration Oracle Flexcube');
        $cfg = config('services.oracle_flexcube', []);
        $this->table(['Clé', 'Valeur'], [
            ['enabled', config('services.flexcube_online_journal.accorgl_resolve_oracle') ? 'true' : 'false'],
            ['host', $cfg['host'] ?? ''],
            ['port', $cfg['port'] ?? ''],
            ['service_name', $cfg['service_name'] ?? ''],
            ['username', $cfg['username'] ?? ''],
            ['password', filled($cfg['password'] ?? null) ? '***' : '(vide)'],
            ['schema', $cfg['schema'] ?? ''],
            ['driver', $resolver->driverLabel()],
            ['oci8', extension_loaded('oci8') ? 'chargé' : 'absent (fallback Python OK)'],
            ['configured', $resolver->isConfigured() ? 'oui' : 'non'],
        ]);

        if (! $resolver->isEnabled()) {
            $this->warn('ORACLE_ACCORGL_ENABLED=false — résolution désactivée (fallback FLEXCUBE_JOURNAL_ACCORGL).');

            return self::SUCCESS;
        }

        if (! $resolver->isConfigured()) {
            $this->error('Configuration Oracle incomplète.');

            return self::FAILURE;
        }

        if ($resolver->driverLabel() === 'aucun') {
            $this->error('Aucun driver Oracle : installez oci8 ou le venv Python (storage/app/oracle-venv + oracledb).');

            return self::FAILURE;
        }

        $accounts = $this->argument('comptes');
        if ($accounts === []) {
            $accounts = ['379200000180', '114000248003', '390000000001', '379200002226'];
            $this->comment('Aucun compte passé — utilisation des exemples du cahier de charges.');
        }

        try {
            $map = $resolver->resolveMany($accounts);
        } catch (\Throwable $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($accounts as $account) {
            $rows[] = [
                $account,
                $map[$account] ?? 'INTROUVABLE',
                isset($map[$account])
                    ? ($map[$account] === 'G' ? 'GL (GLTM_GLMASTER)' : 'Compte client (STTM_CUST_ACCOUNT)')
                    : 'Absent des 2 tables',
            ];
        }

        $this->table(['Compte', 'accorgl', 'Source'], $rows);
        $this->info('OK — résolution terminée.');

        return self::SUCCESS;
    }
}
