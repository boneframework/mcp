<?php

declare(strict_types=1);

namespace Bone\MCP;

use Psr\Http\Server\MiddlewareInterface;

/**
 * The `mcp` config key with defaults applied. Relative paths are resolved against the
 * application root (the working directory Bone runs from).
 */
class McpConfig
{
    public const DEFAULT_ALLOWED_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    public readonly string $path;
    public readonly string $serverName;
    public readonly string $serverVersion;
    public readonly ?string $instructions;
    public readonly string $discoveryBasePath;
    /** @var string[] */
    public readonly array $discoveryScanDirs;
    /** @var string[] */
    public readonly array $discoveryExcludeDirs;
    public readonly string $sessionDirectory;
    public readonly int $sessionTtl;
    /** @var list<string> */
    public readonly array $allowedHosts;
    /** @var list<string|MiddlewareInterface> */
    public readonly array $middleware;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?string $appRoot = null)
    {
        $appRoot ??= (string) getcwd();
        $discovery = (array) ($config['discovery'] ?? []);
        $session = (array) ($config['session'] ?? []);

        $this->path = '/' . trim((string) ($config['path'] ?? '/mcp'), '/');
        $this->serverName = (string) ($config['server_name'] ?? 'Bone Framework MCP Server');
        $this->serverVersion = (string) ($config['server_version'] ?? '1.0.0');
        $this->instructions = isset($config['instructions']) ? (string) $config['instructions'] : null;
        $this->discoveryBasePath = $this->resolve($appRoot, (string) ($discovery['base_path'] ?? 'src'));
        $this->discoveryScanDirs = array_values((array) ($discovery['scan_dirs'] ?? ['Mcp']));
        $this->discoveryExcludeDirs = array_values((array) ($discovery['exclude_dirs'] ?? []));
        $this->sessionDirectory = $this->resolve($appRoot, (string) ($session['directory'] ?? 'data/cache/mcp-sessions'));
        $this->sessionTtl = (int) ($session['ttl'] ?? 3600);
        $this->allowedHosts = array_values(array_map('strval', (array) ($config['allowed_hosts'] ?? self::DEFAULT_ALLOWED_HOSTS)));
        $this->middleware = array_values((array) ($config['middleware'] ?? []));
    }

    private function resolve(string $appRoot, string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($appRoot, '/') . '/' . $path;
    }
}
