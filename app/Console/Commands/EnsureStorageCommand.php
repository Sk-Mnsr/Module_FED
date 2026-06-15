<?php

namespace App\Console\Commands;

use App\Support\EnsureApplicationStorage;
use Illuminate\Console\Command;

class EnsureStorageCommand extends Command
{
    protected $signature = 'app:ensure-storage';

    protected $description = 'Crée les répertoires storage requis (logs, OD, cache, sessions…)';

    public function handle(): int
    {
        EnsureApplicationStorage::run();

        $this->info('Répertoires storage vérifiés.');

        if (! is_writable(storage_path('logs'))) {
            $this->warn('storage/logs n’est pas inscriptible par l’utilisateur courant.');
            $this->line('Sur le serveur : sudo DEPLOY_USER=support bash scripts/fix-storage-permissions.sh');
            $this->line('             sudo usermod -aG www-data support  # puis reconnexion SSH');
        }

        return self::SUCCESS;
    }
}
