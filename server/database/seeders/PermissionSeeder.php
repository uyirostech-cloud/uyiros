<?php
namespace Database\Seeders;

use App\Core\Database;
use App\Core\Str;
use App\Support\Permissions;

final class PermissionSeeder
{
    /** Idempotent: safe to run on every deploy so new permissions appear automatically. */
    public static function run(): int
    {
        $count = 0;
        foreach (Permissions::catalogue() as $slug => [$group, $label]) {
            $existing = Database::scalar('SELECT id FROM permissions WHERE slug = :s', ['s' => $slug]);
            if ($existing === null) {
                Database::run(
                    'INSERT INTO permissions (slug, permission_group, label, created_at)
                     VALUES (:s, :g, :l, :c)',
                    ['s' => $slug, 'g' => $group, 'l' => $label, 'c' => Str::now()]
                );
                $count++;
            } else {
                Database::run(
                    'UPDATE permissions SET permission_group = :g, label = :l WHERE id = :id',
                    ['g' => $group, 'l' => $label, 'id' => $existing]
                );
            }
        }
        return $count;
    }
}
