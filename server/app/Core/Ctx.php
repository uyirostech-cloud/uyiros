<?php
namespace App\Core;

/**
 * The authenticated request context. Built ONLY by the auth + tenant middleware from the
 * session row — never from client input. Every tenant-scoped query reads organizationId
 * from here.
 */
final class Ctx
{
    /** @param string[] $permissions @param int[] $branchIds */
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly ?int $organizationId,
        public readonly ?string $organizationName,
        public readonly ?int $roleId,
        public readonly ?string $roleSlug,
        public readonly array $permissions,
        public readonly array $branchIds,
        public readonly bool $allBranches,
        public readonly bool $isPlatformAdmin,
        public readonly int $sessionId,
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly ?int $doctorId = null,
    ) {
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->can($p)) {
                return true;
            }
        }
        return false;
    }

    /** organization_id for SQL binding. Throws if a clinic query is attempted without a tenant. */
    public function orgId(): int
    {
        if ($this->organizationId === null) {
            throw HttpException::forbidden('This account is not associated with a clinic');
        }
        return $this->organizationId;
    }

    public function canUseBranch(?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }
        return $this->allBranches || in_array($branchId, $this->branchIds, true);
    }

    public function assertBranch(?int $branchId): void
    {
        if (!$this->canUseBranch($branchId)) {
            throw HttpException::forbidden('You do not have access to this branch');
        }
    }

    /** Branch ids to filter a list by; null means "all branches of this organization". */
    public function branchFilter(?int $requested): ?array
    {
        if ($requested !== null) {
            $this->assertBranch($requested);
            return [$requested];
        }
        return $this->allBranches ? null : ($this->branchIds ?: [0]);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->userId,
            'name'              => $this->userName,
            'email'             => $this->userEmail,
            'organization_id'   => $this->organizationId,
            'organization_name' => $this->organizationName,
            'role'              => $this->roleSlug,
            'role_id'           => $this->roleId,
            'permissions'       => $this->permissions,
            'branch_ids'        => $this->branchIds,
            'all_branches'      => $this->allBranches,
            'is_platform_admin' => $this->isPlatformAdmin,
            'doctor_id'         => $this->doctorId,
        ];
    }
}
