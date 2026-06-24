<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'     => 'Test User',
            'email'    => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }
}
