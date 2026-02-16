<?php

namespace App\Bootstrap;

use App\Auth\AuthController;
use App\Auth\AuthMiddleware;
use App\Auth\JwtService;
use App\Auth\RefreshTokenRepository;
use App\Audit\AuditLogger;
use App\Audit\AuditRepository;
use App\BackupOps\BackupOpsController;
use App\BackupOps\BackupOpsRepository;
use App\BackupOps\BackupOpsService;
use App\Catalog\ItemController;
use App\Catalog\ItemRepository;
use App\Catalog\ItemService;
use App\Core\Middleware\JsonBodyMiddleware;
use App\Core\Middleware\PlatformAdminMiddleware;
use App\Core\Middleware\RequestIdMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\RateLimiter;
use App\Core\Validation\Validator;
use App\Accounting\AccountingController;
use App\Accounting\AccountingRepository;
use App\Accounting\AccountingService;
use App\Crm\CrmController;
use App\Crm\CrmRepository;
use App\Crm\CrmService;
use App\Customers\CustomerController;
use App\Customers\CustomerRepository;
use App\Customers\CustomerService;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\PdfService;
use App\Email\EmailRepository;
use App\Fiscal\FiscalAlertService;
use App\Fiscal\FiscalController;
use App\Fiscal\FiscalProviderFactory;
use App\Fiscal\FiscalRepository;
use App\Fiscal\FiscalService;
use App\Inventory\InventoryController;
use App\Inventory\InventoryAdvancedController;
use App\Inventory\InventoryAdvancedRepository;
use App\Inventory\InventoryAdvancedService;
use App\Inventory\InventoryAlertService;
use App\Inventory\InventoryRepository;
use App\Inventory\InventoryService;
use App\Integrations\ExternalDeliveryService;
use App\Integrations\IntegrationController;
use App\Integrations\IntegrationRepository;
use App\Integrations\IntegrationService;
use App\Jobs\JobQueue;
use App\Ops\OpsController;
use App\Ops\OpsObservabilityService;
use App\Ops\OpsOnboardingService;
use App\Ops\OpsDailyCloseService;
use App\Ops\OpsRepository;
use App\Ops\SaasAdminController;
use App\Ops\SaasAdminRepository;
use App\Ops\SaasAdminService;
use App\Invoices\InvoiceController;
use App\Invoices\InvoiceRepository;
use App\Invoices\InvoiceService;
use App\HardwareBridge\HardwareBridgeController;
use App\HardwareBridge\HardwareBridgeRepository;
use App\HardwareBridge\HardwareBridgeService;
use App\Pos\BranchController;
use App\Pos\BranchRepository;
use App\Pos\BranchService;
use App\Pos\CashSessionController;
use App\Pos\CashSessionRepository;
use App\Pos\CashSessionService;
use App\Pos\CashMovementController;
use App\Pos\CashMovementRepository;
use App\Pos\CashMovementService;
use App\Pos\PosSaleController;
use App\Pos\PosSaleRepository;
use App\Pos\PosSaleService;
use App\Pos\PosPaymentRepository;
use App\Pos\RegisterHandshakeService;
use App\Pos\RegisterController;
use App\Pos\RegisterRepository;
use App\Pos\RegisterService;
use App\PaymentsPlus\PaymentProviderFactory;
use App\PaymentsPlus\PaymentsPlusController;
use App\PaymentsPlus\PaymentsPlusRepository;
use App\PaymentsPlus\PaymentsPlusService;
use App\Purchases\PurchaseController;
use App\Purchases\PurchaseRepository;
use App\Purchases\PurchaseService;
use App\Plans\PlanRepository;
use App\Plans\PlanService;
use App\RBAC\AuthorizationMiddleware;
use App\RBAC\RoleRepository;
use App\Recurring\RecurringController;
use App\Recurring\RecurringRepository;
use App\Recurring\RecurringService;
use App\Receivables\ReceivableController;
use App\Receivables\ReceivableRepository;
use App\Receivables\ReceivableService;
use App\Reports\ExecutiveReportController;
use App\Reports\ExecutiveReportRepository;
use App\SecurityPlus\SecurityPlusController;
use App\SecurityPlus\SecurityPlusRepository;
use App\SecurityPlus\SecurityPlusService;
use App\Sync\SyncController;
use App\Sync\SyncRepository;
use App\Sync\SyncStatusRepository;
use App\Templates\TemplateController;
use App\Templates\TemplateRepository;
use App\Templates\TemplateService;
use App\Settings\TenantSettingsRepository;
use App\Settings\TenantSettingsService;
use App\Settings\TenantSettingsValidator;
use App\Tenancy\FiscalGuardMiddleware;
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

        $container->set(UserRepository::class, fn(Container $c) => new UserRepository($c->get('db')));
        $container->set(PlanRepository::class, fn(Container $c) => new PlanRepository($c->get('db')));
        $container->set(PlanService::class, fn(Container $c) => new PlanService($c->get(PlanRepository::class)));
        $container->set(UserController::class, fn(Container $c) => new UserController(
            $c->get(UserRepository::class),
            $c->get(PlanService::class)
        ));
        $container->set(UserService::class, fn() => new UserService());
        $container->set(Validator::class, fn() => new Validator());
        $container->set(CustomerRepository::class, fn(Container $c) => new CustomerRepository($c->get('db')));
        $container->set(CustomerService::class, fn(Container $c) => new CustomerService($c->get(Validator::class)));
        $container->set(CustomerController::class, fn(Container $c) => new CustomerController(
            $c->get(CustomerRepository::class),
            $c->get(CustomerService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(ItemRepository::class, fn(Container $c) => new ItemRepository($c->get('db')));
        $container->set(ItemService::class, fn(Container $c) => new ItemService($c->get(Validator::class)));
        $container->set(ItemController::class, fn(Container $c) => new ItemController(
            $c->get(ItemRepository::class),
            $c->get(ItemService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(InvoiceRepository::class, fn(Container $c) => new InvoiceRepository($c->get('db')));
        $container->set(InvoiceService::class, fn(Container $c) => new InvoiceService($c->get(Validator::class)));
        $container->set(DocumentRepository::class, fn(Container $c) => new DocumentRepository($c->get('db')));
        $container->set(DocumentService::class, fn(Container $c) => new DocumentService(
            $c->get(DocumentRepository::class),
            $c->get(InvoiceRepository::class),
            $c->get(PdfService::class)
        ));
        $container->set(EmailRepository::class, fn(Container $c) => new EmailRepository($c->get('db')));
        $container->set(PdfService::class, fn() => new PdfService());
        $container->set(JobQueue::class, fn(Container $c) => new JobQueue($c->get('db'), $c->get('redis')));
        $container->set(InvoiceController::class, fn(Container $c) => new InvoiceController(
            $c->get(InvoiceRepository::class),
            $c->get(InvoiceService::class),
            $c->get(CustomerRepository::class),
            $c->get(DocumentRepository::class),
            $c->get(DocumentService::class),
            $c->get(EmailRepository::class),
            $c->get(FiscalRepository::class),
            $c->get(FiscalService::class),
            $c->get(JobQueue::class),
            $c->get(AuditLogger::class),
            $c->get(TenantSettingsRepository::class),
            $c->get('db')
        ));
        $container->set(BranchRepository::class, fn(Container $c) => new BranchRepository($c->get('db')));
        $container->set(BranchService::class, fn(Container $c) => new BranchService($c->get(Validator::class)));
        $container->set(BranchController::class, fn(Container $c) => new BranchController(
            $c->get(BranchRepository::class),
            $c->get(BranchService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(RegisterRepository::class, fn(Container $c) => new RegisterRepository($c->get('db')));
        $container->set(RegisterService::class, fn(Container $c) => new RegisterService($c->get(Validator::class)));
        $container->set(RegisterHandshakeService::class, fn(Container $c) => new RegisterHandshakeService(
            $c->get(TenantSettingsRepository::class)
        ));
        $container->set(RegisterController::class, fn(Container $c) => new RegisterController(
            $c->get(RegisterRepository::class),
            $c->get(RegisterService::class),
            $c->get(BranchRepository::class),
            $c->get(AuditLogger::class),
            $c->get(TenantSettingsRepository::class),
            $c->get(RegisterHandshakeService::class),
            $c->get(RateLimiter::class)
        ));
        $container->set(CashSessionRepository::class, fn(Container $c) => new CashSessionRepository($c->get('db')));
        $container->set(CashSessionService::class, fn() => new CashSessionService());
        $container->set(PosSaleRepository::class, fn(Container $c) => new PosSaleRepository($c->get('db')));
        $container->set(PosPaymentRepository::class, fn(Container $c) => new PosPaymentRepository($c->get('db')));
        $container->set(PosSaleService::class, fn() => new PosSaleService());
        $container->set(InventoryRepository::class, fn(Container $c) => new InventoryRepository($c->get('db')));
        $container->set(InventoryService::class, fn(Container $c) => new InventoryService(
            $c->get(InventoryRepository::class),
            $c->get(ItemRepository::class),
            $c->get(BranchRepository::class)
        ));
        $container->set(InventoryAlertService::class, fn() => new InventoryAlertService());
        $container->set(InventoryController::class, fn(Container $c) => new InventoryController(
            $c->get(InventoryRepository::class),
            $c->get(InventoryService::class),
            $c->get(InventoryAlertService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(InventoryAdvancedRepository::class, fn(Container $c) => new InventoryAdvancedRepository($c->get('db')));
        $container->set(InventoryAdvancedService::class, fn(Container $c) => new InventoryAdvancedService(
            $c->get(InventoryAdvancedRepository::class),
            $c->get(BranchRepository::class),
            $c->get(ItemRepository::class),
            $c->get(InventoryRepository::class)
        ));
        $container->set(InventoryAdvancedController::class, fn(Container $c) => new InventoryAdvancedController(
            $c->get(InventoryAdvancedRepository::class),
            $c->get(InventoryAdvancedService::class),
            $c->get(InventoryRepository::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(CrmRepository::class, fn(Container $c) => new CrmRepository($c->get('db')));
        $container->set(CrmService::class, fn(Container $c) => new CrmService(
            $c->get(CrmRepository::class),
            $c->get(CustomerRepository::class),
            $c->get(ItemRepository::class)
        ));
        $container->set(CrmController::class, fn(Container $c) => new CrmController(
            $c->get(CrmRepository::class),
            $c->get(CrmService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(ExecutiveReportRepository::class, fn(Container $c) => new ExecutiveReportRepository($c->get('db')));
        $container->set(ExecutiveReportController::class, fn(Container $c) => new ExecutiveReportController(
            $c->get(ExecutiveReportRepository::class)
        ));
        $container->set(AccountingRepository::class, fn(Container $c) => new AccountingRepository($c->get('db')));
        $container->set(AccountingService::class, fn() => new AccountingService());
        $container->set(AccountingController::class, fn(Container $c) => new AccountingController(
            $c->get(AccountingRepository::class),
            $c->get(AccountingService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(ReceivableRepository::class, fn(Container $c) => new ReceivableRepository($c->get('db')));
        $container->set(ReceivableService::class, fn(Container $c) => new ReceivableService(
            $c->get(CustomerRepository::class)
        ));
        $container->set(ReceivableController::class, fn(Container $c) => new ReceivableController(
            $c->get(ReceivableRepository::class),
            $c->get(ReceivableService::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(IntegrationRepository::class, fn(Container $c) => new IntegrationRepository($c->get('db')));
        $container->set(IntegrationService::class, fn() => new IntegrationService());
        $container->set(ExternalDeliveryService::class, fn(Container $c) => new ExternalDeliveryService(
            $c->get(IntegrationRepository::class)
        ));
        $container->set(IntegrationController::class, fn(Container $c) => new IntegrationController(
            $c->get(IntegrationRepository::class),
            $c->get(IntegrationService::class),
            $c->get(JobQueue::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(PaymentsPlusRepository::class, fn(Container $c) => new PaymentsPlusRepository($c->get('db')));
        $container->set(PaymentProviderFactory::class, fn() => new PaymentProviderFactory());
        $container->set(PaymentsPlusService::class, fn() => new PaymentsPlusService());
        $container->set(PaymentsPlusController::class, fn(Container $c) => new PaymentsPlusController(
            $c->get(PaymentsPlusRepository::class),
            $c->get(PaymentsPlusService::class),
            $c->get(PaymentProviderFactory::class),
            $c->get(TenantSettingsRepository::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(HardwareBridgeRepository::class, fn(Container $c) => new HardwareBridgeRepository($c->get('db')));
        $container->set(HardwareBridgeService::class, fn() => new HardwareBridgeService());
        $container->set(HardwareBridgeController::class, fn(Container $c) => new HardwareBridgeController(
            $c->get(HardwareBridgeRepository::class),
            $c->get(HardwareBridgeService::class),
            $c->get(JobQueue::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(BackupOpsRepository::class, fn(Container $c) => new BackupOpsRepository($c->get('db')));
        $container->set(BackupOpsService::class, fn() => new BackupOpsService());
        $container->set(BackupOpsController::class, fn(Container $c) => new BackupOpsController(
            $c->get(BackupOpsRepository::class),
            $c->get(BackupOpsService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(SecurityPlusRepository::class, fn(Container $c) => new SecurityPlusRepository($c->get('db')));
        $container->set(SecurityPlusService::class, fn() => new SecurityPlusService());
        $container->set(SecurityPlusController::class, fn(Container $c) => new SecurityPlusController(
            $c->get(SecurityPlusRepository::class),
            $c->get(SecurityPlusService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(PurchaseRepository::class, fn(Container $c) => new PurchaseRepository($c->get('db')));
        $container->set(PurchaseService::class, fn(Container $c) => new PurchaseService(
            $c->get(PurchaseRepository::class),
            $c->get(ItemRepository::class),
            $c->get(BranchRepository::class)
        ));
        $container->set(PurchaseController::class, fn(Container $c) => new PurchaseController(
            $c->get(PurchaseRepository::class),
            $c->get(PurchaseService::class),
            $c->get(InventoryRepository::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(CashMovementRepository::class, fn(Container $c) => new CashMovementRepository($c->get('db')));
        $container->set(CashMovementService::class, fn() => new CashMovementService());
        $container->set(CashSessionController::class, fn(Container $c) => new CashSessionController(
            $c->get(CashSessionRepository::class),
            $c->get(CashSessionService::class),
            $c->get(RegisterRepository::class),
            $c->get(BranchRepository::class),
            $c->get(PosPaymentRepository::class),
            $c->get(PosSaleRepository::class),
            $c->get(CashMovementRepository::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(CashMovementController::class, fn(Container $c) => new CashMovementController(
            $c->get(CashMovementRepository::class),
            $c->get(CashMovementService::class),
            $c->get(CashSessionRepository::class),
            $c->get(SyncRepository::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(PosSaleController::class, fn(Container $c) => new PosSaleController(
            $c->get(PosSaleRepository::class),
            $c->get(PosSaleService::class),
            $c->get(CashSessionRepository::class),
            $c->get(BranchRepository::class),
            $c->get(RegisterRepository::class),
            $c->get(InventoryService::class),
            $c->get(CrmRepository::class),
            $c->get(CustomerRepository::class),
            $c->get(TenantSettingsRepository::class),
            $c->get(PosPaymentRepository::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(SyncRepository::class, fn(Container $c) => new SyncRepository($c->get('db')));
        $container->set(SyncStatusRepository::class, fn(Container $c) => new SyncStatusRepository($c->get('db')));
        $container->set(OpsRepository::class, fn(Container $c) => new OpsRepository($c->get('db')));
        $container->set(OpsDailyCloseService::class, fn() => new OpsDailyCloseService());
        $container->set(OpsObservabilityService::class, fn() => new OpsObservabilityService());
        $container->set(OpsOnboardingService::class, fn() => new OpsOnboardingService());
        $container->set(OpsController::class, fn(Container $c) => new OpsController(
            $c->get(OpsRepository::class),
            $c->get(OpsDailyCloseService::class),
            $c->get(OpsObservabilityService::class),
            $c->get(OpsOnboardingService::class),
            $c->get(AuditLogger::class),
            $c->get('db')
        ));
        $container->set(SaasAdminRepository::class, fn(Container $c) => new SaasAdminRepository($c->get('db')));
        $container->set(SaasAdminService::class, fn() => new SaasAdminService());
        $container->set(SaasAdminController::class, fn(Container $c) => new SaasAdminController(
            $c->get(SaasAdminRepository::class),
            $c->get(SaasAdminService::class)
        ));
        $container->set(SyncController::class, fn(Container $c) => new SyncController(
            $c->get(SyncRepository::class),
            $c->get(PosSaleController::class),
            $c->get(CashMovementController::class),
            $c->get(RateLimiter::class),
            $c->get(AuditLogger::class),
            $c->get(SyncStatusRepository::class)
        ));
        $container->set(RecurringRepository::class, fn(Container $c) => new RecurringRepository($c->get('db')));
        $container->set(RecurringService::class, fn(Container $c) => new RecurringService($c->get(Validator::class)));
        $container->set(RecurringController::class, fn(Container $c) => new RecurringController(
            $c->get(RecurringRepository::class),
            $c->get(RecurringService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(TemplateRepository::class, fn(Container $c) => new TemplateRepository($c->get('db')));
        $container->set(TemplateService::class, fn(Container $c) => new TemplateService($c->get(Validator::class)));
        $container->set(TemplateController::class, fn(Container $c) => new TemplateController(
            $c->get(TemplateRepository::class),
            $c->get(TemplateService::class),
            $c->get(AuditLogger::class)
        ));
        $container->set(RefreshTokenRepository::class, fn(Container $c) => new RefreshTokenRepository($c->get('db')));
        $container->set(RoleRepository::class, fn(Container $c) => new RoleRepository($c->get('db')));
        $container->set(TenantSettingsRepository::class, fn(Container $c) => new TenantSettingsRepository($c->get('db')));
        $container->set(TenantSettingsService::class, fn() => new TenantSettingsService());
        $container->set(TenantSettingsValidator::class, fn() => new TenantSettingsValidator());
        $container->set(FiscalRepository::class, fn(Container $c) => new FiscalRepository($c->get('db')));
        $container->set(FiscalProviderFactory::class, fn() => new FiscalProviderFactory());
        $container->set(FiscalAlertService::class, fn(Container $c) => new FiscalAlertService(
            $c->get(EmailRepository::class),
            $c->get(JobQueue::class)
        ));
        $container->set(FiscalService::class, fn(Container $c) => new FiscalService(
            $c->get(TenantSettingsService::class),
            $c->get(TenantSettingsValidator::class)
        ));
        $container->set(FiscalController::class, fn(Container $c) => new FiscalController(
            $c->get(FiscalRepository::class),
            $c->get(FiscalService::class),
            $c->get(TenantSettingsRepository::class),
            $c->get(JobQueue::class),
            $c->get(AuditLogger::class),
            $c->get(RateLimiter::class),
            $c->get(FiscalAlertService::class)
        ));
        $container->set(AuditRepository::class, fn(Container $c) => new AuditRepository($c->get('db')));
        $container->set(AuditLogger::class, fn(Container $c) => new AuditLogger($c->get(AuditRepository::class)));

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
            $this->container->get(TenantSettingsRepository::class),
            $this->container->get(PlanService::class)
        );
        $fiscalGuard = new FiscalGuardMiddleware(
            $this->container->get(TenantSettingsRepository::class),
            $this->container->get(PlanService::class)
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
        $inventoryController = $this->container->get(InventoryController::class);
        $inventoryAdvancedController = $this->container->get(InventoryAdvancedController::class);
        $crmController = $this->container->get(CrmController::class);
        $executiveReportController = $this->container->get(ExecutiveReportController::class);
        $accountingController = $this->container->get(AccountingController::class);
        $receivableController = $this->container->get(ReceivableController::class);
        $integrationController = $this->container->get(IntegrationController::class);
        $paymentsPlusController = $this->container->get(PaymentsPlusController::class);
        $hardwareBridgeController = $this->container->get(HardwareBridgeController::class);
        $backupOpsController = $this->container->get(BackupOpsController::class);
        $securityPlusController = $this->container->get(SecurityPlusController::class);
        $purchaseController = $this->container->get(PurchaseController::class);
        $syncController = $this->container->get(SyncController::class);
        $opsController = $this->container->get(OpsController::class);
        $saasAdminController = $this->container->get(SaasAdminController::class);
        $fiscalController = $this->container->get(FiscalController::class);
        $platformAdmin = new PlatformAdminMiddleware();

        $router->get('/health', function (): array {
            return ['status' => 200, 'data' => ['status' => 'ok']];
        });

        $router->post('/api/v1/auth/login', [$authController, 'login']);
        $router->post('/api/v1/auth/refresh', [$authController, 'refresh']);
        $router->post('/api/v1/auth/logout', [$authController, 'logout']);
        $router->post('/api/v1/fiscal/webhook/ack', [$fiscalController, 'webhookAck']);
        $router->post('/api/v1/payments-plus/webhook', [$paymentsPlusController, 'webhook']);
        $router->get('/api/v1/admin/tenants', [$saasAdminController, 'listTenants'], [$platformAdmin]);
        $router->get('/api/v1/admin/tenants/{id}', [$saasAdminController, 'getTenant'], [$platformAdmin]);
        $router->put('/api/v1/admin/tenants/{id}/status', [$saasAdminController, 'updateTenantStatus'], [$platformAdmin]);
        $router->put('/api/v1/admin/tenants/{id}/modules', [$saasAdminController, 'updateTenantModules'], [$platformAdmin]);
        $router->put('/api/v1/admin/tenants/{id}/subscription', [$saasAdminController, 'updateSubscription'], [$platformAdmin]);
        $router->get('/api/v1/admin/plans', [$saasAdminController, 'listPlans'], [$platformAdmin]);

        $userController = $this->container->get(UserController::class);
        $router->get('/api/v1/me', [$userController, 'me'], [$authMiddleware, $tenantGuard]);

        $invoicingGuard = $tenantGuard->requireModule('invoicing');
        $customersLimitGuard = $tenantGuard->requireLimit('customers.max');
        $itemsLimitGuard = $tenantGuard->requireLimit('items.max');
        $invoicesLimitGuard = $tenantGuard->requireLimit('invoices.max');

        $router->post('/api/v1/customers', [$customerController, 'create'], [
            $authMiddleware,
            $invoicingGuard,
            $customersLimitGuard,
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
            $itemsLimitGuard,
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
            $invoicesLimitGuard,
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
            $fiscalGuard,
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
        $router->get('/api/v1/fiscal/status', [$fiscalController, 'status'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/preflight', [$fiscalController, 'preflight'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->put('/api/v1/fiscal/config', [$fiscalController, 'updateConfig'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.manage'),
        ]);
        $router->post('/api/v1/fiscal/environment/prod/activate', [$fiscalController, 'activateProd'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.manage'),
        ]);
        $router->get('/api/v1/fiscal/documents', [$fiscalController, 'listDocuments'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/documents/search', [$fiscalController, 'searchDocuments'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/documents/summary', [$fiscalController, 'summary'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/metrics', [$fiscalController, 'metrics'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/documents/{id}', [$fiscalController, 'getDocument'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/documents/{id}/events', [$fiscalController, 'listDocumentEvents'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->get('/api/v1/fiscal/documents/{id}/acks', [$fiscalController, 'listDocumentAcks'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.read'),
        ]);
        $router->post('/api/v1/fiscal/documents/{id}/retry', [$fiscalController, 'retryDocument'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.manage'),
        ]);
        $router->post('/api/v1/fiscal/documents/retry-bulk', [$fiscalController, 'retryBulk'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('fiscal.manage'),
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
        $inventoryGuard = $tenantGuard->requireModule('inventory');
        $integrationsGuard = $tenantGuard->requireModule('integrations');
        $accountingGuard = $tenantGuard->requireModule('accounting');
        $paymentsPlusGuard = $tenantGuard->requireModule('payments_plus');
        $hardwareBridgeGuard = $tenantGuard->requireModule('hardware_bridge');
        $backupOpsGuard = $tenantGuard->requireModule('backup_ops');
        $securityPlusGuard = $tenantGuard->requireModule('security_plus');
        $branchesLimitGuard = $tenantGuard->requireLimit('branches.max');
        $registersLimitGuard = $tenantGuard->requireLimit('registers.max');
        $posAccess = new AuthorizationMiddleware('pos.access');
        $router->post('/api/v1/branches', [$branchController, 'create'], [
            $authMiddleware,
            $posGuard,
            $branchesLimitGuard,
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
            $registersLimitGuard,
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
        $router->post('/api/v1/inventory/movements', [$inventoryController, 'createMovement'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->get('/api/v1/inventory/stock', [$inventoryController, 'stock'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.read'),
        ]);
        $router->get('/api/v1/inventory/kardex', [$inventoryController, 'kardex'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.read'),
        ]);
        $router->put('/api/v1/inventory/policies/minmax', [$inventoryController, 'upsertPolicies'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->get('/api/v1/inventory/policies/minmax', [$inventoryController, 'listPolicies'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.read'),
        ]);
        $router->get('/api/v1/inventory/alerts', [$inventoryController, 'alerts'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.read'),
        ]);
        $router->post('/api/v1/inventory/transfers', [$inventoryAdvancedController, 'createTransfer'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/inventory/transfers/{id}/dispatch', [$inventoryAdvancedController, 'dispatchTransfer'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/inventory/transfers/{id}/receive', [$inventoryAdvancedController, 'receiveTransfer'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/inventory/counts', [$inventoryAdvancedController, 'createCount'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/inventory/counts/{id}/close', [$inventoryAdvancedController, 'closeCount'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/suppliers', [$purchaseController, 'createSupplier'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->get('/api/v1/suppliers', [$purchaseController, 'listSuppliers'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.read'),
        ]);
        $router->post('/api/v1/purchases/orders', [$purchaseController, 'createOrder'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/purchases/orders/{id}/receive', [$purchaseController, 'receiveOrder'], [
            $authMiddleware,
            $inventoryGuard,
            new AuthorizationMiddleware('inventory.write'),
        ]);
        $router->post('/api/v1/crm/segments', [$crmController, 'createSegment'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('customers.write'),
        ]);
        $router->post('/api/v1/crm/segments/{id}/customers', [$crmController, 'attachCustomers'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('customers.write'),
        ]);
        $router->post('/api/v1/pricing/lists', [$crmController, 'createPriceList'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('items.write'),
        ]);
        $router->post('/api/v1/pricing/lists/{id}/items', [$crmController, 'upsertPriceListItems'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('items.write'),
        ]);
        $router->get('/api/v1/reports/executive', [$executiveReportController, 'executive'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/accounting/exports/sales', [$accountingController, 'exportSales'], [
            $authMiddleware,
            $accountingGuard,
            new AuthorizationMiddleware('accounting.read'),
        ]);
        $router->get('/api/v1/accounting/exports/collections', [$accountingController, 'exportCollections'], [
            $authMiddleware,
            $accountingGuard,
            new AuthorizationMiddleware('accounting.read'),
        ]);
        $router->get('/api/v1/accounting/exports/purchases', [$accountingController, 'exportPurchases'], [
            $authMiddleware,
            $accountingGuard,
            new AuthorizationMiddleware('accounting.read'),
        ]);
        $router->get('/api/v1/accounting/account-map', [$accountingController, 'getAccountMap'], [
            $authMiddleware,
            $accountingGuard,
            new AuthorizationMiddleware('accounting.read'),
        ]);
        $router->put('/api/v1/accounting/account-map', [$accountingController, 'upsertAccountMap'], [
            $authMiddleware,
            $accountingGuard,
            new AuthorizationMiddleware('accounting.manage'),
        ]);
        $router->post('/api/v1/accounting/periods/close', [$accountingController, 'closePeriod'], [
            $authMiddleware,
            $accountingGuard,
            new AuthorizationMiddleware('accounting.manage'),
        ]);
        $router->post('/api/v1/receivables/accounts', [$receivableController, 'createAccount'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.write'),
        ]);
        $router->get('/api/v1/receivables/accounts', [$receivableController, 'listAccounts'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.read'),
        ]);
        $router->post('/api/v1/receivables/accounts/{id}/payments', [$receivableController, 'addPayment'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.write'),
        ]);
        $router->get('/api/v1/receivables/aging', [$receivableController, 'aging'], [
            $authMiddleware,
            $invoicingGuard,
            new AuthorizationMiddleware('invoices.read'),
        ]);
        $router->get('/api/v1/integrations/connectors', [$integrationController, 'listConnectors'], [
            $authMiddleware,
            $integrationsGuard,
            new AuthorizationMiddleware('integration.read'),
        ]);
        $router->put('/api/v1/integrations/connectors/{code}', [$integrationController, 'upsertConnector'], [
            $authMiddleware,
            $integrationsGuard,
            new AuthorizationMiddleware('integration.manage'),
        ]);
        $router->post('/api/v1/integrations/connectors/{code}/test', [$integrationController, 'testConnector'], [
            $authMiddleware,
            $integrationsGuard,
            new AuthorizationMiddleware('integration.manage'),
        ]);
        $router->post('/api/v1/integrations/events/publish', [$integrationController, 'publishEvent'], [
            $authMiddleware,
            $integrationsGuard,
            new AuthorizationMiddleware('integration.manage'),
        ]);
        $router->get('/api/v1/integrations/deliveries', [$integrationController, 'listDeliveries'], [
            $authMiddleware,
            $integrationsGuard,
            new AuthorizationMiddleware('integration.read'),
        ]);
        $router->get('/api/v1/payments-plus/status', [$paymentsPlusController, 'status'], [
            $authMiddleware,
            $paymentsPlusGuard,
            new AuthorizationMiddleware('payments_plus.read'),
        ]);
        $router->post('/api/v1/payments-plus/transactions/intent', [$paymentsPlusController, 'createIntent'], [
            $authMiddleware,
            $paymentsPlusGuard,
            new AuthorizationMiddleware('payments_plus.manage'),
        ]);
        $router->get('/api/v1/payments-plus/transactions', [$paymentsPlusController, 'listTransactions'], [
            $authMiddleware,
            $paymentsPlusGuard,
            new AuthorizationMiddleware('payments_plus.read'),
        ]);
        $router->get('/api/v1/payments-plus/transactions/{id}', [$paymentsPlusController, 'getTransaction'], [
            $authMiddleware,
            $paymentsPlusGuard,
            new AuthorizationMiddleware('payments_plus.read'),
        ]);
        $router->post('/api/v1/payments-plus/reconcile/daily', [$paymentsPlusController, 'reconcileDaily'], [
            $authMiddleware,
            $paymentsPlusGuard,
            new AuthorizationMiddleware('payments_plus.manage'),
        ]);
        $router->get('/api/v1/payments-plus/reconciliations', [$paymentsPlusController, 'listReconciliations'], [
            $authMiddleware,
            $paymentsPlusGuard,
            new AuthorizationMiddleware('payments_plus.read'),
        ]);
        $router->post('/api/v1/hardware/devices', [$hardwareBridgeController, 'upsertDevice'], [
            $authMiddleware,
            $hardwareBridgeGuard,
            new AuthorizationMiddleware('hardware_bridge.manage'),
        ]);
        $router->get('/api/v1/hardware/devices', [$hardwareBridgeController, 'listDevices'], [
            $authMiddleware,
            $hardwareBridgeGuard,
            new AuthorizationMiddleware('hardware_bridge.read'),
        ]);
        $router->post('/api/v1/hardware/print-jobs', [$hardwareBridgeController, 'createPrintJob'], [
            $authMiddleware,
            $hardwareBridgeGuard,
            new AuthorizationMiddleware('hardware_bridge.manage'),
        ]);
        $router->get('/api/v1/hardware/print-jobs', [$hardwareBridgeController, 'listPrintJobs'], [
            $authMiddleware,
            $hardwareBridgeGuard,
            new AuthorizationMiddleware('hardware_bridge.read'),
        ]);
        $router->post('/api/v1/hardware/devices/{id}/drawer/open', [$hardwareBridgeController, 'openDrawer'], [
            $authMiddleware,
            $hardwareBridgeGuard,
            new AuthorizationMiddleware('hardware_bridge.manage'),
        ]);
        $router->get('/api/v1/hardware/devices/{id}/health', [$hardwareBridgeController, 'health'], [
            $authMiddleware,
            $hardwareBridgeGuard,
            new AuthorizationMiddleware('hardware_bridge.read'),
        ]);
        $router->post('/api/v1/backup/snapshots', [$backupOpsController, 'createSnapshot'], [
            $authMiddleware,
            $backupOpsGuard,
            new AuthorizationMiddleware('backup_ops.manage'),
        ]);
        $router->get('/api/v1/backup/snapshots', [$backupOpsController, 'listSnapshots'], [
            $authMiddleware,
            $backupOpsGuard,
            new AuthorizationMiddleware('backup_ops.read'),
        ]);
        $router->post('/api/v1/backup/restores', [$backupOpsController, 'createRestoreRun'], [
            $authMiddleware,
            $backupOpsGuard,
            new AuthorizationMiddleware('backup_ops.manage'),
        ]);
        $router->get('/api/v1/backup/restores', [$backupOpsController, 'listRestoreRuns'], [
            $authMiddleware,
            $backupOpsGuard,
            new AuthorizationMiddleware('backup_ops.read'),
        ]);
        $router->get('/api/v1/backup/runbook', [$backupOpsController, 'runbook'], [
            $authMiddleware,
            $backupOpsGuard,
            new AuthorizationMiddleware('backup_ops.read'),
        ]);
        $router->get('/api/v1/security-plus/status', [$securityPlusController, 'status'], [
            $authMiddleware,
            $securityPlusGuard,
            new AuthorizationMiddleware('security_plus.read'),
        ]);
        $router->put('/api/v1/security-plus/mfa/totp', [$securityPlusController, 'enrollMfa'], [
            $authMiddleware,
            $securityPlusGuard,
            new AuthorizationMiddleware('security_plus.manage'),
        ]);
        $router->post('/api/v1/security-plus/mfa/verify', [$securityPlusController, 'verifyMfa'], [
            $authMiddleware,
            $securityPlusGuard,
            new AuthorizationMiddleware('security_plus.manage'),
        ]);
        $router->get('/api/v1/security-plus/mfa/methods', [$securityPlusController, 'listMfaMethods'], [
            $authMiddleware,
            $securityPlusGuard,
            new AuthorizationMiddleware('security_plus.read'),
        ]);
        $router->post('/api/v1/security-plus/secrets/rotate', [$securityPlusController, 'rotateSecret'], [
            $authMiddleware,
            $securityPlusGuard,
            new AuthorizationMiddleware('security_plus.manage'),
        ]);
        $router->get('/api/v1/security-plus/secrets/rotations', [$securityPlusController, 'listSecretRotations'], [
            $authMiddleware,
            $securityPlusGuard,
            new AuthorizationMiddleware('security_plus.read'),
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
        $router->get('/api/v1/ops/tenant-metrics', [$opsController, 'tenantMetrics'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/sync-conflicts', [$opsController, 'syncConflicts'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/jobs/queues', [$opsController, 'jobsQueues'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/jobs/dlq', [$opsController, 'dlq'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->post('/api/v1/ops/jobs/dlq/{id}/requeue', [$opsController, 'requeueDlq'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->post('/api/v1/ops/daily-close', [$opsController, 'closeDaily'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('ops.daily_close.manage'),
        ]);
        $router->get('/api/v1/ops/daily-close', [$opsController, 'listDailyClosures'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('ops.daily_close.read'),
        ]);
        $router->get('/api/v1/ops/daily-close/{id}', [$opsController, 'getDailyClosure'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('ops.daily_close.read'),
        ]);
        $router->post('/api/v1/ops/daily-close/{id}/reopen', [$opsController, 'reopenDailyClosure'], [
            $authMiddleware,
            $posGuard,
            $posAccess,
            new AuthorizationMiddleware('ops.daily_close.manage'),
        ]);
        $router->get('/api/v1/ops/sli-slo', [$opsController, 'sliSlo'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/alerts/rules', [$opsController, 'listAlertRules'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->put('/api/v1/ops/alerts/rules', [$opsController, 'upsertAlertRules'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('ops.alerts.manage'),
        ]);
        $router->post('/api/v1/ops/alerts/evaluate', [$opsController, 'evaluateAlerts'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('ops.alerts.manage'),
        ]);
        $router->get('/api/v1/ops/incidents', [$opsController, 'incidents'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/diagnostics', [$opsController, 'diagnostics'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/onboarding/templates', [$opsController, 'onboardingTemplates'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->get('/api/v1/ops/onboarding/checklist', [$opsController, 'onboardingChecklist'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('audit.read'),
        ]);
        $router->put('/api/v1/ops/onboarding/checklist', [$opsController, 'upsertOnboardingChecklist'], [
            $authMiddleware,
            $tenantGuard,
            new AuthorizationMiddleware('ops.onboarding.manage'),
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
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app-error.log';
            $line = '[' . gmdate('c') . '] request_id=' . $requestId
                . ' message=' . $e->getMessage()
                . ' file=' . $e->getFile() . ':' . $e->getLine()
                . PHP_EOL . $e->getTraceAsString() . PHP_EOL . PHP_EOL;
            @file_put_contents($logFile, $line, FILE_APPEND);
            Response::error('SERVER_ERROR', 'Error interno', $requestId, 500);
        }
    }
}
