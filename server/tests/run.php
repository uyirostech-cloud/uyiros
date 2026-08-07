<?php
/**
 * ClinicFlow test runner — no PHPUnit, no Composer, so it runs anywhere including Hostinger.
 *
 *   php server/tests/run.php
 *   php server/tests/run.php --filter=tenant
 *   php server/tests/run.php --keep      keep the test database contents afterwards
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

define('CLINICFLOW_BASE', dirname(__DIR__));

// Point the app at the test database BEFORE bootstrap reads config.
require CLINICFLOW_BASE . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(CLINICFLOW_BASE . '/app');
App\Core\Env::load(CLINICFLOW_BASE . '/.env');
if (is_file(CLINICFLOW_BASE . '/.env.testing')) {
    App\Core\Env::load(CLINICFLOW_BASE . '/.env.testing');
} else {
    App\Core\Env::set('DB_NAME', (string) App\Core\Env::get('DB_NAME', 'clinicflow') . '_test');
}
App\Core\Env::set('APP_ENV', 'testing');

require CLINICFLOW_BASE . '/bootstrap.php';
require CLINICFLOW_BASE . '/tests/Assert.php';
require CLINICFLOW_BASE . '/tests/TestCase.php';
require CLINICFLOW_BASE . '/database/seeders/PermissionSeeder.php';
require CLINICFLOW_BASE . '/database/seeders/PlanSeeder.php';
require CLINICFLOW_BASE . '/database/seeders/DemoDataSeeder.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use Tests\Assert;
use Tests\AssertionFailed;

$filter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = strtolower(substr($arg, 9));
    }
}

$db = Config::get('database.database');
echo "\n  ClinicFlow test suite — database: {$db}\n";

try {
    Database::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  Cannot connect to the test database.\n  " . $e->getMessage() . "\n\n"
        . "  Create it with:\n"
        . "    mysql -e \"CREATE DATABASE {$db} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\"\n\n");
    exit(1);
}

// Rebuild the schema from scratch so every run starts from a known state.
echo "  Preparing schema and fixtures...\n";
$migrator = new Migrator(CLINICFLOW_BASE . '/database/migrations');
$migrator->fresh();
$migrator->run();
Database::run('SET FOREIGN_KEY_CHECKS = 1');
\Database\Seeders\PermissionSeeder::run();
\Database\Seeders\PlanSeeder::run();
Database::insert(
    'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                        is_platform_admin, is_active, all_branches, must_change_password, created_at)
     VALUES (:pub, NULL, NULL, "Platform Super Admin", "platform@clinicflow.test", :h, 1, 1, 1, 0, :created)',
    ['pub' => App\Core\Str::ulid(),
     'h' => App\Domain\Services\AuthService::hash(\Database\Seeders\DemoDataSeeder::DEMO_PASSWORD),
     'created' => App\Core\Str::now()]
);
\Database\Seeders\DemoDataSeeder::run();

// Discover test classes.
$classes = [];
foreach (['Unit', 'Feature'] as $dir) {
    foreach (glob(CLINICFLOW_BASE . "/tests/{$dir}/*Test.php") ?: [] as $file) {
        require_once $file;
        $classes[] = 'Tests\\' . $dir . '\\' . basename($file, '.php');
    }
}
sort($classes);

$passed = 0;
$failed = 0;
$skipped = 0;
$failures = [];
$start = microtime(true);

foreach ($classes as $class) {
    /** @var Tests\TestCase $instance */
    $instance = new $class();
    $suiteName = $instance->name();
    if ($filter !== null && !str_contains(strtolower($suiteName), $filter)) {
        $skipped += count($instance->tests());
        continue;
    }
    echo "\n  {$suiteName}\n";
    foreach ($instance->tests() as $name => $test) {
        if ($filter !== null && !str_contains(strtolower($suiteName . ' ' . $name), $filter)) {
            $skipped++;
            continue;
        }
        try {
            $test();
            $passed++;
            echo "    \033[32mPASS\033[0m  {$name}\n";
        } catch (AssertionFailed $e) {
            $failed++;
            $failures[] = "{$suiteName} :: {$name}\n         {$e->getMessage()}";
            echo "    \033[31mFAIL\033[0m  {$name}\n           {$e->getMessage()}\n";
        } catch (\Throwable $e) {
            $failed++;
            $failures[] = "{$suiteName} :: {$name}\n         "
                . get_class($e) . ': ' . $e->getMessage()
                . "\n         " . $e->getFile() . ':' . $e->getLine();
            echo "    \033[31mERROR\033[0m {$name}\n           "
                . get_class($e) . ': ' . $e->getMessage()
                . "\n           " . $e->getFile() . ':' . $e->getLine() . "\n";
        }
        // A failing test must never leave a transaction open for the next one.
        while (Database::inTransaction()) {
            Database::rollback();
        }
    }
}

$elapsed = round(microtime(true) - $start, 2);
echo "\n  " . str_repeat('-', 62) . "\n";
printf("  %d passed, %d failed, %d skipped   (%d assertions, %ss)\n",
    $passed, $failed, $skipped, Assert::$count, $elapsed);

if ($failures !== []) {
    echo "\n  Failures:\n";
    foreach ($failures as $f) {
        echo "    - {$f}\n";
    }
}
echo "\n";
exit($failed === 0 ? 0 : 1);
