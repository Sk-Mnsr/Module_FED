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
            $this->line('Sur le serveur : sudo chown -R www-data:www-data storage bootstrap/cache');
            $this->line('             sudo chmod -R ug+rwx storage bootstrap/cache');
        }

        return self::SUCCESS;
    }
}
