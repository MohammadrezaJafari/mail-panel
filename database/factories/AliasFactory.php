<?php

namespace Database\Factories;

use App\Models\Alias;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Alias> */
class AliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'organization_id' => fn (array $attrs) => Domain::find($attrs['domain_id'])->organization_id,
            'address' => fn (array $attrs) => fake()->unique()->userName().'@'.Domain::find($attrs['domain_id'])->name,
            'goto' => [fake()->safeEmail()],
            'is_active' => true,
        ];
    }
}
