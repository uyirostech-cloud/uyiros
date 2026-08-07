<?php
namespace App\Core;

/**
 * Server-side validation. Browser validation is UX only; this is the boundary that matters.
 * exists:/unique: rules are tenant-scoped through the supplied Ctx.
 */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly ?Ctx $ctx = null,
    ) {
    }

    public static function make(array $data, array $rules, ?Ctx $ctx = null): array
    {
        return (new self($data, $rules, $ctx))->validate();
    }

    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            if ($value === '' ) {
                $value = null;
            }
            if ($value === null) {
                if ($required) {
                    $this->fail($field, 'This field is required');
                    continue;
                }
                if ($nullable || !array_key_exists($field, $this->data)) {
                    if (array_key_exists($field, $this->data)) {
                        $this->clean[$field] = null;
                    }
                    continue;
                }
            }

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $value = $this->apply($field, $name, $arg, $value);
                if (isset($this->errors[$field])) {
                    continue 2;
                }
            }
            $this->clean[$field] = $value;
        }

        if ($this->errors !== []) {
            throw HttpException::validation($this->errors);
        }
        return $this->clean;
    }

    private function apply(string $field, string $rule, ?string $arg, mixed $value): mixed
    {
        switch ($rule) {
            case 'string':
                if (!is_scalar($value)) {
                    $this->fail($field, 'Must be text');
                    return $value;
                }
                return trim((string) $value);

            case 'int':
                if (!is_numeric($value) || (string) (int) $value !== (string) $value && !is_int($value)) {
                    if (!preg_match('/^-?\d+$/', (string) $value)) {
                        $this->fail($field, 'Must be a whole number');
                        return $value;
                    }
                }
                return (int) $value;

            case 'decimal':
                if (!preg_match('/^-?\d+(\.\d{1,4})?$/', (string) $value)) {
                    $this->fail($field, 'Must be a number');
                    return $value;
                }
                return (string) $value;

            case 'money':
                try {
                    return Money::decimal($value);
                } catch (\InvalidArgumentException) {
                    $this->fail($field, 'Must be a valid amount');
                    return $value;
                }

            case 'boolean':
                if (is_bool($value)) {
                    return $value ? 1 : 0;
                }
                if (in_array((string) $value, ['0', '1', 'true', 'false'], true)) {
                    return in_array((string) $value, ['1', 'true'], true) ? 1 : 0;
                }
                $this->fail($field, 'Must be true or false');
                return $value;

            case 'email':
                $v = filter_var((string) $value, FILTER_VALIDATE_EMAIL);
                if ($v === false) {
                    $this->fail($field, 'Must be a valid email address');
                    return $value;
                }
                return strtolower($v);

            case 'phone':
                $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
                if (strlen($digits) < 7 || strlen($digits) > 15) {
                    $this->fail($field, 'Must be a valid phone number');
                    return $value;
                }
                return trim((string) $value);

            case 'date':
                if (!$this->isDate((string) $value, 'Y-m-d')) {
                    $this->fail($field, 'Must be a valid date (YYYY-MM-DD)');
                }
                return $value;

            case 'datetime':
                $v = str_replace('T', ' ', (string) $value);
                if (strlen($v) === 16) {
                    $v .= ':00';
                }
                if (!$this->isDate($v, 'Y-m-d H:i:s')) {
                    $this->fail($field, 'Must be a valid date and time');
                    return $value;
                }
                return $v;

            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)) {
                    $this->fail($field, 'Must be a valid time (HH:MM)');
                }
                return strlen((string) $value) === 5 ? $value . ':00' : $value;

            case 'in':
                $allowed = explode(',', (string) $arg);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->fail($field, 'Must be one of: ' . implode(', ', $allowed));
                }
                return $value;

            case 'min':
                if ((float) $value < (float) $arg) {
                    $this->fail($field, "Must be at least {$arg}");
                }
                return $value;

            case 'max':
                if ((float) $value > (float) $arg) {
                    $this->fail($field, "Must be at most {$arg}");
                }
                return $value;

            case 'minlen':
                if (mb_strlen((string) $value) < (int) $arg) {
                    $this->fail($field, "Must be at least {$arg} characters");
                }
                return $value;

            case 'maxlen':
                if (mb_strlen((string) $value) > (int) $arg) {
                    $this->fail($field, "Must be at most {$arg} characters");
                }
                return $value;

            case 'array':
                if (!is_array($value)) {
                    $this->fail($field, 'Must be a list');
                }
                return $value;

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $arg, 2), 2, 'id');
                if (!$this->existsScoped($table, $column, $value)) {
                    $this->fail($field, 'Selected record does not exist');
                }
                return $value;

            case 'unique':
                [$table, $column, $ignoreId] = array_pad(explode(',', (string) $arg, 3), 3, null);
                if ($this->existsScoped($table, $column ?? 'id', $value, $ignoreId ? (int) $ignoreId : null)) {
                    $this->fail($field, 'This value is already in use');
                }
                return $value;

            default:
                throw new \LogicException("Unknown validation rule: {$rule}");
        }
    }

    /** Identifiers come from the rule string in code, never from user input; still allow-listed. */
    private function existsScoped(string $table, string $column, mixed $value, ?int $ignoreId = null): bool
    {
        if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
            throw new \LogicException('Invalid table/column in validation rule');
        }
        $sql = "SELECT 1 FROM `{$table}` WHERE `{$column}` = :v";
        $params = ['v' => $value];
        if ($this->ctx?->organizationId !== null && $this->tableIsTenantScoped($table)) {
            $sql .= ' AND organization_id = :org';
            $params['org'] = $this->ctx->organizationId;
        }
        if ($this->columnExists($table, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore';
            $params['ignore'] = $ignoreId;
        }
        return Database::scalar($sql . ' LIMIT 1', $params) !== null;
    }

    private function tableIsTenantScoped(string $table): bool
    {
        return $this->columnExists($table, 'organization_id');
    }

    private static array $columnCache = [];

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, self::$columnCache)) {
            self::$columnCache[$key] = Database::scalar(
                'SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => $table, 'c' => $column]
            ) !== null;
        }
        return self::$columnCache[$key];
    }

    private function isDate(string $value, string $format): bool
    {
        $d = \DateTimeImmutable::createFromFormat($format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }
}
