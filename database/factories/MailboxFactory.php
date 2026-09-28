<?php

namespace Database\Factories;

use App\Enums\MailboxStatus;
use App\Models\Domain;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mailbox> */
class MailboxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'organization_id' => fn (array $attrs) => Domain::find($attrs['domain_id'])->organization_id,
            'local_part' => fake()->unique()->userName(),
            'address' => fn (array $attrs) => $attrs['local_part'].'@'.Domain::find($attrs['domain_id'])->name,
            'name' => fake()->name(),
            'status' => MailboxStatus::Active,
            'quota_mb' => 5120,
        ];
    }
}
