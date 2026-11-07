<?php

namespace Tests\Feature;

use App\Models\MemberApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /memberships must ship every script block as syntactically valid JavaScript
 * and must expose every onclick handler as a real global function.
 *
 * Root cause of "Uncaught ReferenceError: openMemberDetail is not defined":
 * the closing </script> tag had been dropped, so the browser parsed the
 * remainder of the document as raw script source and never defined anything.
 */
class MembershipsScriptIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);
    }

    /**
     * @return array<int, string>  script source blocks in document order
     */
    private function scriptBlocks(string $html): array
    {
        preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/i', $html, $m);

        return $m[1];
    }

    /**
     * @return array<int, string>  onclick handler names referenced in markup
     */
    private function onclickHandlers(string $html): array
    {
        preg_match_all('/onclick\s*=\s*"([^"]*)"/', $html, $m);

        $names = [];
        foreach ($m[1] as $expr) {
            // onclick="foo(...)" or onclick="if(confirm(...))foo(...)" -> first identifier
            // Skip things that are property accesses (event.stopPropagation) or keywords (if)
            if (preg_match('/(?<!\\w)([A-Za-z_$][\w$]*)\s*\(/', $expr, $n)) {
                $name = $n[1];
                // Filter out: event methods, DOM methods, keywords
                if (! in_array($name, ['event', 'stopPropagation', 'getElementById', 'remove', 'if', 'confirm'], true)) {
                    $names[] = $name;
                }
            }
        }

        return array_unique($names);
    }

    private function definedGlobals(string $html): array
    {
        // function name(...) {  OR  window.name = function ...
        preg_match_all('/(?:function\s+([A-Za-z_$][\w$]*)\s*\(|window\.([A-Za-z_$][\w$]*)\s*=)/', $html, $m);

        return array_unique(array_merge($m[1], $m[2]));
    }

    public function test_memberships_page_ships_valid_scripts_and_global_handlers(): void
    {
        $manager = User::factory()->asBranchManager()->create();
        $html = $this->actingAs($manager)->get('/memberships')->getContent();

        // 1. Every <script> block passes node --check.
        foreach ($this->scriptBlocks($html) as $i => $src) {
            $this->assertScriptValid($src, "script block #{$i}");
        }

        // 2. The View button's handler is defined as a top-level global function.
        $this->assertStringContainsString(
            'onclick="openMemberDetail(',
            $html,
            'View button must still call openMemberDetail'
        );

        $globals = $this->definedGlobals($html);

        $this->assertContains('openMemberDetail', $globals,
            'openMemberDetail must be a defined global function, not a string trapped in an unclosed script');

        // 3. Every onclick handler on the page resolves to a defined global.
        $missing = [];
        foreach ($this->onclickHandlers($html) as $name) {
            if (! in_array($name, $globals, true)) {
                $missing[] = $name;
            }
        }

        $this->assertEmpty($missing,
            'onclick handlers with no defined global: ' . implode(', ', $missing));
    }

    /**
     * Run the JS source through node --check. Skip gracefully when node is absent.
     */
    private function assertScriptValid(string $src, string $label): void
    {
        $node = trim((string) shell_exec('node --version 2>NUL'));
        if ($node === '') {
            $this->markTestSkipped('node is not installed; cannot syntax-check script blocks');
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sdcc_js_') . '.js';
        file_put_contents($tmp, $src);

        $out = shell_exec('node --check ' . escapeshellarg($tmp) . ' 2>&1');
        @unlink($tmp);

        $this->assertNull($out, "{$label} failed node --check:\n{$out}");
    }
}