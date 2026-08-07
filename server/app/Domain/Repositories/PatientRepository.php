<?php
namespace App\Domain\Repositories;

use App\Core\Str;

final class PatientRepository extends BaseRepository
{
    protected function table(): string
    {
        return 'patients';
    }

    protected function hasSoftDeletes(): bool
    {
        return true;
    }

    protected function hasBranch(): bool
    {
        return true;
    }

    protected function fillable(): array
    {
        return [
            'branch_id', 'patient_number', 'public_id', 'full_name', 'phone', 'phone_normalized',
            'alt_phone', 'email', 'dob', 'age_years', 'gender', 'blood_group', 'address', 'city',
            'state', 'pin', 'emergency_contact_name', 'emergency_contact_phone', 'occupation',
            'source', 'allergies', 'medical_notes', 'lead_id', 'registered_at', 'is_active',
        ];
    }

    protected function sortable(): array
    {
        return ['id', 'full_name', 'patient_number', 'registered_at', 'created_at', 'city'];
    }

    /** Duplicate detection on the normalized phone key, within this organization only. */
    public function findByPhone(string $phone, ?int $excludeId = null): ?array
    {
        $normalized = Str::normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }
        $sql = 'SELECT id, patient_number, full_name, phone, city
                  FROM patients
                 WHERE organization_id = :ctx_org AND deleted_at IS NULL AND phone_normalized = :p';
        $params = ['p' => $normalized];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :ex';
            $params['ex'] = $excludeId;
        }
        return $this->scopedSelectOne($sql . ' LIMIT 1', $params);
    }

    /** Free-text search across name / phone / UHID / email — parameterized, never concatenated. */
    public function search(string $term, int $limit = 20): array
    {
        $like = '%' . self::escapeLike($term) . '%';
        return $this->scopedSelect(
            'SELECT id, patient_number, full_name, phone, gender, age_years, city
               FROM patients
              WHERE organization_id = :ctx_org AND deleted_at IS NULL
                AND (full_name LIKE :a OR phone LIKE :b OR patient_number LIKE :c OR email LIKE :d)
              ORDER BY full_name LIMIT ' . max(1, min(50, $limit)),
            ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like]
        );
    }

    public function withBranch(int $id): ?array
    {
        return $this->scopedSelectOne(
            'SELECT p.*, b.name AS branch_name
               FROM patients p
               LEFT JOIN branches b ON b.id = p.branch_id AND b.organization_id = p.organization_id
              WHERE p.id = :id AND p.organization_id = :ctx_org AND p.deleted_at IS NULL
              LIMIT 1',
            ['id' => $id]
        );
    }
}
