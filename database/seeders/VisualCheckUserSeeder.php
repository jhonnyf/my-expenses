<?php

namespace Database\Seeders;

use App\Actions\CreateFreeSubscriptionAction;
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
        $user = User::query()->firstOrCreate(
            ['email' => 'visual-check@local.test'],
            [
                'name' => 'Visual Check',
                'password' => Hash::make('visual-check-password'),
                'email_verified_at' => now(),
            ]
        );

        // DatabaseSeeder roda com WithoutModelEvents, então o UserObserver não dispara aqui.
        app(CreateFreeSubscriptionAction::class)->execute($user);
    }
}
