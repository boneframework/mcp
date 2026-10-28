<?php

declare(strict_types=1);

namespace Tests\Bone\MCP;

use Barnacle\Container;
use Bone\MCP\McpConfig;
use Bone\MCP\MCPPackage;
use Bone\MCP\RequestContext;
use Bone\MCP\SessionLock;
use Bone\Router\Router;
use Codeception\Test\Unit;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mcp\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Uid\Uuid;
use Tests\Bone\MCP\Fixtures\Mcp\GreetingTools;

class MCPPackageTest extends Unit
{
    private MCPPackage $package;
    private string $sessionDir;

    protected function _before(): void
    {
        $this->package = new MCPPackage();
        $this->sessionDir = sys_get_temp_dir() . '/bone-mcp-test-' . uniqid();
    }

    protected function _after(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sessionDir));
    }

    private function container(array $mcp = []): Container
    {
        $container = new Container();
        $container['mcp'] = $mcp + [
            'discovery' => ['base_path' => dirname(__DIR__) . '/Fixtures', 'scan_dirs' => ['Mcp']],
            'session' => ['directory' => $this->sessionDir],
            'allowed_hosts' => ['localhost', 'tickets'],
        ];
        $this->package->addToContainer($container);
        $container[GreetingTools::class] = $container->factory(
            fn (Container $c) => new GreetingTools($c->get(RequestContext::class))
        );

        return $container;
    }

    private function handler(Container $container, string $method = 'POST'): callable
    {
        $router = $this->package->addRoutes($container, new Router());
        foreach ($router->getRoutes() as $route) {
            if ($route->getMethod() === $method) {
                return $route->getCallable($container);
            }
        }
        self::fail('No ' . $method . ' route');
    }

    private function request(array $message, ?string $sessionId = null, string $host = 'tickets'): ServerRequestInterface
    {
        $body = (new StreamFactory())->createStream(json_encode($message));
        $body->getContents(); // leave the stream at its end, as earlier middleware would
        $headers = ['Host' => $host, 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream'];
        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return (new ServerRequest([], [], 'http://' . $host . '/mcp', 'POST', $body, $headers))
            ->withAttribute('oauth_client_id', 'client-1');
    }

    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    private function initialize(callable $handler): ResponseInterface
    {
        return $handler($this->request([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1.0.0']],
        ]));
    }

    public function testConfigDefaults(): void
    {
        $config = new McpConfig([], '/app');

        self::assertSame('/mcp', $config->path);
        self::assertSame('Bone Framework MCP Server', $config->serverName);
        self::assertSame('1.0.0', $config->serverVersion);
        self::assertNull($config->instructions);
        self::assertSame('/app/src', $config->discoveryBasePath);
        self::assertSame(['Mcp'], $config->discoveryScanDirs);
        self::assertSame([], $config->discoveryExcludeDirs);
        self::assertSame('/app/data/cache/mcp-sessions', $config->sessionDirectory);
        self::assertSame(3600, $config->sessionTtl);
        self::assertSame(30.0, $config->sessionLockTimeout);
        self::assertSame(McpConfig::DEFAULT_ALLOWED_HOSTS, $config->allowedHosts);
        self::assertSame([], $config->allowedOrigins);
        self::assertSame([], $config->middleware);
    }

    public function testConfigOverrides(): void
    {
        $config = new McpConfig([
            'path' => 'agents/mcp/',
            'server_name' => 'My MCP',
            'server_version' => '2.0.0',
            'instructions' => 'Browse first',
            'discovery' => ['base_path' => '/abs/src', 'scan_dirs' => ['Tools'], 'exclude_dirs' => ['Tools/Old']],
            'session' => ['directory' => 'var/sessions', 'ttl' => 60, 'lock_timeout' => 5],
            'allowed_hosts' => ['example.com'],
            'allowed_origins' => ['https://app.example.com'],
            'middleware' => ['AuthMiddleware'],
        ], '/app/');

        self::assertSame('/agents/mcp', $config->path);
        self::assertSame('My MCP', $config->serverName);
        self::assertSame('2.0.0', $config->serverVersion);
        self::assertSame('Browse first', $config->instructions);
        self::assertSame('/abs/src', $config->discoveryBasePath);
        self::assertSame(['Tools'], $config->discoveryScanDirs);
        self::assertSame(['Tools/Old'], $config->discoveryExcludeDirs);
        self::assertSame('/app/var/sessions', $config->sessionDirectory);
        self::assertSame(60, $config->sessionTtl);
        self::assertSame(5.0, $config->sessionLockTimeout);
        self::assertSame(['example.com'], $config->allowedHosts);
        self::assertSame(['https://app.example.com'], $config->allowedOrigins);
        self::assertSame(['AuthMiddleware'], $config->middleware);
    }

    public function testConfigDefaultsToWorkingDirectory(): void
    {
        self::assertSame(getcwd() . '/src', (new McpConfig())->discoveryBasePath);
    }

    public function testAddToContainer(): void
    {
        $container = new Container();
        $this->package->addToContainer($container);

        self::assertInstanceOf(McpConfig::class, $container->get(McpConfig::class));
        self::assertSame('Bone Framework MCP Server', $container->get(McpConfig::class)->serverName);
        self::assertSame($container->get(RequestContext::class), $container->get(RequestContext::class));
    }

    public function testServerBuildsWithoutDiscoveryDirectory(): void
    {
        $container = $this->container(['discovery' => ['base_path' => '/does/not/exist']]);

        self::assertInstanceOf(Server::class, $container->get(Server::class));
    }

    public function testRoutesUseConfiguredPathAndMiddleware(): void
    {
        $byId = $this->createMock(MiddlewareInterface::class);
        $instance = $this->createMock(MiddlewareInterface::class);
        $container = $this->container(['path' => '/agents', 'middleware' => ['auth.middleware', $instance]]);
        $container['auth.middleware'] = $byId;

        $routes = $this->package->addRoutes($container, new Router())->getRoutes();

        self::assertEqualsCanonicalizing(['GET', 'POST', 'DELETE'], array_map(fn ($r) => $r->getMethod(), $routes));
        foreach ($routes as $route) {
            self::assertSame('/agents', $route->getPath());
            self::assertSame([$byId, $instance], array_values(iterator_to_array($route->getMiddlewareStack())));
        }
    }

    public function testMiddlewareRuns(): void
    {
        $deny = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }
        };
        $container = $this->container(['middleware' => [$deny]]);
        $router = $this->package->addRoutes($container, new Router());

        $response = $router->dispatch($this->request(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testInitializeUsesConfig(): void
    {
        $container = $this->container(['server_name' => 'Test MCP', 'server_version' => '9.9.9', 'instructions' => 'Browse first']);

        $response = $this->initialize($this->handler($container));
        $data = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('', $response->getHeaderLine('Mcp-Session-Id'));
        self::assertSame(['name' => 'Test MCP', 'version' => '9.9.9'], $data['result']['serverInfo']);
        self::assertSame('Browse first', $data['result']['instructions']);
        self::assertSame('client-1', $container->get(RequestContext::class)->getClientId());
    }

    public function testSessionSurvivesAcrossRequestsAndToolsAreDiscovered(): void
    {
        // a fresh container per request, as under PHP-FPM
        $sessionId = $this->initialize($this->handler($this->container()))->getHeaderLine('Mcp-Session-Id');

        $list = $this->json($this->handler($this->container())(
            $this->request(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $sessionId)
        ));
        self::assertSame(['greet'], array_column($list['result']['tools'], 'name'));

        $call = $this->json($this->handler($this->container())(
            $this->request(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'greet', 'arguments' => ['name' => 'Ada']]], $sessionId)
        ));
        self::assertSame(['greeting' => 'Hello Ada', 'client' => 'client-1'], $call['result']['structuredContent']);

        $error = $this->json($this->handler($this->container())(
            $this->request(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'greet', 'arguments' => ['name' => '']]], $sessionId)
        ));
        self::assertTrue($error['result']['isError']);
        self::assertSame('name is required', $error['result']['content'][0]['text']);
    }

    public function testDeleteEndsSession(): void
    {
        $sessionId = $this->initialize($this->handler($this->container()))->getHeaderLine('Mcp-Session-Id');
        $delete = (new ServerRequest([], [], 'http://tickets/mcp', 'DELETE', 'php://memory', ['Host' => 'tickets', 'Mcp-Session-Id' => $sessionId]));

        self::assertSame(200, $this->handler($this->container(), 'DELETE')($delete)->getStatusCode());

        $response = $this->handler($this->container())(
            $this->request(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $sessionId)
        );
        self::assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    public function testConcurrentRequestsOnOneSessionEachGetTheirOwnResponse(): void
    {
        $sessionId = $this->initialize($this->handler($this->container()))->getHeaderLine('Mcp-Session-Id');
        $this->handler($this->container())($this->request(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId));

        // separate PHP processes, as under PHP-FPM; each call holds the session for 300ms
        $processes = [];
        foreach (range(10, 14) as $id) {
            $message = json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => 'greet', 'arguments' => ['name' => 'caller ' . $id, 'delayMs' => 300]]]);
            $command = [PHP_BINARY, dirname(__DIR__) . '/Fixtures/call.php', $this->sessionDir, $sessionId, $message];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $processes[$id] = [$process, $pipes];
        }

        foreach ($processes as $id => [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            proc_close($process);

            $response = json_decode((string) $output, true);
            self::assertIsArray($response, 'Request ' . $id . ' printed: ' . $output . $errors);
            self::assertSame(200, $response['status'], 'Request ' . $id . ' got ' . $output);
            $body = json_decode($response['body'], true);
            self::assertSame($id, $body['id']);
            self::assertSame('Hello caller ' . $id, $body['result']['structuredContent']['greeting']);
        }

        // the SDK's FileSessionLock is configured: one lock file per session next to the session
        self::assertFileExists($this->sessionDir . '/' . $sessionId . '.lock');
    }

    public function testSessionLock(): void
    {
        mkdir($this->sessionDir);
        $lock = new SessionLock($this->sessionDir . '/');
        $sessionId = '0198e5a2-7b1c-7c3d-8e4f-5a6b7c8d9e0f';

        self::assertSame('unlocked', $lock->synchronized(null, fn () => 'unlocked'));
        self::assertSame('not a uuid', $lock->synchronized('../../etc/passwd', fn () => 'not a uuid'));
        self::assertSame([], glob($this->sessionDir . '/*'));

        $path = $lock->pathFor(Uuid::fromString($sessionId));
        self::assertSame($this->sessionDir . '/' . $sessionId . '.lock', $path);
        self::assertSame('locked', $lock->synchronized(strtoupper($sessionId), function () use ($path) {
            // held exclusively: a second, non-blocking lock attempt fails
            $other = fopen($path, 'c');
            $acquired = flock($other, LOCK_EX | LOCK_NB);
            fclose($other);
            self::assertFalse($acquired);

            return 'locked';
        }));

        $other = fopen($path, 'c');
        self::assertTrue(flock($other, LOCK_EX | LOCK_NB), 'released afterwards');
        fclose($other);
    }

    public function testSessionLockReleasesWhenWorkThrows(): void
    {
        mkdir($this->sessionDir);
        $lock = new SessionLock($this->sessionDir);
        $sessionId = '0198e5a2-7b1c-7c3d-8e4f-5a6b7c8d9e0f';

        try {
            $lock->synchronized($sessionId, fn () => throw new \LogicException('boom'));
            self::fail('Expected the exception to propagate');
        } catch (\LogicException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        $other = fopen($lock->pathFor(Uuid::fromString($sessionId)), 'c');
        self::assertTrue(flock($other, LOCK_EX | LOCK_NB));
        fclose($other);
    }

    public function testSessionLockNeedsAWritableDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SessionLock($this->sessionDir . '/missing'))->synchronized('0198e5a2-7b1c-7c3d-8e4f-5a6b7c8d9e0f', fn () => null);
    }

    public function testCrossOriginRequestsNeedAnAllowedOrigin(): void
    {
        $ping = fn (array $mcp) => $this->handler($this->container($mcp))(
            $this->request(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->withHeader('Origin', 'https://app.example.com')
        );

        self::assertSame(403, $ping([])->getStatusCode());
        self::assertNotSame(403, $ping(['allowed_origins' => ['https://app.example.com']])->getStatusCode());
    }

    public function testUnknownHostIsRejected(): void
    {
        $response = $this->handler($this->container())($this->request(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], null, 'evil.example'));

        self::assertSame(403, $response->getStatusCode());
    }
}
