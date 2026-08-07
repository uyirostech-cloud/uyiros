<?php
/**
 * Migration CLI.
 *   php server/database/migrate.php            apply pending migrations
 *   php server/database/migrate.php --status   show applied / pending
 *   php server/database/migrate.php --fresh    drop everything and reapply (development only)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

define('CLINICFLOW_BASE', dirname(__DIR__));
require CLINICFLOW_BASE . '/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;

$options = $argv;
$fresh   = in_array('--fresh', $options, true);
$status  = in_array('--status', $options, true);

$migrator = new Migrator(CLINICFLOW_BASE . '/database/migrations');

try {
    Database::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  Cannot connect to the database.\n  " . $e->getMessage() . "\n\n"
        . "  Check server/.env — DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD.\n\n");
    exit(1);
}

echo "\n  ClinicFlow migrations — database: " . Config::get('database.database') . "\n\n";

if ($status) {
    $applied = $migrator->applied();
    foreach ($migrator->files() as $file) {
        printf("  [%s] %s\n", in_array($file, $applied, true) ? 'x' : ' ', $file);
    }
    echo "\n  " . count($applied) . " applied, " . count($migrator->pending()) . " pending\n\n";
    exit(0);
}

if ($fresh) {
    if (Config::get('app.env') === 'production') {
        fwrite(STDERR, "  Refusing to run --fresh in production.\n\n");
        exit(1);
    }
    echo "  Dropping all tables...\n";
    $migrator->fresh(static fn (string $m) => print("    {$m}\n"));
}

$ran = $migrator->run(static fn (string $name) => print("    migrated  {$name}\n"));

echo $ran === []
    ? "  Nothing to migrate.\n\n"
    : "\n  " . count($ran) . " migration(s) applied.\n\n";
