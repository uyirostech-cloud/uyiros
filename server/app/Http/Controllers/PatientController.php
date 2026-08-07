<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Domain\Repositories\PatientRepository;
use App\Domain\Services\AuditService;
use App\Domain\Services\NumberingService;
use App\Core\Database;

final class PatientController
{
    private const GENDERS = 'Male,Female,Other';
    private const SOURCES = 'Website,Phone,Walk-in,WhatsApp,Facebook,Instagram,Google Ads,Referral,Existing Patient,Other';

    public function index(Request $request, Ctx $ctx): Response
    {
        $repo = new PatientRepository($ctx);
        [$page, $perPage] = $request->pagination();

        $filters = [];
        if (($q = trim((string) $request->query('q', ''))) !== '') {
            $like = '%' . PatientRepository::escapeLike($q) . '%';
            $filters['__raw'][] = [
                '(full_name LIKE :q1 OR phone LIKE :q2 OR patient_number LIKE :q3)',
                ['q1' => $like, 'q2' => $like, 'q3' => $like],
            ];
        }
        if (($branchId = $request->query('branch_id')) !== null && $branchId !== '') {
            $filters['branch_id'] = (int) $branchId;
        }
        if (($gender = $request->query('gender')) !== null && $gender !== '') {
            $filters['gender'] = (string) $gender;
        }
        if (($from = $request->query('from')) !== null && $from !== '') {
            $filters['__raw'][] = ['registered_at >= :from', ['from' => (string) $from]];
        }
        if (($to = $request->query('to')) !== null && $to !== '') {
            $filters['__raw'][] = ['registered_at <= :to', ['to' => (string) $to]];
        }

        [$rows, $total] = $repo->paginate(
            $filters,
            $page,
            $perPage,
            (string) $request->query('sort', 'created_at') ?: 'created_at',
            (string) $request->query('dir', 'desc')
        );

        return Response::paginated(array_map([$this, 'present'], $rows), $total, $page, $perPage);
    }

    public function show(Request $request, Ctx $ctx): Response
    {
        $repo = new PatientRepository($ctx);
        $patient = $repo->withBranch($request->intParam('id'));
        if ($patient === null) {
            throw HttpException::notFound('Patient not found');
        }
        $ctx->assertBranch($patient['branch_id'] === null ? null : (int) $patient['branch_id']);
        return Response::json($this->present($patient));
    }

    public function store(Request $request, Ctx $ctx): Response
    {
        $data = $this->validate($request, $ctx);
        $repo = new PatientRepository($ctx);

        if (!$request->query('force') && !$request->input('force')) {
            $existing = $repo->findByPhone((string) $data['phone']);
            if ($existing !== null) {
                throw HttpException::conflict(
                    'A patient with this phone number already exists',
                    'DUPLICATE_PHONE',
                    ['existing' => $existing]
                );
            }
        }

        $id = Database::transaction(function () use ($repo, $data, $ctx) {
            $data['patient_number']   = NumberingService::next($ctx->orgId(), 'patient');
            $data['public_id']        = Str::ulid();
            $data['phone_normalized'] = Str::normalizePhone((string) $data['phone']);
            $data['registered_at']  ??= Str::today();
            return $repo->create($data);
        });

        $patient = $repo->findOrFail($id);
        AuditService::log($ctx, 'patient.created', 'patient', $id, null, $this->present($patient));

        return Response::json($this->present($patient), 201);
    }

    public function update(Request $request, Ctx $ctx): Response
    {
        $repo = new PatientRepository($ctx);
        $id = $request->intParam('id');
        $before = $repo->findOrFail($id);
        $ctx->assertBranch($before['branch_id'] === null ? null : (int) $before['branch_id']);

        $data = $this->validate($request, $ctx, $id);
        if (array_key_exists('phone', $data)) {
            $data['phone_normalized'] = Str::normalizePhone((string) $data['phone']);
            if (!$request->input('force')) {
                $existing = $repo->findByPhone((string) $data['phone'], $id);
                if ($existing !== null) {
                    throw HttpException::conflict(
                        'Another patient already uses this phone number',
                        'DUPLICATE_PHONE',
                        ['existing' => $existing]
                    );
                }
            }
        }

        $after = $repo->update($id, $data);
        [$old, $new] = AuditService::diff($before, $after);
        if ($new !== []) {
            AuditService::log($ctx, 'patient.updated', 'patient', $id, $old, $new);
        }
        return Response::json($this->present($after));
    }

    public function destroy(Request $request, Ctx $ctx): Response
    {
        $repo = new PatientRepository($ctx);
        $id = $request->intParam('id');
        $before = $repo->findOrFail($id);
        $repo->softDelete($id);
        AuditService::log($ctx, 'patient.deleted', 'patient', $id, $this->present($before), null);
        return Response::json(['message' => 'Patient removed']);
    }

    public function search(Request $request, Ctx $ctx): Response
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return Response::json([]);
        }
        return Response::json((new PatientRepository($ctx))->search($term));
    }

    public function checkDuplicate(Request $request, Ctx $ctx): Response
    {
        $phone = (string) $request->query('phone', '');
        if ($phone === '') {
            throw HttpException::validation(['phone' => 'This field is required']);
        }
        $match = (new PatientRepository($ctx))->findByPhone($phone);
        return Response::json(['duplicate' => $match !== null, 'patient' => $match]);
    }

    private function validate(Request $request, Ctx $ctx, ?int $ignoreId = null): array
    {
        $payload = $request->only([
            'branch_id', 'full_name', 'phone', 'alt_phone', 'email', 'dob', 'age_years', 'gender',
            'blood_group', 'address', 'city', 'state', 'pin', 'emergency_contact_name',
            'emergency_contact_phone', 'occupation', 'source', 'allergies', 'medical_notes',
            'registered_at', 'is_active',
        ]);

        $required = $ignoreId === null ? 'required|' : 'nullable|';
        $data = Validator::make($payload, [
            'branch_id'               => 'nullable|int|exists:branches',
            'full_name'               => $required . 'string|minlen:2|maxlen:160',
            'phone'                   => $required . 'phone|maxlen:30',
            'alt_phone'               => 'nullable|phone|maxlen:30',
            'email'                   => 'nullable|email|maxlen:190',
            'dob'                     => 'nullable|date',
            'age_years'               => 'nullable|int|min:0|max:130',
            'gender'                  => 'nullable|in:' . self::GENDERS,
            'blood_group'             => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'address'                 => 'nullable|string|maxlen:255',
            'city'                    => 'nullable|string|maxlen:80',
            'state'                   => 'nullable|string|maxlen:80',
            'pin'                     => 'nullable|string|maxlen:12',
            'emergency_contact_name'  => 'nullable|string|maxlen:120',
            'emergency_contact_phone' => 'nullable|phone|maxlen:30',
            'occupation'              => 'nullable|string|maxlen:80',
            'source'                  => 'nullable|in:' . self::SOURCES,
            'allergies'               => 'nullable|string|maxlen:2000',
            'medical_notes'           => 'nullable|string|maxlen:2000',
            'registered_at'           => 'nullable|date',
            'is_active'               => 'nullable|boolean',
        ], $ctx);

        if (array_key_exists('branch_id', $data) && $data['branch_id'] !== null) {
            $ctx->assertBranch((int) $data['branch_id']);
        }
        // Derive age from DOB when the client did not supply one.
        if (!empty($data['dob']) && empty($data['age_years'])) {
            $data['age_years'] = (int) (new \DateTimeImmutable($data['dob']))
                ->diff(new \DateTimeImmutable('today'))->y;
        }
        return $data;
    }

    private function present(array $row): array
    {
        unset($row['organization_id'], $row['deleted_at']);
        foreach (['id', 'branch_id', 'age_years', 'lead_id', 'created_by', 'updated_by'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        if (array_key_exists('is_active', $row)) {
            $row['is_active'] = (int) $row['is_active'] === 1;
        }
        return $row;
    }
}
