<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cpf' => null,
            'cnpj' => null,
            'cidade' => fake()->city(),
            'estado' => fake()->stateAbbr(),
            'latitude' => null,
            'longitude' => null,
        ];
    }

    public function withCoordinates(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
