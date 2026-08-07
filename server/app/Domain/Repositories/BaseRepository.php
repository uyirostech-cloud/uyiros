<?php
namespace App\Domain\Repositories;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Str;

/**
 * Every tenant table is reached through this class. It builds its own SQL and always
 * appends `AND organization_id = :ctx_org`, where the value comes from the authenticated
 * session — never from the client. There is no public method here that runs an unscoped
 * query on a tenant table.
 */
abstract class BaseRepository
{
    /** Table name — must match /^[a-z_]+$/ */
    abstract protected function table(): string;

    /** Columns the client may set. Anything else is silently dropped. */
    abstract protected function fillable(): array;

    /** Columns allowed in ORDER BY. Prevents identifier injection through ?sort=. */
    protected function sortable(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    protected function hasSoftDeletes(): bool
    {
        return false;
    }

    protected function hasBranch(): bool
    {
        return false;
    }

    protected function hasTimestamps(): bool
    {
        return true;
    }

    public function __construct(protected readonly Ctx $ctx)
    {
        if (!preg_match('/^[a-z_]+$/', $this->table())) {
            throw new \LogicException('Invalid table name');
        }
    }

    protected function orgId(): int
    {
        return $this->ctx->orgId();
    }

    /** Base WHERE fragment applied to EVERY query built here. */
    private function scope(string $alias = ''): string
    {
        $p = $alias === '' ? '' : $alias . '.';
        $sql = " {$p}organization_id = :ctx_org";
        if ($this->hasSoftDeletes()) {
            $sql .= " AND {$p}deleted_at IS NULL";
        }
        return $sql;
    }

    private function scopeParams(): array
    {
        return ['ctx_org' => $this->orgId()];
    }

    public function find(int $id, string $columns = '*'): ?array
    {
        $row = Database::selectOne(
            "SELECT {$this->safeColumns($columns)} FROM `{$this->table()}` WHERE id = :id AND" . $this->scope() . ' LIMIT 1',
            $this->scopeParams() + ['id' => $id]
        );
        return $row ?: null;
    }

    /** Cross-tenant ids surface as 404, never 403 — a 403 would confirm the record exists. */
    public function findOrFail(int $id, string $columns = '*'): array
    {
        $row = $this->find($id, $columns);
        if ($row === null) {
            throw HttpException::notFound(ucfirst(str_replace('_', ' ', rtrim($this->table(), 's'))) . ' not found');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $filters column => value (or [op, value])
     * @return array{0:array,1:int} rows and total
     */
    public function paginate(
        array $filters,
        int $page,
        int $perPage,
        ?string $sort = null,
        string $dir = 'desc',
        string $columns = '*'
    ): array {
        [$where, $params] = $this->buildWhere($filters);
        $orderBy = $this->buildOrderBy($sort, $dir);
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM `{$this->table()}` WHERE {$where}",
            $params
        );
        $rows = Database::select(
            "SELECT {$this->safeColumns($columns)} FROM `{$this->table()}`
              WHERE {$where} {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return [$rows, $total];
    }

    public function all(array $filters = [], ?string $sort = null, string $dir = 'asc', string $columns = '*', int $limit = 500): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $orderBy = $this->buildOrderBy($sort, $dir);
        $limit = max(1, min(2000, $limit));
        return Database::select(
            "SELECT {$this->safeColumns($columns)} FROM `{$this->table()}` WHERE {$where} {$orderBy} LIMIT {$limit}",
            $params
        );
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        return (int) Database::scalar("SELECT COUNT(*) FROM `{$this->table()}` WHERE {$where}", $params);
    }

    public function exists(int $id): bool
    {
        return $this->find($id, 'id') !== null;
    }

    /** organization_id is set from Ctx here and cannot be overridden by $data. */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        if ($this->hasBranch() && array_key_exists('branch_id', $data)) {
            $this->ctx->assertBranch($data['branch_id'] === null ? null : (int) $data['branch_id']);
        }
        $data['organization_id'] = $this->orgId();
        if ($this->hasTimestamps()) {
            $data['created_at'] = Str::now();
        }
        if ($this->columnExists('created_by') && !array_key_exists('created_by', $data)) {
            $data['created_by'] = $this->ctx->userId;
        }

        $cols = array_keys($data);
        $this->assertIdentifiers($cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table(),
            implode(', ', array_map(static fn ($c) => "`{$c}`", $cols)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        return Database::insert($sql, $data);
    }

    /** Scoped update: a cross-tenant id affects zero rows and raises 404. */
    public function update(int $id, array $data): array
    {
        $existing = $this->findOrFail($id);
        $data = $this->filterFillable($data);
        if ($this->hasBranch() && array_key_exists('branch_id', $data)) {
            $this->ctx->assertBranch($data['branch_id'] === null ? null : (int) $data['branch_id']);
        }
        if ($data === []) {
            return $existing;
        }
        if ($this->hasTimestamps()) {
            $data['updated_at'] = Str::now();
        }
        if ($this->columnExists('updated_by')) {
            $data['updated_by'] = $this->ctx->userId;
        }
        $this->assertIdentifiers(array_keys($data));

        $sets = implode(', ', array_map(static fn ($c) => "`{$c}` = :{$c}", array_keys($data)));
        Database::run(
            "UPDATE `{$this->table()}` SET {$sets} WHERE id = :id AND" . $this->scope(),
            $data + $this->scopeParams() + ['id' => $id]
        );
        return $this->findOrFail($id);
    }

    public function softDelete(int $id): void
    {
        $this->findOrFail($id);
        Database::run(
            "UPDATE `{$this->table()}` SET deleted_at = :now WHERE id = :id AND" . $this->scope(),
            $this->scopeParams() + ['id' => $id, 'now' => Str::now()]
        );
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id);
        Database::run(
            "DELETE FROM `{$this->table()}` WHERE id = :id AND" . $this->scope(),
            $this->scopeParams() + ['id' => $id]
        );
    }

    /**
     * Build a WHERE clause from a filter map. Supported values:
     *   'col' => value                      → col = value
     *   'col' => ['in', [1,2,3]]            → col IN (...)
     *   'col' => ['like', 'abc']            → col LIKE %abc%
     *   'col' => ['>=', '2026-01-01']       → col >= value
     *   'col' => ['null']  / ['notnull']
     *   '__raw' => [[sql, params]]          → developer-supplied SQL with bound params only
     */
    protected function buildWhere(array $filters): array
    {
        $sql = $this->scope();
        $params = $this->scopeParams();
        $i = 0;

        // Branch scoping: users never see branches they are not assigned to.
        if ($this->hasBranch() && !array_key_exists('branch_id', $filters) && !$this->ctx->allBranches) {
            $ids = $this->ctx->branchIds ?: [0];
            $names = [];
            foreach ($ids as $id) {
                $key = 'bscope' . $i++;
                $names[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $sql .= ' AND branch_id IN (' . implode(', ', $names) . ')';
        }

        foreach ($filters as $column => $value) {
            if ($column === '__raw') {
                foreach ($value as [$rawSql, $rawParams]) {
                    $sql .= ' AND (' . $rawSql . ')';
                    $params += $rawParams;
                }
                continue;
            }
            if ($value === null) {
                continue;
            }
            $this->assertIdentifiers([$column]);
            if ($column === 'branch_id' && $this->hasBranch()) {
                $this->ctx->assertBranch((int) (is_array($value) ? ($value[1] ?? 0) : $value));
            }

            if (!is_array($value)) {
                $key = 'f' . $i++;
                $sql .= " AND `{$column}` = :{$key}";
                $params[$key] = $value;
                continue;
            }

            [$op, $operand] = array_pad($value, 2, null);
            switch ($op) {
                case 'in':
                    $operand = array_values(array_unique((array) $operand));
                    if ($operand === []) {
                        $sql .= ' AND 1 = 0';
                        break;
                    }
                    $names = [];
                    foreach ($operand as $v) {
                        $key = 'f' . $i++;
                        $names[] = ':' . $key;
                        $params[$key] = $v;
                    }
                    $sql .= " AND `{$column}` IN (" . implode(', ', $names) . ')';
                    break;
                case 'not_in':
                    $operand = array_values(array_unique((array) $operand));
                    if ($operand === []) {
                        break;
                    }
                    $names = [];
                    foreach ($operand as $v) {
                        $key = 'f' . $i++;
                        $names[] = ':' . $key;
                        $params[$key] = $v;
                    }
                    $sql .= " AND `{$column}` NOT IN (" . implode(', ', $names) . ')';
                    break;
                case 'like':
                    $key = 'f' . $i++;
                    $sql .= " AND `{$column}` LIKE :{$key}";
                    $params[$key] = '%' . self::escapeLike((string) $operand) . '%';
                    break;
                case 'starts':
                    $key = 'f' . $i++;
                    $sql .= " AND `{$column}` LIKE :{$key}";
                    $params[$key] = self::escapeLike((string) $operand) . '%';
                    break;
                case 'null':
                    $sql .= " AND `{$column}` IS NULL";
                    break;
                case 'notnull':
                    $sql .= " AND `{$column}` IS NOT NULL";
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                case '<>':
                case '=':
                    $key = 'f' . $i++;
                    $sql .= " AND `{$column}` {$op} :{$key}";
                    $params[$key] = $operand;
                    break;
                default:
                    throw new \LogicException("Unsupported filter operator: {$op}");
            }
        }
        return [$sql, $params];
    }

    protected function buildOrderBy(?string $sort, string $dir): string
    {
        if ($sort === null || $sort === '') {
            return 'ORDER BY id DESC';
        }
        if (!in_array($sort, $this->sortable(), true)) {
            throw HttpException::badRequest('Invalid sort column', 'INVALID_SORT');
        }
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        return "ORDER BY `{$sort}` {$dir}";
    }

    protected function filterFillable(array $data): array
    {
        $out = [];
        foreach ($this->fillable() as $column) {
            if (array_key_exists($column, $data)) {
                $out[$column] = $data[$column];
            }
        }
        unset($out['organization_id'], $out['id']);
        return $out;
    }

    private function safeColumns(string $columns): string
    {
        if ($columns === '*') {
            return '*';
        }
        $parts = array_map('trim', explode(',', $columns));
        foreach ($parts as $p) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $p)) {
                throw new \LogicException('Invalid column list');
            }
        }
        return implode(', ', array_map(static fn ($p) => "`{$p}`", $parts));
    }

    private function assertIdentifiers(array $columns): void
    {
        foreach ($columns as $c) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', (string) $c)) {
                throw new \LogicException('Invalid column identifier: ' . $c);
            }
        }
    }

    private static array $columnCache = [];

    protected function columnExists(string $column): bool
    {
        $key = $this->table() . '.' . $column;
        if (!array_key_exists($key, self::$columnCache)) {
            self::$columnCache[$key] = Database::scalar(
                'SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => $this->table(), 'c' => $column]
            ) !== null;
        }
        return self::$columnCache[$key];
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Tenant-scoped raw read for joins/aggregations that the generic builder cannot express.
     * The SQL must contain :ctx_org — the guard below refuses anything that forgot the tenant
     * filter, so an unscoped query cannot ship by accident.
     */
    protected function scopedSelect(string $sql, array $params = []): array
    {
        if (!str_contains($sql, ':ctx_org')) {
            throw new \LogicException('Tenant-scoped query must bind :ctx_org');
        }
        return Database::select($sql, $params + $this->scopeParams());
    }

    protected function scopedSelectOne(string $sql, array $params = []): ?array
    {
        if (!str_contains($sql, ':ctx_org')) {
            throw new \LogicException('Tenant-scoped query must bind :ctx_org');
        }
        return Database::selectOne($sql, $params + $this->scopeParams());
    }

    protected function scopedScalar(string $sql, array $params = []): mixed
    {
        if (!str_contains($sql, ':ctx_org')) {
            throw new \LogicException('Tenant-scoped query must bind :ctx_org');
        }
        return Database::scalar($sql, $params + $this->scopeParams());
    }
}
