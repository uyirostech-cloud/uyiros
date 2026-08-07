<?php
/**
 * Interactive first-run setup for a production install: creates one organization,
 * its default roles, a first branch and a Clinic Admin user.
 *
 *   php scripts/create-admin.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

define('CLINICFLOW_BASE', dirname(__DIR__));
require CLINICFLOW_BASE . '/bootstrap.php';

use App\Core\Database;
use App\Core\Str;
use App\Domain\Services\AuthService;
use App\Domain\Services\NumberingService;
use App\Http\Controllers\PlatformController;

function ask(string $label, bool $hidden = false): string
{
    echo $label;
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";
        return $value;
    }
    return trim((string) fgets(STDIN));
}

echo "\n  ClinicFlow — create clinic and administrator\n\n";

$orgName = ask('  Clinic name: ');
$orgCode = strtoupper(ask('  Clinic code (e.g. MAINCLINIC): '));
$branch  = ask('  First branch name [Main Branch]: ') ?: 'Main Branch';
$name    = ask('  Administrator name: ');
$email   = strtolower(ask('  Administrator email: '));
$pass    = ask('  Administrator password: ', true);

if ($orgName === '' || $orgCode === '' || $name === '' || $email === '' || $pass === '') {
    fwrite(STDERR, "\n  All fields are required.\n\n");
    exit(1);
}
if (Database::scalar('SELECT id FROM organizations WHERE code = :c', ['c' => $orgCode]) !== null) {
    fwrite(STDERR, "\n  That clinic code already exists.\n\n");
    exit(1);
}
if (Database::scalar('SELECT id FROM users WHERE email = :e', ['e' => $email]) !== null) {
    fwrite(STDERR, "\n  That email is already registered.\n\n");
    exit(1);
}
try {
    AuthService::validatePasswordStrength($pass);
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  " . $e->getMessage() . "\n\n");
    exit(1);
}
if ((int) Database::scalar('SELECT COUNT(*) FROM permissions') === 0) {
    fwrite(STDERR, "\n  Run `php database/seed.php` first so permissions exist.\n\n");
    exit(1);
}

$orgId = Database::transaction(static function () use ($orgName, $orgCode, $branch, $name, $email, $pass) {
    $orgId = Database::insert(
        'INSERT INTO organizations (public_id, code, name, slug, status, created_at)
         VALUES (:pub, :code, :name, :slug, "active", :created)',
        ['pub' => Str::ulid(), 'code' => $orgCode, 'name' => $orgName,
         'slug' => Str::slug($orgName), 'created' => Str::now()]
    );
    NumberingService::seedDefaults($orgId);
    $roles = PlatformController::seedRoles($orgId);
    PlatformController::seedDefaultData($orgId);

    $branchId = Database::insert(
        'INSERT INTO branches (organization_id, name, code, is_active, created_at)
         VALUES (:o, :n, "MAIN", 1, :created)',
        ['o' => $orgId, 'n' => $branch, 'created' => Str::now()]
    );
    $userId = Database::insert(
        'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                            is_active, all_branches, must_change_password, created_at)
         VALUES (:pub, :o, :r, :n, :e, :h, 1, 1, 0, :created)',
        ['pub' => Str::ulid(), 'o' => $orgId, 'r' => $roles['clinic_admin'], 'n' => $name,
         'e' => $email, 'h' => AuthService::hash($pass), 'created' => Str::now()]
    );
    Database::run('INSERT INTO user_branches (user_id, branch_id, created_at) VALUES (:u, :b, :c)',
        ['u' => $userId, 'b' => $branchId, 'c' => Str::now()]);
    return $orgId;
});

echo "\n  Created organization #{$orgId} with administrator {$email}\n\n";
