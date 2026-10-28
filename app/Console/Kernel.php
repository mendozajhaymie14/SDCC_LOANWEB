<?php

namespace App\Console;

use App\Exceptions\ProtectedDatabaseException;
use Illuminate\Foundation\Console\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    protected $commands = [];

    protected function shouldDiscoverCommands(): bool
    {
        return true;
    }

    /**
     * Run an Artisan console command.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     * @return int
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        $this->bootstrap();
        $this->guardProtectedDatabase($command, $parameters);

        return parent::call($command, $parameters, $outputBuffer);
    }

    /**
     * Run an Artisan console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $output
     * @return int
     */
    public function handle($input, $output = null)
    {
        // Bootstrap the container so config() / Facades exist before guarding.
        // parent::handle() re-bootstraps (cached) then delegates to call().
        $this->bootstrap();

        try {
            $this->guardProtectedDatabaseFromInput($input);
        } catch (ProtectedDatabaseException $e) {
            $out = $output ?? new \Symfony\Component\Console\Output\ConsoleOutput();
            $out->writeln('<error>' . $e->getMessage() . '</error>');

            return ProtectedDatabaseException::EXIT_CODE;
        }

        return parent::handle($input, $output);
    }

    /**
     * Block destructive commands against protected databases.
     *
     * Works for both real CLI (handle()) and tests (call/$this->artisan()).
     */
    protected function guardProtectedDatabase(string $command, array $parameters): void
    {
        $this->checkBlocked($command, $parameters);
    }

    /**
     * Block destructive commands against protected databases.
     *
     * Works for real CLI (handle()).
     */
    protected function guardProtectedDatabaseFromInput($input): void
    {
        $command = $input->getFirstArgument();
        $parameters = [];
        if ($input->hasOption('pretend')) {
            $parameters['pretend'] = (bool) $input->getOption('pretend');
        }
        if ($input->hasOption('dry-run')) {
            $parameters['dry-run'] = (bool) $input->getOption('dry-run');
        }

        $this->checkBlocked($command, $parameters);
    }

    /**
     * Shared guard logic.
     */
    protected function checkBlocked(?string $command, array $parameters): void
    {
        if ($command === null || $command === '') {
            return;
        }

        $blockedCommands = [
            'migrate:fresh', 'migrate:refresh', 'migrate:reset',
            'migrate:rollback', 'db:wipe', 'db:seed', 'schema:dump',
        ];

        $isBlockedCommand = in_array($command, $blockedCommands, true);

        // Plain migrate without --pretend or --dry-run is also blocked
        $isPlainMigrate = $command === 'migrate'
            && (! isset($parameters['pretend']) || ! $parameters['pretend'])
            && (! isset($parameters['dry-run']) || ! $parameters['dry-run']);

        if (! $isBlockedCommand && ! $isPlainMigrate) {
            return;
        }

        // ALLOW_PROTECTED_DB=1 bypasses the guard
        if ((bool) env('ALLOW_PROTECTED_DB', false)) {
            return;
        }

        // Resolve the database connection
        $defaultConnection = config('database.default');
        $connections = config('database.connections');
        $connection = $connections[$defaultConnection] ?? [];

        $databaseName = $connection['database'] ?? $connection['path'] ?? 'unknown';

        $protectedDatabases = config('sdcc.protected_databases', ['sdcc']);
        $protectedDatabases = array_map('strtolower', array_map('trim', $protectedDatabases));

        if (! in_array(strtolower($databaseName), $protectedDatabases, true)) {
            return;
        }

        $msg = "Blocked: {$command} would run against protected database '{$databaseName}'. Use --env=scratch, or prefix ALLOW_PROTECTED_DB=1 if you really mean it.";
        throw new ProtectedDatabaseException($msg);
    }
}