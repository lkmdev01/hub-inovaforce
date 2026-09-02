<?php

namespace App\Console\Commands;

use App\Models\SystemRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'hub:backup-database';

    protected $description = 'Cria uma cópia compactada do banco de dados e remove cópias antigas';

    public function handle(): int
    {
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700, true);
        $destination = $directory.'/database-'.now()->format('Y-m-d-His').'.sql.gz';

        try {
            $connection = (string) config('database.default');
            $driver = (string) config("database.connections.{$connection}.driver");
            $contents = $driver === 'sqlite' ? $this->sqliteDump($connection) : $this->mysqlDump($connection);
            $compressed = gzencode($contents, 9);
            if ($compressed === false) {
                throw new \RuntimeException('Não foi possível compactar o backup do banco de dados.');
            }
            File::put($destination, $compressed);
            @chmod($destination, 0600);
            $this->prune($directory);

            SystemRun::query()->updateOrCreate(['name' => 'database-backup'], [
                'status' => 'ok', 'ran_at' => now(), 'details' => ['file' => basename($destination), 'bytes' => File::size($destination)], 'error_message' => null,
            ]);
            $this->info('Backup criado em storage/app/private/backups.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            SystemRun::query()->updateOrCreate(['name' => 'database-backup'], [
                'status' => 'failed', 'ran_at' => now(), 'error_message' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function sqliteDump(string $connection): string
    {
        $path = (string) config("database.connections.{$connection}.database");

        if ($path === ':memory:' || ! File::exists($path)) {
            throw new \RuntimeException('O arquivo SQLite não está disponível para backup.');
        }

        return (string) File::get($path);
    }

    private function mysqlDump(string $connection): string
    {
        $configuration = (array) config("database.connections.{$connection}");
        $process = new Process([
            'mysqldump', '--single-transaction', '--quick', '--skip-lock-tables',
            '--host='.(string) ($configuration['host'] ?? '127.0.0.1'),
            '--port='.(string) ($configuration['port'] ?? '3306'),
            '--user='.(string) ($configuration['username'] ?? ''),
            (string) ($configuration['database'] ?? ''),
        ], null, ['MYSQL_PWD' => (string) ($configuration['password'] ?? '')]);
        $process->setTimeout(300)->mustRun();

        return $process->getOutput();
    }

    private function prune(string $directory): void
    {
        foreach (File::glob($directory.'/database-*.sql.gz') ?: [] as $file) {
            if (File::lastModified($file) < now()->subDays(14)->timestamp) {
                File::delete($file);
            }
        }
    }
}
