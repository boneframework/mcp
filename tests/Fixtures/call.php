<?php

/**
 * Handles one MCP request in its own PHP process, as PHP-FPM would, and prints the response
 * as JSON: php call.php <session dir> <session id> <request JSON>
 */

declare(strict_types=1);

use Barnacle\Container;
use Bone\MCP\MCPPackage;
use Bone\MCP\RequestContext;
use Bone\Router\Router;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Tests\Bone\MCP\Fixtures\Mcp\GreetingTools;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[, $sessionDir, $sessionId, $message] = $argv;

$package = new MCPPackage();
$container = new Container();
$container['mcp'] = [
    'discovery' => ['base_path' => __DIR__, 'scan_dirs' => ['Mcp']],
    'session' => ['directory' => $sessionDir],
    'allowed_hosts' => ['tickets'],
];
$package->addToContainer($container);
$container[GreetingTools::class] = $container->factory(fn (Container $c) => new GreetingTools($c->get(RequestContext::class)));

$handler = null;
foreach ($package->addRoutes($container, new Router())->getRoutes() as $route) {
    if ($route->getMethod() === 'POST') {
        $handler = $route->getCallable($container);
    }
}

$request = new ServerRequest([], [], 'http://tickets/mcp', 'POST', (new StreamFactory())->createStream($message), [
    'Host' => 'tickets',
    'Content-Type' => 'application/json',
    'Accept' => 'application/json, text/event-stream',
    'Mcp-Session-Id' => $sessionId,
]);
$response = $handler($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => (string) $response->getBody()]);
