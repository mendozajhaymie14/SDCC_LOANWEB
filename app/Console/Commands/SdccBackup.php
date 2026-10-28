<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SdccBackup extends Command
{
    protected $signature = 'sdcc:backup';
    protected $description = 'Create a gzip SQL dump of the current database and store it in storage/app/backups (keeps last 14 backups, git-ignored)';

    public function handle(): int
    {
        $this->info('Creating database backup...');

        $driver = config('database.default');
        $connections = config('database.connections');
        $connection = $connections[$driver] ?? [];

        $database = $connection['database'] ?? '';
        $host = $connection['host'] ?? 'localhost';
        $port = $connection['port'] ?? 3306;
        $username = $connection['username'] ?? 'root';
        $password = $connection['password'] ?? '';

        // Determine the mysqldump path
        $mysqldumpPath = $this->findMysqldump();

        if (! $mysqldumpPath) {
            $this->error('Could not find mysqldump. Install mysql client or set path via C:\\xampp\\mysql\\bin\\mysqldump.exe');
            return self::FAILURE;
        }

        // Ensure storage/app/backups directory exists (outside public/)
        $backupDir = base_path('storage/app/backups');
        if (! is_dir($backupDir)) {
            File::makeDirectory($backupDir, 0755, true, true);
        }

        // Generate filename with timestamp
        $filename = "backup_{$database}_{now()->format('Y-m-d_H-i-s')}.sql.gz";
        $filepath = $backupDir . '/' . $filename;

        // Refuse to write inside public/
        if (strpos($filepath, base_path('public')) === 0) {
            $this->error('Refusing to write backup inside public/ directory. Backups go to storage/app/backups/ instead.');
            return self::FAILURE;
        }

        // Build the mysqldump command
        $dumpCmd = "{$mysqldumpPath} -h {$host} -P {$port} -u {$username}";
        if ($password) {
            $dumpCmd .= " -p{$password}";
        }
        $dumpCmd .= " {$database} | gzip > {$filepath}";

        $process = new Process($dumpCmd);
        $process->run();

        if ($process->isSuccessful()) {
            $this->info("Backup created: {$filename}");
            $this->printBackupInfo($filepath);

            // Keep only the last 14 backups
            $this->pruneOldBackups($backupDir);

            return self::SUCCESS;
        } else {
            $this->error("Backup failed: " . $process->getErrorOutput());
            return self::FAILURE;
        }
    }

    /**
     * Find mysqldump in PATH, then C:\xampp\mysql\bin\mysqldump.exe
     */
    private function findMysqldump(): ?string
    {
        // Check PATH first
        $path = exec('which mysqldump 2>/dev/null');
        if ($path) {
            return trim($path);
        }

        // Check xampp path
        $xamppPath = 'C:\xampp\mysql\bin\mysqldump.exe';
        if (file_exists($xamppPath)) {
            return $xamppPath;
        }

        // Check config in sdcc.php
        $sdccConfig = config('sdcc');
        if (isset($sdccConfig['mysqldump_path']) && $sdccConfig['mysqldump_path']) {
            return $sdccConfig['mysqldump_path'];
        }

        return null;
    }

    /**
     * Prune old backups, keeping the last 14
     */
    private function pruneOldBackups(string $backupDir): void
    {
        $files = glob("{$backupDir}/backup_*.sql.gz");
        if (! $files) {
            return;
        }

        // Sort by modification time, newest first
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        // Keep only the last 14
        $toDelete = array_slice($files, 14);
        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }
}