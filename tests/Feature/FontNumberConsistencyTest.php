<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Part 4 guard: font & number consistency across the four admin screens
 * (loan applications modal/detail, memberships modal, active members list
 * and modal).
 *
 * Fails if any screened blade file — or the footer JS they share —
 * reintroduces a monospace font, a mono-named table class, or the old
 * "Available Monthly" label, or drops the shared .num helper / fmtMoney.
 */
class FontNumberConsistencyTest extends TestCase
{
    private const SCREEN_FILES = [
        'resources/views/admin/partials/application-modal.blade.php',
        'resources/views/admin/application-detail.blade.php',
        'resources/views/admin/memberships.blade.php',
        'resources/views/admin/activemember.blade.php',
        'resources/views/admin/footer.blade.php',
        'resources/views/admin/css.blade.php',
    ];

    /** @test */
    public function no_screen_reintroduces_monospace_the_old_label_or_a_mono_class()
    {
        foreach (self::SCREEN_FILES as $file) {
            $html = file_get_contents(base_path($file));
            $this->assertStringNotContainsStringIgnoringCase(
                'monospace', $html, "{$file} reintroduced a monospace font"
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'ui-monospace', $html, "{$file} reintroduced a ui-monospace stack"
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'courier', $html, "{$file} reintroduced a Courier font"
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'consolas', $html, "{$file} reintroduced a Consolas font"
            );
            $this->assertStringNotContainsString(
                'td-mono', $html, "{$file} still uses the mono-named td-mono class"
            );
            $this->assertStringNotContainsString(
                'Available Monthly', $html, "{$file} still uses the old \"Available Monthly\" label"
            );
        }
    }

    /** @test */
    public function the_shared_num_class_and_fmtmoney_helper_still_exist()
    {
        $css = file_get_contents(base_path('resources/views/admin/css.blade.php'));
        $this->assertStringContainsString('.num {', $css, 'css.blade.php lost the shared .num class');
        $this->assertStringContainsString(
            'font-variant-numeric: tabular-nums;', $css, '.num lost tabular-nums'
        );

        $modal = file_get_contents(
            base_path('resources/views/admin/partials/application-modal.blade.php')
        );
        $this->assertStringContainsString(
            'function fmtMoney(', $modal, 'the shared JS money helper fmtMoney is gone'
        );
    }

    /** @test */
    public function every_screen_formats_money_through_the_shared_helpers()
    {
        // PHP screens use Format::money; JS-built strings use fmtMoney.
        $phpScreens = [
            'resources/views/admin/application-detail.blade.php',
            'resources/views/admin/activemember.blade.php',
        ];
        foreach ($phpScreens as $file) {
            $html = file_get_contents(base_path($file));
            $this->assertStringContainsString(
                'Format::money', $html, "{$file} builds money by hand instead of Format::money"
            );
        }

        $jsScreens = [
            'resources/views/admin/partials/application-modal.blade.php',
            'resources/views/admin/memberships.blade.php',
            'resources/views/admin/footer.blade.php',
        ];
        foreach ($jsScreens as $file) {
            $html = file_get_contents(base_path($file));
            $this->assertStringContainsString(
                'fmtMoney(', $html, "{$file} builds money strings by hand instead of fmtMoney"
            );
        }
    }
}
