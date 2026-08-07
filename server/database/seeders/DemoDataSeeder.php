<?php
namespace Database\Seeders;

use App\Core\Database;
use App\Core\Str;
use App\Domain\Services\AuthService;
use App\Domain\Services\NumberingService;
use App\Http\Controllers\PlatformController;

/**
 * Fictional demo data. No real patient information is used anywhere in this file.
 *
 * Two organizations are created on purpose: the second one exists so tenant isolation can be
 * demonstrated and tested — Clinic A must never be able to see Clinic B's records.
 */
final class DemoDataSeeder
{
    public const DEMO_PASSWORD = 'ClinicFlow#2026';

    public static function run(): array
    {
        $summary = [];
        $summary['demo-general-clinic'] = self::seedPrimary();
        $summary['city-care-clinic']    = self::seedSecondary();
        return $summary;
    }

    private static function seedPrimary(): array
    {
        if (Database::scalar('SELECT id FROM organizations WHERE code = :c', ['c' => 'DEMO']) !== null) {
            return ['skipped' => 'already seeded'];
        }
        $now = Str::now();
        $today = Str::today();

        $planId = Database::scalar("SELECT id FROM plans WHERE code = 'professional'");
        $orgId = Database::insert(
            'INSERT INTO organizations (public_id, code, name, slug, status, plan_id, email, phone,
                                        address, city, state, pin, created_at)
             VALUES (:pub, :code, :name, :slug, :status, :plan, :email, :phone, :addr, :city, :state, :pin, :created)',
            [
                'pub' => Str::ulid(), 'code' => 'DEMO', 'name' => 'Demo General Clinic',
                'slug' => 'demo-general-clinic', 'status' => 'active', 'plan' => $planId,
                'email' => 'contact@demogeneralclinic.test', 'phone' => '04412345678',
                'addr' => '12 Second Avenue', 'city' => 'Chennai', 'state' => 'Tamil Nadu',
                'pin' => '600040', 'created' => $now,
            ]
        );
        NumberingService::seedDefaults($orgId);
        $roles = PlatformController::seedRoles($orgId);
        PlatformController::seedDefaultData($orgId);

        $branches = [];
        foreach ([['Anna Nagar', 'ANN', 'Chennai'], ['T Nagar', 'TNG', 'Chennai']] as [$name, $code, $city]) {
            $branches[$code] = Database::insert(
                'INSERT INTO branches (organization_id, name, code, phone, email, address, city, state, pin,
                                       opening_time, closing_time, working_days, is_active, created_at)
                 VALUES (:o, :n, :c, :ph, :em, :ad, :city, :st, :pin, "09:00:00", "20:00:00", "1,2,3,4,5,6", 1, :created)',
                [
                    'o' => $orgId, 'n' => $name, 'c' => $code,
                    'ph' => '0441111' . random_int(1000, 9999),
                    'em' => strtolower($code) . '@demogeneralclinic.test',
                    'ad' => $name . ' Main Road', 'city' => $city, 'st' => 'Tamil Nadu',
                    'pin' => '600040', 'created' => $now,
                ]
            );
        }

        $users = [
            ['Anita Raman',    'admin@demoklinik.test',        'clinic_admin', true,  null],
            ['Dr Suresh Kumar','suresh.doctor@demoklinik.test','doctor',       false, ['ANN']],
            ['Dr Meera Iyer',  'meera.doctor@demoklinik.test', 'doctor',       false, ['TNG']],
            ['Priya Nair',     'reception@demoklinik.test',    'receptionist', false, ['ANN']],
            ['Rahul Verma',    'sales@demoklinik.test',        'sales',        true,  null],
            ['Karthik Menon',  'operations@demoklinik.test',   'operations',   true,  null],
            ['Divya Shankar',  'accounts@demoklinik.test',     'finance',      true,  null],
        ];
        $userIds = [];
        foreach ($users as [$name, $email, $roleSlug, $allBranches, $branchCodes]) {
            $userId = Database::insert(
                'INSERT INTO users (public_id, organization_id, role_id, name, email, phone, password_hash,
                                    is_active, all_branches, must_change_password, created_at)
                 VALUES (:pub, :o, :r, :n, :e, :p, :h, 1, :ab, 0, :created)',
                [
                    'pub' => Str::ulid(), 'o' => $orgId, 'r' => $roles[$roleSlug], 'n' => $name,
                    'e' => $email, 'p' => '98' . random_int(10000000, 99999999),
                    'h' => AuthService::hash(self::DEMO_PASSWORD),
                    'ab' => $allBranches ? 1 : 0, 'created' => $now,
                ]
            );
            foreach ($branchCodes ?? [] as $code) {
                Database::run('INSERT INTO user_branches (user_id, branch_id, created_at) VALUES (:u, :b, :c)',
                    ['u' => $userId, 'b' => $branches[$code], 'c' => $now]);
            }
            $userIds[$email] = $userId;
        }

        // Doctors linked to their user accounts, so a doctor sees their own queue.
        $doctors = [];
        foreach ([
            ['Dr Suresh Kumar', 'MBBS, MD (General Medicine)', 'General Medicine', 'TN-45211',
             'suresh.doctor@demoklinik.test', '500.00', '300.00', 15, 'ANN'],
            ['Dr Meera Iyer', 'MBBS, DNB (Family Medicine)', 'Family Medicine', 'TN-51884',
             'meera.doctor@demoklinik.test', '600.00', '350.00', 20, 'TNG'],
        ] as [$name, $qual, $spec, $reg, $email, $fee, $followup, $duration, $branchCode]) {
            $doctorId = Database::insert(
                'INSERT INTO doctors (organization_id, user_id, name, qualification, specialization,
                                      registration_number, phone, email, consultation_fee, followup_fee,
                                      default_duration_minutes, is_active, created_at)
                 VALUES (:o, :u, :n, :q, :s, :reg, :ph, :em, :fee, :fu, :dur, 1, :created)',
                [
                    'o' => $orgId, 'u' => $userIds[$email], 'n' => $name, 'q' => $qual, 's' => $spec,
                    'reg' => $reg, 'ph' => '98' . random_int(10000000, 99999999), 'em' => $email,
                    'fee' => $fee, 'fu' => $followup, 'dur' => $duration, 'created' => $now,
                ]
            );
            Database::run('INSERT INTO doctor_branches (organization_id, doctor_id, branch_id, created_at)
                           VALUES (:o, :d, :b, :c)',
                ['o' => $orgId, 'd' => $doctorId, 'b' => $branches[$branchCode], 'c' => $now]);
            for ($day = 1; $day <= 6; $day++) {
                Database::run(
                    'INSERT INTO doctor_schedules (organization_id, doctor_id, branch_id, day_of_week,
                                                   start_time, end_time, slot_minutes, is_active, created_at)
                     VALUES (:o, :d, :b, :day, "09:00:00", "17:00:00", :slot, 1, :created)',
                    ['o' => $orgId, 'd' => $doctorId, 'b' => $branches[$branchCode],
                     'day' => $day, 'slot' => $duration, 'created' => $now]
                );
            }
            $doctors[$name] = $doctorId;
        }

        foreach ([
            ['General Consultation', 'Consultation', '500.00', 0, '0.000'],
            ['Follow-up Consultation', 'Consultation', '300.00', 0, '0.000'],
            ['Injection', 'Procedure', '150.00', 1, '5.000'],
            ['Dressing', 'Procedure', '250.00', 1, '5.000'],
            ['Nebulization', 'Procedure', '350.00', 1, '5.000'],
            ['Basic Health Check', 'Package', '1200.00', 1, '18.000'],
            ['Minor Procedure', 'Procedure', '2500.00', 1, '18.000'],
            ['Medical Certificate', 'Administrative', '200.00', 0, '0.000'],
        ] as [$name, $category, $price, $taxable, $tax]) {
            Database::run(
                'INSERT INTO services (organization_id, name, category, price, is_taxable, tax_percent,
                                       is_active, created_at)
                 VALUES (:o, :n, :c, :p, :t, :tp, 1, :created)',
                ['o' => $orgId, 'n' => $name, 'c' => $category, 'p' => $price,
                 't' => $taxable, 'tp' => $tax, 'created' => $now]
            );
        }

        $patients = self::seedPatients($orgId, $branches, $userIds['admin@demoklinik.test']);
        self::seedLeads($orgId, $branches, $userIds['sales@demoklinik.test'], $now);
        self::seedVendorsAndInventory($orgId, $branches, $now);
        self::seedTasks($orgId, $branches, $userIds, $now);

        Database::run(
            'INSERT INTO subscriptions (organization_id, plan_id, status, billing_cycle, amount, starts_at, created_at)
             VALUES (:o, :p, "active", "monthly", "3999.00", :s, :c)',
            ['o' => $orgId, 'p' => $planId, 's' => $today, 'c' => $now]
        );

        return [
            'organization_id' => $orgId,
            'branches'        => count($branches),
            'users'           => count($userIds),
            'doctors'         => count($doctors),
            'patients'        => count($patients),
        ];
    }

    /** A second, unrelated clinic. Its data must be invisible to Demo General Clinic. */
    private static function seedSecondary(): array
    {
        if (Database::scalar('SELECT id FROM organizations WHERE code = :c', ['c' => 'CITYCARE']) !== null) {
            return ['skipped' => 'already seeded'];
        }
        $now = Str::now();
        $planId = Database::scalar("SELECT id FROM plans WHERE code = 'starter'");

        $orgId = Database::insert(
            'INSERT INTO organizations (public_id, code, name, slug, status, plan_id, email, phone,
                                        city, state, created_at)
             VALUES (:pub, "CITYCARE", "City Care Clinic", "city-care-clinic", "active", :plan,
                     "hello@citycareclinic.test", "08012345678", "Bengaluru", "Karnataka", :created)',
            ['pub' => Str::ulid(), 'plan' => $planId, 'created' => $now]
        );
        NumberingService::seedDefaults($orgId);
        $roles = PlatformController::seedRoles($orgId);
        PlatformController::seedDefaultData($orgId);

        $branchId = Database::insert(
            'INSERT INTO branches (organization_id, name, code, city, state, is_active, created_at)
             VALUES (:o, "Indiranagar", "IND", "Bengaluru", "Karnataka", 1, :created)',
            ['o' => $orgId, 'created' => $now]
        );
        $adminId = Database::insert(
            'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                                is_active, all_branches, must_change_password, created_at)
             VALUES (:pub, :o, :r, "Vikram Rao", "admin@citycare.test", :h, 1, 1, 0, :created)',
            ['pub' => Str::ulid(), 'o' => $orgId, 'r' => $roles['clinic_admin'],
             'h' => AuthService::hash(self::DEMO_PASSWORD), 'created' => $now]
        );

        $doctorId = Database::insert(
            'INSERT INTO doctors (organization_id, name, qualification, specialization, registration_number,
                                  consultation_fee, followup_fee, default_duration_minutes, is_active, created_at)
             VALUES (:o, "Dr Neha Bhat", "MBBS", "General Practice", "KA-77120", "450.00", "250.00", 15, 1, :created)',
            ['o' => $orgId, 'created' => $now]
        );
        Database::run('INSERT INTO doctor_branches (organization_id, doctor_id, branch_id, created_at)
                       VALUES (:o, :d, :b, :c)',
            ['o' => $orgId, 'd' => $doctorId, 'b' => $branchId, 'c' => $now]);

        foreach ([
            ['Ramesh Gowda', '9880011221', 'Male', 44],
            ['Sunita Pillai', '9880011222', 'Female', 36],
            ['Arjun Shetty', '9880011223', 'Male', 29],
        ] as [$name, $phone, $gender, $age]) {
            Database::run(
                'INSERT INTO patients (public_id, organization_id, branch_id, patient_number, full_name,
                                       phone, phone_normalized, gender, age_years, city, state,
                                       registered_at, created_at, created_by)
                 VALUES (:pub, :o, :b, :num, :n, :ph, :norm, :g, :age, "Bengaluru", "Karnataka", :reg, :created, :by)',
                [
                    'pub' => Str::ulid(), 'o' => $orgId, 'b' => $branchId,
                    'num' => NumberingService::next($orgId, 'patient'), 'n' => $name,
                    'ph' => $phone, 'norm' => Str::normalizePhone($phone), 'g' => $gender, 'age' => $age,
                    'reg' => Str::today(), 'created' => $now, 'by' => $adminId,
                ]
            );
        }

        return ['organization_id' => $orgId, 'branches' => 1, 'users' => 1, 'patients' => 3];
    }

    private static function seedPatients(int $orgId, array $branches, int $createdBy): array
    {
        $now = Str::now();
        $rows = [
            ['Arun Prakash',      '9840012301', 'Male',   42, 'B+',  'ANN', 'Walk-in',  'Penicillin'],
            ['Lakshmi Narayanan', '9840012302', 'Female', 35, 'O+',  'ANN', 'Referral', null],
            ['Vignesh Babu',      '9840012303', 'Male',   28, 'A+',  'ANN', 'Website',  null],
            ['Deepa Krishnan',    '9840012304', 'Female', 51, 'AB+', 'TNG', 'Phone',    'Sulfa drugs'],
            ['Mohan Raj',         '9840012305', 'Male',   63, 'O-',  'TNG', 'Walk-in',  null],
            ['Sridevi Ganesan',   '9840012306', 'Female', 47, 'B-',  'ANN', 'WhatsApp', 'Dust, pollen'],
            ['Kavitha Sundaram',  '9840012307', 'Female', 31, 'A-',  'TNG', 'Instagram',null],
            ['Ravi Chandran',     '9840012308', 'Male',   55, 'B+',  'ANN', 'Referral', null],
            ['Anjali Rao',        '9840012309', 'Female', 24, 'O+',  'TNG', 'Google Ads', null],
            ['Prakash Iyer',      '9840012310', 'Male',   38, 'AB-', 'ANN', 'Existing Patient', null],
        ];
        $ids = [];
        foreach ($rows as $i => [$name, $phone, $gender, $age, $blood, $branchCode, $source, $allergies]) {
            $ids[] = Database::insert(
                'INSERT INTO patients (public_id, organization_id, branch_id, patient_number, full_name,
                                       phone, phone_normalized, email, gender, age_years, blood_group,
                                       address, city, state, pin, occupation, source, allergies,
                                       registered_at, created_at, created_by)
                 VALUES (:pub, :o, :b, :num, :n, :ph, :norm, :em, :g, :age, :bg,
                         :addr, "Chennai", "Tamil Nadu", "600040", :occ, :src, :al, :reg, :created, :by)',
                [
                    'pub' => Str::ulid(), 'o' => $orgId, 'b' => $branches[$branchCode],
                    'num' => NumberingService::next($orgId, 'patient'), 'n' => $name,
                    'ph' => $phone, 'norm' => Str::normalizePhone($phone),
                    'em' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
                    'g' => $gender, 'age' => $age, 'bg' => $blood,
                    'addr' => ($i + 4) . ' Gandhi Street', 'occ' => 'Not specified',
                    'src' => $source, 'al' => $allergies,
                    'reg' => gmdate('Y-m-d', time() - random_int(0, 60) * 86400),
                    'created' => $now, 'by' => $createdBy,
                ]
            );
        }
        return $ids;
    }

    private static function seedLeads(int $orgId, array $branches, int $assignedTo, string $now): void
    {
        $leads = [
            ['Suresh Balan',   '9790011001', 'Website',    'New',              'High',   0],
            ['Ramya Prasad',   '9790011002', 'WhatsApp',   'Contacted',        'Medium', 1],
            ['Gopal Krishnan', '9790011003', 'Facebook',   'Follow-up',        'Medium', 2],
            ['Nithya Menon',   '9790011004', 'Google Ads', 'Interested',       'High',   1],
            ['Ajay Kumar',     '9790011005', 'Referral',   'Appointment Booked','High',  3],
            ['Bhavana Reddy',  '9790011006', 'Instagram',  'Not Interested',   'Low',    null],
            ['Manoj Pillai',   '9790011007', 'Phone',      'New',              'Medium', 0],
        ];
        foreach ($leads as [$name, $phone, $source, $status, $priority, $followupInDays]) {
            Database::run(
                'INSERT INTO leads (organization_id, branch_id, lead_number, name, phone, phone_normalized,
                                    email, source, assigned_user_id, priority, status, next_followup_at,
                                    notes, created_at, created_by)
                 VALUES (:o, :b, :num, :n, :ph, :norm, :em, :src, :assigned, :pri, :st, :fu, :notes, :created, :by)',
                [
                    'o' => $orgId, 'b' => $branches['ANN'],
                    'num' => NumberingService::next($orgId, 'lead'), 'n' => $name,
                    'ph' => $phone, 'norm' => Str::normalizePhone($phone),
                    'em' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
                    'src' => $source, 'assigned' => $assignedTo, 'pri' => $priority, 'st' => $status,
                    'fu' => $followupInDays === null ? null : gmdate('Y-m-d H:i:s', time() + $followupInDays * 86400),
                    'notes' => 'Demo lead record — fictional data.',
                    'created' => gmdate('Y-m-d H:i:s', time() - random_int(0, 10) * 86400), 'by' => $assignedTo,
                ]
            );
        }
    }

    private static function seedVendorsAndInventory(int $orgId, array $branches, string $now): void
    {
        $vendors = [];
        foreach ([
            ['MedSupply Chennai', 'Ganesh R', '9840500101', 'Medical Supplies'],
            ['CleanCare Services', 'Latha M', '9840500102', 'Maintenance'],
            ['PrintWorks', 'Anand S', '9840500103', 'Office'],
        ] as [$name, $contact, $phone, $category]) {
            $vendors[] = Database::insert(
                'INSERT INTO vendors (organization_id, name, contact_person, phone, email, category,
                                      is_active, created_at)
                 VALUES (:o, :n, :c, :p, :e, :cat, 1, :created)',
                ['o' => $orgId, 'n' => $name, 'c' => $contact, 'p' => $phone,
                 'e' => Str::slug($name) . '@example.test', 'cat' => $category, 'created' => $now]
            );
        }
        $categoryId = Database::scalar(
            'SELECT id FROM inventory_categories WHERE organization_id = :o AND name = "Consumables"',
            ['o' => $orgId]
        );
        foreach ([
            ['Disposable Gloves (M)', 'GLV-M', 'box', '10.000', '450.00', 40],
            ['Surgical Masks',        'MSK-1', 'box', '15.000', '250.00', 60],
            ['Cotton Rolls',          'CTN-1', 'pack', '8.000', '120.00', 5],
            ['Syringes 5ml',          'SYR-5', 'box', '12.000', '380.00', 3],
        ] as [$name, $sku, $unit, $minStock, $cost, $openingQty]) {
            $itemId = Database::insert(
                'INSERT INTO inventory_items (organization_id, branch_id, category_id, vendor_id, name, sku,
                                              unit, minimum_stock, purchase_cost, is_active, created_at)
                 VALUES (:o, :b, :cat, :v, :n, :sku, :u, :min, :cost, 1, :created)',
                ['o' => $orgId, 'b' => $branches['ANN'], 'cat' => $categoryId, 'v' => $vendors[0],
                 'n' => $name, 'sku' => $sku, 'u' => $unit, 'min' => $minStock,
                 'cost' => $cost, 'created' => $now]
            );
            // Stock only ever changes through a transaction row.
            Database::run(
                'INSERT INTO inventory_transactions (organization_id, branch_id, item_id, type, quantity,
                                                     unit_cost, reference, transacted_at, created_at)
                 VALUES (:o, :b, :i, "in", :q, :c, "Opening stock", :t, :created)',
                ['o' => $orgId, 'b' => $branches['ANN'], 'i' => $itemId, 'q' => $openingQty . '.000',
                 'c' => $cost, 't' => $now, 'created' => $now]
            );
        }
    }

    private static function seedTasks(int $orgId, array $branches, array $userIds, string $now): void
    {
        foreach ([
            ['Restock consumables at Anna Nagar', 'High',   'Open',        1],
            ['Verify AC servicing invoice',       'Medium', 'In Progress', 3],
            ['Update clinic notice board',        'Low',    'Open',        7],
        ] as [$title, $priority, $status, $dueInDays]) {
            Database::run(
                'INSERT INTO tasks (organization_id, branch_id, title, description, assigned_user_id,
                                    priority, due_date, status, created_by, created_at)
                 VALUES (:o, :b, :t, :d, :u, :p, :due, :s, :by, :created)',
                [
                    'o' => $orgId, 'b' => $branches['ANN'], 't' => $title,
                    'd' => 'Demo task — fictional data.',
                    'u' => $userIds['operations@demoklinik.test'], 'p' => $priority,
                    'due' => gmdate('Y-m-d', time() + $dueInDays * 86400), 's' => $status,
                    'by' => $userIds['admin@demoklinik.test'], 'created' => $now,
                ]
            );
        }
    }
}
