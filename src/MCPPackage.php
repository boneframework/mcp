<?php

declare(strict_types=1);

namespace Bone\MCP;

use Barnacle\Container;
use Barnacle\RegistrationInterface;
use Bone\Router\Router;
use Bone\Router\RouterConfigInterface;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class MCPPackage implements RegistrationInterface, RouterConfigInterface
{
    public function addToContainer(Container $c): void
    {
        $c[McpConfig::class] = $c->factory(function (Container $c) {
            /** @var array<string, mixed> $config */
            $config = $c->has('mcp') ? $c->get('mcp') : [];

            return new McpConfig($config);
        });

        $c[RequestContext::class] = new RequestContext();

        $c[Server::class] = $c->factory(function (Container $c) {
            /** @var McpConfig $config */
            $config = $c->get(McpConfig::class);
            $builder = Server::builder()
                ->setServerInfo($config->serverName, $config->serverVersion)
                ->setInstructions($config->instructions)
                ->setContainer($c)
                ->setSession(new FileSessionStore($config->sessionDirectory, $config->sessionTtl));

            if (is_dir($config->discoveryBasePath)) {
                $builder->setDiscovery($config->discoveryBasePath, $config->discoveryScanDirs, $config->discoveryExcludeDirs);
            }

            return $builder->build();
        });

        $c[SessionLock::class] = $c->factory(function (Container $c) {
            /** @var McpConfig $config */
            $config = $c->get(McpConfig::class);

            return new SessionLock($config->sessionDirectory);
        });
    }

    public function addRoutes(Container $c, Router $router): Router
    {
        /** @var McpConfig $config */
        $config = $c->get(McpConfig::class);
        $middleware = array_map(
            fn (string|MiddlewareInterface $mw): MiddlewareInterface => is_string($mw) ? $c->get($mw) : $mw,
            $config->middleware
        );

        $handler = function (ServerRequestInterface $request) use ($c, $config): ResponseInterface {
            /** @var RequestContext $context */
            $context = $c->get(RequestContext::class);
            $context->setRequest($request);

            // earlier middleware may have read the body; the transport reads from the current position
            if ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }

            /** @var Server $server */
            $server = $c->get(Server::class);
            $transport = new StreamableHttpTransport($request, middleware: [
                new CorsMiddleware(),
                new DnsRebindingProtectionMiddleware($config->allowedHosts),
                new ProtocolVersionMiddleware(),
            ]);

            /** @var SessionLock $lock */
            $lock = $c->get(SessionLock::class);
            $sessionId = $request->getHeaderLine(StreamableHttpTransport::SESSION_HEADER);

            return $lock->synchronized($sessionId === '' ? null : $sessionId, fn () => $server->run($transport));
        };

        foreach (['GET', 'POST', 'DELETE'] as $method) {
            $route = $router->map($method, $config->path, $handler);
            foreach ($middleware as $mw) {
                $route->middleware($mw);
            }
        }

        return $router;
    }
}
