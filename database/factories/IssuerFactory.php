<?php

namespace Database\Factories;

use App\Models\Issuer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issuer>
 */
class IssuerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cnpj'          => fake()->numerify('##.###.###/####-##'),
            'name'          => fake()->company(),
            'street'        => fake()->streetName(),
            'street_number' => fake()->buildingNumber(),
            'neighborhood'  => fake()->word(),
            'city'          => fake()->city(),
            'state'         => fake()->stateAbbr(),
            'zip_code'      => fake()->numerify('########'),
        ];
    }
}
