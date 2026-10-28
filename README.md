# mcp
[![Build Status](https://travis-ci.org/boneframework/mcp.png?branch=master)](https://travis-ci.org/boneframework/mcp) [![Code Coverage](https://scrutinizer-ci.com/g/boneframework/mcp/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/boneframework/mcp/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/boneframework/mcp/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/boneframework/mcp/?branch=master) <br />
MCP for Bone Framework

## Installation

Install via composer:

```bash
composer require boneframework/mcp
```

## Configuration

### 1. Register the Package

Enable the package in your `config/packages.php`:

```php
<?php

return [
    'packages' => [
        Bone\BoneDoctrine\BoneDoctrinePackage::class,
        App\AppPackage::class,
        Bone\MCP\MCPPackage::class,
    ],
];
```

Register it after the packages that provide any services you use as middleware (for example `Bone\OAuth2\BoneOAuth2Package`).

### 2. Configure the MCP server

Create `config/mcp.php`. Every key is optional; the defaults are shown:

```php
<?php

return [
    'mcp' => [
        'path' => '/mcp',
        'server_name' => 'Bone Framework MCP Server',
        'server_version' => '1.0.0',
        'instructions' => null, // sent to clients on initialize: tell agents how to use your tools

        // where your tool classes live; relative paths are resolved from the application root
        'discovery' => [
            'base_path' => 'src',
            'scan_dirs' => ['Mcp'],
            'exclude_dirs' => [],
        ],

        // sessions must outlive a single PHP request, so they are stored on disk
        'session' => [
            'directory' => 'data/cache/mcp-sessions',
            'ttl' => 3600,
        ],

        // Host / Origin headers accepted by the DNS rebinding protection (no ports)
        'allowed_hosts' => ['localhost', '127.0.0.1', '[::1]'],

        // PSR-15 middleware applied to the endpoint: container ids/class names or instances
        'middleware' => [],
    ],
];
```

### 3. Secure the endpoint

**The endpoint is open unless you add middleware.** With `delboy1978uk/bone-oauth2` installed,
require a Bearer token like this:

```php
'middleware' => [Bone\OAuth2\Http\Middleware\ResourceServerMiddleware::class],
```

If you serve the app on a hostname other than localhost, add it to `allowed_hosts`, e.g.
`'allowed_hosts' => ['localhost', $_ENV['DOMAIN_NAME']]`, or every request gets
`403 Forbidden: Invalid Host header.`

## Writing tools

Put tool classes in `src/Mcp/` (or wherever `discovery` points) and mark public methods with
`#[McpTool]`. The input schema is generated from the parameter types and docblock.

```php
<?php

namespace App\Mcp;

use Bone\MCP\RequestContext;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

class GreetingTools
{
    public function __construct(private RequestContext $context) {}

    /**
     * Greet someone.
     *
     * @param string $name who to greet
     */
    #[McpTool(name: 'greet')]
    public function greet(string $name): array
    {
        if ($name === '') {
            throw new ToolCallException('name is required'); // returned to the agent as a tool error
        }

        return ['greeting' => 'Hello ' . $name, 'client' => $this->context->getClientId()];
    }
}
```

Tool classes are resolved from the Bone container, so register any class with constructor
dependencies in your package's `addToContainer()`:

```php
$c[GreetingTools::class] = $c->factory(fn (Container $c) => new GreetingTools($c->get(RequestContext::class)));
```

`Bone\MCP\RequestContext` holds the HTTP request being served: use `getAttribute()` for any request
attribute, or `getClientId()` for the `oauth_client_id` set by the OAuth resource server middleware.

Throw `Mcp\Exception\ToolCallException` for errors the agent should see; any other exception is
reported as an opaque internal error.

## Usage

The endpoint speaks MCP's Streamable HTTP transport:

- **POST `/mcp`** — JSON-RPC messages. `initialize` returns an `Mcp-Session-Id` header; send it on every later request
- **GET `/mcp`** — server-sent event stream for the session
- **DELETE `/mcp`** — end the session

```http
POST /mcp
Content-Type: application/json
Accept: application/json, text/event-stream
Authorization: Bearer <token>

{"jsonrpc": "2.0", "id": 1, "method": "initialize",
 "params": {"protocolVersion": "2025-06-18", "capabilities": {}, "clientInfo": {"name": "MyClient", "version": "1.0.0"}}}
```

Note that `clientInfo.version` must be a non-empty string other than `"0"`.

### Concurrent requests

Clients may send several requests on one session at once (e.g. parallel tool calls). The SDK
keeps each session, including its queue of outgoing replies, in one file that every request
loads and saves, so overlapping requests would overwrite each other's replies. The package
therefore holds an exclusive lock per session (`<session id>.lock` next to the session files,
via `flock`) while a request is handled: requests on the same session run one at a time and each
gets its own response, while different sessions still run in parallel. The session directory
must be on a filesystem that supports `flock` (local disks and Docker volumes do; some network
filesystems don't). Lock files are removed by the session store's garbage collection once idle
for longer than the session TTL.

## Development

### Running Tests

```bash
vendor/bin/codecept run Unit --coverage
```

### Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

- Derek McLean - [@delboy1978uk](https://github.com/delboy1978uk)

## License

MIT License. See the [LICENSE](LICENSE) file for details.
