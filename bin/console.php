<?php

declare(strict_types=1);

use App\Bootstrap\Config;
use App\Bootstrap\Env;
use App\Audit\AuditLogger;
use App\Audit\AuditRepository;
//use PDO;
//use Throwable;

require_once dirname(__DIR__) . '/app/Bootstrap/Env.php';
require_once dirname(__DIR__) . '/app/Bootstrap/Config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

Env::load(dirname(__DIR__) . '/.env');

$command = $argv[1] ?? null;

if ($command === null) {
    fwrite(STDOUT, "Uso: php bin/console.php <comando>\n");
    fwrite(STDOUT, "Comandos disponibles:\n");
    fwrite(STDOUT, "  seed:dev   Seed de desarrollo\n");
    exit(0);
}

try {
    if ($command === 'seed:dev') {
        seedDev();
        fwrite(STDOUT, "Seed de desarrollo completado.\n");
        exit(0);
    }

    fwrite(STDERR, "Comando no reconocido: {$command}\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

function seedDev(): void
{
    $db = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::get('DB_HOST', '127.0.0.1'),
            Config::get('DB_PORT', '3306'),
            Config::get('DB_NAME', 'parrepos')
        ),
        Config::get('DB_USER', 'root'),
        Config::get('DB_PASS', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $db->beginTransaction();

    try {
        $tenantId = upsertTenant($db);
        upsertTenantSettings($db, $tenantId);
        $roleId = upsertRole($db, $tenantId, 'OWNER', 'Owner');
        $permissionIds = upsertPermissions($db, $tenantId, [
            'auth.*',
            'tenant_settings.manage',
            'users.manage',
            'roles.manage',
            'audit.read',
        ]);
        upsertRolePermissions($db, $tenantId, $roleId, $permissionIds);
        $userId = upsertAdminUser($db, $tenantId);
        upsertUserRole($db, $tenantId, $userId, $roleId);

        $audit = new AuditLogger(new AuditRepository($db));
        $audit->log($tenantId, 0, 'seed.dev', ['actor' => 'system']);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function upsertTenant(PDO $db): int
{
    $stmt = $db->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
    $stmt->execute(['demo']);
    $row = $stmt->fetch();

    if ($row) {
        $db->prepare('UPDATE tenants SET name = ?, status = ? WHERE id = ?')
            ->execute(['Demo ParrePos', 'ACTIVE', $row['id']]);
        return (int) $row['id'];
    }

    $db->prepare('INSERT INTO tenants (name, slug, status, created_at) VALUES (?, ?, ?, NOW())')
        ->execute(['Demo ParrePos', 'demo', 'ACTIVE']);

    return (int) $db->lastInsertId();
}

function upsertTenantSettings(PDO $db, int $tenantId): void
{
    $settings = [
        'modules' => [
            'invoicing' => ['enabled' => true],
            'pos' => ['enabled' => false],
            'inventory' => ['enabled' => false],
            'recurring' => ['enabled' => true],
        ],
        'security' => [
            'password_policy' => [
                'min_length' => 10,
            ],
        ],
    ];

    $stmt = $db->prepare('SELECT id FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();

    if ($row) {
        $db->prepare('UPDATE tenant_settings SET modules = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($settings), $row['id']]);
        return;
    }

    $db->prepare('INSERT INTO tenant_settings (tenant_id, modules, created_at) VALUES (?, ?, NOW())')
        ->execute([$tenantId, json_encode($settings)]);
}

function upsertRole(PDO $db, int $tenantId, string $code, string $name): int
{
    $stmt = $db->prepare('SELECT id FROM roles WHERE tenant_id = ? AND code = ? LIMIT 1');
    $stmt->execute([$tenantId, $code]);
    $row = $stmt->fetch();

    if ($row) {
        $db->prepare('UPDATE roles SET name = ? WHERE id = ?')->execute([$name, $row['id']]);
        return (int) $row['id'];
    }

    $db->prepare('INSERT INTO roles (tenant_id, code, name, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$tenantId, $code, $name]);

    return (int) $db->lastInsertId();
}

function upsertPermissions(PDO $db, int $tenantId, array $codes): array
{
    $ids = [];

    foreach ($codes as $code) {
        $stmt = $db->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND code = ? LIMIT 1');
        $stmt->execute([$tenantId, $code]);
        $row = $stmt->fetch();

        if ($row) {
            $ids[] = (int) $row['id'];
            continue;
        }

        $db->prepare('INSERT INTO permissions (tenant_id, code, name, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([$tenantId, $code, $code]);
        $ids[] = (int) $db->lastInsertId();
    }

    return $ids;
}

function upsertRolePermissions(PDO $db, int $tenantId, int $roleId, array $permissionIds): void
{
    foreach ($permissionIds as $permissionId) {
        $stmt = $db->prepare('SELECT id FROM role_permissions WHERE tenant_id = ? AND role_id = ? AND permission_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $roleId, $permissionId]);
        if ($stmt->fetch()) {
            continue;
        }

        $db->prepare('INSERT INTO role_permissions (tenant_id, role_id, permission_id) VALUES (?, ?, ?)')
            ->execute([$tenantId, $roleId, $permissionId]);
    }
}

function upsertAdminUser(PDO $db, int $tenantId): int
{
    $email = 'admin@demo.local';
    $name = 'Admin Demo';
    $passwordHash = password_hash('Admin12345!', PASSWORD_DEFAULT);

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        $db->prepare('UPDATE users SET tenant_id = ?, name = ?, status = ?, password_hash = ? WHERE id = ?')
            ->execute([$tenantId, $name, 'ACTIVE', $passwordHash, $row['id']]);
        return (int) $row['id'];
    }

    $db->prepare('INSERT INTO users (tenant_id, email, password_hash, name, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$tenantId, $email, $passwordHash, $name, 'ACTIVE']);

    return (int) $db->lastInsertId();
}

function upsertUserRole(PDO $db, int $tenantId, int $userId, int $roleId): void
{
    $stmt = $db->prepare('SELECT id FROM user_roles WHERE tenant_id = ? AND user_id = ? AND role_id = ? LIMIT 1');
    $stmt->execute([$tenantId, $userId, $roleId]);
    if ($stmt->fetch()) {
        return;
    }

    $db->prepare('INSERT INTO user_roles (tenant_id, user_id, role_id, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$tenantId, $userId, $roleId]);
}
