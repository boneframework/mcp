<?php

declare(strict_types=1);

namespace Tests\Bone\MCP\Fixtures\Mcp;

use Bone\MCP\RequestContext;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * A tool class with a constructor dependency, resolved from the container.
 */
class GreetingTools
{
    public function __construct(private RequestContext $context) {}

    /**
     * Greet someone on behalf of the calling OAuth client.
     *
     * @param string $name who to greet
     * @return array<string, string|null>
     */
    #[McpTool(name: 'greet')]
    public function greet(string $name): array
    {
        if ($name === '') {
            throw new ToolCallException('name is required');
        }

        return ['greeting' => 'Hello ' . $name, 'client' => $this->context->getClientId()];
    }
}
