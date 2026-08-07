<?php
namespace App\Domain\Repositories;

final class BranchRepository extends BaseRepository
{
    protected function table(): string
    {
        return 'branches';
    }

    protected function hasSoftDeletes(): bool
    {
        return true;
    }

    protected function fillable(): array
    {
        return ['name', 'code', 'phone', 'email', 'address', 'city', 'state', 'pin',
                'opening_time', 'closing_time', 'working_days', 'is_active'];
    }

    protected function sortable(): array
    {
        return ['id', 'name', 'code', 'city', 'created_at'];
    }
}
