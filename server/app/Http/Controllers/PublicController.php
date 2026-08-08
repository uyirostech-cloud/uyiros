<?php
namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Unauthenticated, marketing-facing endpoints — the landing and pricing pages read these
 * without a session. Nothing here returns tenant or clinical data.
 */
final class PublicController
{
    public function plans(Request $request): Response
    {
        $rows = Database::select(
            'SELECT code, name, description, price_monthly, price_yearly, max_branches, max_users
               FROM plans WHERE is_active = 1 ORDER BY price_monthly'
        );
        return Response::json(array_map(static fn ($p) => [
            'code'          => $p['code'],
            'name'          => $p['name'],
            'description'   => $p['description'],
            'price_monthly' => $p['price_monthly'],
            'price_yearly'  => $p['price_yearly'],
            'max_branches'  => (int) $p['max_branches'],
            'max_users'     => (int) $p['max_users'],
        ], $rows));
    }

    public function stats(Request $request): Response
    {
        // Coarse, non-identifying counts only — safe to expose publicly as social proof.
        $organizations = (int) Database::scalar("SELECT COUNT(*) FROM organizations WHERE status IN ('active','trial')");
        $branches = (int) Database::scalar('SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL');
        return Response::json([
            'clinics'  => $organizations,
            'branches' => $branches,
        ]);
    }
}
