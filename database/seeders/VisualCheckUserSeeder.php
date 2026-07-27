<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuário fixo para checagens visuais automatizadas (Playwright).
 * Nunca deve rodar fora de ambiente local — ver guard em DatabaseSeeder.
 */
class VisualCheckUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'visual-check@local.test'],
            [
                'name' => 'Visual Check',
                'password' => Hash::make('visual-check-password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
