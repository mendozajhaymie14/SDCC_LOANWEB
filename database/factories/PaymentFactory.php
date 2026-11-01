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
            'loan_application_id' => null,
            'user_id' => null,
            'amount_paid' => 1000,
            'remaining_balance_after' => 0,
            'reference_no' => null,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'notes' => null,
            'encoded_by' => null,
        ];
    }
}
