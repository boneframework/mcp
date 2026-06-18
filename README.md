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

### 2. Configure MCP Server (Optional)

Create `config/mcp.php` to customize the MCP server:

```php
<?php

return [
    'mcp' => [
        'server_name' => 'Your Application MCP',
        'server_version' => '1.0.0',
        'instructions' => 'MCP server for managing your application features.',
    ],
];
```

## Usage

The MCP server is now available at `/mcp` endpoint in your application.

### Access the MCP Endpoint

- **GET `/mcp`** - Health check / connection test
- **POST `/mcp`** - Send JSON-RPC requests to the MCP server

### Example JSON-RPC Request

```json
POST /mcp
Content-Type: application/json

{
    "jsonrpc": "2.0",
    "method": "initialize",
    "params": {
        "protocolVersion": "2024-11-05",
        "capabilities": {},
        "clientInfo": {
            "name": "MyClient",
            "version": "1.0.0"
        }
    },
    "id": 1
}
```

## MCP Capabilities

The Bone Framework MCP package provides:

- **Tools** - Executable functions that AI agents can call
- **Resources** - Data sources that can be read
- **Prompts** - Pre-defined templates for AI interactions
- **Server-initiated Communication** - Elicitations, sampling, logging, progress notifications

## Development

### Running Tests

```bash
vendor/bin/codecept run
```

### Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

- Derek McLean - [@delboy1978uk](https://github.com/delboy1978uk)

## License

MIT License. See the [LICENSE](LICENSE) file for details.
