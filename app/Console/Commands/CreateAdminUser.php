<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {--name=} {--email=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée un nouvel utilisateur SuperAdmin (IT)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Création d\'un nouvel utilisateur SuperAdmin (IT)');
        $this->newLine();

        // Récupérer les paramètres ou demander interactivement
        $name = $this->option('name') ?: $this->ask('Nom complet');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Mot de passe (minimum 8 caractères)');
        $passwordConfirmation = $this->option('password') ?: $this->secret('Confirmer le mot de passe');

        // Valider les données
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            $this->error('Erreurs de validation :');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return Command::FAILURE;
        }

        // Vérifier que le rôle SuperAdmin (IT) existe
        $superAdminRole = Role::where('slug', 'it')->first();

        if (!$superAdminRole) {
            $this->error('Le rôle SuperAdmin n\'existe pas dans la base de données.');
            $this->info('Veuillez exécuter le seeder des rôles d\'abord : php artisan db:seed --class=RoleSeeder');
            return Command::FAILURE;
        }

        // Créer l'utilisateur
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'fonction' => 'SuperAdmin',
                'password_change_required' => false,
                'activated' => true,
            ]);

            // Assigner le rôle SuperAdmin
            $user->roles()->attach($superAdminRole->id);
            $user->syncAccessProfileFromRoles();
            $user->save();

            $this->newLine();
            $this->info('✓ Utilisateur SuperAdmin créé avec succès !');
            $this->info("  Nom: {$user->name}");
            $this->info("  Email: {$user->email}");
            $this->info("  Rôle: SuperAdmin");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la création de l\'utilisateur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
