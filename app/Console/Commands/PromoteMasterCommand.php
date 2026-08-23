<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PromoteMasterCommand extends Command
{
    protected $signature = 'hub:promote-master {email? : E-mail da conta existente} {--exclusive : Revoga o acesso master das demais contas}';

    protected $description = 'Promove uma conta existente ao acesso master do Hub';

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask('E-mail da conta'))));

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            $this->error('Informe um endereço de e-mail válido.');

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('Nenhuma conta foi encontrada com esse e-mail. Peça para a pessoa se cadastrar primeiro.');

            return self::FAILURE;
        }

        if ($this->option('exclusive')) {
            User::query()->where('is_admin', true)->whereKeyNot($user->id)->update(['is_admin' => false]);
        }

        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info("Acesso master concedido para {$user->email}.");
        $this->line('Recomendação: ative a autenticação em duas etapas em Configurações > Segurança.');

        return self::SUCCESS;
    }
}
