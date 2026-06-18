<?php

namespace Bone\MCP;

use Barnacle\Container;
use Barnacle\RegistrationInterface;
use Bone\Router\Router;
use Bone\Router\RouterConfigInterface;
use PhpMcp\Server\Server;           // or the equivalent from dtyq/php-mcp
use PhpMcp\Server\Transport\HttpTransport; // adjust based on chosen library

class McpPackage implements RegistrationInterface, RouterConfigInterface
{
    public function addToContainer(Container $c): void
    {
        // Register the MCP Server
        $c[Server::class] = $c->factory(function (Container $c) {
            $server = Server::make()
                ->withServerInfo('My Bone MCP Server', '1.0.0')
                ->withCapabilities(tools: true, resources: true, prompts: true)
                ->build();

            // Register your tool classes here (attribute discovery)
            // $server->registerToolClass(ExampleTools::class);

            return $server;
        });
    }

    public function addRoutes(Container $c, Router $router): Router
    {
        // Main MCP endpoint - this is the standard
        $router->map(['POST', 'GET'], '/mcp', function ($request) use ($c) {
            $server = $c->get(Server::class);
            $transport = new HttpTransport();   // or StreamableHttpTransport etc.

            return $transport->handle($request, $server);
        });

        return $router;
    }
}
