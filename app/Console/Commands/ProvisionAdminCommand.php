<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProvisionAdminCommand extends Command
{
    protected $signature = 'hub:provision-admin';

    protected $description = 'Cria ou atualiza com segurança a conta administradora do Hub';

    public function handle(CreateTeam $createTeam): int
    {
        $name = trim((string) $this->ask('Nome do administrador'));
        $email = Str::lower(trim((string) $this->ask('E-mail do administrador')));
        $password = (string) $this->secret('Senha (mínimo 16 caracteres, maiúscula, minúscula, número e símbolo)');
        $passwordConfirmation = (string) $this->secret('Confirme a senha');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                Password::min(16)->mixedCase()->numbers()->symbols()->uncompromised(),
                'confirmed',
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $otherAdministrators = User::query()
            ->where('is_admin', true)
            ->whereRaw('LOWER(email) <> ?', [$email])
            ->count();

        if ($otherAdministrators > 0 && ! $this->confirm(
            "Revogar o acesso administrativo de {$otherAdministrators} outra(s) conta(s)?",
            true,
        )) {
            $this->warn('Nenhuma alteração foi realizada.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password, $otherAdministrators, $createTeam): User {
            if ($otherAdministrators > 0) {
                User::query()
                    ->where('is_admin', true)
                    ->whereRaw('LOWER(email) <> ?', [$email])
                    ->update(['is_admin' => false]);
            }

            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user) {
                $user->forceFill([
                    'name' => $name,
                    'password' => $password,
                    'is_admin' => true,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'is_admin' => true,
                    'email_verified_at' => now(),
                ]);
            }

            if (! $user->personalTeam()) {
                $createTeam->handle($user, 'Inovaforce', true);
            }

            return $user;
        });

        $this->newLine();
        $this->info("Administrador configurado: {$user->email}");
        $this->line('A senha não foi gravada no terminal nem no repositório.');
        $this->line('Após entrar, ative a autenticação em duas etapas em Configurações > Segurança.');

        return self::SUCCESS;
    }
}
