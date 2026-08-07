<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class AuditLogController
{
    public function index(Request $request, Ctx $ctx): Response
    {
        [$page, $perPage] = $request->pagination(50);
        $where = 'organization_id = :o';
        $params = ['o' => $ctx->orgId()];

        if (($action = $request->query('action')) !== null && $action !== '') {
            $where .= ' AND action = :action';
            $params['action'] = (string) $action;
        }
        if (($entity = $request->query('entity_type')) !== null && $entity !== '') {
            $where .= ' AND entity_type = :entity';
            $params['entity'] = (string) $entity;
        }
        if (($userId = $request->query('user_id')) !== null && $userId !== '') {
            $where .= ' AND user_id = :uid';
            $params['uid'] = (int) $userId;
        }
        if (($from = $request->query('from')) !== null && $from !== '') {
            $where .= ' AND created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if (($to = $request->query('to')) !== null && $to !== '') {
            $where .= ' AND created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        $total = (int) Database::scalar("SELECT COUNT(*) FROM audit_logs WHERE {$where}", $params);
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT id, user_id, user_name, action, entity_type, entity_id,
                    old_values, new_values, ip, created_at
               FROM audit_logs WHERE {$where}
              ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return Response::paginated(array_map(static fn ($r) => [
            'id'          => (int) $r['id'],
            'user_id'     => $r['user_id'] === null ? null : (int) $r['user_id'],
            'user_name'   => $r['user_name'],
            'action'      => $r['action'],
            'entity_type' => $r['entity_type'],
            'entity_id'   => $r['entity_id'] === null ? null : (int) $r['entity_id'],
            'old_values'  => $r['old_values'] === null ? null : json_decode($r['old_values'], true),
            'new_values'  => $r['new_values'] === null ? null : json_decode($r['new_values'], true),
            'ip'          => $r['ip'],
            'created_at'  => $r['created_at'],
        ], $rows), $total, $page, $perPage);
    }
}
