<?php
/**
 * The complete API route table.
 *
 * Every protected route states the permission it requires, right here. The Router refuses to
 * register a route that declares neither a permission nor an explicit public/authOnly marker,
 * so an endpoint cannot ship without an authorization decision.
 */

use App\Core\Router;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;

$r = new Router();

/* ---------------------------------------------------------------- public */
$r->get('/health', [HealthController::class, 'index'], null, public: true);

$r->post('/auth/login',           [AuthController::class, 'login'],          null, public: true);
$r->post('/auth/forgot-password', [AuthController::class, 'forgotPassword'], null, public: true);
$r->post('/auth/reset-password',  [AuthController::class, 'resetPassword'],  null, public: true);

/* ------------------------------------------------- authenticated (no permission needed) */
$r->get('/auth/me',              [AuthController::class, 'me'],             null, authOnly: true);
$r->post('/auth/logout',         [AuthController::class, 'logout'],         null, authOnly: true);
$r->post('/auth/change-password',[AuthController::class, 'changePassword'], null, authOnly: true);

/* ---------------------------------------------------------------- dashboard */
$r->get('/dashboard/summary', [DashboardController::class, 'summary'], 'dashboard.view');
$r->get('/dashboard/charts',  [DashboardController::class, 'charts'],  'dashboard.view');

/* ---------------------------------------------------------------- patients */
$r->get('/patients',                 [PatientController::class, 'index'],          'patient.view');
$r->get('/patients/search',          [PatientController::class, 'search'],         'patient.view');
$r->get('/patients/check-duplicate', [PatientController::class, 'checkDuplicate'], 'patient.view');
$r->get('/patients/{id}',            [PatientController::class, 'show'],           'patient.view');
$r->post('/patients',                [PatientController::class, 'store'],          'patient.create');
$r->put('/patients/{id}',            [PatientController::class, 'update'],         'patient.update');
$r->delete('/patients/{id}',         [PatientController::class, 'destroy'],        'patient.delete');

/* ---------------------------------------------------------------- administration */
$r->get('/branches',        [BranchController::class, 'index'],   'branch.view');
$r->get('/branches/{id}',   [BranchController::class, 'show'],    'branch.view');
$r->post('/branches',       [BranchController::class, 'store'],   'branch.manage');
$r->put('/branches/{id}',   [BranchController::class, 'update'],  'branch.manage');
$r->delete('/branches/{id}',[BranchController::class, 'destroy'], 'branch.manage');

$r->get('/users',                     [UserController::class, 'index'],         'user.view');
$r->get('/users/{id}',                [UserController::class, 'show'],          'user.view');
$r->post('/users',                    [UserController::class, 'store'],         'user.manage');
$r->put('/users/{id}',                [UserController::class, 'update'],        'user.manage');
$r->post('/users/{id}/deactivate',    [UserController::class, 'deactivate'],    'user.manage');
$r->post('/users/{id}/reset-password',[UserController::class, 'resetPassword'], 'user.manage');

$r->get('/roles',             [RoleController::class, 'index'],       'role.view');
$r->get('/roles/permissions', [RoleController::class, 'permissions'], 'role.view');
$r->post('/roles',            [RoleController::class, 'store'],       'role.manage');
$r->put('/roles/{id}',        [RoleController::class, 'update'],      'role.manage');
$r->delete('/roles/{id}',     [RoleController::class, 'destroy'],     'role.manage');

$r->get('/settings',                  [SettingsController::class, 'index'],              'settings.view');
$r->put('/settings',                  [SettingsController::class, 'update'],             'settings.manage');
$r->put('/settings/organization',     [SettingsController::class, 'updateOrganization'], 'settings.manage');
$r->put('/settings/numbering',        [SettingsController::class, 'updateSequence'],     'settings.manage');

$r->get('/audit-logs', [AuditLogController::class, 'index'], 'audit.view');

/* ---------------------------------------------------------------- platform admin (Level 1) */
$r->platform('GET',  '/platform/overview',            [PlatformController::class, 'overview'],               'platform.organization.manage');
$r->platform('GET',  '/platform/organizations',       [PlatformController::class, 'organizations'],          'platform.organization.manage');
$r->platform('POST', '/platform/organizations',       [PlatformController::class, 'createOrganization'],     'platform.organization.manage');
$r->platform('PUT',  '/platform/organizations/{id}/status', [PlatformController::class, 'updateOrganizationStatus'], 'platform.organization.manage');
$r->platform('GET',  '/platform/plans',               [PlatformController::class, 'plans'],                  'platform.plan.manage');
$r->platform('POST', '/platform/plans',               [PlatformController::class, 'createPlan'],             'platform.plan.manage');
$r->platform('GET',  '/platform/subscriptions',       [PlatformController::class, 'subscriptions'],          'platform.subscription.manage');
$r->platform('GET',  '/platform/users',               [PlatformController::class, 'users'],                  'platform.user.view');

return $r;
