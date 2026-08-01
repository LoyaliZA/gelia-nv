<?php

namespace Tests\Unit\Console;

use App\Console\Commands\DbBackupCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DbBackupComprimirTest extends TestCase
{
    public function test_comprimir_sin_llave_deja_sql_gz_y_borra_sql(): void
    {
        $dir = storage_path('app/backups_test_'.uniqid());
        File::ensureDirectoryExists($dir);
        $sql = $dir.'/sample.sql';
        File::put($sql, "-- dump\nSELECT 1;\n");

        try {
            $cmd = app(DbBackupCommand::class);
            $final = $cmd->comprimirYOpcionalmenteCifrar($sql);

            $this->assertSame($sql.'.gz', $final);
            $this->assertFileExists($final);
            $this->assertFileDoesNotExist($sql);
            $this->assertStringContainsString('SELECT 1', gzdecode(File::get($final)));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
