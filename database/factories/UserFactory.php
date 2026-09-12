<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'terms_accepted_at' => now(),
            'terms_version' => config('legal.current_terms_version'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user still needs to accept the current Terms/Privacy version.
     */
    public function withPendingTermsAcceptance(): static
    {
        return $this->state(fn (array $attributes) => [
            'terms_accepted_at' => null,
            'terms_version' => null,
        ]);
    }

    /**
     * Promove o usuário para o plano Pro. A assinatura Grátis já é criada
     * automaticamente pelo UserObserver, então aqui só atualizamos o plano.
     */
    public function pro(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->subscription()->update([
                'plan' => SubscriptionPlan::Pro,
                'status' => SubscriptionStatus::Active,
            ]);
        });
    }
}
