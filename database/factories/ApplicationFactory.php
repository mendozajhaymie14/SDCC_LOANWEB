<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'loan_application_id' => null,
            'app_id' => 'APP-' . fake()->unique()->numerify('####'),
            'applicant' => fake()->name(),
            'loan_type' => 'regular',
            'amount' => 50000,
            'term_months' => 12,
            'status' => 'Pending',
            'ai_score' => null,
            'recommended_amount' => 0,
            'risk_level' => 'None',
            'risk_score' => 0,
            'risk_factors' => null,
        ];
    }
}
