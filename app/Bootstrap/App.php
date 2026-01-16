<?php

namespace App\Bootstrap;

use App\Auth\AuthController;
use App\Auth\AuthMiddleware;
use App\Auth\JwtService;
use App\Auth\RefreshTokenRepository;
use App\Audit\AuditLogger;
use App\Audit\AuditRepository;
use App\Core\Middleware\JsonBodyMiddleware;
use App\Core\Middleware\RequestIdMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\RateLimiter;
use App\RBAC\AuthorizationMiddleware;
use App\RBAC\RoleRepository;
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

        $router->get('/health', function (): array {
            return ['status' => 200, 'data' => ['status' => 'ok']];
        });

        $router->post('/api/v1/auth/login', [$authController, 'login']);
        $router->post('/api/v1/auth/refresh', [$authController, 'refresh']);
        $router->post('/api/v1/auth/logout', [$authController, 'logout']);

        $userController = $this->container->get(UserController::class);
        $router->get('/api/v1/me', [$userController, 'me'], [$authMiddleware, $tenantGuard]);

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
