<?php
namespace App\Core;

final class Migrator
{
    public function __construct(private readonly string $dir)
    {
    }

    public function ensureTable(): void
    {
        Database::run(
            "CREATE TABLE IF NOT EXISTS migrations (
               id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
               migration VARCHAR(190) NOT NULL,
               batch INT UNSIGNED NOT NULL,
               ran_at DATETIME NOT NULL,
               PRIMARY KEY (id),
               UNIQUE KEY uq_migration (migration)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return string[] */
    public function files(): array
    {
        $files = glob(rtrim($this->dir, '/') . '/*.php') ?: [];
        sort($files);
        return array_map(static fn ($f) => basename($f, '.php'), $files);
    }

    /** @return string[] */
    public function applied(): array
    {
        $this->ensureTable();
        return array_column(Database::select('SELECT migration FROM migrations ORDER BY id'), 'migration');
    }

    /** @return string[] */
    public function pending(): array
    {
        return array_values(array_diff($this->files(), $this->applied()));
    }

    /** @return string[] names of migrations that ran */
    public function run(?callable $log = null): array
    {
        $this->ensureTable();
        $pending = $this->pending();
        if ($pending === []) {
            return [];
        }
        $batch = (int) (Database::scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') ?? 0) + 1;
        $ran = [];
        foreach ($pending as $name) {
            $definition = require rtrim($this->dir, '/') . '/' . $name . '.php';
            foreach ($definition['up'] as $sql) {
                Database::run($sql);
            }
            Database::run(
                'INSERT INTO migrations (migration, batch, ran_at) VALUES (:m, :b, :r)',
                ['m' => $name, 'b' => $batch, 'r' => Str::now()]
            );
            $ran[] = $name;
            $log && $log($name);
        }
        return $ran;
    }

    /** Drops every table in the current database. Development use only. */
    public function fresh(?callable $log = null): void
    {
        $tables = array_map(
            static fn ($r) => reset($r),
            Database::select('SHOW TABLES')
        );
        if ($tables !== []) {
            Database::run('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
                    continue;
                }
                Database::run("DROP TABLE IF EXISTS `{$table}`");
            }
            Database::run('SET FOREIGN_KEY_CHECKS = 1');
            $log && $log('dropped ' . count($tables) . ' tables');
        }
    }
}
