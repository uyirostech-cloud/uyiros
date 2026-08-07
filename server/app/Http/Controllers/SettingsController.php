<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Domain\Services\AuditService;
use App\Domain\Services\NumberingService;

final class SettingsController
{
    /** Only these keys can be written — an arbitrary key cannot be smuggled into settings. */
    private const ALLOWED = [
        'clinic.tagline', 'clinic.website', 'clinic.registration_number',
        'branding.primary_color', 'branding.logo_path',
        'invoice.prefix', 'invoice.padding', 'invoice.footer_note', 'invoice.default_tax_percent',
        'patient.prefix', 'patient.padding',
        'appointment.default_duration', 'appointment.allow_outside_schedule', 'appointment.slot_minutes',
        'prescription.header_note', 'prescription.footer_note',
        'notification.followup_reminder', 'notification.low_stock_alert',
    ];

    public function index(Request $request, Ctx $ctx): Response
    {
        $org = Database::selectOne(
            'SELECT id, code, name, email, phone, address, city, state, pin, country,
                    timezone, currency, currency_symbol, logo_path, status
               FROM organizations WHERE id = :o',
            ['o' => $ctx->orgId()]
        );
        $settings = [];
        foreach (Database::select(
            'SELECT setting_key, setting_value FROM organization_settings WHERE organization_id = :o',
            ['o' => $ctx->orgId()]
        ) as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $sequences = Database::select(
            'SELECT sequence_key, prefix, padding, next_value FROM number_sequences
              WHERE organization_id = :o ORDER BY sequence_key',
            ['o' => $ctx->orgId()]
        );

        return Response::json([
            'organization' => $org,
            'settings'     => $settings,
            'sequences'    => array_map(static fn ($s) => [
                'key'        => $s['sequence_key'],
                'prefix'     => $s['prefix'],
                'padding'    => (int) $s['padding'],
                'next_value' => (int) $s['next_value'],
                'preview'    => $s['prefix'] . '-' . str_pad((string) $s['next_value'], (int) $s['padding'], '0', STR_PAD_LEFT),
            ], $sequences),
            'allowed_keys' => self::ALLOWED,
        ]);
    }

    public function updateOrganization(Request $request, Ctx $ctx): Response
    {
        $data = Validator::make($request->only([
            'name', 'email', 'phone', 'address', 'city', 'state', 'pin', 'country',
            'timezone', 'currency', 'currency_symbol',
        ]), [
            'name'            => 'nullable|string|minlen:2|maxlen:160',
            'email'           => 'nullable|email|maxlen:190',
            'phone'           => 'nullable|phone|maxlen:30',
            'address'         => 'nullable|string|maxlen:255',
            'city'            => 'nullable|string|maxlen:80',
            'state'           => 'nullable|string|maxlen:80',
            'pin'             => 'nullable|string|maxlen:12',
            'country'         => 'nullable|string|maxlen:80',
            'timezone'        => 'nullable|string|maxlen:64',
            'currency'        => 'nullable|string|maxlen:8',
            'currency_symbol' => 'nullable|string|maxlen:8',
        ], $ctx);

        if ($data === []) {
            return Response::json(['message' => 'Nothing to update']);
        }
        $before = Database::selectOne('SELECT * FROM organizations WHERE id = :o', ['o' => $ctx->orgId()]);

        $sets = [];
        $params = ['o' => $ctx->orgId(), 'u' => Str::now()];
        foreach ($data as $key => $value) {
            $sets[] = "`{$key}` = :{$key}";           // keys come from the fixed list above
            $params[$key] = $value;
        }
        Database::run('UPDATE organizations SET ' . implode(', ', $sets) . ', updated_at = :u WHERE id = :o', $params);

        $after = Database::selectOne('SELECT * FROM organizations WHERE id = :o', ['o' => $ctx->orgId()]);
        [$old, $new] = AuditService::diff($before, $after);
        if ($new !== []) {
            AuditService::log($ctx, 'settings.organization_updated', 'organization', $ctx->orgId(), $old, $new);
        }
        return Response::json(['message' => 'Clinic information updated']);
    }

    public function update(Request $request, Ctx $ctx): Response
    {
        $settings = $request->input('settings');
        if (!is_array($settings) || $settings === []) {
            throw HttpException::validation(['settings' => 'Provide at least one setting']);
        }
        $before = [];
        foreach (Database::select(
            'SELECT setting_key, setting_value FROM organization_settings WHERE organization_id = :o',
            ['o' => $ctx->orgId()]
        ) as $row) {
            $before[$row['setting_key']] = $row['setting_value'];
        }

        $written = [];
        Database::transaction(function () use ($settings, $ctx, &$written) {
            foreach ($settings as $key => $value) {
                if (!in_array($key, self::ALLOWED, true)) {
                    throw HttpException::validation(['settings' => "Unknown setting: {$key}"]);
                }
                if (is_array($value) || is_object($value)) {
                    throw HttpException::validation(['settings' => "Setting {$key} must be a scalar value"]);
                }
                $value = $value === null ? null : mb_substr((string) $value, 0, 2000);
                Database::run(
                    'INSERT INTO organization_settings (organization_id, setting_key, setting_value, updated_at, updated_by)
                     VALUES (:o, :k, :v, :u, :by)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                             updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
                    ['o' => $ctx->orgId(), 'k' => $key, 'v' => $value, 'u' => Str::now(), 'by' => $ctx->userId]
                );
                $written[$key] = $value;
            }
        });

        $old = array_intersect_key($before, $written);
        AuditService::log($ctx, 'settings.updated', 'organization', $ctx->orgId(), $old, $written);
        return Response::json(['message' => 'Settings saved', 'settings' => $written]);
    }

    public function updateSequence(Request $request, Ctx $ctx): Response
    {
        $data = Validator::make($request->only(['key', 'prefix', 'padding']), [
            'key'     => 'required|in:' . implode(',', array_keys(NumberingService::DEFAULTS)),
            'prefix'  => 'required|string|minlen:1|maxlen:12',
            'padding' => 'required|int|min:3|max:10',
        ], $ctx);

        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $data['prefix']) ?? '');
        if ($prefix === '') {
            throw HttpException::validation(['prefix' => 'Prefix must contain letters or digits']);
        }
        $before = Database::selectOne(
            'SELECT prefix, padding FROM number_sequences WHERE organization_id = :o AND sequence_key = :k',
            ['o' => $ctx->orgId(), 'k' => $data['key']]
        );
        Database::run(
            'UPDATE number_sequences SET prefix = :p, padding = :d
              WHERE organization_id = :o AND sequence_key = :k',
            ['p' => $prefix, 'd' => (int) $data['padding'], 'o' => $ctx->orgId(), 'k' => $data['key']]
        );
        AuditService::log($ctx, 'settings.numbering_changed', 'number_sequence', null,
            $before, ['key' => $data['key'], 'prefix' => $prefix, 'padding' => (int) $data['padding']]);
        return Response::json(['message' => 'Numbering format updated']);
    }
}
