<?php

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Domain> */
class DomainFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->domainName(),
            'status' => DomainStatus::Active,
        ];
    }
}
