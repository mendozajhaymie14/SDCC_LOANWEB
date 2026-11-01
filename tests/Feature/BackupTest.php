<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
    {
        use RefreshDatabase;

        private function makeAdmin(): User
        {
            return User::factory()->asAdmin()->create([
                'email' => 'admin@example.com',
            ]);
        }

        private function makeNonAdmin(): User
        {
            return User::factory()->asLoanOfficer()->create([
                'email' => 'officer@example.com',
            ]);
        }

        private function makeBackupFile(string $filename, string $content = "SELECT 1;\n"): string
        {
            $dir = base_path('storage/app/backups');
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }
            $filepath = $dir . '/' . $filename;
            file_put_contents($filepath, gzencode($content, 9));
            return $filepath;
        }

        protected function tearDown(): void
        {
            // Remove any backup files created during tests so they don't leak
            // into other tests (e.g. corrupt files triggering gzdecode errors).
            $dir = base_path('storage/app/backups');
            if (File::isDirectory($dir)) {
                foreach (File::glob("{$dir}/backup_test_*.sql.gz") as $file) {
                    @unlink($file);
                }
                foreach (File::glob("{$dir}/backup_sdcc_*.sql.gz") as $file) {
                    @unlink($file);
                }
            }

            parent::tearDown();
        }

    /** @test */
    public function backup_page_requires_admin_role()
    {
        $admin = $this->makeAdmin();
        $nonAdmin = $this->makeNonAdmin();

        $this->actingAs($admin, 'web')
            ->get(route('admin.backup'))
            ->assertStatus(200);

        $this->actingAs($nonAdmin, 'web')
            ->get(route('admin.backup'))
            ->assertStatus(403);
    }

    /** @test */
    public function backup_restore_requires_admin_role()
    {
        $admin = $this->makeAdmin();
        $nonAdmin = $this->makeNonAdmin();

        $filename = 'backup_test_2024-01-01_00-00-00.sql.gz';
        $this->makeBackupFile($filename);

        // Admin can POST (not 403; may be 422/200/500 depending on validation/DB)
        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
                'confirm' => 'RESTORE',
            ]);
        $this->assertNotEquals(403, $response->getStatusCode());

        // Non-admin gets 403
        $this->actingAs($nonAdmin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
                'confirm' => 'RESTORE',
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function restore_rejects_missing_confirmation_field()
    {
        $admin = $this->makeAdmin();
        $filename = 'backup_test_2024-01-01_00-00-00.sql.gz';
        $this->makeBackupFile($filename);

        // Regular form POST without confirm → Laravel redirect (302) with validation errors
        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
            ])
            ->assertStatus(302);
    }

    /** @test */
    public function restore_rejects_wrong_confirmation_text()
    {
        $admin = $this->makeAdmin();
        $filename = 'backup_test_2024-01-01_00-00-00.sql.gz';
        $this->makeBackupFile($filename);

        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
                'confirm' => 'YES',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Confirmation failed. Please type RESTORE to confirm.',
            ]);
    }

    /** @test */
    public function restore_rejects_invalid_filename_pattern()
    {
        $admin = $this->makeAdmin();

        // Path traversal attempt
        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => '../../../etc/passwd',
                'confirm' => 'RESTORE',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid backup file name.',
            ]);

        // Random file not matching pattern
        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => 'random_file.sql.gz',
                'confirm' => 'RESTORE',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid backup file name.',
            ]);

        // Valid pattern but file doesn't exist
        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => 'backup_sdcc_2024-01-01_00-00-00.sql.gz',
                'confirm' => 'RESTORE',
            ])
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Selected backup file does not exist.',
            ]);
    }

    /** @test */
    public function restore_proceeds_with_valid_confirmation()
    {
        $admin = $this->makeAdmin();
        $filename = 'backup_test_2024-01-01_00-00-00.sql.gz';
        $this->makeBackupFile($filename);

        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
                'confirm' => 'RESTORE',
            ]);

        // On SQLite, restore proceeds (pre-restore backup skipped). Not 403/422.
        $this->assertNotEquals(403, $response->getStatusCode());
        $this->assertNotEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function restore_writes_audit_log_on_success()
    {
        $admin = $this->makeAdmin();
        $filename = 'backup_test_2024-01-01_00-00-00.sql.gz';

        // Simple SQLite-compatible SQL dump
        $sql = "CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);\n";
        $this->makeBackupFile($filename, $sql);

        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
                'confirm' => 'RESTORE',
            ]);

        $auditLog = AuditLog::where('action', 'DB Restore')
            ->where('details', 'like', '%' . $filename . '%')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log entry for DB Restore was not created');
        $this->assertEquals($admin->id, $auditLog->actor_id);
        $this->assertEquals('admin', $auditLog->actor_role);
    }

    /** @test */
    public function restore_writes_audit_log_on_failure()
    {
        $admin = $this->makeAdmin();
        $filename = 'backup_sdcc_2024-01-01_00-00-00.sql.gz';

        // Corrupt gzip file that will fail to decompress
        $dir = base_path('storage/app/backups');
        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, 'not a valid gzip file');

        $this->actingAs($admin, 'web')
            ->post(route('admin.backup.restore'), [
                'filename' => $filename,
                'confirm' => 'RESTORE',
            ]);

        $auditLog = AuditLog::where('action', 'DB Restore Failed')
            ->where('details', 'like', '%' . $filename . '%')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log entry for DB Restore Failed was not created');
        $this->assertEquals($admin->id, $auditLog->actor_id);
    }

    /** @test */
    public function download_backup_requires_admin_role()
    {
        $admin = $this->makeAdmin();
        $nonAdmin = $this->makeNonAdmin();

        $filename = 'backup_test_2024-01-01_00-00-00.sql.gz';
        $this->makeBackupFile($filename);

        // Admin can download (not 403)
        $response = $this->actingAs($admin, 'web')
            ->get(route('admin.backup.download', $filename));
        $this->assertNotEquals(403, $response->getStatusCode());

        // Non-admin gets 403
        $this->actingAs($nonAdmin, 'web')
            ->get(route('admin.backup.download', $filename))
            ->assertStatus(403);
    }

    /** @test */
    public function download_backup_rejects_path_traversal()
    {
        $admin = $this->makeAdmin();

        // Path doesn't match route regex → 404
        $this->actingAs($admin, 'web')
            ->get('/admin/backup/download/../../../etc/passwd')
            ->assertStatus(404);

        // Invalid pattern → 404
        $this->actingAs($admin, 'web')
            ->get('/admin/backup/download/random.sql.gz')
            ->assertStatus(404);
    }
}