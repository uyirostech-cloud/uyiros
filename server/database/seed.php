<?php
/**
 * Seeder CLI.
 *   php server/database/seed.php           permissions + plans + platform admin (production-safe)
 *   php server/database/seed.php --demo    also creates two fictional clinics with demo data
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

define('CLINICFLOW_BASE', dirname(__DIR__));
require CLINICFLOW_BASE . '/bootstrap.php';

require CLINICFLOW_BASE . '/database/seeders/PermissionSeeder.php';
require CLINICFLOW_BASE . '/database/seeders/PlanSeeder.php';
require CLINICFLOW_BASE . '/database/seeders/DemoDataSeeder.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Str;
use App\Domain\Services\AuthService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;

$demo = in_array('--demo', $argv, true);

if ($demo && Config::get('app.env') === 'production') {
    fwrite(STDERR, "  Refusing to seed demo data in production.\n\n");
    exit(1);
}

echo "\n  Seeding " . Config::get('database.database') . "\n\n";

$permissions = PermissionSeeder::run();
echo "    permissions   +{$permissions} new (" . count(App\Support\Permissions::all()) . " total)\n";

$plans = PlanSeeder::run();
echo "    plans         +{$plans}\n";

// Platform super admin — separate from every clinic (organization_id is NULL).
$platformEmail = 'platform@clinicflow.test';
if (Database::scalar('SELECT id FROM users WHERE email = :e', ['e' => $platformEmail]) === null) {
    Database::insert(
        'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                            is_platform_admin, is_active, all_branches, must_change_password, created_at)
         VALUES (:pub, NULL, NULL, "Platform Super Admin", :e, :h, 1, 1, 1, 0, :created)',
        ['pub' => Str::ulid(), 'e' => $platformEmail,
         'h' => AuthService::hash(DemoDataSeeder::DEMO_PASSWORD), 'created' => Str::now()]
    );
    echo "    platform admin created ({$platformEmail})\n";
} else {
    echo "    platform admin already exists\n";
}

if ($demo) {
    echo "\n  Demo organizations:\n";
    foreach (DemoDataSeeder::run() as $slug => $info) {
        echo "    {$slug}: " . json_encode($info) . "\n";
    }
    echo "\n  Demo password for every seeded account: " . DemoDataSeeder::DEMO_PASSWORD . "\n";
}

echo "\n  Done.\n\n";
