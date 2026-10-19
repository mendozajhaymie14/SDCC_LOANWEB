<?php

namespace Database\Factories;

use App\Models\CoopMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoopMemberFactory extends Factory
{
    protected $model = CoopMember::class;

    public function definition(): array
    {
        return [
            'member_id' => fake()->unique()->numerify('MEM#######'),
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'email' => fake()->unique()->safeEmail(),
            'is_registered' => true,
            'status' => 'Active',
            'user_id' => User::factory(),
            'approved_credit_limit' => fake()->randomNumber(5),
            'share_capital' => fake()->randomNumber(5),
            'savings_balance' => fake()->randomNumber(5),
            'is_migs' => false,
        ];
    }
}