<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'loan_application_id' => 1,
            'user_id' => 1,
            'amount_paid' => 1000,
            'remaining_balance_after' => 4000,
            'reference_no' => 'REF-' . fake()->unique()->numerify('####'),
            'payment_date' => now(),
            'payment_method' => 'cash',
            'notes' => null,
            'encoded_by' => 1,
        ];
    }
}
