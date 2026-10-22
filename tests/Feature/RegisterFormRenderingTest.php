<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * The registration form must render as real HTML.
 *
 * Two bugs hid behind this page once:
 *
 *  1. An escaped quote inside the double-quoted `x-data` attribute closed
 *     the attribute early, so Alpine never parsed the component.
 *  2. A `@if` inside the `<x-input>` component tag stopped Blade from
 *     compiling the component, so the e-mail field was emitted as a literal
 *     `<x-input>` element instead of an `<input>`.
 */
class RegisterFormRenderingTest extends TestCase
{
    private function html(): string
    {
        $this->get('/register')->assertOk();

        return $this->get('/register')->getContent();
    }

    public function test_the_email_field_is_a_real_input_element(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringNotContainsString('<x-input', $html, 'A component tag was left uncompiled.');
    }

    public function test_the_x_data_attribute_is_not_cut_short_by_an_escaped_quote(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('meta[name=\"csrf-token\"]', $html);
        $this->assertStringContainsString('meta[name=csrf-token]', $html);
    }

    public function test_both_choices_and_their_fields_are_present(): void
    {
        $html = $this->html();

        foreach (['member_id', 'member_code', 'first_name', 'middle_name', 'last_name', 'email', 'password', 'password_confirmation'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "Missing field [{$id}].");
        }

        $this->assertStringContainsString('name="is_existing_member"', $html);
        $this->assertStringContainsString('Apply for membership here', $html);
    }

    public function test_the_gmail_hint_follows_the_config_switch(): void
    {
        Config::set('sdcc.gmail_only_email', true);
        $this->assertStringContainsString('pattern=".+@gmail\.com"', $this->html());

        Config::set('sdcc.gmail_only_email', false);
        $this->assertStringNotContainsString('pattern=".+@gmail\.com"', $this->html());
    }
}
