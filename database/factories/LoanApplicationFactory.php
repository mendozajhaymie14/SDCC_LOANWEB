<?php

namespace Database\Factories;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApplicationFactory extends Factory
{
    protected $model = LoanApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'contact_number' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'birth_date' => now()->subYears(30),
            'civil_status' => 'Single',
            'employment_status' => 'Employed',
            'employer_name' => 'Test Company',
            'source_of_income' => 'Salary',
            'monthly_income' => 50000,
            'loan_type' => 'regular',
            'purpose_of_loan' => 'Personal expenses',
            'amount' => 50000,
            'share_capital' => 30000,
            'term_months' => 12,
            'status' => 'pending',
            'reference' => LoanApplication::makeReference(),
            'remaining_balance' => 0,
            'next_due_date' => null,
            'recommended_amount' => 0,
            'risk_level' => 'None',
            'risk_score' => 0,
            'risk_factors' => null,
        ];
    }
}
