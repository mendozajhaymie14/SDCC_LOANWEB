<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempEnvDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump_env(): void
    {
        fwrite(STDERR, "\n=== ENV DIAGNOSTIC ===\n");
        fwrite(STDERR, "app()->environment()=" . app()->environment() . "\n");
        fwrite(STDERR, "app()->runningUnitTests()=" . var_export(app()->runningUnitTests(), true) . "\n");
        fwrite(STDERR, "config('database.default')=" . config('database.default') . "\n");
        fwrite(STDERR, "config('database.connections.sqlite.database')=" . config('database.connections.sqlite.database') . "\n");
        fwrite(STDERR, "config('app.debug')=" . var_export(config('app.debug'), true) . "\n");
        fwrite(STDERR, "session.driver=" . config('session.driver') . "\n");
        fwrite(STDERR, "APP_ENV from env()=" . env('APP_ENV') . "\n");
        fwrite(STDERR, "=== END DIAGNOSTIC ===\n\n");

        $this->assertTrue(true);
    }
}