<?php
namespace App\Domain\Services;

use App\Core\Database;

/**
 * Organization-aware business numbers (PAT-000001, INV-000001, …).
 * Allocated under a row lock inside the caller's transaction so concurrent inserts
 * cannot produce duplicates. Never generated in the browser.
 */
final class NumberingService
{
    public const DEFAULTS = [
        'patient'      => ['PAT',  6],
        'lead'         => ['LEAD', 6],
        'appointment'  => ['APT',  6],
        'invoice'      => ['INV',  6],
        'payment'      => ['PAY',  6],
        'expense'      => ['EXP',  6],
        'prescription' => ['RX',   6],
        'refund'       => ['REF',  6],
    ];

    public static function next(int $organizationId, string $key): string
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new \LogicException("Unknown number sequence: {$key}");
        }
        $ownTransaction = !Database::inTransaction();
        if ($ownTransaction) {
            Database::begin();
        }
        try {
            $row = Database::selectOne(
                'SELECT prefix, padding, next_value FROM number_sequences
                  WHERE organization_id = :o AND sequence_key = :k FOR UPDATE',
                ['o' => $organizationId, 'k' => $key]
            );
            if ($row === null) {
                [$prefix, $padding] = self::DEFAULTS[$key];
                Database::run(
                    'INSERT INTO number_sequences (organization_id, sequence_key, prefix, padding, next_value)
                     VALUES (:o, :k, :p, :d, 1)',
                    ['o' => $organizationId, 'k' => $key, 'p' => $prefix, 'd' => $padding]
                );
                $row = Database::selectOne(
                    'SELECT prefix, padding, next_value FROM number_sequences
                      WHERE organization_id = :o AND sequence_key = :k FOR UPDATE',
                    ['o' => $organizationId, 'k' => $key]
                );
            }
            $value = (int) $row['next_value'];
            Database::run(
                'UPDATE number_sequences SET next_value = next_value + 1
                  WHERE organization_id = :o AND sequence_key = :k',
                ['o' => $organizationId, 'k' => $key]
            );
            $number = $row['prefix'] . '-' . str_pad((string) $value, (int) $row['padding'], '0', STR_PAD_LEFT);
            if ($ownTransaction) {
                Database::commit();
            }
            return $number;
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                Database::rollback();
            }
            throw $e;
        }
    }

    public static function seedDefaults(int $organizationId): void
    {
        foreach (self::DEFAULTS as $key => [$prefix, $padding]) {
            Database::run(
                'INSERT IGNORE INTO number_sequences (organization_id, sequence_key, prefix, padding, next_value)
                 VALUES (:o, :k, :p, :d, 1)',
                ['o' => $organizationId, 'k' => $key, 'p' => $prefix, 'd' => $padding]
            );
        }
    }
}
