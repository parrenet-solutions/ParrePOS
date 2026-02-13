<?php

declare(strict_types=1);

use App\Audit\AuditLogger;
use App\Audit\AuditRepository;
use App\Bootstrap\Config;
use App\Bootstrap\Env;

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
    printHelp();
    exit(0);
}

try {
    switch ($command) {
        case 'seed:dev':
            seedDev();
            fwrite(STDOUT, "Seed de desarrollo completado.\n");
            exit(0);

        case 'migrate':
            migrate();
            exit(0);

        case 'migrate:status':
            migrateStatus();
            exit(0);

        default:
            fwrite(STDERR, "Comando no reconocido: {$command}\n");
            printHelp();
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

function printHelp(): void
{
    fwrite(STDOUT, "Uso: php bin/console.php <comando>\n");
    fwrite(STDOUT, "Comandos disponibles:\n");
    fwrite(STDOUT, "  migrate          Ejecuta migraciones pendientes\n");
    fwrite(STDOUT, "  migrate:status   Lista migraciones aplicadas/pendientes\n");
    fwrite(STDOUT, "  seed:dev         Seed de desarrollo\n");
}

function dbConnection(): PDO
{
    return new PDO(
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
}

function migrate(): void
{
    $db = dbConnection();

    $files = migrationFiles();
    if ($files === []) {
        fwrite(STDOUT, "No hay archivos en database/migrations.\n");
        return;
    }

    if (!migrationsTableExists($db)) {
        bootstrapMigrationsTable($db, $files);
    }

    $applied = appliedMigrations($db);
    $executed = 0;

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            fwrite(STDOUT, "[SKIP] {$name}\n");
            continue;
        }

        $sql = (string) file_get_contents($file);
        if (trim($sql) === '') {
            throw new RuntimeException("Migración vacía: {$name}");
        }

        $checksum = hash('sha256', $sql);

        $db->beginTransaction();
        try {
            $db->exec($sql);

            $stmt = $db->prepare(
                'INSERT INTO schema_migrations (migration, checksum, executed_at) VALUES (?, ?, NOW())'
            );
            $stmt->execute([$name, $checksum]);

            $db->commit();
            $executed++;
            fwrite(STDOUT, "[OK] {$name}\n");
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    fwrite(STDOUT, "Migraciones ejecutadas: {$executed}\n");
}

function migrateStatus(): void
{
    $db = dbConnection();

    $files = migrationFiles();
    $applied = migrationsTableExists($db) ? appliedMigrations($db) : [];

    if ($files === []) {
        fwrite(STDOUT, "No hay archivos en database/migrations.\n");
        return;
    }

    $pending = 0;
    fwrite(STDOUT, "Estado de migraciones:\n");

    foreach ($files as $file) {
        $name = basename($file);
        $status = isset($applied[$name]) ? 'APPLIED' : 'PENDING';
        if ($status === 'PENDING') {
            $pending++;
        }

        fwrite(STDOUT, sprintf("- [%s] %s\n", $status, $name));
    }

    fwrite(STDOUT, "Pendientes: {$pending}\n");
}

function migrationsTableExists(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'schema_migrations'"
    );
    $row = $stmt->fetch();
    return (int) ($row['cnt'] ?? 0) > 0;
}

function bootstrapMigrationsTable(PDO $db, array $files): void
{
    $bootstrap = null;
    foreach ($files as $file) {
        if (basename($file) === '000_schema_migrations.sql') {
            $bootstrap = $file;
            break;
        }
    }

    if ($bootstrap === null) {
        throw new RuntimeException('Falta database/migrations/000_schema_migrations.sql para iniciar migraciones');
    }

    $sql = (string) file_get_contents($bootstrap);
    if (trim($sql) === '') {
        throw new RuntimeException('Migracion vacia: 000_schema_migrations.sql');
    }

    $db->exec($sql);

    if (!migrationsTableExists($db)) {
        throw new RuntimeException('No se pudo crear schema_migrations desde 000_schema_migrations.sql');
    }

    $checksum = hash('sha256', $sql);
    $stmt = $db->prepare(
        'INSERT IGNORE INTO schema_migrations (migration, checksum, executed_at) VALUES (?, ?, NOW())'
    );
    $stmt->execute([basename($bootstrap), $checksum]);
}

function appliedMigrations(PDO $db): array
{
    $rows = $db->query('SELECT migration FROM schema_migrations')->fetchAll();
    $map = [];

    foreach ($rows as $row) {
        $map[(string) $row['migration']] = true;
    }

    return $map;
}

function migrationFiles(): array
{
    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql');
    if ($files === false) {
        return [];
    }

    sort($files);
    return $files;
}

function seedDev(): void
{
    $db = dbConnection();

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
            'customers.read',
            'customers.write',
            'items.read',
            'items.write',
            'invoices.read',
            'invoices.write',
            'invoices.issue',
            'invoices.void',
            'templates.manage',
            'recurring.manage',
            'pos.access',
            'pos.sales.read',
            'pos.sales.write',
            'pos.sales.pay',
            'pos.sales.void',
            'pos.cash.open',
            'pos.cash.close',
            'pos.cash.adjust',
            'pos.cash.movements.read',
            'pos.cash.movements.reverse',
            'pos.registers.manage',
            'branches.manage',
            'sync.read',
            'sync.write',
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
            'pos' => ['enabled' => true],
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

    $stmt = $db->prepare('SELECT id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1');
    $stmt->execute([$tenantId, $email]);
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
