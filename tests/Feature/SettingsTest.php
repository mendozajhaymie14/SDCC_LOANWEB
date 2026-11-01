<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin()
    {
        return User::factory()->create([
            'usertype' => 'admin',
            'email'    => 'admin@example.com',
        ]);
    }

    /** @test */
    public function editable_settings_can_be_read_from_setting_model()
    {
        // Seed a cooperative setting
        Setting::set('cooperative_name', 'Test Coop');

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Test Coop');
    }

    /** @test */
    public function templates_can_be_saved_and_rendered_with_placeholders()
    {
        $admin = $this->makeAdmin();

        // Save a template with placeholders
        Setting::set('template_approval', 'Approved {member_name} for ₱{amount}');

        $loan = \App\Models\LoanApplication::factory()->create([
            'user_id' => User::factory()->create(['email' => 'test@example.com'])->id,
            'full_name' => 'Test Borrower',
            'amount' => 5000,
        ]);

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Approved');
        $response->assertSee('{member_name}');
    }

    /** @test */
    public function readonly_settings_are_displayed_from_config()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Default Term');
        $response->assertSee('Penalty Rate');
        $response->assertSee('Minimum Score');
        $response->assertSee('High Risk Threshold');
        $response->assertSee('Grace Period');
        $response->assertSee('Reminder Frequency');
    }

    /** @test */
    public function setting_model_save_works_with_validation()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->post(route('settings.update'), [
                'cooperative_name'     => 'Updated Coop',
                'cooperative_email'    => 'updated@example.com',
                'cooperative_address'  => 'Updated Address',
                'cooperative_phone'    => '09171234567',
                'annual_interest_rate' => '15',
            ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Coop', Setting::get('cooperative_name'));
        $this->assertEquals('15', Setting::get('annual_interest_rate'));
    }
}