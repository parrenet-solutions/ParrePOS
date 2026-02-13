<?php

namespace App\Bootstrap;

use App\Auth\AuthController;
use App\Auth\AuthMiddleware;
use App\Auth\JwtService;
use App\Auth\RefreshTokenRepository;
use App\Audit\AuditLogger;
use App\Audit\AuditRepository;
use App\Catalog\ItemController;
use App\Catalog\ItemRepository;
use App\Catalog\ItemService;
use App\Core\Middleware\JsonBodyMiddleware;
use App\Core\Middleware\RequestIdMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\RateLimiter;
use App\Core\Validation\Validator;
use App\Customers\CustomerController;
use App\Customers\CustomerRepository;
use App\Customers\CustomerService;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\PdfService;
use App\Email\EmailRepository;
use App\Jobs\JobQueue;
use App\Invoices\InvoiceController;
use App\Invoices\InvoiceRepository;
use App\Invoices\InvoiceService;
use App\Pos\BranchController;
use App\Pos\BranchRepository;
use App\Pos\BranchService;
use App\Pos\CashSessionController;
use App\Pos\CashSessionRepository;
use App\Pos\CashSessionService;
use App\Pos\CashMovementController;
use App\Pos\CashMovementRepository;
use App\Pos\CashMovementService;
use App\Pos\InventoryMovementRepository;
use App\Pos\PosSaleController;
use App\Pos\PosSaleRepository;
use App\Pos\PosSaleService;
use App\Pos\PosPaymentRepository;
use App\Pos\RegisterHandshakeService;
use App\Pos\RegisterController;
use App\Pos\RegisterRepository;
use App\Pos\RegisterService;
use App\RBAC\AuthorizationMiddleware;
use App\RBAC\RoleRepository;
use App\Recurring\RecurringController;
use App\Recurring\RecurringRepository;
use App\Recurring\RecurringService;
use App\Sync\SyncController;
use App\Sync\SyncRepository;
use App\Sync\SyncStatusRepository;
use App\Templates\TemplateController;
use App\Templates\TemplateRepository;
use App\Templates\TemplateService;
use App\Settings\TenantSettingsRepository;
use App\Tenancy\TenantGuardMiddleware;
use App\Users\UserController;
use App\Users\UserRepository;
use App\Users\UserService;
use App\Shared\Exceptions\HttpException;
use PDO;
use Redis;
use Throwable;

class App
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function bootstrap(): self
    {
        Env::load(dirname(__DIR__, 2) . '/.env');

        $container = new Container();

        $container->set('db', function (): PDO {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_NAME', 'parrepos')
            );

            return new PDO($dsn, Config::get('DB_USER', 'root'), Config::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        });

        $container->set('redis', function (): ?Redis {
            $host = Config::get('REDIS_HOST');
            if (!$host || !class_exists(Redis::class)) {
                return null;
            }

            $redis = new Redis();
            $redis->connect($host, (int) Config::get('REDIS_PORT', 6379));
            $password = Config::get('REDIS_PASS');
            if ($password) {
                $redis->auth($password);
            }
            return $redis;
        });

        $container->set(RateLimiter::class, function (Container $c): RateLimiter {
            return new RateLimiter($c->get('redis'));
        });

        $container->set(JwtService::class, function (): JwtService {
            return new JwtService(
                Config::get('JWT_ACCESS_SECRET', 'access-secret'),
                Config::get('JWT_REFRESH_SECRET', 'refresh-secret'),
                (int) Config::get('JWT_ACCESS_TTL', 900),
                (int) Config::get('JWT_REFRESH_TTL', 1209600)
            );
        });

        $container->set(UserRepository::class, fn (Container $c) => new UserRepository($c->get('db')));
        $container->set(UserController::class, fn (Container $c) => new UserController($c->get(UserRepository::class)));
        $container->set(UserService::class, fn () => new UserService());
        $container->set(Validator::class, fn () => new Validator());
        $container->set(CustomerRepository::class, fn (Container $c) => new CustomerRepository($c->get('db')));
        $container->set(CustomerService::class, fn (Container $c) => new CustomerService($c->get(Validator::class)));
        $container->set(CustomerController::class, fn (Container $c) => new CustomerController(
            $c->get(CustomerRepository::class),
            $c->get(CustomerService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(ItemRepository::class, fn (Container $c) => new ItemRepository($c->get('db')));
        $container->set(ItemService::class, fn (Container $c) => new ItemService($c->get(Validator::class)));
        $container->set(ItemController::class, fn (Container $c) => new ItemController(
            $c->get(ItemRepository::class),
            $c->get(ItemService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(InvoiceRepository::class, fn (Container $c) => new InvoiceRepository($c->get('db')));
        $container->set(InvoiceService::class, fn (Container $c) => new InvoiceService($c->get(Validator::class)));
        $container->set(DocumentRepository::class, fn (Container $c) => new DocumentRepository($c->get('db')));
        $container->set(DocumentService::class, fn (Container $c) => new DocumentService(
            $c->get(DocumentRepository::class),
            $c->get(InvoiceRepository::class),
            $c->get(PdfService::class)
        ));
        $container->set(EmailRepository::class, fn (Container $c) => new EmailRepository($c->get('db')));
        $container->set(PdfService::class, fn () => new PdfService());
        $container->set(JobQueue::class, fn (Container $c) => new JobQueue($c->get('db'), $c->get('redis')));
        $container->set(InvoiceController::class, fn (Container $c) => new InvoiceController(
            $c->get(InvoiceRepository::class),
            $c->get(InvoiceService::class),
            $c->get(CustomerRepository::class),
            $c->get(DocumentRepository::class),
            $c->get(DocumentService::class),
            $c->get(EmailRepository::class),
            $c->get(JobQueue::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(BranchRepository::class, fn (Container $c) => new BranchRepository($c->get('db')));
        $container->set(BranchService::class, fn (Container $c) => new BranchService($c->get(Validator::class)));
        $container->set(BranchController::class, fn (Container $c) => new BranchController(
            $c->get(BranchRepository::class),
            $c->get(BranchService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(RegisterRepository::class, fn (Container $c) => new RegisterRepository($c->get('db')));
        $container->set(RegisterService::class, fn (Container $c) => new RegisterService($c->get(Validator::class)));
        $container->set(RegisterHandshakeService::class, fn (Container $c) => new RegisterHandshakeService(
            $c->get(TenantSettingsRepository::class)
        ));
        $container->set(RegisterController::class, fn (Container $c) => new RegisterController(
            $c->get(RegisterRepository::class),
            $c->get(RegisterService::class),
            $c->get(BranchRepository::class),
            $c->get(AuditLogger::class),
            $c->get(TenantSettingsRepository::class),
            $c->get(RegisterHandshakeService::class),
            $c->get(RateLimiter::class)
        ));
        $container->set(CashSessionRepository::class, fn (Container $c) => new CashSessionRepository($c->get('db')));
        $container->set(CashSessionService::class, fn () => new CashSessionService());
        $container->set(PosSaleRepository::class, fn (Container $c) => new PosSaleRepository($c->get('db')));
        $container->set(PosPaymentRepository::class, fn (Container $c) => new PosPaymentRepository($c->get('db')));
        $container->set(PosSaleService::class, fn () => new PosSaleService());
        $container->set(InventoryMovementRepository::class, fn (Container $c) => new InventoryMovementRepository($c->get('db')));
        $container->set(CashMovementRepository::class, fn (Container $c) => new CashMovementRepository($c->get('db')));
        $container->set(CashMovementService::class, fn () => new CashMovementService());
        $container->set(CashSessionController::class, fn (Container $c) => new CashSessionController(
            $c->get(CashSessionRepository::class),
            $c->get(CashSessionService::class),
            $c->get(RegisterRepository::class),
            $c->get(BranchRepository::class),
            $c->get(PosPaymentRepository::class),
            $c->get(PosSaleRepository::class),
            $c->get(CashMovementRepository::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(CashMovementController::class, fn (Container $c) => new CashMovementController(
            $c->get(CashMovementRepository::class),
            $c->get(CashMovementService::class),
            $c->get(CashSessionRepository::class),
            $c->get(SyncRepository::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(PosSaleController::class, fn (Container $c) => new PosSaleController(
            $c->get(PosSaleRepository::class),
            $c->get(PosSaleService::class),
            $c->get(CashSessionRepository::class),
            $c->get(BranchRepository::class),
            $c->get(RegisterRepository::class),
            $c->get(InventoryMovementRepository::class),
            $c->get(TenantSettingsRepository::class),
            $c->get(PosPaymentRepository::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(SyncRepository::class, fn (Container $c) => new SyncRepository($c->get('db')));
        $container->set(SyncStatusRepository::class, fn (Container $c) => new SyncStatusRepository($c->get('db')));
        $container->set(SyncController::class, fn (Container $c) => new SyncController(
            $c->get(SyncRepository::class),
            $c->get(PosSaleController::class),
            $c->get(CashMovementController::class),
            $c->get(RateLimiter::class),
            $c->get(AuditLogger::class),
            $c->get(SyncStatusRepository::class)
        ));
        $container->set(RecurringRepository::class, fn (Container $c) => new RecurringRepository($c->get('db')));
        $container->set(RecurringService::class, fn (Container $c) => new RecurringService($c->get(Validator::class)));
        $container->set(RecurringController::class, fn (Container $c) => new RecurringController(
            $c->get(RecurringRepository::class),
            $c->get(RecurringService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(TemplateRepository::class, fn (Container $c) => new TemplateRepository($c->get('db')));
        $container->set(TemplateService::class, fn (Container $c) => new TemplateService($c->get(Validator::class)));
        $container->set(TemplateController::class, fn (Container $c) => new TemplateController(
            $c->get(TemplateRepository::class),
            $c->get(TemplateService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(RefreshTokenRepository::class, fn (Container $c) => new RefreshTokenRepository($c->get('db')));
        $container->set(RoleRepository::class, fn (Container $c) => new RoleRepository($c->get('db')));
        $container->set(TenantSettingsRepository::class, fn (Container $c) => new TenantSettingsRepository($c->get('db')));
        $container->set(AuditRepository::class, fn (Container $c) => new AuditRepository($c->get('db')));
        $container->set(AuditLogger::class, fn (Container $c) => new AuditLogger($c->get(AuditRepository::class)));

        $container->set(AuthController::class, function (Container $c): AuthController {
            return new AuthController(
                $c->get(UserRepository::class),
                $c->get(UserService::class),
                $c->get(JwtService::class),
                $c->get(RefreshTokenRepository::class),
                $c->get(RateLimiter::class),
                $c->get(AuditLogger::class),
                $c->get(RoleRepository::class)
            );
        });

        return new self($container);
    }

    public function run(): void
    {
        $router = new Router();
        $router->addMiddleware(new RequestIdMiddleware());
        $router->addMiddleware(new JsonBodyMiddleware());

        $authMiddleware = new AuthMiddleware(
            $this->container->get(JwtService::class),
            $this->container->get(RoleRepository::class)
        );

        $tenantGuard = new TenantGuardMiddleware(
            $this->container->get(TenantSettingsRepository::class)
        );

        $authController = $this->container->get(AuthController::class);
        $customerController = $this->container->get(CustomerController::class);
        $itemController = $this->container->get(ItemController::class);
        $invoiceController = $this->container->get(InvoiceController::class);
        $templateController = $this->container->get(TemplateController::class);
        $recurringController = $this->container->get(RecurringController::class);
        $branchController = $this->container->get(BranchController::class);
        $registerController = $this->container->get(RegisterController::class);
        $cashSessionController = $this->container->get(CashSessionController::class);
        $posSaleController = $this->container->get(PosSaleController::class);
        $cashMovementController = $this->container->get(CashMovementController::class);
        $syncController = $this->container->get(SyncController::class);

        $router->get('/health', function (): array {
            return ['status' => 200, 'data' => ['status' => 'ok']];
        });

        $router->post('/api/v1/auth/login', [$authController, 'login']);
        $router->post('/api/v1/auth/refresh', [$authController, 'refresh']);
        $router->post('/api/v1/auth/logout', [$authController, 'logout']);

        $userController = $this->container->get(UserController::class);
        $router->get('/api/v1/me', [$userController, 'me'], [$authMiddleware, $tenantGuard]);

        $invoicingGuard = $tenantGuard->requireModule('invoicing');

        $router->post('/api/v1/customers', [$customerController, 'create'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('customers.write'),
        ]);
        $router->put('/api/v1/customers/{id}', [$customerController, 'update'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('customers.write'),
        ]);
        $router->get('/api/v1/customers/{id}', [$customerController, 'get'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('customers.read'),
        ]);
        $router->get('/api/v1/customers', [$customerController, 'list'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('customers.read'),
        ]);

        $router->post('/api/v1/items', [$itemController, 'create'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('items.write'),
        ]);
        $router->put('/api/v1/items/{id}', [$itemController, 'update'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('items.write'),
        ]);
        $router->get('/api/v1/items/{id}', [$itemController, 'get'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('items.read'),
        ]);
        $router->get('/api/v1/items', [$itemController, 'list'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('items.read'),
        ]);

        $router->post('/api/v1/invoices', [$invoiceController, 'create'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.write'),
        ]);
        $router->get('/api/v1/invoices', [$invoiceController, 'list'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.read'),
        ]);
        $router->get('/api/v1/invoices/{id}', [$invoiceController, 'get'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.read'),
        ]);
        $router->post('/api/v1/invoices/{id}/issue', [$invoiceController, 'issue'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.issue'),
        ]);
        $router->post('/api/v1/invoices/{id}/void', [$invoiceController, 'void'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.void'),
        ]);
        $router->get('/api/v1/invoices/{id}/pdf', [$invoiceController, 'pdf'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.read'),
        ]);
        $router->post('/api/v1/invoices/{id}/send-email', [$invoiceController, 'sendEmail'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.write'),
        ]);

        $router->post('/api/v1/invoice-templates', [$templateController, 'create'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('templates.manage'),
        ]);
        $router->put('/api/v1/invoice-templates/{id}', [$templateController, 'update'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('templates.manage'),
        ]);
        $router->get('/api/v1/invoice-templates/{id}', [$templateController, 'get'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('templates.manage'),
        ]);
        $router->get('/api/v1/invoice-templates', [$templateController, 'list'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('templates.manage'),
        ]);
        $router->post('/api/v1/invoice-templates/{id}/set-default', [$templateController, 'setDefault'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('templates.manage'),
        ]);

        $posGuard = $tenantGuard->requireModule('pos');
        $posAccess = new AuthorizationMiddleware('pos.access');
        $router->post('/api/v1/branches', [$branchController, 'create'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('branches.manage'),
        ]);
        $router->put('/api/v1/branches/{id}', [$branchController, 'update'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('branches.manage'),
        ]);
        $router->get('/api/v1/branches/{id}', [$branchController, 'get'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('branches.manage'),
        ]);
        $router->get('/api/v1/branches', [$branchController, 'list'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('branches.manage'),
        ]);

        $router->post('/api/v1/pos/registers', [$registerController, 'create'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.registers.manage'),
        ]);
        $router->post('/api/v1/pos/registers/handshake', [$registerController, 'handshake'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
        ]);
        $router->put('/api/v1/pos/registers/{id}', [$registerController, 'update'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.registers.manage'),
        ]);
        $router->get('/api/v1/pos/registers/{id}', [$registerController, 'get'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.registers.manage'),
        ]);
        $router->get('/api/v1/pos/registers', [$registerController, 'list'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.registers.manage'),
        ]);

        $router->post('/api/v1/pos/cash-sessions/open', [$cashSessionController, 'open'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.cash.open'),
        ]);
        $router->post('/api/v1/pos/cash-sessions/{id}/close', [$cashSessionController, 'close'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.cash.close'),
        ]);

        $router->post('/api/v1/pos/cash-movements', [$cashMovementController, 'create'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.cash.adjust'),
        ]);
        $router->get('/api/v1/pos/cash-movements', [$cashMovementController, 'list'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.cash.movements.read'),
        ]);
        $router->get('/api/v1/pos/cash-movements/summary', [$cashMovementController, 'summary'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.cash.movements.read'),
        ]);
        $router->post('/api/v1/pos/cash-movements/{id}/reverse', [$cashMovementController, 'reverse'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.cash.movements.reverse'),
        ]);

        $router->post('/api/v1/pos/sales', [$posSaleController, 'create'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.sales.pay'),
        ]);
        $router->post('/api/v1/pos/sales/{id}/void', [$posSaleController, 'void'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.sales.void'),
        ]);
        $router->post('/api/v1/pos/sales/{id}/hold', [$posSaleController, 'hold'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.sales.write'),
        ]);
        $router->post('/api/v1/pos/sales/{id}/resume', [$posSaleController, 'resume'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.sales.write'),
        ]);
        $router->get('/api/v1/pos/sales/{id}', [$posSaleController, 'get'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.sales.read'),
        ]);
        $router->get('/api/v1/pos/sales', [$posSaleController, 'list'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('pos.sales.read'),
        ]);

        $router->post('/api/v1/sync/events', [$syncController, 'ingest'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('sync.write'),
        ]);
        $router->get('/api/v1/sync/status', [$syncController, 'status'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('sync.read'),
        ]);

        $recurringGuard = $tenantGuard->requireModule('recurring');
        $router->post('/api/v1/recurring-rules', [$recurringController, 'create'], [
            $authMiddleware,
            $recurringGuard,
            new AuthorizationMiddleware('recurring.manage'),
        ]);
        $router->get('/api/v1/recurring-rules', [$recurringController, 'list'], [
            $authMiddleware,
            $recurringGuard,
            new AuthorizationMiddleware('recurring.manage'),
        ]);
        $router->post('/api/v1/recurring-rules/{id}/pause', [$recurringController, 'pause'], [
            $authMiddleware,
            $recurringGuard,
            new AuthorizationMiddleware('recurring.manage'),
        ]);
        $router->post('/api/v1/recurring-rules/{id}/resume', [$recurringController, 'resume'], [
            $authMiddleware,
            $recurringGuard,
            new AuthorizationMiddleware('recurring.manage'),
        ]);

        $router->get('/api/v1/secure-example', function (): array {
            return ['status' => 200, 'data' => ['message' => 'Acceso permitido']];
        }, [
            $authMiddleware,
            $tenantGuard->requireModule('pos'),
            new AuthorizationMiddleware('pos.access'),
        ]);

        $request = Request::fromGlobals();
        $requestId = $request->getHeader('X-Request-Id') ?? bin2hex(random_bytes(16));
        $request = $request->withAttribute('request_id', $requestId);

        try {
            $result = $router->dispatch($request);
            $finalRequest = $result['request'] ?? $request;
            $requestId = $finalRequest->getAttribute('request_id', $requestId);
            Response::ok($result['data'] ?? null, $requestId, $result['status'] ?? 200);
        } catch (HttpException $e) {
            $requestId = $request->getAttribute('request_id', $requestId);
            Response::error($e->getErrorCode(), $e->getMessage(), $requestId, $e->getStatus(), $e->getDetails());
        } catch (Throwable $e) {
            $requestId = $request->getAttribute('request_id', $requestId);
            Response::error('SERVER_ERROR', 'Error interno', $requestId, 500);
        }
    }
}
