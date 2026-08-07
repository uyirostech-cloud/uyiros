<?php
namespace App\Domain\Services;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;

final class AuditService
{
    public static function log(
        ?Ctx $ctx,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        array $metadata = []
    ): void {
        Database::run(
            'INSERT INTO audit_logs
               (organization_id, user_id, user_name, action, entity_type, entity_id,
                old_values, new_values, metadata, ip, user_agent, created_at)
             VALUES (:org, :uid, :uname, :action, :etype, :eid, :old, :new, :meta, :ip, :ua, :created)',
            [
                'org'     => $ctx?->organizationId,
                'uid'     => $ctx?->userId,
                'uname'   => $ctx?->userName,
                'action'  => $action,
                'etype'   => $entityType,
                'eid'     => $entityId,
                'old'     => $old === null ? null : self::encode($old),
                'new'     => $new === null ? null : self::encode($new),
                'meta'    => $metadata === [] ? null : self::encode($metadata),
                'ip'      => $ctx?->ip,
                'ua'      => $ctx?->userAgent,
                'created' => Str::now(),
            ]
        );
    }

    /** Log an event that has no Ctx yet (login attempts, password resets). */
    public static function logAnonymous(
        ?int $organizationId,
        ?int $userId,
        string $action,
        array $metadata = [],
        string $ip = '',
        string $userAgent = ''
    ): void {
        Database::run(
            'INSERT INTO audit_logs
               (organization_id, user_id, action, metadata, ip, user_agent, created_at)
             VALUES (:org, :uid, :action, :meta, :ip, :ua, :created)',
            [
                'org' => $organizationId, 'uid' => $userId, 'action' => $action,
                'meta' => $metadata === [] ? null : self::encode($metadata),
                'ip' => $ip, 'ua' => mb_substr($userAgent, 0, 255), 'created' => Str::now(),
            ]
        );
    }

    private static function encode(array $data): string
    {
        return json_encode(Logger::redact($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /** Diff two record states so the log stores only what actually changed. */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before) || (string) $before[$key] !== (string) $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }
        return [$old, $new];
    }
}
