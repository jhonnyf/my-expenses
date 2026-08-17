<?php

namespace Database\Seeders;

use App\Actions\CreateDefaultCategoriesAction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'jhonnyf7@gmail.com',
            'password' => Hash::make('123123'),
        ]);

        app(CreateDefaultCategoriesAction::class)->execute($user);

        if (app()->isLocal()) {
            $this->call(VisualCheckUserSeeder::class);
        }
    }
}
