<?php
namespace Database\Seeders;

use App\Core\Database;
use App\Core\Str;

final class PlanSeeder
{
    public static function run(): int
    {
        $plans = [
            ['starter',      'Starter',      'Single branch, up to 5 users',      '1499.00', '14990.00', 1,  5],
            ['professional', 'Professional', 'Up to 3 branches, 20 users',        '3999.00', '39990.00', 3,  20],
            ['enterprise',   'Enterprise',   'Unlimited branches and users',      '8999.00', '89990.00', 50, 500],
        ];
        $count = 0;
        foreach ($plans as [$code, $name, $desc, $monthly, $yearly, $branches, $users]) {
            if (Database::scalar('SELECT id FROM plans WHERE code = :c', ['c' => $code]) !== null) {
                continue;
            }
            Database::run(
                'INSERT INTO plans (code, name, description, price_monthly, price_yearly,
                                    max_branches, max_users, is_active, created_at)
                 VALUES (:c, :n, :d, :pm, :py, :mb, :mu, 1, :created)',
                ['c' => $code, 'n' => $name, 'd' => $desc, 'pm' => $monthly, 'py' => $yearly,
                 'mb' => $branches, 'mu' => $users, 'created' => Str::now()]
            );
            $count++;
        }
        return $count;
    }
}
