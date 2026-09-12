<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptUserProfileDocuments extends Command
{
    protected $signature = 'user-profiles:encrypt-documents {--dry-run : Apenas conta quantos perfis seriam alterados, sem gravar}';

    protected $description = 'Criptografa CPF/CNPJ já existentes em users_profiles e recalcula os hashes de deduplicação';

    /**
     * Usa DB::table (não o model Eloquent) de propósito: assim o comando
     * funciona independentemente de o cast `encrypted` já estar ativo no
     * model, e evita descriptografar acidentalmente um valor que já foi
     * migrado (rodar o comando duas vezes precisa ser seguro/idempotente).
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $affected = 0;

        DB::table('users_profiles')
            ->select('id', 'cpf', 'cnpj')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $row) use ($dryRun, &$affected) {
                $update = [];

                if ($row->cpf !== null && ! $this->alreadyEncrypted($row->cpf)) {
                    $update['cpf'] = Crypt::encryptString($row->cpf);
                    $update['cpf_hash'] = hash_hmac('sha256', $row->cpf, config('app.key'));
                }

                if ($row->cnpj !== null && ! $this->alreadyEncrypted($row->cnpj)) {
                    $update['cnpj'] = Crypt::encryptString($row->cnpj);
                    $update['cnpj_hash'] = hash_hmac('sha256', $row->cnpj, config('app.key'));
                }

                if ($update === []) {
                    return;
                }

                $affected++;

                if (! $dryRun) {
                    DB::table('users_profiles')->where('id', $row->id)->update($update);
                }
            });

        $action = $dryRun ? 'seriam alterados' : 'alterados';
        $this->info("{$affected} perfil(is) {$action}.");

        return self::SUCCESS;
    }

    private function alreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
