<?php

namespace Bone\MCP;

use Barnacle\Container;
use Barnacle\RegistrationInterface;
use Bone\Router\Router;
use Bone\Router\RouterConfigInterface;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ServerRequestInterface;

class MCPPackage implements RegistrationInterface, RouterConfigInterface
{
    public function addToContainer(Container $c): void
    {
        $c[Server::class] = $c->factory(function (Container $c) {
            return Server::builder()
                ->setServerInfo('Bone Framework MCP Server', '1.0.0')
                ->setDiscovery(__DIR__, ['.'])
                ->build();
        });
    }

    public function addRoutes(Container $c, Router $router): Router
    {
        $router->map('GET', '/mcp', function (ServerRequestInterface $request) use ($c) {
            /** @var Server $server */
            $server = $c->get(Server::class);
            $transport = new StreamableHttpTransport($request);

            return $server->run($transport);
        });

        $router->map('POST', '/mcp', function (ServerRequestInterface $request) use ($c) {
            /** @var Server $server */
            $server = $c->get(Server::class);
            $transport = new StreamableHttpTransport($request);

            return $server->run($transport);
        });

        return $router;
    }
}
