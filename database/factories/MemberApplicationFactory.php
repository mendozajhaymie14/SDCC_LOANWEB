<?php

namespace Database\Factories;

use App\Models\MemberApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberApplicationFactory extends Factory
{
    protected $model = MemberApplication::class;

    public function definition(): array
    {
        return [
            'surname' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->firstName(),
            'house_no' => fake()->buildingNumber(),
            'street' => fake()->streetName(),
            'barangay' => fake()->word(),
            'municipality' => fake()->city(),
            'province' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'date_of_birth' => now()->subYears(rand(25, 60)),
            'monthly_income' => 50000,
            'existing_monthly_obligations' => 1500,
            'savings_balance' => 0,
            'stay_years' => 5,
            'stay_months' => 3,
            'perm_street' => fake()->streetName(),
            'perm_barangay' => fake()->word(),
            'perm_municipality' => fake()->city(),
            'perm_province' => fake()->state(),
            'perm_zip_code' => fake()->postcode(),
            'perm_stay_years' => 3,
            'perm_stay_months' => 2,
            'residency_type' => 'owned',
            'contact_number' => '09' . fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'nationality' => 'Filipino',
            'place_of_birth' => fake()->city(),
            'occupation' => fake()->jobTitle(),
            'id_type' => 'drivers_license',
            'id_number' => fake()->numerify('##########'),
            'id_front_path' => 'test/front-' . fake()->uuid() . '.jpg',
            'id_back_path' => 'test/back-' . fake()->uuid() . '.jpg',
            'status' => 'pending',
            'reviewed_at' => null,
            'reviewed_by' => null,
            'approved_member_id' => null,
            'user_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }
}